<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   Extra_Events
 * @author    Vincent Saïsset <vs@extralagence.com>
 * @license   GPL-2.0+
 * @link      http://www.extralagence.com
 * @copyright 2014 Extra l'agence
 *
 * @wordpress-plugin
 * Plugin Name:       Extra Events
 * Plugin URI:        https://github.com/extralagence/extra-events
 * Description:       Extra plugin to override events-manager
 * Version:           1.0.0
 * Author:            Vincent Saïsset
 * Author URI:        http://www.extralagence.com
 * Text Domain:       extra-events
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/extralagence/extra-events
 * WordPress-Plugin-Boilerplate: v2.6.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'public/class-extra-events.php' );
require_once( plugin_dir_path( __FILE__ ) . 'public/class-profile-editor.php' );
require_once( plugin_dir_path( __FILE__ ) . 'FieldInterface.php' );
require_once( plugin_dir_path( __FILE__ ) . 'AbstractField.php' );
//Require once each fields
Extra_Events::$field_types_by_name = array();
foreach (scandir(plugin_dir_path( __FILE__ ).'/fields') as $field_folder) {
	$path = dirname(__FILE__).'/fields/'.$field_folder.'/'.$field_folder.'.php';
	if (is_file($path)) {
		require_once $path;
		$field_name = 'ExtraEvents\\Fields\\'.$field_folder;
		/* @var $field_type \ExtraEvents\Fields\FieldInterface */
		$field_type =  new $field_name();
		Extra_Events::$field_types_by_name[$field_type::get_name()] = $field_type;
	}
}

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook( __FILE__, array( 'Extra_Events', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Extra_Events', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Extra_Events', 'get_instance' ) );
add_action( 'plugins_loaded', array( 'Extra_Profile_Editor', 'get_instance' ) );

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

/*
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to to give the lightest footprint possible.
 */
if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-extra-events-admin.php' );
	add_action( 'plugins_loaded', array( 'Extra_Events_Admin', 'get_instance' ) );

}
