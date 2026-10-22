<?php
/**
 * Read-only diagnostic for the theme's search gate.  Changes nothing.
 *
 * Prints what the gate would decide and why: which theme is actually loaded, whether the gate's
 * functions are there, the settings, the allowlist index, the crawler ranges, and whether a
 * given visitor is currently banned, recently passed, or a verified crawler.
 *
 * Written for the case where a gated url behaves differently on one server than another.  The
 * usual answer is that PHP-FPM is still serving a previously compiled functions.php, which this
 * script cannot see -- read the note it prints at the end.
 *
 *   php tools/odr_gate_check.php /path/to/wordpress [visitor-ip] [site-host] [--with-plugins]
 *
 * Plugins aren't loaded unless --with-plugins is given: nothing checked here needs them, and some
 * don't survive being loaded from the command line.
 *
 * The ip is the address of the browser you're testing from (whatismyip), because the ban and
 * pass markers are keyed per visitor -- not the server's own address.
 */

// NOTE: not $wp -- that is one of WordPress's own globals, and clobbering it at top level
// breaks taxonomy registration during bootstrap.
// This file is inside the theme, so it is reachable over http.  It prints configuration, so
// refuse to do anything unless it was started from a shell.
if ( PHP_SAPI !== 'cli' ) {
    if ( ! headers_sent() ) {
        header( 'HTTP/1.1 403 Forbidden' );
        header( 'Content-Type: text/plain; charset=utf-8' );
    }
    echo "This diagnostic runs from the command line only.\n";
    exit;
}

// positional arguments, with any --option left out wherever it appears
$odr_args = array_values(array_filter(array_slice($argv, 1), function ($a) { return strpos($a, '--') !== 0; }));
$odr_wp_root = $odr_args[0] ?? '';
$odr_ip = $odr_args[1] ?? '';
$odr_host = $odr_args[2] ?? '';

if ($odr_wp_root === '' || !is_file(rtrim($odr_wp_root, '/') . '/wp-load.php')) {
    fwrite(STDERR, "usage: php tools/odr_gate_check.php /path/to/wordpress [visitor-ip] [site-host] [--with-plugins]\n");
    exit(1);
}

// Loading WordPress outside a web request can end the script without a word: a fatal error when
// the CLI's display_errors is off, or a wp-config / plugin redirect (force-https, multisite site
// lookup, security plugins) that exits because there's no host.  Make both of those visible.
ini_set('display_errors', 'stderr');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// Breadcrumbs, so an early stop can say how far loading got.  WordPress accepts hooks registered
// in $wp_filter before it loads, which is the only way to watch its own bootstrap from outside.
$odr_trace = array();
$odr_crumb = function ($label) {
    return array(10 => array(array(
        'function' => function ($arg = null) use ($label) {
            $GLOBALS['odr_trace'][] = (is_string($arg) && $arg !== '') ? $label . ' ' . $arg : $label;
            return $arg;
        },
        'accepted_args' => 1,
    )));
};
// Names a hook callback the way a person would look for it: Class::method or function, plus file
$odr_describe = function ($cb) {
    try {
        if (is_array($cb) && count($cb) === 2) {
            $class = is_object($cb[0]) ? get_class($cb[0]) : (string) $cb[0];
            $r = new ReflectionMethod($class, (string) $cb[1]);
            $name = $class . '::' . $cb[1];
        } elseif (is_string($cb) && strpos($cb, '::') !== false) {
            $r = new ReflectionMethod($cb);
            $name = $cb;
        } elseif ($cb instanceof Closure || is_string($cb)) {
            $r = new ReflectionFunction($cb);
            $name = ($cb instanceof Closure) ? 'closure' : $cb;
        } elseif (is_object($cb)) {
            $r = new ReflectionMethod($cb, '__invoke');
            $name = get_class($cb) . '::__invoke';
        } else {
            return 'unrecognised callback';
        }
        $file = $r->getFileName();
        return $name . ($file ? '  (' . $file . ':' . $r->getStartLine() . ')' : '');
    } catch (Throwable $e) {
        return 'unrecognised callback';
    }
};
// Runs first on a hook and wraps every other callback on it, so an exit() inside one of them
// leaves its name behind.  Only the hooks the trace showed being reached need this.
$odr_watch = function ($hook) use ($odr_describe) {
    return array(PHP_INT_MIN => array(array(
        'function' => function ($arg = null) use ($hook, $odr_describe) {
            $wp_hook = $GLOBALS['wp_filter'][$hook] ?? null;
            if (!($wp_hook instanceof WP_Hook))
                return $arg;
            foreach ($wp_hook->callbacks as $priority => $list) {
                if ($priority === PHP_INT_MIN)
                    continue;
                foreach ($list as $id => $entry) {
                    $orig = $entry['function'];
                    $label = $hook . ' priority ' . $priority . ': ' . $odr_describe($orig);
                    $wp_hook->callbacks[$priority][$id]['function'] = function (...$args) use ($orig, $label) {
                        $GLOBALS['odr_running'] = $label;
                        $result = call_user_func_array($orig, $args);
                        $GLOBALS['odr_running'] = null;
                        return $result;
                    };
                }
            }
            return $arg;
        },
        'accepted_args' => 1,
    )));
};
$odr_running = null;

