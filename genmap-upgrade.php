<?php
/**
 * Plugin Name: Genmap Upgrade
 * Plugin URI: https://github.com/DiscipleTools/disciple-tools-one-page-template
 * Description: Genmap Samples for Disciple.Tools
 * Version:  1.1
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/DiscipleTools/disciple-tools-one-page-template
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.6
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

add_action( 'after_setup_theme', function (){
    $required_dt_theme_version = '1.0';
    $wp_theme = wp_get_theme();
    $version = $wp_theme->version;
    /*
     * Check if the Disciple.Tools theme is loaded and is the latest required version
     */
    $is_theme_dt = strpos( $wp_theme->get_template(), "disciple-tools-theme" ) !== false || $wp_theme->name === "Disciple Tools";
    if ( $is_theme_dt && version_compare( $version, $required_dt_theme_version, "<" ) ) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error notice-admin_page is-dismissible" data-notice="admin_page">Disciple
                Tools Theme not active or not latest version for Admin Page plugin.
            </div><?php
        });
        return false;
    }
    if ( !$is_theme_dt ){
        return false;
    }
    /**
     * Load useful function from the theme
     */
    if ( !defined( 'DT_FUNCTIONS_READY' ) ){
        require_once get_template_directory() . '/dt-core/global-functions.php';
    }
    return Genmap_Upgrade::instance();
} );


/**
 * Class Admin_Page
 */
class Genmap_Upgrade {


    /**  Singleton */
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {

        if ( dt_has_permissions( [ 'dt_all_access_contacts', 'view_project_metrics' ] ) ){
            require_once( 'dt-metrics/combined/genmap.php' );
        }

    }

}
