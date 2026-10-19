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
 *   php tools/odr_gate_check.php /path/to/wordpress [visitor-ip]
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

$odr_wp_root = $argv[1] ?? '';
$odr_ip = $argv[2] ?? '';

if ($odr_wp_root === '' || !is_file(rtrim($odr_wp_root, '/') . '/wp-load.php')) {
    fwrite(STDERR, "usage: php tools/odr_gate_check.php /path/to/wordpress [visitor-ip]\n");
    exit(1);
}

define('WP_USE_THEMES', false);
require rtrim($odr_wp_root, '/') . '/wp-load.php';

echo "theme\n";
printf("  active theme : %s\n", get_stylesheet());
printf("  version      : %s\n", wp_get_theme()->get('Version'));
printf("  gate loaded  : %s\n", function_exists('odr_search_gate') ? 'yes' : 'NO  <-- functions.php is not the deployed version');
printf("  crawler fn   : %s\n", function_exists('odr_identify_verified_crawler') ? 'yes' : 'no');

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
$cf = $dir . '/crawler_ips.php';
if (is_file($cf)) {
    $d = include $cf;
    printf("  file     : present, %d ipv4 + %d ipv6 prefixes\n", count($d['ipv4'] ?? array()), count($d['ipv6'] ?? array()));
} else {
    echo "  file     : MISSING (it is gitignored, so a deploy does not bring it)\n";
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
