<?php

namespace ExtraEvents\Fields;

class StartConditional extends AbstractField {
	public static function init($plugin_slug) {
		wp_enqueue_script( $plugin_slug . '-start-conditional', plugins_url( 'js/start-conditional.js', __FILE__ ), array( 'jquery' ), \Extra_Events::VERSION );
	}


	public static function get_name() {
		return 'extra_start_conditional';
	}

	public static function get_admin_label() {
		return __("Début de condition", "extra-admin");
	}

	public static function get_front($field) {
		$id = $field['fieldid'];
		$label = $field['label'];
		$required = ($field['required'] == 1) ? ' <span class="em-form-required">*</span>' : '';

		$html  = '';
		$html .= 	'<p class="input-group input-checkbox input-field-'.$id.'">';
		$html .=		'<input type="checkbox" name="'.$id.'" id="'.$id.'" value="1" >';
		$html .=		'<label for="'.$id.'">'.$label.$required.'</label>';
		$html .= 	'</p>';
		$html .= 	'<div class="conditional">';

		return $html;
	}

	public static function get_admin($field, $value) {
		$id = $field['fieldid'];
		$checked = ($value == 1) ? ' checked="checked"' : '';
		return '<input type="checkbox" name="'.$id.'" id="'.$id.'" value="1"'.$checked.'>';
	}

	public static function get_formatted_value($field, $value) {
		if ($value == 1) {
			return __("Oui", "extra-events");
		} else  {
			return __("Non", "extra-events");
		}
	}

	public static function is_empty($field, $value, &$EM_Form) {
		$is_empty = false;
		if (empty($value)) {
			$EM_Form->add_error(__("Merci de confirmer : ", "extra-admin").$field['label']);
			$is_empty = true;
		}

		return $is_empty;
	}
}