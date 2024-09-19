<?php
/**
 * ODR Astra Child Theme Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ODR Astra Child Theme
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ODR_ASTRA_CHILD_THEME_VERSION', '1.0.8' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {
  wp_enqueue_style( 'odr-astra-child-theme-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ODR_ASTRA_CHILD_THEME_VERSION, 'all' );
}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );
add_action( 'wp_enqueue_scripts', 'ODR_include_scripts', 95 );
add_action( 'wp_enqueue_scripts', 'ODR_include_styles', 150 );


/**
 * Sets the redirect value for login redirection
 */
add_action("um_after_login_fields", function(){
    if( isset( $_SERVER['HTTP_REFERER'] ) && !isset( $_REQUEST['redirect_to'] ) ){
        // Sanitize url (remove http(s):// and domain
        $url = preg_replace("/^[^:]+:\/\/[^/?#]+/", '', $_SERVER['HTTP_REFERER']);
        echo "<input type='hidden' name='redirect_to' value='".( $url )."'>";
    }
});
/*
 * Always redirect users to homepage after login
 */
// function custom_login_redirect() {
    // return '/';
// }
// add_filter('login_redirect', 'custom_login_redirect');




/*
 * Before the 404 header is sent, this function determines
 * if the data is coming from ODR.  If so, no 404 header is
 * sent.
 */
add_action( 'pre_handle_404', function() {
    global $wp;
    $request = explode( '/', $wp->request );
    if ( is_page( 'odr' ) || preg_match("/odr/", current( $request )) ) {
        return FALSE;
    }

} );


/*
 * Always redirect users to homepage after logout
 */
add_action('wp_logout','auto_redirect_after_logout');

function auto_redirect_after_logout(){
    if (isset($_COOKIE['PHPSESSID'])) {
        setcookie('PHPSESSID', time()-3600);
    }
    wp_safe_redirect( '/' );
    exit;
}

function ODR_include_styles() {
  global $wp;
  $request = explode( '/', $wp->request );
  if ( is_page( 'odr' ) || preg_match("/odr/", current( $request )) ) {
    $base_path = "/odr_rruff/";
    // <!-- PURE CSS -->
    wp_enqueue_style('pure-css', get_site_url() . $base_path . 'css/external/pure-min.css');
    wp_enqueue_style('pure-responsive', get_site_url() . $base_path . 'css/external/pure-grids-responsive-min.css');

    // <!-- Symfony Stylesheets -->
    wp_enqueue_style('', get_site_url() . $base_path . 'css/odr_wordpress.1.8.0.css');
    // <!-- Theme CSS -->
    wp_enqueue_style('smart_1.8.0', get_site_url() . $base_path . 'css/themes/css_smart/smart_wordpress.1.8.0.css');
    
    wp_enqueue_style('font-awesome-4.6.3', get_site_url() . $base_path . 'css/fonts/font-awesome-4.6.3/css/font-awesome.css');
    // <!-- External Styles -->
    wp_enqueue_style('jquery-ui-1.12.0', get_site_url() . $base_path . 'css/external/jquery-ui-1.12.0.css');
    wp_enqueue_style('jquery.switchButton', get_site_url() . $base_path . 'css/external/jquery.switchButton.css');
    wp_enqueue_style('introjs', get_site_url() . $base_path . 'css/external/introjs.min.css');
    wp_enqueue_style('jgrowl', get_site_url() . $base_path . 'css/external/jquery.jgrowl.css');
    // <!-- Hands on table -->
    wp_enqueue_style('handsontable', get_site_url() . $base_path . 'js/mylibs/handsontable/dist/handsontable.full.min.css');
    // <!-- Dynamic Tables -->
    wp_enqueue_style('datatables', get_site_url() . $base_path . 'js/mylibs/DataTables/datatables.min.css');
    // <!-- Remodal -->
    wp_enqueue_style('remodal', get_site_url() . $base_path . 'js/mylibs/remodal-master/dist/remodal.css');
    wp_enqueue_style('remodal-default-theme', get_site_url() . $base_path . 'js/mylibs/remodal-master/dist/remodal-default-theme.css');
    // <!-- Overhang -->
    wp_enqueue_style('overhang', get_site_url() . $base_path . 'js/mylibs/overhang/overhang.min.css');
    // <!-- Plotly Bars Stylesheet -->
    wp_enqueue_style('plotyl_bars', get_site_url() . $base_path . 'css/external/plotly_bars.css');
    // <!-- OpenLayers -->
    wp_enqueue_style('openlayers', get_site_url() . $base_path . 'css/external/ol.css');
  }
}


