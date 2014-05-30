<?php
/**
 * Extra Events.
 *
 * @package   Extra_Events_Admin
 * @author    Vincent Saïsset <vs@extralagence.com>
 * @license   GPL-2.0+
 * @link      http://www.extralagence.com
 * @copyright 2014 Extra l'agence
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-plugin-name.php`
 *
 * @package Extra_Events_Admin
 * @author  Vincent Saïsset <vs@extralagence.com>
 */
class Extra_Events_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		/*
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		$plugin = Extra_Events::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		require_once( plugin_dir_path( __FILE__ ) . 'class-export-xls.php' );
		add_action('init', array( $this, 'init_actions' ),11);

		add_filter( 'extra_add_global_options_section', array( $this, 'add_global_options' ));



		// HOOK EVENTS MANAGER
		add_action( 'emp_form_add_custom_fields', array( $this, 'add_custom_fields'));
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		/*
		 * @TODO :
		 *
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @TODO:
	 *
	 * - Rename "Extra_Events" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {

		}
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @TODO:
	 *
	 * - Rename "Extra_Events" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), Extra_Events::VERSION );
		}
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		 *
		 *        Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
		 * - Change 'Page Title' to the title of your plugin admin page
		 * - Change 'Menu Text' to the text for menu item for the plugin settings page
		 * - Change 'manage_options' to the capability you see fit
		 *   For reference: http://codex.wordpress.org/Roles_and_Capabilities
		 */
//		$this->plugin_screen_hook_suffix = add_options_page(
//			__( 'Page Title', $this->plugin_slug ),
//			__( 'Menu Text', $this->plugin_slug ),
//			'manage_options',
//			$this->plugin_slug,
//			array( $this, 'display_plugin_admin_page' )
//		);
		$this->plugin_screen_hook_suffix = add_submenu_page(
			'edit.php?post_type=event',
			__( 'Exporter les inscriptions', $this->plugin_slug ),
			__( 'Exporter', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once( 'views/admin.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}

	public function init_actions() {
		//Export XLS
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_xls'){
			$exporter = new Extra_Events_Export_Xls();
			$exporter->export();
			exit;
		}
	}

	/**
	 * @param $sections mixed|array
	 */
	public function add_global_options ($sections) {
		$sections[] = array(
			'icon' => ' el-icon-calendar',
			'title' => __("Exportation des réservations", 'extra-admin'),
			'desc' => null,
			'fields' => array(
				array(
					'id' => 'extra_events_export_filter_section_1',
					'type' => 'section',
					'indent' => true,
					'title' =>  __('Premier filtre', $this->plugin_slug)
				),
				array(
					'id' => 'extra_events_export_filter_enable_1',
					'type' => 'checkbox',
					'title' => __('Activer le premier filtre', $this->plugin_slug),
				),
				array(
					'id' => 'extra_events_export_filter_name_1',
					'type' => 'text',
					'title' => __('Nom du premier filtre', $this->plugin_slug),
				),
				array(
					'id' => 'extra_events_export_filter_1',
					'type' => 'textarea',
					'title' => __('Premier filtre', $this->plugin_slug),
					'desc' => __("Identifiant des champs séparés par des virgules <br>(ex: first_name, last_name, booking_comment, dbem_address)", $this->plugin_slug)
				),


				array(
					'id' => 'extra_events_export_filter_section_2',
					'type' => 'section',
					'indent' => true,
					'title' =>  __('Deuxième filtre', $this->plugin_slug)
				),
				array(
					'id' => 'extra_events_export_filter_enable_2',
					'type' => 'checkbox',
					'title' => __('Activer le deuxième filtre', $this->plugin_slug),
				),
				array(
					'id' => 'extra_events_export_filter_name_2',
					'type' => 'text',
					'title' => __('Nom du deuxième filtre', $this->plugin_slug),
				),
				array(
					'id' => 'extra_events_export_filter_2',
					'type' => 'textarea',
					'title' => __('Deuxième filtre', $this->plugin_slug),
					'desc' => __("Identifiant des champs séparés par des virgules <br>(ex: first_name, last_name, booking_comment, dbem_address)", $this->plugin_slug)
				),


				array(
					'id' => 'extra_events_export_filter_section_3',
					'type' => 'section',
					'indent' => true,
					'title' =>  __('Troisième filtre', $this->plugin_slug)
				),
				array(
					'id' => 'extra_events_export_filter_enable_3',
					'type' => 'checkbox',
					'title' => __('Activer le troisième filtre', $this->plugin_slug),
				),
				array(
					'id' => 'extra_events_export_filter_name_3',
					'type' => 'text',
					'title' => __('Nom du troisième filtre', $this->plugin_slug),
				),
				array(
					'id' => 'extra_events_export_filter_3',
					'type' => 'textarea',
					'title' => __('troisième filtre', $this->plugin_slug),
					'desc' => __("Identifiant des champs séparés par des virgules <br>(ex: first_name, last_name, booking_comment, dbem_address)", $this->plugin_slug)
				)
			)
		);

		return $sections;
	}


	/**************************
	 *
	 * HOOKS EVENTS MANAGER
	 *
	 *************************/

	public function add_custom_fields($field_values) {
		/* @var $field_type FieldInterface */
		foreach (Extra_Events::$field_types_by_name as $field_type_name => $field_type) {
			$selected = ($field_values['type'] == $field_type_name) ? ' selected="selected"' : '';
			$override_type = ($field_type::get_admin_override_type() != null) ? ' data-override-type="'.$field_type::get_admin_override_type().'"' : '';

			echo '<option value="'.$field_type::get_name().'"'.$selected.$override_type.'>'.$field_type::get_admin_label().'</option>';
		}
	}
}
