<?php

namespace ExtraEvents\Fields;

interface FieldInterface {

	/**
	 * Field initialization (enqueue script, etc.)
	 * @param $plugin_slug string for prefix naming
	 */
	static function init($plugin_slug);

	/**
	 * @return string field unique key
	 */
	static function get_name();

	/**
	 * @return string Text in admin combo box
	 */
	static function get_admin_label();

	/**
	 * @return string a default Events Manager Pro's type
	 */
	static function get_admin_override_type();

	/**
	 * @param $field mixed|array
	 *
	 * @return string
	 */
	static function get_front($field);

	/**
	 * @param $field mixed|array
	 * @param $value string
	 * @param $EM_Form \EM_Form
	 *
	 * @return bool
	 */
	static function is_empty($field, $value, &$EM_Form);

	/**
	 * @param $field mixed|array
	 * @param $value string
	 * @param $EM_Form \EM_Form
	 *
	 * @return bool
	 */
	static function validate($field, $value, &$EM_Form);
}