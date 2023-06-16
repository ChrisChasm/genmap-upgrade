<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.


class DT_Metrics_Groups_Genmap extends DT_Metrics_Chart_Base
{
    //slug and title of the top menu folder
    public $base_slug = 'groups'; // lowercase
    public $slug = 'genmap'; // lowercase
    public $base_title;
    public $title;
    public $js_object_name = 'wp_js_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $permissions = [ 'dt_all_access_contacts', 'view_project_metrics' ];
    public $namespace = null;

    public function __construct() {
        parent::__construct();
        if ( !$this->has_permission() ){
            return;
        }
        $this->base_title = __( 'Genmap', 'disciple_tools' );
        $this->title = __( 'Groups Genmap', 'disciple_tools' );

        $url_path = dt_get_url_path( true );
        if ( "metrics/$this->base_slug/$this->slug" === $url_path ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }

        $this->namespace = "dt-metrics/$this->base_slug/$this->slug";
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function add_api_routes() {

        $version = '1';
        $namespace = 'dt/v' . $version;
        register_rest_route(
            $namespace, '/metrics/group/genmap', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'get_genmap' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

    }

    public function tree( WP_REST_Request $request ) {
        if ( !$this->has_permission() ){
            return new WP_Error( __METHOD__, 'Missing Permissions', [ 'status' => 400 ] );
        }
        return $this->get_group_generations_tree();
    }

    public function scripts() {



        $js_file_name = 'groups/genmap.js';
        wp_enqueue_script( 'dt_metrics_project_script', plugin_dir_url(__DIR__) . $js_file_name, [
            'jquery',
            'lodash'
        ], filemtime( plugin_dir_path(__DIR__) . $js_file_name ), true );
        wp_localize_script(
            'dt_metrics_project_script', 'dtMetricsProject', [
                'root' => esc_url_raw( rest_url() ),
                'theme_uri' => get_template_directory_uri(),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'current_user_login' => wp_get_current_user()->user_login,
                'current_user_id' => get_current_user_id(),
                'map_key' => empty( DT_Mapbox_API::get_key() ) ? '' : DT_Mapbox_API::get_key(),
                'data' => $this->data(),
            ]
        );

        wp_enqueue_script( 'orgchart_js', 'https://cdnjs.cloudflare.com/ajax/libs/orgchart/3.7.0/js/jquery.orgchart.min.js', [
            'jquery',
        ], '3.7.0', true );
        $css_file_name = 'common/jquery.orgchart.custom.css';
        wp_enqueue_style( 'orgchart_css', plugin_dir_url(__DIR__) . $css_file_name, [], filemtime( plugin_dir_path(__DIR__)  . $css_file_name ) );


    }

    public function data() {
        return [
            'translations' => [
                'title_group_tree' => __( 'Group Generation Tree', 'disciple_tools' ),
                'highlight_active' => __( 'Highlight Active', 'disciple_tools' ),
                'highlight_churches' => __( 'Highlight Churches', 'disciple_tools' ),
                'members' => __( 'Members', 'disciple_tools' ),
                'view_record' => __( 'View Record', 'disciple_tools' ),
                'assigned_to' => __( 'Assigned To', 'disciple_tools' ),
                'status' => __( 'Status', 'disciple_tools' ),
                'total_members' => __( 'Total Members', 'disciple_tools' ),
                'view_group' => __( 'View Group', 'disciple_tools' ),

            ],
        ];
    }

    public function get_genmap() {
        $query = dt_queries()->tree( 'multiplying_groups_only' );
        if ( is_wp_error( $query ) ){
            return $this->_circular_structure_error( $query );
        }
        if ( empty( $query ) ) {
            return $this->_no_results();
        }
        $menu_data = $this->prepare_menu_array( $query );
        dt_write_log( $menu_data );
        $group_array = $this->build_group_array( 0, $menu_data, 0 );
        dt_write_log( $group_array );
        return $group_array;
    }
    public function prepare_menu_array( $query ) {
        // prepare special array with parent-child relations
        $menu_data = array(
            'items' => array(),
            'parents' => array()
        );

        foreach ( $query as $menu_item )
        {
            $menu_data['items'][$menu_item['id']] = $menu_item;
            $menu_data['parents'][$menu_item['parent_id']][] = $menu_item['id'];
        }
        return $menu_data;
    }
    public function build_group_array( $parent_id, $menu_data, $gen ) {
        $html = [];
        $children = [];
        if ( isset( $menu_data['parents'][$parent_id] ) )
        {
            $next_gen = $gen + 1;

            foreach ( $menu_data['parents'][$parent_id] as $item_id )
            {
                $children[] = $this->build_group_array( $item_id, $menu_data, $next_gen );
            }
        }
        $html = [
            'id' => $parent_id,
            'name' => $menu_data['items'][ $parent_id ]['name'] ?? 'SYSTEM' ,
            'content' => ( $parent_id ) ? 'Gen ' . $gen . ' - ' . $menu_data['items'][ $parent_id ]['group_type'] : '',
            'children' => $children,
        ];
        return $html;
    }

}
new DT_Metrics_Groups_Genmap();


