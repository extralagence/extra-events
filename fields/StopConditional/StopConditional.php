<?php

namespace ExtraEvents\Fields;

class StopConditional  extends AbstractField {
	public static function get_name() {
		return 'extra_stop_conditional';
	}

	public static function get_admin_label() {
		return __("Fin de condition", "extra-admin");
	}

	public static function get_front($field) {
		return '</div>';
	}
}