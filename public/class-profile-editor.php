<?php
/**
 * Extra Events.
 *
 * @package   Extra_Profile_Editor
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
 * functionality, then refer to `class-extra-profile-editor-admin.php`
 *
 * @package Extra_Profile_Editor
 * @author  Vincent Saïsset <vs@extralagence.com>
 */
class Extra_Profile_Editor {

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
    protected $plugin_slug = 'extra-profile-editor';

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    /**
     * The array of templates that this plugin tracks.
     *
     * @since    1.0.0
     *
     * @var      array
     */
    protected $templates;

    public static $statusMessage = "";
    public static $EM_FORM;

    /**
     * Initialize the plugin by setting localization and loading public scripts
     * and styles.
     *
     * @since     1.0.0
     */
    private function __construct() {

        // HOOK TO ADD PAGE TEMPLATES
        add_filter('page_attributes_dropdown_pages_args', array( $this, 'register_project_templates' ) );
        add_filter('wp_insert_post_data', array( $this, 'register_project_templates' ) );
        add_filter('template_include', array( $this, 'view_project_template') );
        $this->templates = array(
            'template-profile-editor.php'     => __( 'Gestion de mon profil', $this->plugin_slug )
        );
        $templates = wp_get_theme()->get_page_templates();
        $templates = array_merge( $templates, $this->templates );

        // Load public-facing style sheet and JavaScript.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        add_action('template_redirect', array($this, 'initialize_form'));



    }

    function initialize_form() {
        if(is_page_template('template-profile-editor.php')) {
            $current_user = wp_get_current_user();
            if ( $current_user->exists() ) {
                $custom_fields = get_option( 'em_user_fields' );
                self::$EM_FORM = new EM_Form( 'em_user_fields' );
                self::$EM_FORM->form_required_error = get_option('em_booking_form_error_required');
                self::checkForm( $current_user, $custom_fields );
            }
        }
    }

    public static function checkForm($current_user, $custom_fields) {

        self::$statusMessage = '';

        if(isset($_POST) && array_key_exists("_wpnonce", $_POST) && wp_verify_nonce($_POST['_wpnonce'], 'extra-profile-editor-nonce')) {

            $valid = true;

           if(isset($_POST['user_email'])) {
               if(!is_email($_POST['user_email'])) {
                    $valid = false;
                   self::$EM_FORM->add_error(__('Email non-valide', 'extra-events'));
               } else {
                    $current_user->__set('user_email', $_POST['user_email']);
               }
           }

           if(isset($_POST['first_name'])) {
                $current_user->__set('first_name', $_POST['first_name']);
           }

           if(isset($_POST['last_name'])) {
                $current_user->__set('last_name', $_POST['last_name']);
           }


            /* Update user password. */
           if ( !empty($_POST['pass1'] ) && !empty( $_POST['pass2'] ) ) {
                if ( $_POST['pass1'] == $_POST['pass2'] ) {
                    $current_user->__set('user_pass', $_POST['pass1']);
                }else {
                    $valid = false;
                    self::$EM_FORM->add_error(__('Les mots de passe doivent être identiques', 'extra-events'));
                }
            }

            if(isset($custom_fields) && !empty($custom_fields)) {
                foreach($custom_fields as $custom_field) {
                    //if(isset($_POST[$custom_field['fieldid']]) && !empty($_POST[$custom_field['fieldid']])) {
                        $valid_field = self::$EM_FORM->validate_field($custom_field['fieldid'], $_POST[$custom_field['fieldid']]);
                        if($valid_field) {
                            update_user_meta( $current_user->data->ID, $custom_field['fieldid'], $_POST[$custom_field['fieldid']] );
                        } else {
                            $valid = false;
                        }
                    //}
                }
            }

            if(!$valid) {
                self::$statusMessage .= '<div class="message errors">';
                foreach(self::$EM_FORM->get_errors() as $error) {
                    self::$statusMessage .= '<p>' . $error . '</p>';
                }
                self::$statusMessage .= '</div>';
            } else {

                $user_id = wp_update_user( $current_user );

                if ( is_wp_error( $user_id ) ) {
                    $valid = false;
                    self::$statusMessage .=  '<p class="message errors">' . __('Erreur lors de l\'enregistrement', 'extra-events') . '</p>';
                } else {
                    self::$statusMessage .=  '<p class="message success">' . __('Mise à jour effectuée avec succès', 'extra-events') . '</p>';
                }

            }

        }

    }


    /**************************
     *
     * ADD TEMPLATES
     *
     *************************/
     /**
     * Adds our template to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doens't really exist.
     *
     * @param   array    $atts    The attributes for the page attributes dropdown
     * @return  array    $atts    The attributes for the page attributes dropdown
     * @verison 1.0.0
     * @since   1.0.0
     */
    public function register_project_templates( $atts ) {

        // Create the key used for the themes cache
        $cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

        // Retrieve the cache list. If it doesn't exist, or it's empty prepare an array
        $templates = wp_cache_get( $cache_key, 'themes' );

        if ( empty( $templates ) ) {
            $templates = array();
        } // end if

        // Since we've updated the cache, we need to delete the old cache
        wp_cache_delete( $cache_key , 'themes');

        // Now add our template to the list of templates by merging our templates
        // with the existing templates array from the cache.
        $templates = array_merge( $templates, $this->templates );

        // Add the modified cache to allow WordPress to pick it up for listing
        // available templates
        wp_cache_add( $cache_key, $templates, 'themes', 1800 );

        return $atts;

    } // end register_project_templates

    /**
     * Checks if the template is assigned to the page
     *
     * @version 1.0.0
     * @since   1.0.0
     */
    public function view_project_template( $template ) {

        global $post;

        if(!isset($post)) {
            return $template;
        }

        if ( ! isset( $this->templates[ get_post_meta( $post->ID, '_wp_page_template', true ) ] ) ) {
            return $template;
        } // end if

        $file = plugin_dir_path( __FILE__ ) . '../templates/' . get_post_meta( $post->ID, '_wp_page_template', true );

        // Just to be safe, we check if the file exist first
        if( file_exists( $file ) ) {
            return $file;
        } // end if

        return $template;

    } // end view_project_template





    /**
     * Enqueue styles if in the correct template page
     *
     * @version 1.0.0
     * @since   1.0.0
     */
    public function enqueue_styles() {
        if(is_page_template('template-profile-editor.php')) {
            wp_enqueue_style( $this->plugin_slug . '-profile-editor', plugins_url( 'assets/css/extra-profile-editor.less', __FILE__ ), array(), self::VERSION );
        }
    }

    /**
     * Enqueue scripts if in the correct template page
     *
     * @version 1.0.0
     * @since   1.0.0
     */
    public function enqueue_scripts() {
        if(is_page_template('template-profile-editor.php')) {
            wp_enqueue_script( 'password-strength-meter' );
            wp_enqueue_script( $this->plugin_slug . '-profile-editor', plugins_url( 'assets/js/extra-profile-editor.js', __FILE__ ), array( 'jquery' ), self::VERSION );
        }
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

}