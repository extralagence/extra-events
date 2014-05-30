<?php

namespace ExtraEvents\Fields;

class Time extends AbstractField {
	public static function init($plugin_slug) {
		wp_enqueue_script( $plugin_slug . '-time', plugins_url( 'js/time.js', __FILE__ ), array( 'jquery' ), \Extra_Events::VERSION );
	}

	public static function get_name() {
		return 'extra_time';
	}

	public static function get_admin_label() {
		return __("Heure (extra)", "extra-admin");
	}

	public static function get_front($field) {
		$id = $field['fieldid'];
		$label = $field['label'];
		$required = ($field['required'] == 1) ? ' <span class="em-form-required">*</span>' : '';

		$html = '';
		$html .= '<p class="input-extra-time input-slider input-group input-text input-field-'.$id.'">';
		$html .= 	'<label for="'.$id.'">'.$label.$required.'</label>';
		$html .= 	'<input type="text" name="'.$id.'" id="'.$id.'" class="input">';
		$html .= '</p>';

		return $html;
	}

	public static function get_admin_override_type() {
		return 'text';
	}

	public static function is_empty($field, $value, &$EM_Form) {
		$is_empty = false;

		if (empty($value)) {
			$EM_Form->add_error(__("Merci de remplir ce champ : ", "extra-admin").$field['label']);
			$is_empty = true;
		}
		return $is_empty;
	}


	public static function validate($field, $value, &$EM_Form) {
		$matches = array();
		preg_match('/(2[0-3]|1[0-9]|[0-9])h[0-5][0-9]/', $value, $matches);

		$success = false;
		if(isset($matches[0])) {
			$success = ($value == $matches[0]);
		}
		if (!$success) {
			$EM_Form->add_error('Veuillez utiliser un format d\'heure valide (exemple : 15h30 ou 9h15)');
		} else {
			$min = apply_filters('extra_events_field_time_min', null, $field);
			$max = apply_filters('extra_events_field_time_max', null, $field);

			$value_array = explode('h', $value);
			$hours = $value_array[0];
			$minutes = $value_array[1];

			if ($min !== null) {
				$min_array = explode('h', $min);
				$min_hours = intval($min_array[0]);
				$min_minutes = 0;
				if (count($min_array) > 1) {
					$min_minutes = intval($min_array[1]);
				} else {
					$min = $min_hours.'h';
				}
				if ($hours < $min_hours || ($hours == $min_hours && $minutes < $min_minutes)) {
					$success = false;
					$EM_Form->add_error('Veuillez renseigner une heure supérieure à '.$min.' pour ce champ : '.$field['label']);
				}
			}
			if ($max !== null) {
				$max_array = explode('h', $max);
				$max_hours = intval($max_array[0]);
				$max_minutes = 0;
				if (count($max_array) > 1) {
					$max_minutes = intval($max_array[1]);
				} else {
					$max = $max_hours.'h';
				}
				if ($hours > $max_hours || ($hours == $max_hours && $minutes > $max_minutes)) {
					$success = false;
					$EM_Form->add_error('Veuillez renseigner une heure inférieure à '.$max.' pour ce champ : '.$field['label']);
				}
			}
		}

		return $success;
	}
}