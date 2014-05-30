<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 13/05/2014
 * Time: 11:58
 */


class Extra_Events_Export_Xls {

	protected $plugin_slug;

	function _construct() {
		/* @var $plugin Extra_Events */
		$plugin = Extra_Events::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
	}

	public function export() {
		global $extra_options;

		//TODO Manage col_template restriction with extra_options

		$filtered_columns = array();
		$filter = (isset($_REQUEST['filter'])) ? $_REQUEST['filter'] : null;
		if ($filter != null && $filter != 'all') {
			$filtered_columns = explode(',', $extra_options[$filter]);
			$filtered_columns = array_map('trim', $filtered_columns);
		}

		$events = EM_Events::get();
		$_REQUEST['limit'] = 0;

		if (empty ($events)) {
			wp_die(
				__("Il n'y a aucun évévenement depuis lequel exporter des inscriptions.", $this->plugin_slug)
				.'<br><a href="' . admin_url( 'edit.php?post_type=event&page=extra-events' ) . '">&laquo; '.__("Retour", "extra-events").'</a>'
			);
			die;
		}

		/** Include PHPExcel */
		require_once dirname(__FILE__) . '/../admin/includes/PHPExcel/Classes/PHPExcel.php';
		PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);

		$xls_file = $this->create_xls_file();

		// Each event match to an excel worksheet
		$first = true;
		foreach ($events as $current_event) {
			/**
			 * @var $EM_Event EM_Event
			 */
			$EM_Event = $current_event;

			$EM_Bookings_Table = new EM_Bookings_Table();
			// Hack $EM_Bookings_Table to have all bookings
			$EM_Bookings_Table->statuses[$EM_Bookings_Table->status]['search'] = false;
			// Hack $EM_Bookings_Table to have all columns

			$allowed_columns = $EM_Bookings_Table->cols_template;
			// We manage attendee form column name
			$EM_Attendees_Form = EM_Attendees_Form::get_form($EM_Event);
			if ($EM_Attendees_Form != null) {
				foreach($EM_Attendees_Form->form_fields as $attendee_field_key => $attendee_field) {
					$allowed_columns[$attendee_field_key] = $attendee_field['label'];
				}
			}

			// Use to check columns ids
//			var_dump($allowed_columns);
//			die;

			if (!empty($filtered_columns)) {
				$new_allowed_columns = array();
				foreach ($filtered_columns as $col) {
					$new_allowed_columns[$col] = $allowed_columns[$col];
				}
				$allowed_columns = $new_allowed_columns;
			}

			if (!empty($allowed_columns)) {
				$EM_Bookings_Table->cols = array_keys($allowed_columns);
			}

//			var_dump($allowed_columns);
//			die;

			$EM_Bookings_Table->limit = 150; //if you're having server memory issues, try messing with this number
			$EM_Bookings = $EM_Bookings_Table->get_bookings();

			$tickets = $EM_Event->get_tickets();

			$tickets_by_id = array();
			foreach($tickets as $ticket) {
				$tickets_by_id[$ticket->ticket_id] = $ticket;
			}



			$xls_sheet = $this->create_xls_sheet($xls_file, $EM_Event->post_title, $allowed_columns, $first, count($tickets_by_id) > 1);
			$xls_row_id = 2;
			while(!empty($EM_Bookings->bookings)){
				foreach( $EM_Bookings->bookings as $EM_Booking ) {
					//Display all values
					/* @var $EM_Booking EM_Booking */
					/* @var $EM_Ticket_Booking EM_Ticket_Booking */

					foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking){
						/* @var $EM_Ticket_Booking EM_Ticket_Booking */
						$ticket_name = null;
						if (count($tickets_by_id) > 1) {
							/* @var $ticket EM_Ticket */
							$ticket = $tickets_by_id[$EM_Ticket_Booking->ticket_id];
							//$ticket = $EM_Ticket_Booking->get_ticket();
							$ticket_name = $ticket->ticket_name;
						}

						$row = $EM_Bookings_Table->get_row($EM_Ticket_Booking, true);
						$attendees = (isset($EM_Booking->booking_meta['attendees'])) ? $EM_Booking->booking_meta['attendees'] : null;

						if (!empty($attendees) && $this->check_show_attendees($allowed_columns, $EM_Attendees_Form->form_fields)) {
							// Strange there is sevreal attendees data set
							$attendees = array_pop($attendees);
							foreach ($attendees as $attendee) {
								$row = $this->insert_attendee_into_row($allowed_columns, $row, $attendee);

								$this->create_xls_row($xls_sheet, $row, $xls_row_id, $ticket_name);
								$xls_row_id ++;
							}
						} else {
							$this->create_xls_row($xls_sheet, $row, $xls_row_id, $ticket_name);
							$xls_row_id ++;
						}
					}
				}
				//reiterate loop
				$EM_Bookings_Table->offset += $EM_Bookings_Table->limit;
				$EM_Bookings = $EM_Bookings_Table->get_bookings();

				$first = false;
			}
		}

		// Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$xls_file->setActiveSheetIndex(0);
		/** Error reporting */
		error_reporting(E_ALL);
		ini_set('display_errors', TRUE);
		ini_set('display_startup_errors', TRUE);
		date_default_timezone_set('Europe/Paris');

		if (PHP_SAPI == 'cli')
			die('This example should only be run from a Web Browser');

		// Redirect output to a client’s web browser (Excel2007)
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="'.__("export-reservations-", $this->plugin_slug).date('d-m-Y-H\hi').'.xlsx"');
		header('Cache-Control: max-age=0');
		// If you're serving to IE 9, then the following may be needed
		header('Cache-Control: max-age=1');

		// If you're serving to IE over SSL, then the following may be needed
		header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
		header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
		header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
		header ('Pragma: public'); // HTTP/1.0

		$objWriter = PHPExcel_IOFactory::createWriter($xls_file, 'Excel2007');
		$objWriter->save('php://output');
		exit;
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