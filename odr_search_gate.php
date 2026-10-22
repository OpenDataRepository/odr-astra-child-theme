<?php

/**
 * Search gate for the shortcut search routes.
 *
 * The theme's 404 pre-handler turns urls like /Abellaite, /ima/<name> and /amcsd/<name> into ODR
 * searches.  That is cheap for a visitor and expensive for the server, and bots were triggering a
 * search for every url they felt like guessing at -- so these routes were closed to everyone who
 * isn't logged in.
 *
 * This reopens them, in this order:
 *
 *   1. Logged in                     -> search runs, as before.
 *   2. Term is on an allowed list    -> search runs.  Mineral names come from ODR's generated
 *                                       name files; RRUFF ids are matched by pattern.  Where the
 *                                       list gives a record uuid and that record has a cached
 *                                       static page, the visitor is sent straight to it.
 *                                       The url must be the name or id and nothing else: any
 *                                       query string skips this step.
 *   3. Verified search crawler       -> search runs, but only when that toggle is on.
 *   4. Already failed a challenge    -> minimal 404 for the ban duration, no challenge shown.
 *   5. Anything else                 -> reCAPTCHA.  Pass and the search runs (and keeps running
 *                                       for the same window); fail and the client is banned.
 *
 * Step 4 exists to stay inside reCAPTCHA's free tier: once a client fails, it costs nothing to
 * refuse them for a while.  A ban only closes these search routes -- the rest of the site, static
 * pages included, is unaffected.
 *
 * Settings live in wp-admin under Settings -> ODR Search Gate (see odr_search_gate_admin.php).
 *
 * This file can also be run from the command line to rebuild the allowlist index without
 * WordPress, which is how cron should do it:
 *
 *   php odr_search_gate.php --build-index --source-dir=/path/to/data-publisher/web/uploads/IMA \
 *                           --out=/path/to/wp-content/uploads/odr-search-allowlist.php
 */


/**
 * Option name holding every setting for this gate.
 */
define( 'ODR_SG_OPTION', 'odr_search_gate' );

/**
 * reCAPTCHA v2 verification endpoint.
 */
define( 'ODR_SG_SITEVERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify' );

/**
 * Basename of the generated allowlist index, inside wp-content/uploads.
 */
define( 'ODR_SG_INDEX_BASENAME', 'odr-search-allowlist.php' );


/* -------------------------------------------------------------------------- settings ----- */

/**
 * Defaults for every setting, so a fresh install behaves sanely and get_option() never returns a
 * half-populated array.
 *
 * @return array
 */
function odr_sg_defaults() {
    return array(
        // Whether this site has lists of search terms that are allowed through without a
        // challenge.  Off by default: this theme runs on several sites and only the mineral ones
        // have those lists, so everywhere else the gate is just login + crawlers + reCAPTCHA.
        'use_name_lists' => 0,

        // from https://www.google.com/recaptcha/admin -- reCAPTCHA v2, "I'm not a robot"
        'recaptcha_site_key' => '',
        'recaptcha_secret' => '',

        // let verified Google/Bing/DuckDuckGo crawlers run searches that aren't on a list.
        // Off by default: a crawler guessing urls is still a bot spending a search.
        'allow_verified_crawlers' => 0,

        // how long a failed client is refused, and how long a passing client is trusted
        'block_seconds' => 7200,

        // absolute path of the ODR web root, e.g. /home/rruff/data-publisher/web.  Used to find
        // both the generated name files and the cached static pages.  Blank disables the
        // cached-page redirect and leaves the allowlist to whatever index has been built.
        'odr_web_dir' => '',

        // url path ODR is served under on this site, e.g. /odr_rruff
        'odr_base_path' => '/odr_rruff',

        // where to keep the generated allowlist index.  Blank means wp-content/uploads, which is
        // right for most installs; set it when uploads is read-only, or when the index should
        // live somewhere a deploy or cron can write.
        'index_path' => '',
    );
}

/**
 * All settings, defaults filled in.
 *
 * @return array
 */
function odr_sg_settings() {
    $stored = get_option( ODR_SG_OPTION, array() );
    if ( ! is_array( $stored ) )
        $stored = array();

    return array_merge( odr_sg_defaults(), $stored );
}

/**
 * One setting.
 *
 * @param string $key
 *
 * @return mixed
 */
