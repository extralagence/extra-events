<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 21/05/2014
 * Time: 17:07
 */

namespace ExtraEvents\Fields;

abstract class AbstractField implements FieldInterface {
	/**
	 * Field initialization (enqueue script, etc.)
	 * @param $plugin_slug string for prefix naming
	 */
	public static function init($plugin_slug) {}

	public static function get_admin_override_type() {
		return null;
	}

	/**
	 * @param $field mixed|array
	 * @param $value string
	 * @param $EM_Form \EM_Form
	 *
	 * @return bool
	 */
	public static function is_empty($field, $value, &$EM_Form) {
		return false;
	}

	/**
	 * @param $field mixed|array
	 * @param $value string
	 * @param $EM_Form \EM_Form
	 *
	 * @return bool
	 */
	public static function validate($field, $value, &$EM_Form) {
		return true;
	}
}