$wp_filter = array(
    'mu_plugin_loaded'      => $odr_crumb('mu-plugin loaded:'),
    'muplugins_loaded'      => $odr_crumb('[all mu-plugins loaded]'),
    'network_plugin_loaded' => $odr_crumb('network plugin loaded:'),
    'plugin_loaded'         => $odr_crumb('plugin loaded:'),
    'plugins_loaded'        => $odr_crumb('[all plugins loaded]'),
    'setup_theme'           => $odr_crumb('[loading theme]'),
    'after_setup_theme'     => $odr_crumb('[theme loaded]'),
    'init'                  => $odr_crumb('[init]') + $odr_watch('init'),
    'wp_loaded'             => $odr_crumb('[wp_loaded]') + $odr_watch('wp_loaded'),
);

// Everything this checks lives in the theme, so leave plugins out of this run (it changes nothing
// stored -- they're only hidden from this one process).  Some plugins don't survive a CLI load:
// Spectra's templates library exits on wp_loaded when its WP_Filesystem() write check fails
// for a user that doesn't own the files.  Pass --with-plugins to load them anyway.
$odr_with_plugins = in_array('--with-plugins', $argv, true);
if (!$odr_with_plugins) {
    $odr_no_plugins = array(10 => array(array('function' => function () { return array(); }, 'accepted_args' => 1)));
    $wp_filter['option_active_plugins'] = $odr_no_plugins;
    $wp_filter['site_option_active_sitewide_plugins'] = $odr_no_plugins;
}

$odr_loaded = false;
register_shutdown_function(function () use (&$odr_loaded, $odr_host) {
    if ($odr_loaded)
        return;
    $e = error_get_last();
    fwrite(STDERR, "\nStopped while loading WordPress, before any checks ran.\n");
    if ($e !== null && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, sprintf("  fatal error: %s\n  in %s:%d\n", $e['message'], $e['file'], $e['line']));
    } else {
        fwrite(STDERR, "  No fatal error, so something called exit() -- usually a redirect in wp-config.php\n");
        fwrite(STDERR, "  or a plugin that expects a web request.\n");
        if ($odr_host === '')
            fwrite(STDERR, "  Try again with the site's host name as the third argument, e.g. rruff.net\n");
    }

    // how far it got: the culprit is whatever loads right after the last line here
    $trace = $GLOBALS['odr_trace'] ?? array();
    fwrite(STDERR, "\n  How far loading got:\n");
    fwrite(STDERR, sprintf("    wp-config.php read      : %s\n", defined('DB_NAME') ? 'yes' : 'NO  <-- it stopped in wp-config.php itself'));
    fwrite(STDERR, sprintf("    wp-settings.php started : %s\n", defined('WPINC') ? 'yes' : 'no'));
    if (defined('WPINC') && $trace === array()) {
        fwrite(STDERR, "    no plugin had loaded yet -- look at the drop-ins in wp-content (advanced-cache.php,\n");
        fwrite(STDERR, "    db.php, object-cache.php, sunrise.php) and at the first mu-plugin\n");
    }
    foreach ($trace as $line)
        fwrite(STDERR, "    $line\n");
    if (!empty($GLOBALS['odr_running'])) {
        fwrite(STDERR, "\n  It exited inside this callback:\n");
        fwrite(STDERR, "    " . $GLOBALS['odr_running'] . "\n");
    }
    if ($trace !== array() && strpos(end($trace), 'plugin loaded:') !== false) {
        fwrite(STDERR, "    <-- stopped while loading the NEXT plugin after this one.  List them in load order\n");
        fwrite(STDERR, "        with:  wp plugin list --status=active  (or check active_plugins in wp_options)\n");
    }
});

// What a web request would have, so host-dependent code takes the same path it does for a visitor
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = 443;
if ($odr_host !== '') {
    $_SERVER['HTTP_HOST'] = $odr_host;
    $_SERVER['SERVER_NAME'] = $odr_host;
}