function odr_sg_setting( $key ) {
    $settings = odr_sg_settings();
    return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}


/* ------------------------------------------------------------------- allowlist index ----- */

/**
 * Where the generated allowlist index lives.
 *
 * It goes in wp-content/uploads rather than the theme so a theme deploy can't wipe it and so the
 * webserver user can write it.
 *
 * @return string
 */
function odr_sg_index_path() {
    $configured = odr_sg_setting( 'index_path' );
    if ( ! empty( $configured ) )
        return apply_filters( 'odr_sg_index_path', $configured );

    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) )
        return apply_filters( 'odr_sg_index_path', '' );

    return apply_filters( 'odr_sg_index_path', $uploads['basedir'] . '/' . ODR_SG_INDEX_BASENAME );
}

/**
 * Loads the generated index.
 *
 * A missing index means nothing matches, which sends every anonymous search to the challenge.
 * That is the safe direction -- the alternative is building a multi-megabyte index inside a page
 * request -- but it does mean the index has to be built from admin or cron.
 *
 * @param bool $refresh Drops the in-request cache, for after a rebuild
 *
 * @return array  'minerals' and 'amcsd' maps of lowercased name => record uuid
 */
function odr_sg_index( $refresh = false ) {
    static $index = null;
    if ( $refresh )
        $index = null;
    if ( $index !== null )
        return $index;

    $index = array( 'minerals' => array(), 'amcsd' => array(), 'built' => 0 );

    $path = odr_sg_index_path();
    if ( $path === '' || ! file_exists( $path ) )
        return $index;

    $loaded = include $path;
    if ( is_array( $loaded ) && isset( $loaded['minerals'] ) && isset( $loaded['amcsd'] ) )
        $index = $loaded;

    return $index;
}

/**
 * Rebuilds the allowlist index from ODR's generated name files.
 *
 * The sources are a few megabytes of php assignments.  They are parsed with a regex rather than
 * included: including them would define arrays of thousands of entries just to throw them away,
 * and large generated php files have been known to take out php on these servers.
 *
 * @param string|null $source_dir Defaults to <odr_web_dir>/uploads/IMA
 * @param string|null $out Defaults to odr_sg_index_path()
 *
 * @return array|string  Counts on success, or a string describing the failure
 */
function odr_sg_build_index( $source_dir = null, $out = null ) {
    if ( $source_dir === null ) {
        if ( ! odr_sg_setting( 'use_name_lists' ) )
            return 'name lists are turned off for this site';

        $web_dir = odr_sg_setting( 'odr_web_dir' );
        if ( empty( $web_dir ) )
            return 'no ODR web directory configured';
        $source_dir = rtrim( $web_dir, '/' ) . '/uploads/IMA';
    }
    if ( $out === null )
        $out = odr_sg_index_path();

    if ( $out === '' )
        return 'could not work out where to write the index';
    if ( ! is_dir( $source_dir ) )
        return 'no such directory: ' . $source_dir;

    // base file first, then the update file on top -- the update wins on a collision.
    // NOTE: mineral_names_update.php is a delta on its base, while amcsd_mineral_names_update.php
    //  is closer to a full replacement.  Merging in this order handles both.
    $groups = array(
        'minerals' => array(
            'files' => array( 'mineral_names.php', 'mineral_names_update.php' ),
            // the ascii variants matter: a url can't carry "Thebaite-(NH<sub>4</sub>)"
            'pattern' => '/\$mineral(?:_ascii)?_names_lowercase\[\s*[\'"]([0-9a-f]{20,40})[\'"]\s*\]\s*=\s*[\'"](.*?)[\'"]\s*;/',
        ),
        'amcsd' => array(
            'files' => array( 'amcsd_mineral_names.php', 'amcsd_mineral_names_update.php' ),
            'pattern' => '/\$amcsd_mineral_names\[\s*[\'"]([0-9a-f]{20,40})[\'"]\s*\]\s*=\s*[\'"](.*?)[\'"]\s*;/',
        ),
    );

    $index = array(
        'built' => time(),
        'sources' => array(),
        'minerals' => array(),
        'amcsd' => array(),
    );

    foreach ( $groups as $key => $group ) {
        foreach ( $group['files'] as $basename ) {
            $path = $source_dir . '/' . $basename;
            if ( ! is_file( $path ) )
                continue;

            $contents = file_get_contents( $path );
            if ( $contents === false )
                return 'could not read ' . $path;

            $found = preg_match_all( $group['pattern'], $contents, $matches, PREG_SET_ORDER );
            unset( $contents );

            if ( $found ) {
                foreach ( $matches as $match ) {
                    $name = odr_sg_normalize_term( $match[2] );
                    if ( $name !== '' )
                        $index[ $key ][ $name ] = $match[1];
                }
            }
            unset( $matches );

            $index['sources'][ $basename ] = array(
                'mtime' => filemtime( $path ),
                'matched' => (int)$found,
            );
        }
    }

    if ( empty( $index['minerals'] ) && empty( $index['amcsd'] ) )
        return 'parsed the sources but found no names -- has their format changed?';

    $written = odr_sg_write_index( $out, $index );
    if ( $written !== true )
        return $written;

    // anything that already loaded the index in this request is now holding a stale copy
    if ( function_exists( 'get_option' ) )
        odr_sg_index( true );

    return array(
        'minerals' => count( $index['minerals'] ),
        'amcsd' => count( $index['amcsd'] ),
        'sources' => $index['sources'],
        'path' => $out,
    );
}

