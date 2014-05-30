<?php

namespace ExtraEvents\Fields;

class StartFieldset  extends AbstractField {
	public static function get_name() {
		return 'extra_start_fieldset';
	}

	public static function get_admin_label() {
		return __("Début de rubrique", "extra-admin");
	}

	public static function get_admin_override_type() {
		return 'html';
	}

	public static function get_front($field) {
		$html = 	'<fieldset id="'.$field['fieldid'].'">';
		$html .= 	'	<legend>'.$field['label'].'</legend>';

		return $html;
	}
}