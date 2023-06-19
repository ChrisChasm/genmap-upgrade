<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.


class DT_Metrics_Groups_Genmap extends DT_Metrics_Chart_Base
{
    //slug and title of the top menu folder
    public $base_slug = 'combined'; // lowercase
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
        $this->title = __( 'Genmap', 'disciple_tools' );

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
            $namespace, '/metrics/combined/genmap', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'tree' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }
    public function tree( WP_REST_Request $request ) {
        if ( !$this->has_permission() ){
            return new WP_Error( __METHOD__, 'Missing Permissions', [ 'status' => 400 ] );
        }
        $params = dt_sanitize_array( $request->get_params() );
        if ( ! isset( $params['p2p_type'], $params['post_type'] ) ) {
            return new WP_Error( __METHOD__, 'Missing type', [ 'status' => 400 ] );
        }
        $query = $this->get_query( $params['post_type'], $params['p2p_type'] );
        return $this->get_genmap( $query  );
    }

    public function scripts() {

        $js_file_name = 'combined/genmap.js';
        wp_enqueue_script( 'dt_metrics_project_script', plugin_dir_url(__DIR__) . $js_file_name, [
            'jquery',
            'lodash'
        ], filemtime( plugin_dir_path(__DIR__) . $js_file_name ), true );
        wp_localize_script(
            'dt_metrics_project_script', 'dtMetricsProject', [
                'root' => esc_url_raw( rest_url() ),
                'site_url' => esc_url_raw( site_url() ),
                'theme_uri' => get_template_directory_uri(),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'current_user_login' => wp_get_current_user()->user_login,
                'current_user_id' => get_current_user_id(),
                'map_key' => empty( DT_Mapbox_API::get_key() ) ? '' : DT_Mapbox_API::get_key(),
                'data' => [],
                'translations' => [
                    'title' => __( 'Generation Map', 'disciple_tools' ),
                    'highlight_active' => __( 'Highlight Active', 'disciple_tools' ),
                    'highlight_churches' => __( 'Highlight Churches', 'disciple_tools' ),
                    'members' => __( 'Members', 'disciple_tools' ),
                    'view_record' => __( 'View Record', 'disciple_tools' ),
                    'assigned_to' => __( 'Assigned To', 'disciple_tools' ),
                    'status' => __( 'Status', 'disciple_tools' ),
                    'total_members' => __( 'Total Members', 'disciple_tools' ),
                    'view_group' => __( 'View Group', 'disciple_tools' ),
                ],
            ]
        );

        wp_enqueue_script( 'orgchart_js', 'https://cdnjs.cloudflare.com/ajax/libs/orgchart/3.7.0/js/jquery.orgchart.min.js', [
            'jquery',
        ], '3.7.0', true );
        $css_file_name = 'common/jquery.orgchart.custom.css';
        wp_enqueue_style( 'orgchart_css', plugin_dir_url(__DIR__) . $css_file_name, [], filemtime( plugin_dir_path(__DIR__)  . $css_file_name ) );
    }
    public function get_query( $post_type, $p2p_type  ) {
        global $wpdb;
        $query = $wpdb->get_results( $wpdb->prepare ( "
                    SELECT
                      a.ID         as id,
                      0            as parent_id,
                      a.post_title as name
                    FROM $wpdb->posts as a
                    WHERE a.post_type = %s
                    AND a.ID NOT IN (
                      SELECT DISTINCT (p2p_from)
                      FROM $wpdb->p2p
                      WHERE p2p_type = %s
                      GROUP BY p2p_from
                    )
                      AND a.ID IN (
                      SELECT DISTINCT (p2p_to)
                      FROM $wpdb->p2p
                      WHERE p2p_type = %s
                      GROUP BY p2p_to
                    )
                    UNION
                    SELECT
                      p.p2p_from  as id,
                      p.p2p_to    as parent_id,
                      (SELECT sub.post_title FROM $wpdb->posts as sub WHERE sub.ID = p.p2p_from ) as name
                    FROM $wpdb->p2p as p
                    WHERE p.p2p_type = %s;
                ", $post_type, $p2p_type, $p2p_type, $p2p_type ), ARRAY_A );
        return $query;
    }

    public function get_genmap( $query ) {

        if ( is_wp_error( $query ) ){
            return $this->_circular_structure_error( $query );
        }
        if ( empty( $query ) ) {
            return $this->_no_results();
        }
        $menu_data = $this->prepare_menu_array( $query );
        return $this->build_array( 0, $menu_data, 0 );
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
    public function build_array( $parent_id, $menu_data, $gen ) {
        $children = [];
        if ( isset( $menu_data['parents'][$parent_id] ) )
        {
            $next_gen = $gen + 1;
            foreach ( $menu_data['parents'][$parent_id] as $item_id )
            {
                $children[] = $this->build_array( $item_id, $menu_data, $next_gen );
            }
        }
        $array = [
            'id' => $parent_id,
            'name' => $menu_data['items'][ $parent_id ]['name'] ?? 'SYSTEM' ,
            'content' => 'Gen ' . $gen ,
            'children' => $children,
        ];
        return $array;
    }
}
new DT_Metrics_Groups_Genmap();