/**
 * Whether any source file has changed since the index was built.
 *
 * The rebuild is cheap, but rewriting the index replaces a file opcache is holding, so an hourly
 * job should only do it when something actually moved.  An index that has never been built, or
 * that doesn't know its sources, counts as stale.
 *
 * @param string|null $source_dir Defaults to <odr_web_dir>/uploads/IMA
 *
 * @return bool
 */
function odr_sg_index_is_stale( $source_dir = null ) {
    if ( ! odr_sg_setting( 'use_name_lists' ) )
        return false;

    if ( $source_dir === null ) {
        $web_dir = odr_sg_setting( 'odr_web_dir' );
        if ( empty( $web_dir ) )
            return false;   // nothing to build from, so nothing to be stale about
        $source_dir = rtrim( $web_dir, '/' ) . '/uploads/IMA';
    }

    $index = odr_sg_index();
    if ( empty( $index['built'] ) || empty( $index['sources'] ) )
        return true;

    foreach ( $index['sources'] as $basename => $meta ) {
        $path = $source_dir . '/' . $basename;
        if ( ! is_file( $path ) )
            continue;
        if ( filemtime( $path ) > (int)$meta['mtime'] )
            return true;
    }

    // a source that exists now but wasn't in the index at all
    foreach ( array( 'mineral_names.php', 'mineral_names_update.php',
                     'amcsd_mineral_names.php', 'amcsd_mineral_names_update.php' ) as $basename ) {
        if ( is_file( $source_dir . '/' . $basename ) && ! isset( $index['sources'][ $basename ] ) )
            return true;
    }

    return false;
}


/**
 * Writes the index as a plain php array, so opcache holds it and repeat loads cost nothing.
 *
 * Renders to a temporary file beside the target and renames it into place, so a request can never
 * read a half-written index.
 *
 * @param string $out
 * @param array $index
 *
 * @return true|string
 */
function odr_sg_write_index( $out, $index ) {
    $directory = dirname( $out );
    if ( ! is_dir( $directory ) )
        return 'no such directory: ' . $directory;
    if ( ! is_writable( $directory ) )
        return $directory . ' is not writable';

    $lines = array();
    $lines[] = '<?php';
    $lines[] = '';
    $lines[] = '// Allowed search terms for the theme\'s shortcut search routes.';
    $lines[] = '// GENERATED -- rebuilt from ODR\'s mineral name files.  Do not edit.';
    $lines[] = '// Built ' . gmdate( 'c', $index['built'] ) . '.';
    $lines[] = '';
    $lines[] = 'return array(';
    $lines[] = "    'built' => " . (int)$index['built'] . ',';
    $lines[] = "    'sources' => array(";
    foreach ( $index['sources'] as $basename => $meta ) {
        $lines[] = "        '" . addslashes( $basename ) . "' => array('mtime' => "
                 . (int)$meta['mtime'] . ", 'matched' => " . (int)$meta['matched'] . '),';
    }
    $lines[] = '    ),';

    foreach ( array( 'minerals', 'amcsd' ) as $key ) {
        $lines[] = "    '" . $key . "' => array(";
        foreach ( $index[ $key ] as $name => $uuid ) {
            $lines[] = "        '" . addslashes( $name ) . "' => '" . $uuid . "',";
        }
        $lines[] = '    ),';
    }
    $lines[] = ');';
    $lines[] = '';

    $temp = $out . '.new.' . getmypid();
    if ( file_put_contents( $temp, implode( "\n", $lines ) ) === false ) {
        @unlink( $temp );
        return 'could not write ' . $temp;
    }
    @chmod( $temp, 0644 );

    if ( ! @rename( $temp, $out ) ) {
        @unlink( $temp );
        return 'could not move the index into place';
    }

    return true;
}

