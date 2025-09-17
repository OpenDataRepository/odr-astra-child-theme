<?php
use Symfony\Component\HttpFoundation\Request;


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
define( 'CHILD_THEME_ODR_ASTRA_CHILD_THEME_VERSION', '1.0.9' );

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
add_action(/**
 * @return void
 */ "um_after_login_fields", function(){
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
    $current_uri = $_SERVER['REQUEST_URI'];
    $request = explode('/', $current_uri);

    switch ($current_uri) {
        // TODO Handle External Referrers from MinDat & WebMin
        //
        // Mindat - Raman & XRD Search
        // http://rruff.geo.arizona.edu/rruff/new_rruff/constructor.php?txt_mineral=actinolite&sel_chemistry_incl_bool=and&txt_chemistry_incl=&sel_chemistry_excl_bool=and&txt_chemistry_excl=&sel_general_bool=and&txt_general=&sel_sort=names&sel_sort_dir=asc&limit=100000&offset=0&r=sample_search&new_sample_search=1
        case (bool)preg_match('/^\/rruff\/new_rruff\/constructor.php/i', $current_uri):
            if(!preg_match('/\/\?/', $current_uri)) {
                parse_str($_SERVER['QUERY_STRING'], $queryArray);
                // PROD {"dt_id":"738","sort_by":[{"sort_df_id":"7052","sort_dir":"asc"}],"7052":"actinolite"}
                $search_params = [];
                $search_params['dt_id'] = 738;
                $search_params['7052'] = $queryArray['txt_mineral'];
                $search_params['sort_by'] = [[
                    "sort_df_id" => "7052",
                    "sort_dir" => "asc"
                ]];
                $search_query = base64_encode(json_encode($search_params));
                $search_query = preg_replace('/\=+$/', '', $search_query);
                $baseurl = '/odr/rruff_sample#/odr/search/display/0/' . $search_query;
                wp_redirect($baseurl); exit();
            }
            break;
        //
        // Mindat - Reference Search
        // https://beta.rruff.net/odr/rruff_reference#/odr/search/display/1999/eyJkdF9pZCI6IjczNCJ9/1
        // http://rruff.info/rruff_1.0/reference_search.php?txt_mineral=actinolite&rruff_action=sbmt_reference_search
        case (bool)preg_match('/^\/rruff_1.0\/reference_search.php/i', $current_uri):
            if(!preg_match('/\/\?/', $current_uri)) {
                parse_str($_SERVER['QUERY_STRING'], $queryArray);
                // PROD {"dt_id":"738","sort_by":[{"sort_df_id":"7052","sort_dir":"asc"}],"7052":"actinolite"}
                $search_params = [];
                $search_params['dt_id'] = 734;
                $search_params['gen'] = $queryArray['txt_mineral'];
                $search_query = base64_encode(json_encode($search_params));
                $search_query = preg_replace('/\=+$/', '', $search_query);
                $baseurl = '/odr/rruff_reference#/odr/search/display/0/' . $search_query;
                wp_redirect($baseurl); exit();
            }
            break;
        //
        // Mindat - AMCSD Search
        // http://rruff.geo.arizona.edu/AMS/result.php?mineral=actinolite
        case (bool)preg_match('/^\/AMS\/result.php/i', $current_uri):
            if(!preg_match('/\/\?/', $current_uri)) {
                parse_str($_SERVER['QUERY_STRING'], $queryArray);
                // PROD {"dt_id":"738","sort_by":[{"sort_df_id":"7052","sort_dir":"asc"}],"7052":"actinolite"}
                $search_params = [];
                $search_params['dt_id'] = 771;
                $search_params['7197'] = $queryArray['mineral'];
                // TODO Cover all parameters.
                /*
                 * Keyword	Searches by	Sample Query
                 *  mineral	By mineral name
                 *  http://rruff.geo.arizona.edu/AMS/result.php?mineral=heulandite
                 *  Returns all the datafiles for the mineral heulandite.
                 *
                 *  Alternately, you may also search for minerals using this form:
                 *  http://rruff.geo.arizona.edu/AMS/minerals/heulandite
                 *
                   author	By author name	http://rruff.geo.arizona.edu/AMS/result.php?author=Downs R T
                 *  Returns all the datafiles for the author Downs R T.
                 *
                 *  Alternately, you may also search for authors using this form:
                 *  http://rruff.geo.arizona.edu/AMS/authors/Downs
                 *
                 *  chemistry	By element	http://rruff.geo.arizona.edu/AMS/result.php?chemistry=Mg,Si,O
                 *  Returns all files containing the elements Mg,Si,O.
                 *
                 *  CellValues	By cell parameters	http://rruff.geo.arizona.edu/AMS/result.php?CellValues=sg=A2_122
                 *  Returns all files with the space group A2_122.
                 *
                 *  key	By keyword	http://rruff.geo.arizona.edu/AMS/result.php?key=Arizona
                 *  Returns all the datafiles containing the Search Term "Arizona".
                 *
                 *  diff	Diffraction Values	http://rruff.geo.arizona.edu/AMS/result.php?diff=vals(9.4401,6.6713),opt(),type(d-spacing),tolerance(.001)
                 *  Returns all records with d-spacing values of 9.4401 and 6.6713 with a tolerance of .001.
                 *
                 *  http://rruff.geo.arizona.edu/AMS/result.php?diff=vals(6.6713,9.4401),opt(Cu),type(2-Theta),tolerance(.5)
                 *
                 *  Returns records with 2-theta values using a wavelength of copper and a tolerance of .5.
                 *
                 *  viewing	Sets viewing format	http://rruff.geo.arizona.edu/AMS/result.php?author=Downs R T&viewing=dif
                 *  Would return all records by author "Downs R T" and sets viewing format to diffraction values. Available options are "amc" (the default), "cif," or "dif", which will create a download file with diffraction data. A special option "rawcif" is available that returns a raw text dump of the cif data results.
                 *
                 *  download	Sets download format	http://rruff.geo.arizona.edu/AMS/result.php?author=Downs R T&download=dif
                 *  Would return all records by author "Downs R T" and sets download format to diffraction values. Available options are "amc" (the default), "cif," or "dif", which will create a download file with diffraction data.
                 *
                 *  logic	Sets matching logic format	http://rruff.geo.arizona.edu/AMS/result.php?author=Downs R T&mineral=Hazenite&download=dif
                 *  Would return all records by author "Downs R T" AND matching mineral name "Hazenite". Default is "AND".
                 *
                 */
                $search_query = base64_encode(json_encode($search_params));
                $search_query = preg_replace('/\=+$/', '', $search_query);
                $baseurl = '/odr/amcsd#/odr/search/display/0/' . $search_query;
                wp_redirect($baseurl); exit();
            }
            break;
        //
        // Mindat - Reference PDF Direct Links
        // http://rruff.geo.arizona.edu/doclib/am/vol83/AM83_458.pdf
        //
        /*
         * odr_download_file_by_name:
         *  path: /view/{datatype_uuid}/file_download/{file_name}
         *  defaults: { _controller: ODRAdminBundle:API:fileDownloadByName }
         *  methods: [ GET ]
         *  requirements:
         *      datatype_uuid: '^[a-z0-9]+$'
                file_name: '[a-zA-Z0-9\s\.-]+'
         */
        case (bool)preg_match('/^\/doclib\/am/i', $current_uri):
            if(!preg_match('/\/\?/', $current_uri)) {
                $parts = preg_split('/\//', $current_uri);
                $baseurl = '/odr/view/734/file_download/' . $parts[count($parts) - 1];
                wp_redirect($baseurl); exit();
            }
            break;



        //
        // IMA Mineral List - Mineral Sesarch
        //
        case (bool)preg_match('/^\/ima-mineral-list\//i', $current_uri):
            if(!preg_match('/\/\?/', $current_uri)) {
                $parts = preg_split('/\//', $current_uri);;
                $baseurl = '/' . $parts[1] . '/?' . $parts[2];
                wp_redirect($baseurl); exit();
            }
            break;

        //
        // IMA Data lookup - Mineral
        //
        case (bool)preg_match('/^\/ima\/./i', $current_uri):
            // Build Base64 URL for IMA
            // {"dt_id":"736","7052":"actinolite","7062":"-1094,-1104"}
            $search_params = [];
            $search_params['dt_id'] = 736;
            $search_params['7052'] = urldecode($request[count($request)-1]);
            $search_params['7062'] = "-1094,-1104";
            $search_query = base64_encode(json_encode($search_params));
            $search_query = preg_replace('/\=+$/','',$search_query);
            $baseurl = '/odr/ima#/odr/search/display/2004/' . $search_query;
            wp_redirect($baseurl); exit();
            break;

        //
        // AMCSD Mineral Search
        //
        case (bool)preg_match('/^\/amcsd\/./i', $current_uri):
            // Build Base64 URL for AMCSD
            // odr/amcsd#/odr/search/display/2187/
            // {"dt_id":"736","7052":"actinolite","7062":"-1094,-1104"}
            $search_params = [];
            $search_params['dt_id'] = 771;
            $search_params['7197'] = urldecode($request[count($request)-1]);
            $search_query = base64_encode(json_encode($search_params));
            $search_query = preg_replace('/\=+$/','',$search_query);
            $baseurl = '/odr/amcsd#/odr/search/display/2187/' . $search_query;
            wp_redirect($baseurl); exit();
            break;

        // RRUFF - Mineral or RRUFF ID Search
        case (bool)preg_match('/^\/./i', $current_uri):
            // Adding exceptions for know Wordpress or ODR Paths
            if (
                !preg_match('/^\/odr\//', $current_uri)
                && !preg_match('/^\/wp-admin\//i', $current_uri)
                && !preg_match('/^\/about\/$/i', $current_uri)
                && !preg_match('/^\/ima$/i', $current_uri)
                && !preg_match('/^\/ima\/$/i', $current_uri)
                && !preg_match('/^\/ima-mineral-list$/i', $current_uri)
                && !preg_match('/^\/ima-mineral-list\/$/i', $current_uri)
                && !preg_match('/^\/amcsd$/i', $current_uri)
                && !preg_match('/^\/amcsd\/$/i', $current_uri)
                && !preg_match('/^\/rruff_reference\/$/i', $current_uri)
                && !preg_match('/^\/rruff_cellparams\/$/i', $current_uri)
                && !preg_match('/^\/about\/contact-us$/i', $current_uri)
                && !preg_match('/^\/about\/contact-us\/$/i', $current_uri)
                && !preg_match('/^\/about\/contribute$/i', $current_uri)
                && !preg_match('/^\/about\/contribute\/$/i', $current_uri)
            ) {
                // Matches RRUFF IDs
                // PROD {"dt_id":"738","sort_by":[{"sort_df_id":"7052","sort_dir":"asc"}],"7052":"actinolite"}
                $search_params = [];
                $search_params['dt_id'] = 738;
                $search_params['7052'] = $request[count($request) - 2];
                $search_params['sort_by'] = [[
                   "sort_df_id" => "7052",
                    "sort_dir" => "asc"
                ]];
                // var_dump(json_encode($search_params));exit();
                $search_query = base64_encode(json_encode($search_params));
                $search_query = preg_replace('/\=+$/', '', $search_query);
                $baseurl = '/odr/rruff_sample#/odr/search/display/0/' . $search_query;
                wp_redirect($baseurl); exit();
            }
            break;
    }

    $request = explode('/', $wp->request);
    if (
        is_page('odr')
        || preg_match("/odr/", current($request))
        || preg_match("/ima/", current($request))
    ) {
        print "TRIGGER"; exit();
        // TODO This isn't used for anything
        $wp->odr_original_url = $wp->request;
        return FALSE;
    }
});


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
      && !empty($post)
    ) {
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
    include_once(__DIR__.'/../../data-publisher/web/uploads/IMA/mineral_names_update.php');
    foreach($mineral_names_lowercase as $mineral_name) {
        if(trim(mb_strtolower(urldecode($url_stub))) === $mineral_name) {
           return true;
        }
    }
    return false;
}

