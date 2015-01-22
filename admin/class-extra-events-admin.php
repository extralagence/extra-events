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


	protected $events;
	protected $all_booking_ids_by_bill_events = array();

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
		require_once( plugin_dir_path( __FILE__ ) . 'class-export-bill.php' );
		add_action('init', array( $this, 'init_actions' ),11);

		add_filter( 'extra_add_global_options_section', array( $this, 'add_global_options' ));



		// HOOK EVENTS MANAGER
		add_action( 'emp_form_add_custom_fields', array( $this, 'add_custom_fields'));
		add_filter( 'emp_forms_output_field_input', array( $this, 'output_field_input' ), 10, 4);
		add_filter( 'extra_emp_forms_get_formatted_value', array( $this, 'output_field_formatted_value' ), 10, 2);


		// BILL GENERATION
		add_action( 'wp_ajax_generate_bills', array( $this, 'generate_bills') );
		add_action( 'wp_ajax_create_zip_bill', array( $this, 'create_zip_bill') );

		add_filter('em_bookings_table_booking_actions_5', array( $this, 'actions_for_bill'), 10, 2);
		//add_filter('em_bookings_table_booking_actions_4', 'actions_for_bill', 10, 2);
		//add_filter('em_bookings_table_booking_actions_3', 'actions_for_bill', 10, 2);
		//add_filter('em_bookings_table_booking_actions_2', 'actions_for_bill', 10, 2);
		add_filter('em_bookings_table_booking_actions_1',  array( $this, 'actions_for_bill'), 10, 2);
		add_filter('em_bookings_table_booking_actions_0',  array( $this, 'actions_for_bill'), 10, 2);

		add_filter('em_action_bookings_bill_generate',  array( $this, 'em_action_bill_generate'), 10, 2);

		add_filter('em_booking_save', array( $this, 'update_bill_on_booking_save'), 10, 2);
		add_action('em_bookings_single_metabox_footer', array( $this, 'show_booking_admin_button'), 10, 1);
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
//			wp_enqueue_style( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' );
			wp_enqueue_style( $this->plugin_slug . '-admin-style', plugins_url( 'assets/css/admin.css', __FILE__ ));

			$this->events = EM_Events::get();

			$this->all_booking_ids_by_bill_events = array();
			/* @var $EM_Event Em_Event */
			foreach ($this->events as $EM_Event) {
				$bookings = $EM_Event->get_bookings();
				$booking_ids_for_current_event = array();

				/* @var $EM_Booking EM_Booking */
				foreach ($bookings->bookings as $EM_Booking) {
					switch ($EM_Booking->booking_status) {
						case 0 :
						case 1 :
						case 5 :
							$booking_ids_for_current_event[] = $EM_Booking->booking_id;
							break;
						default:
							break;
					}
				}

				if (!empty($booking_ids_for_current_event)) {
					$this->all_booking_ids_by_bill_events[] = array(
						'id' => $EM_Event->event_id,
						'name' => $EM_Event->event_name,
						'bookingIds' => $booking_ids_for_current_event
					);
				}
			}

			$exportBillOptions = array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'allBookingIdsByEventIds' => $this->all_booking_ids_by_bill_events
			);

			wp_localize_script($this->plugin_slug . '-admin-script', 'exportBillOptions', $exportBillOptions);
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

	/**
	 * Callback for emp_forms_output_field
	 *
	 * @param $html
	 * @param $fields
	 * @param $field
	 *
	 * @return string
	 */
	public function output_field_input($html, $empForm, $field, $field_value) {

		$type = $field['type'];
		if (array_key_exists($type, Extra_Events::$field_types_by_name)) {
			/* @var $field_type \ExtraEvents\Fields\FieldInterface */
			$field_type = Extra_Events::$field_types_by_name[$type];
			$field_type::init($this->plugin_slug);
			$html = $field_type::get_admin($field, $field_value);
		}


//		$type = $field['type'];
//		if (array_key_exists($type, Extra_Events::$field_types_by_name)) {
//			/* @var $field_type \ExtraEvents\Fields\FieldInterface */
//			$field_type = Extra_Events::$field_types_by_name[$type];
//			$field_type::init($this->plugin_slug);
//			$html = $field_type::get_front($field);
//		}
//
//		if ($type == 'checkbox') {
//			$html = $this->output_checkbox($field);
//		}

		return $html;
	}

	public function output_field_formatted_value($field_value, $field) {
		$field_formatted_value = $field_value;

		$type = $field['type'];
		if (array_key_exists($type, Extra_Events::$field_types_by_name)) {
			/* @var $field_type \ExtraEvents\Fields\FieldInterface */
			$field_type = Extra_Events::$field_types_by_name[$type];
			$field_type::init($this->plugin_slug);

			$field_formatted_value = $field_type::get_formatted_value($field, $field_value);
		}

		return $field_formatted_value;
	}

	/***************************
	 *
	 *
	 * BILLS GENERATION
	 *
	 *
	 **************************/
	public function generate_bills() {
		$responses = array();
		$exporter = new Extra_Events_Export_Bill();

		$force_refresh = intval($_POST['force_refresh']) == 1;
		$raw_booking_ids = $_POST['booking_ids'];
		foreach ( $raw_booking_ids as $current_raw_id ) {
			$responses[] = $exporter->check_bill(intval($current_raw_id), $force_refresh);
		}

		echo wp_json_encode($responses);

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	public function create_zip_bill() {
		$exporter = new Extra_Events_Export_Bill();

		$raw_ids = $_POST['booking_ids'];
		$booking_ids = array();
		foreach ( $raw_ids as $current_raw_id ) {
			$booking_ids[] = intval($current_raw_id);
		}

		$event_id = intval($_POST['event_id']);
		$current_zip = intval($_POST['current_zip']);
		$total_zip = intval($_POST['total_zip']);
		$response = $exporter->create_zip($event_id, $booking_ids, $current_zip, $total_zip);

		echo wp_json_encode($response);
		wp_die(); // this is required to terminate immediately and return a proper response
	}

	/**
	 * @param $actions
	 * @param $EM_Booking EM_Booking
	 *
	 * @return mixed
	 */
	public function actions_for_bill($actions, $EM_Booking) {
		if (defined('EXTRA_EVENTS_BILL') && EXTRA_EVENTS_BILL == true) {
			// Only for waiting, waiting paiement and approve booking !
			if ($EM_Booking->booking_status == 5 || $EM_Booking->booking_status == 1 || $EM_Booking->booking_status == 0) {
				$bill_location = Extra_Events_Export_Bill::get_bill_location($EM_Booking->event_id, $EM_Booking->booking_id);
				if (file_exists($bill_location['pdfpath'])) {
					$actions['bill_see'] = '<a href="'.$bill_location['pdfurl'].'" target="_blank">Voir la facture</a>';
				} else {
					//$actions['bill_generate'] = '<a href="#bill_generate">Générer la facture</a>';
					// Hack using class: em-bookings-reject
					$actions['bill_generate'] = '<a class="em-bookings-bill-generate em-bookings-reject" href="'.em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'bookings_bill_generate', 'booking_id'=>$EM_Booking->booking_id)).'">'.esc_html__emp('Générer la facture', 'extra').'</a>';
				}
			}
		}

		return $actions;
	}

	/**
	 * @param $return
	 * @param $EM_Booking EM_Booking
	 *
	 * @return array
	 */
	function em_action_bill_generate($return, $EM_Booking) {
		$bill = apply_filters('extra-events-generate-bill', null, $EM_Booking);
		$success = $bill != null;
		$message = ($success) ? '<a href="'.$bill['pdfurl'].'" target="_blank">'.__('Voir la facture générée', 'extra').'</a>' : __("Oups une erreur est survenue...", 'extra');

		if (defined( 'DOING_AJAX')) {
			echo $message;
			die;
		}

		return array('result'=> $success, 'message'=> $message);
	}

	/**
	 * @param $saved boolean
	 * @param $EM_Booking EM_Booking
	 *
	 * @return boolean
	 */
	public function update_bill_on_booking_save ($saved, $EM_Booking) {
		if (is_admin()) {
			if ($saved) {
				switch ($EM_Booking->booking_status) {
					case 0 :
					case 1 :
					case 5 :
						apply_filters('extra-events-generate-bill', null, $EM_Booking);
						break;
					default:
						break;
				}
			}
		}

		return $saved;
	}

	/**
	 * @param $EM_Booking EM_Booking
	 */
	public function show_booking_admin_button ($EM_Booking) {
		if (defined('EXTRA_EVENTS_BILL') && EXTRA_EVENTS_BILL == true) {
			$bill_location = Extra_Events_Export_Bill::get_bill_location($EM_Booking->event_id, $EM_Booking->booking_id);
			$has_bill = file_exists($bill_location['pdfpath']);
			?>

			<style>
				.extra-events-notification {
					line-height: 40px;
					margin-bottom: 10px;
				}

				.extra-events-loader {
					position: relative;
					top: 5px;
					margin-left: 10px;
					margin-right: 10px;
					visibility: hidden;
				}
				.extra-events-loader.loading {
					visibility: visible;
				}
			</style>

			<div class="extra-events-notification extra-events-notification-success" style="display: none;"><?php _e("La facture a été générée", 'extra-admin'); ?></div>
			<div class="extra-events-notification extra-events-notification-error" style="display: none;"><?php _e("Impossible de générer la facture", 'extra-admin'); ?></div>

			<div id="em-gateway-payment" class="stuffbox">
				<h3>Facture associée</h3>

				<div class="inside">
					<div class="has_bill"<?php echo ($has_bill) ? '' : ' style="display: none;"'; ?>>
						<p><?php _e("Il y a une facture associée", 'extra-admin'); ?></p>
						<a class="extra-events-button button button-primary extra-events-generate-bill" href="#" target="_blank">Regénérer la facture</a>
						<img class="extra-events-loader" src="<?php echo plugin_dir_url( __FILE__ ) . '../admin/assets/img/ajax-loader.gif' ?>" />
						<a class="extra-events-button button button-primary" href="<?php echo $bill_location['pdfurl']; ?>" target="_blank">Voir la facture</a>
					</div>
					<div class="not_bill"<?php echo ($has_bill) ? ' style="display: none;"' : ''; ?>>
						<p><?php _e("Il n'y a pas de facture associée", 'extra-admin'); ?></p>
						<a class="extra-events-button button button-primary extra-events-generate-bill" href="#" target="_blank">Générer la facture</a>
						<img class="extra-events-loader" src="<?php echo plugin_dir_url( __FILE__ ) . '../admin/assets/img/ajax-loader.gif' ?>" />
					</div>
				</div>
				<script>
					// SORRY FOR INLINE JAVASCRIPT :'(
					jQuery(function ($) {
						var ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ) ?>',
							bookingId = <?php echo $EM_Booking->booking_id; ?>;

						$('.extra-events-generate-bill').on('click', function (event) {
							event.preventDefault();

							$('.extra-events-notification').hide();
							$('.extra-events-button').addClass('disabled');
							$('.extra-events-loader').addClass('loading');

							var data = {
								'action': 'generate_bills',
								'booking_ids': [bookingId],
								'force_refresh' : 1
							};

							$.post(ajaxUrl, data, function(responseString) {
								var responses = null;
								try {
									responses = $.parseJSON(responseString);
								} catch (e) {
									console.log(responseString);
									$('.extra-events-notification-error').addClass('error').show();
								}
								if (responses.length == 1) {
									var response = responses[0];
									if (response.success) {
										$('.has_bill').show();
										$('.not_bill').hide();
										$('.extra-events-notification-success').addClass('updated').show();
									} else {
										$('.extra-events-notification-error').addClass('error').show();
									}
								} else {
									$('.extra-events-notification-error').addClass('error').show();
								}
								$('.extra-events-button').removeClass('disabled');
								$('.extra-events-loader').removeClass('loading');
							});
						});
					});
				</script>
			</div>
			<?php
		}
	}
}