function ODR_include_scripts() {
  global $wp;
  $request = explode( '/', $wp->request );
  if ( is_page( 'odr' ) || preg_match("/odr/", current( $request )) ) {
    $base_path = "/odr_rruff/";
    // <!-- ODR Main JS Bundle -->
    // wp_enqueue_script('', get_site_url() . $base_path . 'js/bundle.js');

    // <!-- Essential polyfills -->
    wp_enqueue_script('modernizr', get_site_url() . $base_path . '/js/mylibs/polyfills/modernizr-2.6.1.min.js');
    // <!--[if lt IE 9]>wp_enqueue_script('', get_site_url() . $base_path . 'js/mylibs/polyfills/selectivizr.js"></script><![endif]-->
    // <!--[if lt IE 10]>wp_enqueue_script('', get_site_url() . $base_path . 'js/mylibs/polyfills/excanvas.js"></script><![endif]-->
    // <!--[if lt IE 10]>wp_enqueue_script('', get_site_url() . $base_path . 'js/mylibs/polyfills/classlist.js"></script><![endif]-->
    // <!-- Grab Google CDN's jQuery, with a protocol relative URL; fall back to local if offline -->
    wp_enqueue_script('jquery-3.3.1', 'https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js');
    // <!-- Do the same with jQuery UI -->
    wp_enqueue_script('jquery-ui-1.12.0', get_site_url() . $base_path . 'js/libs/jquery-ui-1.12.0.min.js');
    wp_enqueue_script('lo-dash', get_site_url() . $base_path . 'js/libs/lo-dash.min.js');
    // <!-- General Scripts -->
    wp_enqueue_script('hashchange', get_site_url() . $base_path . 'js/mylibs/jquery.hashchange.js');
    wp_enqueue_script('idle-timer', get_site_url() . $base_path . 'js/mylibs/jquery.idle-timer.js');
    wp_enqueue_script('jgrowl', get_site_url() . $base_path . 'js/mylibs/jquery.jgrowl.js');
    wp_enqueue_script('scrollTo', get_site_url() . $base_path . 'js/mylibs/jquery.scrollTo.js');
    wp_enqueue_script('jquery.ui.touch-punch', get_site_url() . $base_path . 'js/mylibs/jquery.ui.touch-punch.js');
    wp_enqueue_script('switchButton', get_site_url() . $base_path . 'js/mylibs/jquery.switchButton.js');
    wp_enqueue_script('introjs', get_site_url() . $base_path . 'js/mylibs/introjs.min.js');
    wp_enqueue_script('jquery.md5', get_site_url() . $base_path . 'js/libs/plugins/md5/jquery.md5.js');
    // <!-- Tooltips -->
    wp_enqueue_script('tipsy', get_site_url() . $base_path . 'js/mylibs/tooltips/jquery.tipsy.js');
    // <!-- Multiple/Large File Uploads -->
    wp_enqueue_script('flow', get_site_url() . $base_path . 'js/mylibs/flow.js/dist/flow.min.js');
    // <!-- What is this for? -->
    wp_enqueue_script('plusplus', get_site_url() . $base_path . 'js/mylibs/jquery.plusplus.js');
    // <!-- Probably not used -->
    wp_enqueue_script('multiaccordion', get_site_url() . $base_path . 'js/mylibs/jquery.ui.multiaccordion.js');
    // <!-- TODO Ancient - is this needed. -->
    wp_enqueue_script('number-functions', get_site_url() . $base_path . 'js/mylibs/number-functions.js');
    // <!-- Forms -->
    wp_enqueue_script('validate', get_site_url() . $base_path . 'js/libs/plugins/validate/jquery.validate.min.js');
    // <!-- Fullstats -->
    wp_enqueue_script('css-transform', get_site_url() . $base_path . 'js/mylibs/fullstats/jquery.css-transform.js');
    wp_enqueue_script('animate-css-rotate-scale', get_site_url() . $base_path . 'js/mylibs/fullstats/jquery.animate-css-rotate-scale.js');
    wp_enqueue_script('sparkline', get_site_url() . $base_path . 'js/mylibs/fullstats/jquery.sparkline.js');
    // <!-- Dynamic Tables -->
    wp_enqueue_script('datatables', get_site_url() . $base_path . 'js/mylibs/DataTables/datatables.min.js');
    wp_enqueue_script('datatables.inut', get_site_url() . $base_path . 'js/mylibs/DataTables/datatables.input.js');
    wp_enqueue_script('handsontable', get_site_url() . $base_path . 'js/mylibs/handsontable/dist/handsontable.full.min.js');
    // <!-- Images Loaded -->
    wp_enqueue_script('imagesloaded', get_site_url() . $base_path . 'js/mylibs/imagesloaded/imagesloaded.pkgd.js');
    // <!-- In-browser Markdown -->
    wp_enqueue_script('markdown', get_site_url() . $base_path . 'js/mylibs/markdown-it-8.4.0/dist/markdown-it.min.js');
    // <!-- Ajax File Downloads -->
    wp_enqueue_script('fileDownload', get_site_url() . $base_path . 'js/mylibs/jquery.fileDownload.js');
    // <!-- Plot.ly JS Plots Based on D3 -->
    wp_enqueue_script('d3.v4', get_site_url() . $base_path . 'js/mylibs/d3.v4.min.js');
    wp_enqueue_script('plotly-1.58.5', get_site_url() . $base_path . 'js/mylibs/plotly-1.58.5.min.js');
    wp_enqueue_script('d3-queue', get_site_url() . $base_path . 'js/mylibs/d3-queue.v3.min.js');
    wp_enqueue_script('odr_plotly_graphs', get_site_url() . $base_path . 'js/mylibs/odr_plotly_graphs.js');
    wp_enqueue_script('remodal', get_site_url() . $base_path . 'js/mylibs/remodal-master/dist/remodal.js');
    // <!-- Overhang -->
    wp_enqueue_script('overhang', get_site_url() . $base_path . 'js/mylibs/overhang/overhang.min.js');
    // <!-- App JS -->
    wp_enqueue_script('odr_app', get_site_url() . $base_path . 'js/app.js');
    // <!-- Openlayers -->
    wp_enqueue_script('openlayers', get_site_url() . $base_path . 'js/mylibs/ol.js');
    // <!-- Render Plugins -->
    // <script type="text/javascript" src="{{ site_baseurl }}/js/mylibs/odr_cellparam_plugin.js"></script>
    // <script type="text/javascript" src="{{ site_baseurl }}/js/mylibs/odr_chemistry_plugin.js"></script>
    // <script type="text/javascript" src="{{ site_baseurl }}/js/mylibs/odr_graph_plugin.js"></script>
    // <script type="text/javascript" src="{{ site_baseurl }}/js/mylibs/odr_gcms_plugin.js"></script>

    wp_enqueue_script('odr_cellparam', get_site_url() . $base_path . '/js/mylibs/odr_cellparam_plugin.js');
    wp_enqueue_script('odr_chemistry', get_site_url() . $base_path . 'js/mylibs/odr_chemistry_plugin.js');
    wp_enqueue_script('odr_graph', get_site_url() . $base_path . 'js/mylibs/odr_graph_plugin.js');
    // <script type="text/javascript" src="{{ site_baseurl }}/js/mylibs/odr_gcms_plugin.js"></script>
    wp_enqueue_script('odr_gcms', get_site_url() . $base_path . 'js/mylibs/odr_gcms_plugin.js');
    // <script type="text/javascript" src="{{ site_baseurl }}/js/mylibs/odr_filter_graph_plugin.js"></script>
    wp_enqueue_script('odr_filter_graph', get_site_url() . $base_path . 'js/mylibs/odr_filter_graph_plugin.js');
  }
}

