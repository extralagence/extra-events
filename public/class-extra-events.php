<?php
/**
 * Extra Events.
 *
 * @package   Extra_Events
 * @author    Vincent Saïsset <vs@extralagence.com>
 * @license   GPL-2.0+
 * @link      http://www.extralagence.com
 * @copyright 2014 Extra l'agence
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-extra-events-admin.php`
 *
 * @package Extra_Events
 * @author  Vincent Saïsset <vs@extralagence.com>
 */
class Extra_Events {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'extra-events';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	public static $field_types_by_name = array();

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// HOOK EVENTS MANAGER
		add_filter( 'emp_forms_output_field', array( $this, 'output_field' ), 10, 3);
		add_filter('emp_form_validate_field', array( $this, 'validate_field' ), 20, 4);

		add_action('init', array( $this, 'init_paybox_gateway'));
	}

	public function init_paybox_gateway() {
		if (apply_filters('extra_events_paybox_enabled', false) === true) {
			if (class_exists('EM_Gateways')) {
				require_once( dirname(__FILE__) . '/class-extra-gateway-paybox.php' );
				EM_Gateways::register_gateway('paybox', 'Extra_Gateway_Paybox');
			}
		}
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( (! is_plugin_active( 'events-manager/events-manager.php' ) || ! is_plugin_active( 'events-manager-pro/events-manager-pro.php' )) and current_user_can( 'activate_plugins' ) ) {
			// Stop activation redirect and show error
			wp_die(__("Désolé, mais ce plugin nécessite Events Manager et Event Manager Pro pour être activé", "extra-events").' <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; '.__("Retourner aux plugins", "extra-events").'</a>');
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}


	/**************************
	 *
	 * HOOKS EVENTS MANAGER
	 *
	 *************************/

	/**
	 * Callback for emp_forms_output_field
	 *
	 * @param $html
	 * @param $fields
	 * @param $field
	 *
	 * @return string
	 */
	public function output_field($html, $fields, $field) {

		$type = $field['type'];
		if (array_key_exists($type, Extra_Events::$field_types_by_name)) {
			/* @var $field_type \ExtraEvents\Fields\FieldInterface */
			$field_type = Extra_Events::$field_types_by_name[$type];
			$field_type::init($this->plugin_slug);
			$html = $field_type::get_front($field);
		}

		if ($type == 'checkbox') {
			$html = $this->output_checkbox($field);
		}

		return $html;
	}

	private function output_checkbox($field) {
		ob_start();
		$required = ( !empty($field['required']) ) ? ' '.apply_filters('emp_forms_output_field_required','<span class="em-form-required">*</span>'):'';

		$tip_type = $field['type'];

		$default = '';
		if(!empty($_REQUEST[$field['fieldid']])) {
			$default = is_array($_REQUEST[$field['fieldid']]) ? $_REQUEST[$field['fieldid']]:esc_attr($_REQUEST[$field['fieldid']]);
		}

		$field_name = !empty($field['name']) ? $field['name']:$field['fieldid'];
		?>
		<p class="input-group input-<?php echo $field['type']; ?> input-field-<?php echo $field['fieldid'] ?>">
			<input type="checkbox" name="<?php echo $field_name ?>" id="<?php echo $field['fieldid'] ?>" value="1" <?php if( ($default && $default != 'n/a') || $field['options_checkbox_checked']) echo 'checked="checked"'; ?> />

			<label for='<?php echo $field['fieldid'] ?>'>
				<?php if( !empty($field['options_'.$tip_type.'_tip']) ): ?>
					<span class="form-tip" title="<?php echo esc_attr($field['options_'.$tip_type.'_tip']); ?>">
										<?php echo $field['label'] ?> <?php echo $required  ?>
									</span>
				<?php else: ?>
					<?php echo $field['label'] ?> <?php echo $required  ?>
				<?php endif; ?>
			</label>
		</p>
		<?php

		return ob_get_clean();
	}


	/**
	 * Callback for emp_form_validate_field
	 *
	 * @param $result
	 * @param $field
	 * @param $value
	 * @param $EM_Form EM_Form
	 *
	 * @return int
	 */
	public function validate_field($result, $field, $value, $EM_Form) {
		/* @var $EM_Booking EM_Booking */
		/* @var $extra_event_metabox ExtraMetaBox */
		global $EM_Booking;
		$value_by_field_id = $EM_Booking->booking_meta['booking'];

		$type = $field['type'];
		$required = $field['required'] == 1;

		//Requirement management for extra fields
		if ($required) {
			if (array_key_exists($type, Extra_Events::$field_types_by_name)) {
				/* @var $field_type \ExtraEvents\Fields\FieldInterface */
				$field_type = Extra_Events::$field_types_by_name[$type];
				if ($field_type::is_empty($field, $value, $EM_Form)) {
					$result = false;
				}
			}
		}

		$ignored = false;

		// Requirement management when in conditional block
		if($type != \ExtraEvents\Fields\StartConditional::get_name() && $type != \ExtraEvents\Fields\StopConditional::get_name()) {
			$parent_conditional_fields = $this->get_parent_conditional_fields($field, $EM_Form->form_fields);
			if(count($parent_conditional_fields) > 0) {
				$all_parents_selected = true;
				$i = 0;
				while($i < count($parent_conditional_fields) && $all_parents_selected) {
					$parent_conditional_field = $parent_conditional_fields[$i];
					$currentValue = $value_by_field_id[$parent_conditional_field['fieldid']];
					if ($currentValue == false) {
						$all_parents_selected = false;
					}
					$i++;
				}

				if (!$all_parents_selected) {
					$ignored = true;
					if (!$result && $required) {
						array_pop($EM_Form->errors);
						$result = 1;
					}
				}
			}
		}

		if (!$ignored) {
			if (array_key_exists($type, Extra_Events::$field_types_by_name)) {
				/* @var $field_type \ExtraEvents\Fields\FieldInterface */
				$field_type = Extra_Events::$field_types_by_name[$type];
				if (!$field_type::validate($field, $value, $EM_Form)) {
					$result = false;
				}
			}
		}

		return $result;
	}

	/**
	 * @param $field mixed|array
	 * @param $fields mixed|array
	 *
	 * @return array
	 */
	protected function get_parent_conditional_fields($field, $fields) {
		$field_id = $field['fieldid'];
		$parent_conditional_fields = array();

		$fields_by_position = array();

		$current_position = null;
		$i = 0;
		foreach ($fields as $current_id => $current_field) {
			if($current_id == $field_id) {
				$current_position = $i;
			}
			$fields_by_position[] = $current_field;
			$i++;
		}

		$i = $current_position;
		while($i > 0) {
			$current_field = $fields_by_position[$i];
			if ($current_field['type'] == \ExtraEvents\Fields\StartConditional::get_name()) {
				if ($this->is_in_conditional($current_position, $i, $fields_by_position)) {
					$parent_conditional_fields[] = $current_field;
				}
			}
			$i--;
		}

		return $parent_conditional_fields;
	}

	/**
	 * @param $element_to_test_position
	 * @param $start_position
	 * @param $fields_by_position
	 *
	 * @return bool
	 */
	protected function is_in_conditional($element_to_test_position, $start_position, $fields_by_position) {
		$nb_open = 1;

		$is_in = false;

		$i = $start_position + 1;
		while ($i < count($fields_by_position) && $nb_open > 0) {
			$current_field = $fields_by_position[$i];
			$current_type = $current_field['type'];

			if ($i == $element_to_test_position) {
				$is_in = true;
			}

			if ($current_type == \ExtraEvents\Fields\StartConditional::get_name()) {
				$nb_open++;
			} else if ($current_type == \ExtraEvents\Fields\StopConditional::get_name()) {
				$nb_open--;
			}
			$i++;
		}

		return $is_in;
	}
}