<?php 
/* Template Name: ODR System Wrapper 
 *
 * @package WordPress
 * @subpackage ODR-Astra-Child-Theme
 * @since ODR Astra Child Theme 1.0
 *
 */

ob_start();
  get_header();
  $wp_header = ob_get_contents();
ob_end_clean();

ob_start();
  get_footer();
  $wp_footer = ob_get_contents();
ob_end_clean();

umask(0000);

use Symfony\Component\HttpFoundation\Request;


// This check prevents access to debug front controllers that are deployed by accident to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
if (!in_array(@$_SERVER['REMOTE_ADDR'], array(
	'216.220.244.174',
	'216.220.244.127',
    '216.220.243.238',
	'172.58.217.90',
        '127.0.0.1',
        '::1',
    ))
) {
    header('HTTP/1.0 403 Forbidden');
    print "-- " . $_SERVER['REMOTE_ADDR'] . " --<br />";
    exit('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}

/**
 * @var Composer\Autoload\ClassLoader
 */
$loader = require __DIR__.'/../../data-publisher/app/autoload.php';
require_once __DIR__.'/../../data-publisher/app/bootstrap.php.cache';
$kernel = new AppKernel('prod', false);
$kernel->loadClassCache();
$request = Request::createFromGlobals();
// Add the Wordpress Header to the Request Obj
// Fix the AST (astra) container
$request->wordpress_header = preg_replace('/ast-container">/', 'ast-container"></div><div>', $wp_header);
$request->wordpress_footer = $wp_footer;
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);


?>