define('WP_USE_THEMES', false);
require rtrim($odr_wp_root, '/') . '/wp-load.php';
$odr_loaded = true;

// A page-cache plugin may have opened an output buffer that never gets flushed outside a web
// request...close them so this report actually prints, keeping anything they'd caught
while (ob_get_level() > 0) {
    $caught = ob_get_clean();
    if (trim((string) $caught) !== '')
        fwrite(STDERR, "(output from WordPress while loading:)\n" . $caught . "\n");
}

echo "theme\n";
printf("  active theme : %s\n", get_stylesheet());
printf("  version      : %s\n", wp_get_theme()->get('Version'));
printf("  gate loaded  : %s\n", function_exists('odr_search_gate') ? 'yes' : 'NO  <-- functions.php is not the deployed version');
printf("  crawler fn   : %s\n", function_exists('odr_identify_verified_crawler') ? 'yes' : 'no');
printf("  plugins      : %s\n", $odr_with_plugins ? 'loaded (--with-plugins)' : 'not loaded for this check (not needed; add --with-plugins to load them)');

$dir = get_stylesheet_directory();
printf("  functions.php: %d gate call sites, mtime %s\n",
    substr_count((string) @file_get_contents($dir . '/functions.php'), 'odr_search_gate( '),
    date('Y-m-d H:i', (int) @filemtime($dir . '/functions.php')));

echo "\nsettings (Settings -> ODR Search Gate)\n";
$s = get_option('odr_search_gate', array());
$s = is_array($s) ? $s : array();
printf("  recaptcha keys set      : %s\n", (!empty($s['recaptcha_site_key']) && !empty($s['recaptcha_secret'])) ? 'yes' : 'NO (unlisted searches get the 404, not a challenge)');
printf("  use_name_lists          : %s\n", !empty($s['use_name_lists']) ? 'on' : 'off');
printf("  allow_verified_crawlers : %s\n", !empty($s['allow_verified_crawlers']) ? 'ON  <-- verified crawlers skip the challenge' : 'off');
printf("  block_seconds           : %d\n", (int) ($s['block_seconds'] ?? 0));
printf("  index_path              : %s\n", $s['index_path'] ?? '(default: wp-content/uploads)');

echo "\nallowlist index\n";
if (function_exists('odr_sg_index')) {
    $i = odr_sg_index();
    printf("  built    : %s\n", empty($i['built']) ? 'never' : gmdate('Y-m-d H:i', (int) $i['built']) . ' UTC');
    printf("  minerals : %d\n  amcsd    : %d\n", count($i['minerals'] ?? array()), count($i['amcsd'] ?? array()));
} else {
    echo "  (gate not loaded, so nothing to report)\n";
}

echo "\ncrawler ranges\n";
$cf = (string) ($s['crawler_ip_file'] ?? '');
printf("  setting  : %s\n", $cf !== '' ? $cf : '(not set -- Settings -> ODR Search Gate -> Crawler IP file)');
if ($cf !== '' && function_exists('odr_crawler_ip_load')) {
    $d = odr_crawler_ip_load($cf);
    if ($d['error'] !== null) {
        printf("  file     : PROBLEM -- %s\n", $d['error']);
    } else {
        printf("  file     : %d ipv4 + %d ipv6 prefixes, generated %s\n",
            count($d['data']['ipv4']), count($d['data']['ipv6']), $d['data']['generated'] ?? 'unknown');
        // this runs as whoever started it, not as the web server
        echo "             (readable by this shell user -- check the settings page to confirm the web server can read it too)\n";
    }
}

if ($odr_ip !== '') {
    echo "\nthis visitor ($odr_ip)\n";
    $b = get_transient('odr_sg_block_' . md5($odr_ip));
    $p = get_transient('odr_sg_pass_'  . md5($odr_ip));
    printf("  banned            : %s\n", $b ? 'YES, since ' . date('H:i:s', (int) $b) : 'no');
    printf("  passed recently   : %s  %s\n", $p ? 'YES, at ' . date('H:i:s', (int) $p) : 'no',
        $p ? '<-- this is why the gate lets you straight through' : '');
    if (function_exists('odr_identify_verified_crawler')) {
        $c = odr_identify_verified_crawler($odr_ip);
        printf("  verified crawler  : %s\n", $c === null ? 'no' : $c);
    }
}

echo "\nNOTE: opcache cannot be read from the command line -- the CLI has its own, separate from\n";
echo "      PHP-FPM's.  If everything above looks right but the site still behaves like the old\n";
echo "      code, reload PHP-FPM.\n";