/**
 * Normalizes a name or a url segment into the form the index is keyed by.
 *
 * @param string $term
 *
 * @return string
 */
function odr_sg_normalize_term( $term ) {
    $term = strip_tags( (string)$term );
    $term = trim( $term );
    // the generated files already lowercase their ascii names
    if ( function_exists( 'mb_strtolower' ) )
        $term = mb_strtolower( $term, 'UTF-8' );
    else
        $term = strtolower( $term );

    return $term;
}

/**
 * Looks a term up in one of the index's lists.
 *
 * @param string $list 'minerals' or 'amcsd'
 * @param string $term
 *
 * @return string|null  The record uuid, or null when the term isn't on the list
 */
function odr_sg_lookup( $list, $term ) {
    $index = odr_sg_index();
    if ( empty( $index[ $list ] ) )
        return null;

    $key = odr_sg_normalize_term( $term );
    return isset( $index[ $list ][ $key ] ) ? $index[ $list ][ $key ] : null;
}

/**
 * Whether a term is a RRUFF id we're happy to search on without a challenge.
 *
 * Deliberately narrow: R followed by exactly six digits.  The sample search also understands D
 * and X prefixes, but those aren't on this allowlist, so they take the challenge.
 *
 * @param string $term
 *
 * @return bool
 */
function odr_sg_is_rruff_id( $term ) {
    return (bool)preg_match( '/^R\d{6}$/i', trim( (string)$term ) );
}

/**
 * Whether the request url carries a query string, including a bare trailing "?".
 *
 * Checks REQUEST_URI rather than QUERY_STRING, which is empty for "/Bustamite?".
 *
 * @return bool
 */
function odr_sg_uri_has_query() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string)$_SERVER['REQUEST_URI'] : '';
    return strpos( $uri, '?' ) !== false;
}

/**
 * Records what the gate decided, where the apache access log can pick it up.
 *
 * Sent as an X-ODR-Gate response header, which apache can log with %{X-ODR-Gate}o under both
 * mod_php and PHP-FPM.  Under mod_php it's also an apache note, for %{odr_gate}n.
 *
 * Values: logged-in, allowlist, allowlist-cached, crawler, passed-earlier, solved, challenge,
 * challenge-stale-form, challenge-google-unreachable, banned, failed-now-banned,
 * no-recaptcha-keys.
 *
 * @param string $outcome
 */
function odr_sg_log_outcome( $outcome ) {
    if ( ! headers_sent() )
        header( 'X-ODR-Gate: ' . $outcome );
    if ( function_exists( 'apache_note' ) )
        apache_note( 'odr_gate', $outcome );
}


/* ---------------------------------------------------------------------- cached pages ----- */

/**
 * Public url of a record's cached static page, or null when there isn't one.
 *
 * ODR renders these to <web>/uploads/static/<site folder>/<datatype uuid>/<record uuid>.html.
 * Both middle components vary per install, so rather than making them settings this globs for the
 * record and remembers which folder answered.
 *
 * Roughly 40% of the minerals on these lists have no cached page, so callers have to handle null.
 *
 * @param string $uuid Record uuid
 *
 * @return string|null
 */
