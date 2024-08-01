<?php 
/* Template Name: ODR System Wrapper 
 *
 * @package WordPress
 * @subpackage ODR-Astra-Child-Theme
 * @since ODR Astra Child Theme 1.0
 *
 */

$wp_header = "";
$wp_footer = "";
/*
 * Do not build header and footer for images and static graphs.
 * The output does not use these values.
 */
if(!preg_match("/graph\/static/", $_SERVER['REQUEST_URI'])) {

  // Begin Header Object
  ob_start();

  $output = '';
  // Run the search plugin here for search page
  /* Search Shortcode
  [odr-rruff-search-display datatype_id = "738"
      general_search = "gen"
      chemistry_incl = "7055"
    mineral_name = "7052"
      sample_id = "7069"
      redirect_url = "/odr/rruff_sample#/odr/search/display/2010"]
  */

  $content = '[odr-rruff-search-display datatype_id = "738"
      general_search = "gen"
      chemistry_incl = "7055"
      mineral_name = "7052"
      sample_id = "7069"
      redirect_url = "/odr/rruff_sample#/odr/search/display/2010"]';

  // Probably missing some CSS, etc.
  $output .= do_shortcode( $content, false );

  get_header();
  $wp_header = ob_get_contents();
  // Must inject every time because hash (#) can't be read by server
  // Hidden by default until detected by admin
  if(preg_match("/odr\/rruff_sample/", $_SERVER['REQUEST_URI'])) {
    $wp_header .= '<div id="odr_rruff_search_dialog" style="display: none;">';
    $wp_header .= $output;
    $wp_header .= '</div>';
  }

  // End Header Object
  ob_end_clean();

  ob_start();
    get_footer();
    $wp_footer_content = ob_get_contents();
    $wp_footer = '';
    // if(preg_match("/odr\/rruff_sample/", $_SERVER['REQUEST_URI'])) {
      // $wp_footer .= $output;
    // }
    $wp_footer .= $wp_footer_content;
  ob_end_clean();
}

// Replace title in header
// $wp_header = preg_replace('/<title>[.*]?=(<\/title>)/','<title>TEST</title>'); // . wp_title('&raquo;',true) . '</title>');

umask(0000);

use Symfony\Component\HttpFoundation\Request;


// This check prevents access to debug front controllers that are deployed by accident to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
/*
if (!in_array(@$_SERVER['REMOTE_ADDR'], array(
	'216.220.244.174',
	'216.220.244.127',
    '216.220.243.238',
	'216.220.243.164',
	'172.58.217.90',
        '127.0.0.1',
        '::1',
    ))
) {
    header('HTTP/1.0 403 Forbidden');
    print "-- " . $_SERVER['REMOTE_ADDR'] . " --<br />";
    exit('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}
*/

/**
 * Get Wordpress user Email and set for auto-login
 */
global $current_user;
get_currentuserinfo();
$odr_wordpress_user = $current_user->user_email;
putenv("WORDPRESS_USER=$odr_wordpress_user");

// print $odr_wordpress_user; exit();
//
global $wp_query;
status_header( 200 );
$wp_query->is_page = true;
$wp_query->is_404=false;

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

// Kernel process request
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

