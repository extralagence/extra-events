<?php

namespace ExtraEvents\Fields;

class Date extends AbstractField {

	public static function init($plugin_slug) {
//		wp_enqueue_style($plugin_slug . '-date', plugins_url( 'css/date.less', __FILE__ ), array(), \Extra_Events::VERSION );
		wp_enqueue_script( $plugin_slug . '-date', plugins_url( 'js/date.js', __FILE__ ), array( 'jquery' ), \Extra_Events::VERSION );
	}

	public static function get_name() {
		return 'extra_date';
	}

	public static function get_admin_label() {
		return __("Date (extra)", "extra-admin");
	}

	public static function get_front($field) {
		$id = $field['fieldid'];
		$label = $field['label'];
		$required = ($field['required'] == 1) ? ' <span class="em-form-required">*</span>' : '';

		$html = '';
		$html .= '<p class="input-extra-date input-group input-text input-field-'.$id.'">';
		$html .= 	'<label for="'.$id.'">'.$label.$required.'</label>';
		$html .= 	'<input type="text" name="'.$id.'" id="'.$id.'" class="input">';
		$html .= '</p>';

		return $html;
	}

	public static function get_admin($field, $value) {
		$id = $field['fieldid'];

		$html  = '<p class="input-extra-date input-group input-text input-field-'.$id.'">';
		$html .= 	'<input type="text" name="'.$id.'" id="'.$id.'" class="input" value="'.$value.'">';
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
		$success = true;
		if (!empty($value)) {
			$date_array = explode('/', $value);

			if (count($date_array) != 3 || !checkdate($date_array[1], $date_array[0], $date_array[2])) {
				$success = false;
				$EM_Form->add_error('Veuillez utiliser un format de date valide pour ce champ : '.$field['label'].' (exemple : 02/11/2013)');
			} else {
				$min = apply_filters('extra_events_field_date_min', null, $field);
				$max = apply_filters('extra_events_field_date_max', null, $field);

				$time_min = null;
				if ($min != null) {
					$date_time_min = \DateTime::createFromFormat( 'd/m/Y', $min);
					$time_min = $date_time_min->getTimestamp();
				}
				$time_max = null;
				if ($max != null) {
					$date_time_max = \DateTime::createFromFormat( 'd/m/Y', $max);
					$time_max = $date_time_max->getTimestamp();
				}
				$date_time = \DateTime::createFromFormat( 'd/m/Y', $value);
				$time = $date_time->getTimestamp();

				if ($time_min !== null && $time < $time_min) {
					$success = false;
					$EM_Form->add_error('Veuillez renseigner une date supérieure au '.$min.' pour ce champ : '.$field['label']);
				}
				if ($time_max !== null && $time > $time_max) {
					$success = false;
					$EM_Form->add_error('Veuillez renseigner une date inférieure au '.$max.' pour ce champ : '.$field['label']);
				}
			}

		}

		return $success;
	}
}