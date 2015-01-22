<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 13/05/2014
 * Time: 11:58
 */


class Extra_Events_Export_Bill {

	protected $plugin_slug;

	function _construct() {
		/* @var $plugin Extra_Events */
		$plugin = Extra_Events::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
	}

	public static function get_bill_location($event_id, $booking_id) {
		$upload_dir = wp_upload_dir();
		$url_dir = $upload_dir['baseurl'].'/bills/event_'.$event_id;
		$path_dir = $upload_dir['basedir'].'/bills/event_'.$event_id;
		$pdf_url = $url_dir.'/facture_'.$booking_id.'.pdf';
		$pdf_path = $path_dir.'/facture_'.$booking_id.'.pdf';

		return array(
			'baseurl' => $url_dir,
			'basepath' => $path_dir,
			'pdfurl' => $pdf_url,
			'pdfpath' => $pdf_path,
		);
	}

	public function create_zip($event_id, $booking_ids, $current_zip, $total_zip) {

		$EM_Event = new EM_Event($event_id);
		$zip_archive_filename = sanitize_file_name($EM_Event->event_name);

		if (!($current_zip == 1 && $total_zip == 1)) {
			$zip_archive_filename = $zip_archive_filename.'_part_'.$current_zip;
		}

		$response = array(
			'success' => false,
			'bookingIds' => $booking_ids,
			'url' => '',
			'currentZip' => $current_zip,
			'totalZip' => $total_zip,
			'errorMessage' => __("Oups une erreur est survenue lors de la création du zip", 'extra-admin')
		);

		$upload_dir = wp_upload_dir();
		$url_dir = $upload_dir['baseurl'];
		$upload_dir = $upload_dir['basedir'];

		$basename = '/'.$zip_archive_filename.'.zip';
		$filename = $upload_dir.$basename;
		$zip = new ZipArchive();

		if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
			$response['success'] = false;
			$response['errorMessage'] = "Impossible d'ouvrir le fichier ".$filename;

			return $response;
		}

		// Each event match to an excel worksheet
		foreach ($booking_ids as $booking_id) {
			$bill_location = self::get_bill_location($event_id, $booking_id);
			$zip->addFile($bill_location['pdfpath'], 'facture_'.$booking_id.'.pdf');
		}
		$zip->close();

		$response['success'] = true;
		$response['url'] = $url_dir.$basename;

		return $response;
	}

	public function check_bill($booking_id, $force_refresh) {
		$EM_Booking = new EM_Booking($booking_id);
		$person = $EM_Booking->get_person();

		$response = array (
			'success' => false,
			'bookingId' => $booking_id,
			'personEmail' => $person->user_email,
			'personId' => $person->ID
		);

		$bill_location = self::get_bill_location($EM_Booking->event_id, $EM_Booking->booking_id);

		if (!$force_refresh && file_exists($bill_location['pdfpath'])) {
			$response['success'] = true;
		} else {
			$bill = apply_filters('extra-events-generate-bill', null, $EM_Booking);
			if ($bill != null) {
				$response['success'] = true;
			}
		}

		return $response;
	}

	protected function check_show_attendees($allowed_columns, $attendee_columns) {
		$found = false;
		foreach (array_keys($attendee_columns) as $attendee_key) {
			$index = array_search($attendee_key, array_keys($allowed_columns));
			if ($index !== false) {
				$found = true;
				break;
			}
		}

		return $found;
	}

	protected function insert_attendee_into_row($allowed_columns, $row, $attendee) {
		foreach ($attendee as $key_to_insert => $data_to_insert) {
			$index = array_search($key_to_insert, array_keys($allowed_columns));
			if ($index !== false) {
				$row[$index] = $data_to_insert;
			}
		}

		return $row;
	}

	/**
	 * @return \PHPExcel
	 */
	protected function create_xls_file() {
		$xls_file = new PHPExcel();
		// Set document properties
		$xls_file->getProperties()->setCreator("Extra l'agence")
			->setLastModifiedBy("Extra l'agence")
			->setTitle(__("Export des réservations", $this->plugin_slug))
			->setSubject(__("Export des réservations", $this->plugin_slug))
			->setDescription(__("Export des réservations", $this->plugin_slug))
			->setKeywords(__("export réservations", $this->plugin_slug))
			->setCategory(__("export réservations", $this->plugin_slug));

		return $xls_file;
	}

	/**
	 * @param $xls_file \PHPExcel
	 * @param $sheet_name string
	 * @param $col_templates mixed|array
	 *
	 * @return PHPExcel_Worksheet
	 */
	protected function create_xls_sheet(&$xls_file, $sheet_name, $col_templates, $first, $add_ticket_column = false) {
		if ($first) {
			$xls_sheet = $xls_file->getSheet(0);
			$xls_sheet->setTitle($sheet_name);
		} else {
			$xls_sheet = new \PHPExcel_Worksheet($xls_file, $sheet_name);
			$xls_file->addSheet($xls_sheet);
		}
		$i = 0;
		foreach ($col_templates as $key => $name) {
			if ($key != 'actions') {
				$xls_sheet->setCellValueByColumnAndRow($i, 1, $name);
				$xls_file->getActiveSheet()->getColumnDimensionByColumn($i)->setAutoSize(true);
				$xls_file->getActiveSheet()->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
				$i++;
			}
		}
		if ($add_ticket_column) {
			//Ticket column
			$xls_sheet->setCellValueByColumnAndRow($i, 1, __("Nom du billet", $this->plugin_slug));
			$xls_file->getActiveSheet()->getColumnDimensionByColumn($i)->setAutoSize(true);
			$xls_file->getActiveSheet()->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
		}

		return $xls_sheet;
	}

//	/**
//	 * @param $key string
//	 * @param $name string
//	 *
//	 * @return string|void
//	 */
//	protected function rename_column ($key, $name) {
//		if ($key == 'user_name') {
//			$name = __("Login participant", $this->plugin_slug);
//		} else if ($key == 'first_name') {
//			$name = __("Prénom participant", $this->plugin_slug);
//		} else if ($key == 'last_name') {
//			$name = __("Nom participant", $this->plugin_slug);
//		} else if ($key == 'booking_price') {
//			$name = __("Total payé", $this->plugin_slug);
//		} else if ($key == 'gateway') {
//			$name = __("Moyen de paiement", $this->plugin_slug);
//		} else if ($key == 'dbem_city') {
//			$name = __("Ville", $this->plugin_slug);
//		} else if ($key == 'dbem_state') {
//			$name = __("Région", $this->plugin_slug);
//		}
//
//		return $name;
//	}

	/**
	 * @param $xls_sheet \PHPExcel_Worksheet
	 * @param $booking_row mixed|array
	 * @param $xls_row_id int
	 */
	protected function create_xls_row(&$xls_sheet, $booking_row, $xls_row_id, $ticket_name = null) {
		$i = 0;
		foreach ($booking_row as $booking_cell_value) {
			$xls_sheet->setCellValueByColumnAndRow($i, $xls_row_id, $booking_cell_value);
			$xls_sheet->getCellByColumnAndRow($i, $xls_row_id);
			$xls_sheet->getStyleByColumnAndRow($i, $xls_row_id)->getAlignment()->setWrapText(true);
			$i++;
		}

		if ($ticket_name != null) {
			//Ticket column
			$xls_sheet->setCellValueByColumnAndRow($i, $xls_row_id, $ticket_name);
			$xls_sheet->getCellByColumnAndRow($i, $xls_row_id);
			$xls_sheet->getStyleByColumnAndRow($i, $xls_row_id)->getAlignment()->setWrapText(true);
		}
	}
}