function odr_sg_cached_page_url( $uuid ) {
    if ( ! preg_match( '/^[0-9a-f]{20,40}$/', $uuid ) )
        return null;

    $web_dir = odr_sg_setting( 'odr_web_dir' );
    if ( empty( $web_dir ) )
        return null;

    $static_dir = rtrim( $web_dir, '/' ) . '/uploads/static';
    if ( ! is_dir( $static_dir ) )
        return null;

    // the <site folder>/<datatype uuid> pair is stable, so look it up once per request cycle
    $prefix = get_transient( 'odr_sg_static_prefix' );

    if ( is_string( $prefix ) && $prefix !== '' ) {
        $candidate = $static_dir . '/' . $prefix . '/' . $uuid . '.html';
        if ( is_file( $candidate ) )
            return odr_sg_static_url( $prefix, $uuid );
    }

    // ...otherwise find it.  The glob only expands directories; the filename is exact.
    foreach ( glob( $static_dir . '/*/*/' . $uuid . '.html' ) as $match ) {
        $found = substr( dirname( $match ), strlen( $static_dir ) + 1 );
        set_transient( 'odr_sg_static_prefix', $found, DAY_IN_SECONDS );
        return odr_sg_static_url( $found, $uuid );
    }

    // some installs nest the site folder one level deeper (e.g. "host/odr_rruff")
    foreach ( glob( $static_dir . '/*/*/*/' . $uuid . '.html' ) as $match ) {
        $found = substr( dirname( $match ), strlen( $static_dir ) + 1 );
        set_transient( 'odr_sg_static_prefix', $found, DAY_IN_SECONDS );
        return odr_sg_static_url( $found, $uuid );
    }

    return null;
}

/**
 * Builds the public url for a cached page.
 *
 * @param string $prefix "<site folder>/<datatype uuid>", relative to uploads/static
 * @param string $uuid
 *
 * @return string
 */
function odr_sg_static_url( $prefix, $uuid ) {
    $base = '/' . trim( (string)odr_sg_setting( 'odr_base_path' ), '/' );
    if ( $base === '/' )
        $base = '';

    return $base . '/uploads/static/' . $prefix . '/' . $uuid . '.html';
}


/* --------------------------------------------------------------------- client status ----- */

/**
 * The requesting address.
 *
 * REMOTE_ADDR only, never a forwarding header -- those are client-supplied, and a ban that can be
 * shed by sending a header is no ban at all.  If this site ever sits behind a CDN this has to
 * become that CDN's own header (Cloudflare: HTTP_CF_CONNECTING_IP).
 *
 * @return string
 */
function odr_sg_client_ip() {
    return isset( $_SERVER['REMOTE_ADDR'] ) ? (string)$_SERVER['REMOTE_ADDR'] : '';
}

/**
 * Transient key for a client, hashed so addresses aren't sitting in the options table and so the
 * key length is predictable.
 *
 * @param string $kind 'block' or 'pass'
 *
 * @return string
 */
function odr_sg_client_key( $kind ) {
    return 'odr_sg_' . $kind . '_' . md5( odr_sg_client_ip() );
}

/**
 * Whether this client failed a challenge recently.
 *
 * @return bool
 */
function odr_sg_is_blocked() {
    return (bool)get_transient( odr_sg_client_key( 'block' ) );
}

/**
 * Bans this client from the shortcut searches for the configured window.
 */
function odr_sg_block_client() {
    $seconds = (int)odr_sg_setting( 'block_seconds' );
    if ( $seconds < 60 )
        $seconds = 60;

    set_transient( odr_sg_client_key( 'block' ), time(), $seconds );
}

/**
 * Whether this client has already passed a challenge recently.
 *
 * Without this every search would cost another verification, which is exactly what the free tier
 * doesn't allow.
 *
 * @return bool
 */
function odr_sg_has_passed() {
    return (bool)get_transient( odr_sg_client_key( 'pass' ) );
}

/**
 * Remembers that this client passed, for the same window a failure would have banned them.
 */
function odr_sg_mark_passed() {
    $seconds = (int)odr_sg_setting( 'block_seconds' );
    if ( $seconds < 60 )
        $seconds = 60;

    set_transient( odr_sg_client_key( 'pass' ), time(), $seconds );
}


/* -------------------------------------------------------------------------- reCAPTCHA ----- */

/**
 * Whether reCAPTCHA is configured well enough to challenge anyone.
 *
 * @return bool
 */
function odr_sg_recaptcha_ready() {
    return ( odr_sg_setting( 'recaptcha_site_key' ) !== '' && odr_sg_setting( 'recaptcha_secret' ) !== '' );
}

/**
 * Asks Google whether a response token is good.
 *
 * A network failure returns null rather than false: it isn't the visitor's fault, so they
 * shouldn't be banned for it.
 *
 * @param string $token
 *
 * @return bool|null
 */
