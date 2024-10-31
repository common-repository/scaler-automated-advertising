<?php
/*
Plugin Name: Scaler Automated Advertising
Plugin URI: http://getscaler.com/scaler_wp
Description: Краткое описание плагина.
Version: 1.0
Author: Scaler
Author URI: http://getscaler.com
*/

define( 'SCALER_VERSION', '1.0.0' );
define( 'SCALER__MINIMUM_WP_VERSION', '4.0' );
define( 'SCALER__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( SCALER__PLUGIN_DIR . 'scaler-functions.php' );

register_activation_hook( __FILE__,  'plugin_activation' );
register_deactivation_hook( __FILE__, 'plugin_deactivation' );

add_action( 'admin_menu', 'sc_register_menu_page' );
add_action( 'wp_head', 'sc_init' );
add_action( 'woocommerce_thankyou', 'sc_autocomplete_order' , 10 );
add_action( 'admin_enqueue_scripts', 'sc_add_ui_script'  );
add_action( 'admin_enqueue_scripts', 'sc_add_stripe_script');
add_action( 'admin_enqueue_scripts', 'sc_add_app_style'  );
//add_action( 'admin_menu', 'sc_dashboard', 10, 1 );

