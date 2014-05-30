<?php

namespace ExtraEvents\Fields;

class StopFieldset extends AbstractField {
	public static function get_name() {
		return 'extra_stop_fieldset';
	}

	public static function get_admin_label() {
		return __("Fin de rubrique", "extra-admin");
	}

	public static function get_front($field) {
		return '</fieldset>';
	}
}