// Load custom template for web requests going to "/account" or "/account/<..>/...
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
  /*
  else if(preg_match("/^([R|r]\d+)$/", $wp->request, $matches)) {
      // Matches RRUFF IDs
      // {"dt_id":"738","7069":"r040032"}
      $search_params = [];
      $search_params['dt_id'] = 738;
      $search_params['7069'] = $request[count($request)-1];
      $search_query = base64_encode(json_encode($search_params));
      $search_query = preg_replace('/\=+$/','',$search_query);
      $baseurl = '/odr/rruff_sample#/odr/search/display/2010/' . $search_query;
      wp_redirect($baseurl);
  }
  else if (count($request) == 1 && is_mineral_name($request[count($request)-1])) {
      // if directly match mineral name -> RRUFF
      // This should be a bare stub
      $search_params = [];
      $search_params['dt_id'] = 738;
      $search_params['7052'] = urldecode($request[count($request)-1]);
      $search_query = base64_encode(json_encode($search_params));
      $search_query = preg_replace('/\=+$/','',$search_query);
      $baseurl = '/odr/rruff_sample#/odr/search/display/2010/' . $search_query;
      wp_redirect($baseurl);
  }
  else if (is_mineral_name($request[count($request)-1])) {
      // Else match /stub/mineral_name
      // Checking last parameter - is mineral
      // Matching IMA List
      if(preg_match('/^ima-mineral-list\//i',$wp->request)) {
          $parts = preg_split('/\//', $wp->request);
          $baseurl = '/' . $parts[0] . '/?' . $parts[1];
          wp_redirect($baseurl);
      }
      // Matching IMA DATA
      else if(preg_match('/^ima\//i',$wp->request)) {
          // Build Base64 URL for IMA
          // {"dt_id":"736","7052":"actinolite","7062":"-1094,-1104"}
          $search_params = [];
          $search_params['dt_id'] = 736;
          $search_params['7052'] = urldecode($request[count($request)-1]);
          $search_params['7062'] = "-1094,-1104";
          $search_query = base64_encode(json_encode($search_params));
          $search_query = preg_replace('/\=+$/','',$search_query);
          $baseurl = '/odr/ima#/odr/search/display/2004/' . $search_query;
          wp_redirect($baseurl);
      }
      else if(preg_match('/^amcsd\//i',$wp->request)) {
          // Build Base64 URL for AMCSD
          // odr/amcsd#/odr/search/display/2187/
          // {"dt_id":"736","7052":"actinolite","7062":"-1094,-1104"}
          $search_params = [];
          $search_params['dt_id'] = 771;
          $search_params['7197'] = urldecode($request[count($request)-1]);
          $search_query = base64_encode(json_encode($search_params));
          $search_query = preg_replace('/\=+$/','',$search_query);
          $baseurl = '/odr/amcsd#/odr/search/display/2187/' . $search_query;
          wp_redirect($baseurl);
      }
      // Matching RRUFF Sample
      else {
          // {"dt_id":"738","gen":"Tetradymite","7052":"Tetradymite"}
      }
  }
    */
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
    if (
        is_page( 'odr' )
        || preg_match("/odr/", current( $request ))
        || preg_match("/ima/", current( $request ))
    ) {
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

add_action( 'profile_update', 'odr_profile_update', 10, 2 );


/**
 * Synchronize WordPress profile email changes with ODR
 *
 * object(stdClass)[1286]
 * public 'ID' => string '1' (length=1)
 * public 'user_login' => string 'rruff' (length=5)
 * public 'user_pass' => string '$wp$2y$10$tQ1j3OjH1v5R1cHYiSUm9.Ozzm7pDP4i.dm23gvoLZNc3LDncsMRe' (length=63)
 * public 'user_nicename' => string 'rruff' (length=5)
 * public 'user_email' => string 'nate@opendatarepository.org' (length=27)
 * public 'user_url' => string 'http://beta.rruff.net' (length=21)
 * public 'user_registered' => string '2022-11-25 16:20:19' (length=19)
 * public 'user_activation_key' => string '' (length=0)
 * public 'user_status' => string '0' (length=1)
 * public 'display_name' => string 'rruff' (length=5)
 */
function odr_profile_update( $user_id, $old_user_data ) {
    // Get Old User Data
    $old_user_email = $old_user_data->data->user_email;

    // Get Latest User Data
    $user = get_userdata( $user_id );
    $new_user_email = $user->user_email;

    // Update Changed Email
    if ( $new_user_email !== $old_user_email ) {
        // Load Symfony Components & Wordpress globals
        putenv("WORDPRESS_USER=$new_user_email");
        putenv("WORDPRESS_USER_OLD_EMAIL=$old_user_email");
        // Run the ODR Kernel
        runODRKernel();
    }

}

add_filter( 'insert_user_meta', function( $meta, $user, $update ) {
    if( true !== $update ) return $meta;

    // Get the previous meta
    $old_meta = get_user_meta( $user->ID );

    if( $old_meta[ 'first_name' ][0] !== $meta[ 'first_name' ]
        || $old_meta[ 'last_name' ][0] !== $meta[ 'last_name' ]
    ) {
        putenv("WORDPRESS_ODR_ADMIN_ACTION=true");
        putenv("WORDPRESS_UPDATE_USER=true");
        putenv("WORDPRESS_USER=".$user->data->user_email);
        putenv("WORDPRESS_USER_FIRST_NAME=".$meta['first_name']);
        putenv("WORDPRESS_USER_LAST_NAME=".$meta['last_name']);
        // Run the ODR Kernel
        runODRKernel();
    }
    return $meta;
}, 1, 3 );


/**
 * Adds action to display custom admin notices
 *
 * Uses getTransient to get process success/fail
 * variables from related WPAutoLoginSubscriber
 *
 */
add_action( 'admin_notices', 'odr_admin_notice' );

/**
 * Displays an admin notice based on specific transient values related to ODR user actions.
 *
 * This function checks for transients that indicate different scenarios
 * such as user creation, update, or errors in ODR. Based on the transient,
 * it triggers a corresponding admin notice.
 *
 * @return void
 */
function odr_admin_notice() {
    if( get_transient( 'odr-admin-notice-user-created' ) ){
        // Need to add link to open ODR Permissions System (for specific user?)
        wp_admin_notice( '<p>User created in ODR -- success.<br />Please use the link below to grant user permissions in 
        ODR:<br /><br /><a href="/odr#/odr/admin/user/list" target="_blank">Edit Permissions</a></p>', [ 'type' => 'success', 'dismissible' => true ] );
        delete_transient( 'odr-admin-notice-user-created' );
    }
    if( get_transient( 'odr-admin-notice-user-found' ) ){
        // Need to add link to open ODR Permissions System (for specific user?)
        wp_admin_notice( 'User found in ODR -- success.', [ 'type' => 'success' , 'dismissible' => true] );
        delete_transient( 'odr-admin-notice-user-found' );
    }
    if( get_transient( 'odr-admin-notice-user-updated-email' ) ){
        wp_admin_notice( 'User email updated in ODR (auto-login) -- success.', [ 'type' => 'success' , 'dismissible' => true] );
        delete_transient( 'odr-admin-notice-user-updated-email' );
    }
    if( get_transient( 'odr-admin-notice-user-updated-email-exists' ) ){
        wp_admin_notice( 'User updated in ODR (account already exists) -- success.', [ 'type' => 'success' , 'dismissible' => true] );
        delete_transient( 'odr-admin-notice-user-updated-email-exists' );
    }
    if( get_transient( 'odr-admin-notice-user-updated' ) ){
        wp_admin_notice( 'User updated in ODR -- success.', [ 'type' => 'success' , 'dismissible' => true] );
        delete_transient( 'odr-admin-notice-user-updated' );
    }
    if( get_transient( 'odr-admin-notice-error' ) ){
        wp_admin_notice( 'Error with ODR profile update.', [ 'type' => 'error' , 'dismissible' => true] );
        delete_transient( 'odr-admin-notice-error' );
    }
    // else if( get_transient( 'odr-admin-notice-user-created' ) ){
        // wp_admin_notice( 'User created in ODR -- success.', [ 'type' => 'success' ] );
    // }
    // else if( get_transient( 'odr-admin-notice-user-created' ) ){
        // wp_admin_notice( 'User created in ODR -- success.', [ 'type' => 'success' ] );
    // }
}

add_action( 'user_register', 'odr_registration_save', 10, 1 );

/**
 * Handles the registration of a new user and integrates the user creation with the ODR system.
 *
 * @param int $user_id The ID of the newly created WordPress user.
 * @return void
 */
function odr_registration_save( $user_id ) {

    // if ( isset( $_POST['first_name'] ) )
        // update_user_meta($user_id, 'first_name', $_POST['first_name']);

    /**
     * Array (
     *   [action] => createuser
     *   [_wpnonce_create-user] => ed51632490
     *   [_wp_http_referer] => /wp-admin/user-new.php
     *   [user_login] => test_2025080601
     *   [email] => test_2025080601@stoneumbrella.com
     *   [first_name] => Test
     *   [last_name] => 2025080601
     *   [url] =>
     *   [pass1] => DPz**orcSz(QmB^Ze3xLRN*N
     *   [pass2] => DPz**orcSz(QmB^Ze3xLRN*N
     *   [send_user_notification] => 1
     *   [role] => subscriber
     *   [createuser] => Add User
     * )
     */

    // Create user in ODR using user email
    if(
        isset( $_POST['action'] ) && $_POST['action'] == 'createuser'
        && isset( $_POST['user_login'] ) && isset( $_POST['email'] )
        && isset( $_POST['first_name'] ) && isset( $_POST['last_name'] )
    ) {
        if(strlen($_POST['email']) > 0) {
            putenv("WORDPRESS_ODR_ADMIN_ACTION=true");
            putenv("WORDPRESS_CREATE_USER=true");
            putenv("WORDPRESS_USER=".$_POST['email']);
            putenv("WORDPRESS_USER_FIRST_NAME=".$_POST['first_name']);
            putenv("WORDPRESS_USER_LAST_NAME=".$_POST['last_name']);
            // Run the ODR Kernel
            runODRKernel();
        }
    }

}

add_filter( 'wp_nav_menu_items', 'odr_dynamic_menu_items', 10, 2);
/**
 * Modifies dynamic menu items by replacing placeholders with user-specific data.
 *
 * @param string $menu_string The original menu string that may contain placeholders.
 * @param array $args Additional arguments for the menu (typically unused in this function).
 *
 * @return string The updated menu string with placeholders replaced by dynamic content.
 */
function odr_dynamic_menu_items($menu_string, $args ) {
    if(preg_match('/#profile_name#/', $menu_string)) {
        // Or do_shortcode(), if you must.
        $user = wp_get_current_user();
        $menu_string = preg_replace(
            '/#profile_name#/',
            $user->user_firstname,
            $menu_string
        );
    }
    if(preg_match('/#wp_logout_url#/', $menu_string)) {
        // Or do_shortcode(), if you must.
        $user = wp_get_current_user();
        $menu_string = preg_replace(
            '/#wp_logout_url#/',
            wp_logout_url(),
            $menu_string
        );
    }
    return $menu_string;
}



/**
 * Executes the ODR Kernel by handling and processing the incoming HTTP request.
 *
 * @param bool $send Determines whether the response should be sent and the kernel terminated.
 * @return void
 */
function runODRKernel($send = false) {
    /**
     * @var Composer\Autoload\ClassLoader
     */
    $loader = require __DIR__.'/../../data-publisher/app/autoload.php';
    require_once __DIR__.'/../../data-publisher/app/bootstrap.php.cache';
    $kernel = new AppKernel('prod', false);
    $kernel->loadClassCache();
    $request = Request::createFromGlobals();

    // Kernel process request
    $response = $kernel->handle($request);
    if($send) {
        $response->send();
        $kernel->terminate($request, $response);
    }
}