add_action('wp_head', 'add_ODR_headers');
function add_ODR_headers(){
    global $post;
    global $wp;
    $request = explode( '/', $wp->request );
    if ( 
      ( is_page( 'odr' ) || preg_match("/odr/", current( $request )) )
      && !empty($post)) {
        // get custom field for 'head'
        $headHTML = get_post_meta($post->ID, 'head', true);
        if(!empty($headHTML)){
	    $odr_headers = file_get_contents(__DIR__.'/../../data-publisher/app/Resources/views/header.html.twig');
	    $headHTML .= $odr_headers;
            echo $headHTML;
        }
    }
}

/**
 * Determines if the URL request is for a known mineral
 * @param $url_stub
 * @return bool
 */
function is_mineral_name($url_stub) {
    include_once(__DIR__.'/../../data-publisher/web/uploads/IMA/mineral_names.php');
    foreach($mineral_names_lowercase as $mineral_name) {
        if(trim(strtolower($url_stub)) === $mineral_name) {
           return true;
        }
    }
    return false;
}

// Load custom template for web requests going to "/account" or "/account/<..>/..."
add_filter( 'template_include', 'odr_load_system_template' );
/**
 * This seems to run on every page
 * @param $original_template
 * @return void
 */
function odr_load_system_template( $original_template ) {
  global $wp;
  $request = explode( '/', $wp->request );
  if ( is_page( 'odr' ) || preg_match("/odr/", current( $request )) ) {
        return plugin_dir_path( __FILE__ ) . 'page-odr.php';
  }
  else if(preg_match("/^([R|r]\d+)$/", $wp->request, $matches)) {
      // {"dt_id":"738","7069":"r040032"}
      $search_params = [];
      $search_params['dt_id'] = 738;
      $search_params['7069'] = $request[count($request)-1];
      $search_query = base64_encode(json_encode($search_params));
      $search_query = preg_replace('/\=+$/','',$search_query);
      $baseurl = '/odr/rruff_sample#/odr/search/display/2010/' . $search_query;
      wp_redirect($baseurl);
  }
  else if (is_mineral_name($request[count($request)-1])) {
      if(preg_match('/ima\//',$wp->request)) {
          // Build Base64 URL for IMA
          // {"dt_id":"736","7052":"actinolite","7062":"-1094,-1104"}
          $search_params = [];
          $search_params['dt_id'] = 736;
          $search_params['7052'] = $request[count($request)-1];
          $search_params['7062'] = "-1094,-1104";
          $search_query = base64_encode(json_encode($search_params));
          $search_query = preg_replace('/\=+$/','',$search_query);
          $baseurl = '/odr/ima#/odr/search/display/2004/' . $search_query;
      }
      else {
          // {"dt_id":"738","gen":"Tetradymite","7052":"Tetradymite"}
          $search_params = [];
          $search_params['dt_id'] = 738;
          $search_params['7052'] = $request[count($request)-1];
          $search_query = base64_encode(json_encode($search_params));
          $search_query = preg_replace('/\=+$/','',$search_query);
          $baseurl = '/odr/rruff_sample#/odr/search/display/2010/' . $search_query;
      }
      wp_redirect($baseurl);
  }
  return $original_template;
}

/*
 * Disable 404 redirects when unknown request goes to "/account/<..>/..." 
 * which allows a custom template to load. See https://wordpress.stackexchange.com/questions/3326/301-redirect-instead-of-404-when-url-is-a-prefix-of-a-post-or-page-name
 *
 */
add_filter('redirect_canonical', 'odr_disable_404_redirection_for_odr_system');

/**
 * Only runs on 404/NOT FOUND
 * @param $redirect_url
 * @return void
 */
function odr_disable_404_redirection_for_odr_system( $redirect_url ) {
    global $wp;
    $request = explode( '/', $wp->request );
    if ( is_page( 'odr' ) || preg_match("/odr/", current( $request )) ) {
        return false;
    }
    return $redirect_url;
}

// Makes sure that any request going to /account/... will respond with a proper 200 http code
add_action( 'init', 'odr_rewrites_init' );
function odr_rewrites_init(){
    add_rewrite_rule( '^odr/(.+)', 'index.php', 'top' );
}

/*
 * Add user to ODR after user add in Wordpress
 */
add_action( 'user_register', function ( $user_id ) {
    // TODO Add new action here
} );