function odr_sg_verify_recaptcha( $token ) {
    if ( $token === '' )
        return false;

    $response = wp_remote_post( ODR_SG_SITEVERIFY_URL, array(
        'timeout' => 10,
        'body' => array(
            'secret' => odr_sg_setting( 'recaptcha_secret' ),
            'response' => $token,
            'remoteip' => odr_sg_client_ip(),
        ),
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'odr_search_gate: reCAPTCHA verification failed to reach google: ' . $response->get_error_message() );
        return null;
    }

    $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $decoded ) || ! isset( $decoded['success'] ) ) {
        error_log( 'odr_search_gate: unexpected reCAPTCHA response' );
        return null;
    }

    return (bool)$decoded['success'];
}

/**
 * Renders the challenge and exits.
 *
 * Deliberately a bare page rather than the site template: it is served to clients we don't trust
 * yet, so it loads nothing but Google's widget.
 *
 * @param string $uri The url being asked for, which the form posts back to
 * @param string $term What they searched for, shown so the page makes sense
 */
function odr_sg_render_challenge( $uri, $term ) {
    if ( ! headers_sent() ) {
        // 403 rather than 200, so a challenge can be told apart from a real page in the access
        // log.  Browsers render the body the same either way (Cloudflare's challenges do this too).
        status_header( 403 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        // the challenge itself has nothing worth indexing
        header( 'X-Robots-Tag: noindex, nofollow' );
    }

    $site_key = esc_attr( odr_sg_setting( 'recaptcha_site_key' ) );
    $action = esc_attr( $uri );
    $shown = esc_html( $term );
    $has_term = ( trim( (string) $term ) !== '' );
    $nonce = esc_attr( wp_create_nonce( 'odr_sg_challenge' ) );

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow">'
       . '<title>Confirm you are human</title>'
       . '<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
       . '<style>'
       . 'body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;padding:40px 16px;'
       . 'background:#fff;color:#333;display:flex;justify-content:center}'
       . 'main{max-width:30rem}h1{font-size:1.3rem;margin:0 0 .75rem}'
       . 'p{line-height:1.5;margin:0 0 1rem}.q{font-weight:600}'
       . 'button{font:inherit;padding:.5rem 1rem;margin-top:1rem;cursor:pointer}'
       . '</style></head><body><main>'
       . '<h1>Confirm you are human</h1>'
       . '<p>'
       . ( $has_term
            ? 'Searching for <span class="q">' . $shown . '</span> needs a quick check first. '
            : 'This page runs a database lookup, so it needs a quick check first. ' )
       . 'Automated traffic has been running large numbers of searches, so lookups that aren\'t '
       . 'for a known mineral name or RRUFF ID are confirmed this way.</p>'
       . '<form method="post" action="' . $action . '">'
       . '<input type="hidden" name="odr_sg_recaptcha" value="1">'
       . '<input type="hidden" name="odr_sg_nonce" value="' . $nonce . '">'
       . '<div class="g-recaptcha" data-sitekey="' . $site_key . '"></div>'
       . '<button type="submit">Continue</button>'
       . '</form>'
       . '<p><a href="/">Return to the homepage</a></p>'
       . '</main></body></html>';

    exit;
}


/* --------------------------------------------------------------------------- the gate ----- */

/**
 * Decides whether a shortcut search may run.
 *
 * Returns normally when the search should go ahead; otherwise redirects, challenges, or serves the
 * minimal 404, and exits.
 *
 * @param string $route 'root', 'ima', 'amcsd', or 'none' for a url that carries no searchable
 *                      term (the retired query-string endpoints), which therefore has no list to
 *                      be on and always takes the crawler / ban / challenge path
 * @param string $term The search term from the url, '' when the route has none
 */
function odr_search_gate( $route, $term ) {
    // 1. logged in: nothing to check
    if ( is_user_logged_in() ) {
        odr_sg_log_outcome( 'logged-in' );
        return;
    }

    // 2. on an allowed list?  Only sites with the mineral name lists have anything to check,
    //    and when that's off nothing here touches the index or the filesystem.
    $uuid = null;
    $allowed = false;

    if ( $route === 'none' || ! odr_sg_setting( 'use_name_lists' ) ) {
        // Nothing to match against, so fall through to the crawler / ban / challenge steps.
        // 'none' is for urls whose payload is a query string rather than a search term: there is
        // no name to check against a list, and checking the last path segment ("locality.php")
        // would be meaningless at best and a false match at worst.
    }
    else if ( odr_sg_uri_has_query() || $term !== trim( (string)$term ) ) {
        // The list only vouches for the bare name or id.  Bots were sending an allowed name with
        // a payload of their own behind it (/Bustamite?sample_child_id=...&sample_id=...), so
        // anything more than exactly the name -- a query string, even an empty one, or
        // surrounding whitespace -- takes the challenge like any other unlisted search.
    }
    else if ( $route === 'amcsd' ) {
        $uuid = odr_sg_lookup( 'amcsd', $term );
        $allowed = ( $uuid !== null );
        // NOTE: the uuid on this list is an IMA mineral record, not an AMCSD one, so it is used
        //  only to decide the term is allowed...the caller still runs its AMCSD search
        $uuid = null;
    }
    else {
        $uuid = odr_sg_lookup( 'minerals', $term );
        $allowed = ( $uuid !== null );

        if ( ! $allowed && $route === 'root' && odr_sg_is_rruff_id( $term ) ) {
            // no uuid to redirect to for a RRUFF id, but the search itself is fine
            $allowed = true;
        }
    }

    if ( $allowed ) {
        // send them to the cached page when one exists, which costs the server a static file
        // instead of a search
        if ( $uuid !== null ) {
            $cached = odr_sg_cached_page_url( $uuid );
            if ( $cached !== null ) {
                // a logged-in visitor gets bounced on to the live record by the cached page
                // itself, so this is safe for everyone
                odr_sg_log_outcome( 'allowlist-cached' );
                wp_redirect( $cached, 302 );
                exit;
            }
        }

        odr_sg_log_outcome( 'allowlist' );
        return;
    }

    // 3. a verified crawler, if we're letting them through
    if ( odr_sg_setting( 'allow_verified_crawlers' ) && function_exists( 'odr_identify_verified_crawler' ) ) {
        if ( odr_identify_verified_crawler() !== null ) {
            odr_sg_log_outcome( 'crawler' );
            return;
        }
    }

    // 4. already banned, or already proved themselves
    if ( odr_sg_is_blocked() ) {
        odr_sg_log_outcome( 'banned' );
        odr_send_minimal_404();
    }

    if ( odr_sg_has_passed() ) {
        odr_sg_log_outcome( 'passed-earlier' );
        return;
    }

    // 5. the challenge
    if ( ! odr_sg_recaptcha_ready() ) {
        // nothing to challenge with, so behave as the route did before this existed
        error_log( 'odr_search_gate: reCAPTCHA is not configured, refusing an unlisted search' );
        odr_sg_log_outcome( 'no-recaptcha-keys' );
        odr_send_minimal_404();
    }

    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

    if ( ! empty( $_POST['odr_sg_recaptcha'] ) ) {
        $nonce = isset( $_POST['odr_sg_nonce'] ) ? $_POST['odr_sg_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'odr_sg_challenge' ) ) {
            // a stale form, not a bot: ask again rather than banning
            odr_sg_log_outcome( 'challenge-stale-form' );
            odr_sg_render_challenge( $uri, $term );
        }

        $token = isset( $_POST['g-recaptcha-response'] ) ? (string)$_POST['g-recaptcha-response'] : '';
        $verdict = odr_sg_verify_recaptcha( $token );

        if ( $verdict === true ) {
            odr_sg_mark_passed();
            odr_sg_log_outcome( 'solved' );
            return;
        }

        if ( $verdict === null ) {
            // couldn't reach google -- don't ban someone for our own outage
            odr_sg_log_outcome( 'challenge-google-unreachable' );
            odr_sg_render_challenge( $uri, $term );
        }

        odr_sg_block_client();
        odr_sg_log_outcome( 'failed-now-banned' );
        odr_send_minimal_404();
    }

    odr_sg_log_outcome( 'challenge' );

    odr_sg_render_challenge( $uri, $term );
}


/* ------------------------------------------------------------------------------ cron ----- */

/**
 * Rebuilds the index hourly, so minerals added to ODR start resolving on their own.
 *
 * NOTE: WP-Cron only runs when something requests the site, so "hourly" means "within an hour of
 * the next page view".  A real system cron is more dependable -- see SETUP-search-gate.md -- and
 * the two are safe to use together: whichever runs first rebuilds, and the other finds the index
 * current and does nothing.
 *
 * NOTE: these hooks have to be registered here rather than in odr_search_gate_admin.php, which
 * only loads for admin requests...wp-cron.php is a front-end request, so a handler registered
 * under is_admin() would never fire.  They're guarded because this file is also runnable from the
 * command line, where WordPress doesn't exist.
 */
if ( function_exists( 'add_action' ) )
    add_action( 'after_setup_theme', 'odr_sg_schedule_rebuild' );

function odr_sg_schedule_rebuild() {
    // this job used to be daily...if an old schedule is still registered, drop it
    if ( wp_next_scheduled( 'odr_sg_daily_rebuild' ) )
        wp_clear_scheduled_hook( 'odr_sg_daily_rebuild' );

    // no name lists on this site means nothing to rebuild, so don't keep waking up for it
    if ( ! odr_sg_setting( 'use_name_lists' ) ) {
        if ( wp_next_scheduled( 'odr_sg_hourly_rebuild' ) )
            wp_clear_scheduled_hook( 'odr_sg_hourly_rebuild' );
        return;
    }

    $next = wp_next_scheduled( 'odr_sg_hourly_rebuild' );
    if ( $next && wp_get_schedule( 'odr_sg_hourly_rebuild' ) !== 'hourly' ) {
        // recurrence changed under us
        wp_clear_scheduled_hook( 'odr_sg_hourly_rebuild' );
        $next = false;
    }

    if ( ! $next )
        wp_schedule_event( time() + MINUTE_IN_SECONDS * 5, 'hourly', 'odr_sg_hourly_rebuild' );
}

if ( function_exists( 'add_action' ) )
    add_action( 'odr_sg_hourly_rebuild', 'odr_sg_cron_rebuild' );

function odr_sg_cron_rebuild() {
    if ( ! odr_sg_setting( 'use_name_lists' ) )
        return;

    // only rewrite the index when a source file has actually changed -- the rebuild is cheap, but
    // replacing the file every hour would needlessly invalidate it in opcache
    if ( ! odr_sg_index_is_stale() )
        return;

    $result = odr_sg_build_index();
    if ( is_string( $result ) )
        error_log( 'odr_search_gate: hourly index rebuild failed: ' . $result );
}


/* ------------------------------------------------------------------------------- cli ----- */

/**
 * Rebuilds the index from the command line, for cron.  Does nothing when WordPress loads this.
 */
if ( PHP_SAPI === 'cli' && isset( $argv ) && in_array( '--build-index', $argv, true ) ) {
    $options = array( 'source-dir' => null, 'out' => null );
    foreach ( $argv as $argument ) {
        if ( preg_match( '/^--(source-dir|out)=(.+)$/', $argument, $match ) )
            $options[ $match[1] ] = $match[2];
    }

    if ( $options['source-dir'] === null || $options['out'] === null ) {
        fwrite( STDERR, "usage: php odr_search_gate.php --build-index --source-dir=DIR --out=FILE [--if-stale]\n" );
        exit( 1 );
    }

    // --if-stale makes this safe to run hourly: it only rewrites the index when one of the
    //  source files is newer than the index's record of it
    if ( in_array( '--if-stale', $argv, true ) && file_exists( $options['out'] ) ) {
        $existing = include $options['out'];
        $stale = false;
        if ( ! is_array( $existing ) || empty( $existing['sources'] ) ) {
            $stale = true;
        }
        else {
            foreach ( $existing['sources'] as $basename => $meta ) {
                $path = $options['source-dir'] . '/' . $basename;
                if ( is_file( $path ) && filemtime( $path ) > (int)$meta['mtime'] ) {
                    $stale = true;
                    break;
                }
            }
        }

        if ( ! $stale ) {
            echo "index is current, nothing to do\n";
            exit( 0 );
        }
    }

    $result = odr_sg_build_index( $options['source-dir'], $options['out'] );
    if ( is_string( $result ) ) {
        fwrite( STDERR, 'error: ' . $result . "\n" );
        exit( 1 );
    }

    printf(
        "wrote %s\n  %d mineral names\n  %d amcsd names\n",
        $result['path'], $result['minerals'], $result['amcsd']
    );
    foreach ( $result['sources'] as $basename => $meta )
        printf( "  %-36s %d matches\n", $basename, $meta['matched'] );
    exit( 0 );
}
