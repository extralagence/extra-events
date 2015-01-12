<?php
/**
 * Created by PhpStorm.
 * User: vincent
 * Date: 04/06/2014
 * Time: 14:30
 */


class Extra_Gateway_Paybox extends EM_Gateway {
	//change these properties below if creating a new gateway, not advised to change this for PayPal
	var $gateway = 'paybox';
	var $title = 'PayBox';
	var $status = 4;
	var $status_txt = "Paiement PayBox en attente";
	var $button_enabled = true;
	var $payment_return = true;
	var $count_pending_spaces = false;
	var $supports_multiple_bookings = true;

	var $preprod_url = 'preprod-tpeweb.paybox.com';
	var $prod_url = 'tpeweb.paybox.com';
	var $prod_url_mirror = 'tpeweb1.paybox.com';

	var $protocole = 'https://';
	var $cgi_part_url = '/cgi/MYchoix_pagepaiement.cgi';

	// var $preprod_admin = 'https://preprod-admin.paybox.com/';

	/**
	 * Sets up gateaway and adds relevant actions/filters
	 */
	function __construct() {
		//Booking Interception
		if( $this->is_active() && absint(get_option('em_'.$this->gateway.'_booking_timeout')) > 0 ){
			$this->count_pending_spaces = true;
		}
		parent::__construct();
		$this->status_txt = __("Paiement PayBox en attente", "extra");
		if($this->is_active()) {
			add_action('em_gateway_js', array(&$this,'em_gateway_js'));
			//Gateway-Specific
			add_action('em_template_my_bookings_header',array(&$this,'say_thanks')); //say thanks on my_bookings page
			add_filter('em_bookings_table_booking_actions_4', array(&$this,'bookings_table_actions'),1,2);
			add_filter('em_my_bookings_booking_actions', array(&$this,'em_my_bookings_booking_actions'),1,2);
			//set up cron
			$timestamp = wp_next_scheduled('emp_paybox_cron');
			if( absint(get_option('em_paybox_booking_timeout')) > 0 && !$timestamp ){
				$result = wp_schedule_event(time(),'em_minute','emp_paybox_cron');
			}elseif( !$timestamp ){
				wp_unschedule_event($timestamp, 'emp_paybox_cron');
			}
		}else{
			//unschedule the cron
			wp_clear_scheduled_hook('emp_paybox_cron');
		}


	}

	/*
	 * --------------------------------------------------
	 * Booking Interception - functions that modify booking object behaviour
	 * --------------------------------------------------
	 */

	/**
	 * Intercepts return data after a booking has been made and adds paybox vars, modifies feedback message.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	function booking_form_feedback( $return, $EM_Booking = false ){
		//Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
		if( is_object($EM_Booking) && $this->uses_gateway($EM_Booking) ){
			if( !empty($return['result']) && $EM_Booking->get_price() > 0 && $EM_Booking->booking_status == $this->status ){
				$return['message'] = get_option('em_paybox_booking_feedback');
				$paybox_url = $this->get_paybox_url();
				if ($paybox_url == null) {
					$return['message'] = nl2br(get_option('em_paybox_booking_feedback_try_later'));
				}
				$paybox_vars = $this->get_paybox_vars($EM_Booking);
				$paybox_return = array('paybox_url'=>$paybox_url, 'paybox_vars'=>$paybox_vars);
				$return = array_merge($return, $paybox_return);
			}else{
				//returning a free message
				$return['message'] = get_option('em_paybox_booking_feedback_free');
			}
		}
		return $return;
	}

	/**
	 * Called if AJAX isn't being used, i.e. a javascript script failed and forms are being reloaded instead.
	 * @param string $feedback
	 * @return string
	 */
	function booking_form_feedback_fallback( $feedback ){
		global $EM_Booking;
		if( is_object($EM_Booking) ){
			$feedback .= "<br />" . __('To finalize your booking, please click the following button to proceed to PayBox.','em-pro'). $this->em_my_bookings_booking_actions('',$EM_Booking);
		}
		return $feedback;
	}

	/**
	 * Triggered by the em_booking_add_yourgateway action, hooked in EM_Gateway. Overrides EM_Gateway to account for non-ajax bookings (i.e. broken JS on site).
	 * @param EM_Event $EM_Event
	 * @param EM_Booking $EM_Booking
	 * @param boolean $post_validation
	 */
	function booking_add($EM_Event, $EM_Booking, $post_validation = false){
		parent::booking_add($EM_Event, $EM_Booking, $post_validation);
		if( !defined('DOING_AJAX') ){ //we aren't doing ajax here, so we should provide a way to edit the $EM_Notices ojbect.
			add_action('option_dbem_booking_feedback', array(&$this, 'booking_form_feedback_fallback'));
		}
	}

	/*
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing paybox bookings
	 * --------------------------------------------------
	 */

	/**
	 * Instead of a simple status string, a resume payment button is added to the status message so user can resume booking from their my-bookings page.
	 * @param string $message
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	function em_my_bookings_booking_actions( $message, $EM_Booking){
		global $wpdb;
		if($this->uses_gateway($EM_Booking) && $EM_Booking->booking_status == $this->status){
			//first make sure there's no pending payments
			$pending_payments = $wpdb->get_var('SELECT COUNT(*) FROM '.EM_TRANSACTIONS_TABLE. " WHERE booking_id='{$EM_Booking->booking_id}' AND transaction_gateway='{$this->gateway}' AND transaction_status='Pending'");
			if( $pending_payments == 0 ){
				//user owes money!
				$paybox_vars = $this->get_paybox_vars($EM_Booking);
				$form = '<form action="'.$this->get_paybox_url().'" method="post">';
				foreach($paybox_vars as $key=>$value){
					$form .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
				}
				$form .= '<input type="submit" value="'.__('Resume Payment','em-pro').'">';
				$form .= '</form>';
				$message .= $form;
			}
		}
		return $message;
	}

	/**
	 * Outputs extra custom content e.g. the PayBox logo by default.
	 */
	function booking_form(){
		echo get_option('em_'.$this->gateway.'_form');
	}

	/**
	 * Outputs some JavaScript during the em_gateway_js action, which is run inside a script html tag, located in gateways/gateway.paybox.js
	 */
	function em_gateway_js(){
		include(dirname(__FILE__).'/assets/js/paybox.js');
	}

	/**
	 * Adds relevant actions to booking shown in the bookings table
	 * @param EM_Booking $EM_Booking
	 */
	function bookings_table_actions( $actions, $EM_Booking ){
		return array(
			'approve' => '<a class="em-bookings-approve em-bookings-approve-offline" href="'.em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'bookings_approve', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Approve','dbem').'</a>',
			'delete' => '<span class="trash"><a class="em-bookings-delete" href="'.em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'bookings_delete', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Delete','dbem').'</a></span>',
			'edit' => '<a class="em-bookings-edit" href="'.em_add_get_params($EM_Booking->get_event()->get_bookings_url(), array('booking_id'=>$EM_Booking->booking_id, 'em_ajax'=>null, 'em_obj'=>null)).'">'.__('Edit/View','dbem').'</a>',
		);
	}

	/*
	 * --------------------------------------------------
	 * PayBox Functions - functions specific to paybox payments
	 * --------------------------------------------------
	 */

	/**
	 * Retreive the paybox vars needed to send to the gatway to proceed with payment
	 * @param EM_Booking $EM_Booking
	 */
	function get_paybox_vars($EM_Booking){
		global $wp_rewrite, $EM_Notices;
		$notify_url = $this->get_payment_return_url().'&pbx_ipn=0';
		$waiting_url = $this->get_payment_return_url().'&pbx_ipn=0';
		$cancel_url = $this->get_payment_return_url().'&pbx_ipn=0'; //get_option('em_'. $this->gateway . "_cancel_return" );
		$response_url = $this->get_payment_return_url().'&pbx_ipn=1';

		//$currency_string = get_option('dbem_bookings_currency', 'USD');
		//WE FORCE CURRENCY TO EUR
		$currency = 978;

		// PRICE IN CENTS
		$price = $EM_Booking->get_price()*100;

		//TODO switch vars if test;
		$pbx_site = get_option('em_'. $this->gateway . "_site");
		$pbx_rang = get_option('em_'. $this->gateway . "_rank");
		$pbx_identifiant = get_option('em_'. $this->gateway . "_id");
		$hmac_key = get_option('em_'. $this->gateway . "_hmac_key");
		if (get_option('em_'. $this->gateway . "_status" ) == 'test') {
			$pbx_site = get_option('em_'. $this->gateway . "_site_test");
			$pbx_rang = get_option('em_'. $this->gateway . "_rank_test");
			$pbx_identifiant = get_option('em_'. $this->gateway . "_id_test");
			$hmac_key = get_option('em_'. $this->gateway . "_hmac_key_test");
		}

		$paybox_vars = array(
			'PBX_SITE' => $pbx_site,
			'PBX_RANG' => $pbx_rang,
			'PBX_IDENTIFIANT' => $pbx_identifiant,
			'PBX_TOTAL' => $price,
			'PBX_DEVISE' => $currency,
			'PBX_CMD' => $EM_Booking->booking_id.'_'.$EM_Booking->event_id.'_'.get_option('em_'. $this->gateway . "_suffix_command"),
			'PBX_PORTEUR' => $EM_Booking->get_person()->user_email,
			'PBX_RETOUR' => 'pbx_price:M;pbx_ref:R;pbx_auto:A;pbx_date:W;pbx_time:Q;pbx_error:E;pbx_signature:K',
			'PBX_TIME' => date('c'),
			'PBX_EFFECTUE' => $notify_url,
			'PBX_ATTENTE' => $waiting_url,
			'PBX_ANNULE' => $cancel_url,
			'PBX_REPONDRE_A' => $response_url
		);
		if( get_option('em_'. $this->gateway . "_language" ) ){
			$paybox_vars['PBX_LANGUE'] = get_option('em_'. $this->gateway . "_language" );
		}

		$paybox_vars = apply_filters('em_gateway_paybox_get_paybox_vars', $paybox_vars, $EM_Booking, $this);
		$paybox_vars['PBX_HASH'] = 'SHA512';

		$string_paybox_vars = '';
		$first = true;
		foreach ($paybox_vars as $key => $value) {
			if (!$first) {
				$string_paybox_vars .= '&';
			} else {
				$first = false;
			}

			$string_paybox_vars .= $key.'='.$value;
		}


		$binary_hmac_key = pack("H*", $hmac_key);

		$hmac = strtoupper(hash_hmac('sha512', $string_paybox_vars, $binary_hmac_key));
		$paybox_vars['PBX_HMAC'] = $hmac;

		return $paybox_vars;
	}

	/**
	 * gets paybox gateway url (sandbox or live mode)
	 * @returns string
	 */
	function get_paybox_url() {
		$url = null;
		if (get_option('em_'. $this->gateway . "_status" ) == 'test') {
			$url = $this->protocole.$this->preprod_url.$this->cgi_part_url;
		} else {
//			$url = $this->protocole.$this->prod_url.$this->cgi_part_url;
			//TODO TEST WITH REAL PROD !
			$servers = array($this->prod_url, //serveur primaire
				$this->prod_url_mirror); //serveur secondaire
			$serverOK = "";
			foreach($servers as $server){
				$doc = new DOMDocument();
				$doc->loadHTMLFile($this->protocole.$server.'/load.html');
				$server_status = "";
				$element = $doc->getElementById('server_status');
				if($element){
					$server_status = $element->textContent;
				}
				if($server_status == "OK"){
					//Le serveur est prêt et les services opérationnels
					$serverOK = $server;
					$url = $this->protocole.$server.$this->cgi_part_url;
					break;
				}
				// else : La machine est disponible mais les services ne le sont pas.
			}
			if(!$serverOK){
				$url = null;
			}
		}

		return $url;
	}

	function say_thanks(){

		if( isset($_REQUEST['thanks_paybox']) && !empty($_REQUEST['thanks_paybox']) ) {
			if ($_REQUEST['thanks_paybox'] == '2' || $_REQUEST['thanks_paybox'] == 2) {
				echo "<div class='em-booking-message em-booking-message-success manual_approval_thanks'>".nl2br(get_option('em_'.$this->gateway.'_booking_feedback_manual_approval_thanks')).'</div><br />';
				if (!is_user_logged_in()) {
					echo "<div class='em-booking-message em-booking-message-success manual_approval_thanks_logout'>".nl2br(get_option('em_'.$this->gateway.'_booking_feedback_manual_approval_thanks_logout')).'</div><br />';
				}
			} else if ($_REQUEST['thanks_paybox'] == '3' || $_REQUEST['thanks_paybox'] == 3) {
				echo "<div class='em-booking-message em-booking-message-success cancel_thanks'>".nl2br(get_option('em_'.$this->gateway.'_booking_feedback_cancel_thanks')).'</div><br />';
				if (!is_user_logged_in()) {
					echo "<div class='em-booking-message em-booking-message-success cancel_thanks_logout'>".nl2br(get_option('em_'.$this->gateway.'_booking_feedback_cancel_thanks_logout')).'</div><br />';
				}
			} else {
				echo "<div class='em-booking-message em-booking-message-success thanks'>".nl2br(get_option('em_'.$this->gateway.'_booking_feedback_thanks')).'</div><br />';
				if (!is_user_logged_in()) {
					echo "<div class='em-booking-message em-booking-message-success thanks_logout'>".nl2br(get_option('em_'.$this->gateway.'_booking_feedback_thanks_logout')).'</div><br />';
				}
			}
		}
	}


	/**
	 * Records a transaction according to this booking and gateway type.
	 * @param EM_Booking $EM_Booking
	 * @param float $amount
	 * @param string $currency
	 * @param int $timestamp
	 * @param string $txn_id
	 * @param int $status
	 * @param string $note
	 */
	function record_transaction_no_duplication($EM_Booking, $amount, $currency, $timestamp, $txn_id, $status, $note) {
		global $wpdb;
		$data = array();
		$data['booking_id'] = $EM_Booking->booking_id;
		$data['transaction_gateway_id'] = $txn_id;
		$data['transaction_timestamp'] = $timestamp;
		$data['transaction_currency'] = $currency;
		$data['transaction_status'] = $status;
		$data['transaction_total_amount'] = $amount;
		$data['transaction_note'] = $note;
		$data['transaction_gateway'] = $this->gateway;

		if( !empty($txn_id) ){
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT transaction_id, transaction_status, transaction_gateway_id, transaction_total_amount FROM ".EM_TRANSACTIONS_TABLE." WHERE transaction_gateway = %s AND transaction_gateway_id = %s", $this->gateway, $txn_id ) );
		}
		$table = EM_TRANSACTIONS_TABLE;
		if( is_multisite() && !EM_MS_GLOBAL && !empty($EM_Event->blog_id) && !is_main_site($EM_Event->blog_id) ){
			//we must get the prefix of the transaction table for this event's blog if it is not the root blog
			$table = $wpdb->get_blog_prefix($EM_Event->blog_id).'em_transactions';
		}
		if( !empty($existing->transaction_gateway_id) && $amount == $existing->transaction_total_amount) {

			$wpdb->update( $table, $data, array('transaction_id' => $existing->transaction_id) );
		} else {
			// Insert
			$wpdb->insert( $table, $data );
		}
	}

	private function check_signature() {
		$check = false;

		// ouverture de la clé publique Paybox
		$fp = $filedata = $key = FALSE;                         // initialisation variables
		//dirname(__FILE__).'/assets/js/paybox.js'
		$fsize =  filesize(dirname(__FILE__).'/assets/certificate/pubkey.pem');            // taille du fichier
		$fp = fopen(dirname(__FILE__).'/assets/certificate/pubkey.pem', 'r' );             // ouverture fichier
		$filedata = fread( $fp, $fsize );                       // lecture contenu fichier
		fclose( $fp );                                          // fermeture fichier
		$key = openssl_pkey_get_public( $filedata );        	// recuperation de la cle publique


		$ipn = $_GET['pbx_ipn'] == '1';
		$ipn_string = ($ipn) ? 'yes' : 'no';

		$data = array();
		if ($ipn) {
			// 'pbx_price:M;pbx_ref:R;pbx_auto:A;pbx_date:W;pbx_time:Q;pbx_error:E;pbx_signature:K'
			$data['pbx_price'] = $_GET['pbx_price'];
			$data['pbx_ref'] = $_GET['pbx_ref'];
			$data['pbx_auto'] = $_GET['pbx_auto'];
			$data['pbx_date'] = $_GET['pbx_date'];
			$data['pbx_time'] = $_GET['pbx_time'];
			$data['pbx_error'] = $_GET['pbx_error'];
		} else {
			$data = $_GET;
		}
		$data_string = '';
		$first = true;
		foreach ($data as $data_key => $value) {
			if ($data_key != 'pbx_signature') {
				if ($first) {
					$first = false;
				} else {
					$data_string .= '&';
				}
				$data_string .= $data_key.'='.urlencode($value);
			}
		}

		$signature = $_GET['pbx_signature'];
		$signature = base64_decode($signature);

		$ok = openssl_verify( $data_string, $signature, $key );
		if ($ok == 1) {
			$check = true;
		} elseif ($ok == 0) {
			EM_Pro::log("Invalid signature, is ipn : {$ipn_string}", "paybox");
		} else {
			EM_Pro::log("Error signature verification, is ipn : {$ipn_string}", "paybox");
		}

		return $check;
	}

	/**
	 * Runs when PayBox sends IPNs to the return URL provided during bookings and EM setup. Bookings are updated and transactions are recorded accordingly.
	 */
	function handle_payment_return() {
		$success = false;
		$manual_approval = false;

		if (   isset($_GET['pbx_signature'])
			&& isset($_GET['pbx_error'])
			&& isset($_GET['pbx_price'])
			&& isset($_GET['pbx_ref'])
			&& isset($_GET['pbx_auto'])
			&& isset($_GET['pbx_date'])
			&& isset($_GET['pbx_time'])
			&& isset($_GET['pbx_ipn'])
		) {

			$ipn = $_GET['pbx_ipn'] == '1';
			$error = $_GET['pbx_error'];
			// PAYBOX PRICES ARE IN CENTS
			$price = ($_GET['pbx_price']) / 100;
			$ref = $_GET['pbx_ref'];
			$ref_array = explode('_', $ref);
			$booking_id = $ref_array[0];
			$event_id = $ref_array[1];
			$authorization_id = $_GET['pbx_auto'];
			$date = $_GET['pbx_date'];
			$time = $_GET['pbx_time'];
			$timestamp = DateTime::createFromFormat('dmY H:i:s', $date.' '.$time)->format('Y-m-d H:i:s');

			$validation = $this->check_signature();

			if ($validation) {
				EM_Pro::log("{$error} successfully received for {$price} EUR (Reference : {$ref}) - Bank authorization id : {$authorization_id} - IPN : {$ipn}", 'paybox');
			} else {
				//log error if needed, send error header and exit
				$signature = $_GET['pbx_signature'];
				EM_Pro::log( array('IPN Signature Verification Error', 'Signature'=> $signature, '$_GET'=> $_GET), 'paybox' );
				header('HTTP/1.0 502 Bad Gateway');
				exit;
			}

			$EM_Booking = em_get_booking($booking_id);
			if( !empty($EM_Booking->booking_id)){

				//booking exists
				$EM_Booking->manage_override = true; //since we're overriding the booking ourselves.
				$user_id = $EM_Booking->person_id;

				switch ($error) {
					case '00000' :
						// SUCCESS
						// case: successful payment
						$this->record_transaction_no_duplication($EM_Booking, $price, 'EUR', $timestamp, $ref, __("Approuvée", "extra-events"), '');

						if( $price >= $EM_Booking->get_price() ){
							if ( (!get_option('em_'.$this->gateway.'_manual_approval', false) || !get_option('dbem_bookings_approval')) ) {
								// Automatic approval
								$EM_Booking->approve(true, true); //approve and ignore spaces
								$manual_approval = false;
							} else {
								// Manual approval
								$EM_Booking->set_status(0); //Set back to normal "pending"
								$manual_approval = true;
							}
							$success = true;
							do_action('em_payment_processed', $EM_Booking, $this);
						} else {
							$EM_Booking->set_status(0); //Set back to normal "pending"
							// Payment cancel
							$success = false;
						}

						break;

					case '00001' :
					case '00003' :
						// ERROR ON PAYBOX SERVER TRY SECOND URL
						EM_Pro::log( 'ERROR ON PAYBOX SERVER TRY SECOND URL', 'paybox' );
						break;

					case '00004' :
						// CRYPTOGRAMME INVALID
						EM_Pro::log( 'CRYPTOGRAMME INVALID', 'paybox' );
						break;

					case '00006' :
						// SITE, RANK OR ID INVALID
						EM_Pro::log( 'SITE, RANK OR ID INVALID', 'paybox' );
						break;

					case '00009' :
						// ERROR CREATION SUBSCRIPTION
						EM_Pro::log( 'ERROR CREATION SUBSCRIPTION', 'paybox' );
						break;

					case '00010' :
						// UNKNOWN CURRENCY
						EM_Pro::log( 'UNKNOWN CURRENCY', 'paybox' );
						break;

					case '00011' :
						// INVALID PRICE
						EM_Pro::log( 'INVALID PRICE', 'paybox' );
						break;

					case '00015' :
						// ALREADY PAID
						EM_Pro::log( 'ALREADY PAID', 'paybox' );
						break;

					case '00016' :
						// SUBSCRIPTION ALREADY EXIST
						EM_Pro::log( 'SUBSCRIPTION ALREADY EXIST', 'paybox' );
						break;

					case '00021' :
						// CARD NON AUTHORIZED
						EM_Pro::log( 'CARD NON AUTHORIZED', 'paybox' );
						break;

					case '00029' :
						// ERROR ON PBX_EMPREINTE. INVALID DATA
						EM_Pro::log( 'ERROR ON PBX_EMPREINTE. INVALID DATA', 'paybox' );
						break;

					case '00030' :
						// TIMEOUT : WAITING > 15MIN
						EM_Pro::log( 'TIMEOUT : WAITING > 15MIN', 'paybox' );
						break;

					case '00033' :
						// IP CLIENT NOT AUTHORIZED
						EM_Pro::log( 'IP CLIENT NOT AUTHORIZED', 'paybox' );
						break;

					case '00040' :
						// 3-DSECURE INVALID
						EM_Pro::log( '3-DSECURE INVALID', 'paybox' );
						break;

					case '99999' :
						// WAITING PAIEMENT (EXAMPLE PAYPAL)

						// case: payment is pending
						$note = 'Last transaction is pending.';
						$this->record_transaction_no_duplication($EM_Booking, $price, 'EUR', $timestamp, $ref, __("En Attente", "extra-events"), $note);

						do_action('em_payment_pending', $EM_Booking, $this);
						break;

					default :
						if (substr($error, 0, 3) == '001') {
							// 001xx ERROR ON BANK
						}
						break;
				}
			} else {
				if( is_numeric($event_id) && is_numeric($booking_id) && ($error == '00000') ){
					$message = apply_filters('em_gateway_paybox_bad_booking_email',"
A Payment has been received by PayBox for a non-existent booking.

Event Details : %event%

It may be that this user's booking has timed out yet they proceeded with payment at a later stage.

In some cases, it could be that other payments not related to Events Manager are triggering this error. If that's the case, you can prevent this from happening by changing the URL in your IPN settings to:

". get_home_url() ."

To refund this transaction, you must go to your PayBox account and search for this transaction:

Reference : %ref%
Authorization id : %authorization%

When viewing the transaction details, you should see an option to issue a refund.

If there is still space available, the user must book again.

Sincerely,
Events Manager
					", $booking_id, $event_id);
					$EM_Event = new EM_Event($event_id);
					$event_details = $EM_Event->name . " - " . date_i18n(get_option('date_format'), $EM_Event->start);
					$message  = str_replace(array('%ref%','%autorization%', '%event%'), array($ref, $authorization_id, $event_details), $message);
					wp_mail(get_option('em_'. $this->gateway . "_email" ), __('Unprocessed payment needs refund'), $message);
				}else{
					//header('Status: 404 Not Found');
					EM_Pro::log('Error: Bad IPN request, custom ID does not correspond with any pending booking.');
					//echo "<pre>"; print_r($_POST); echo "</pre>";
					//exit;
				}
			}
		} else {
			// Did not find expected POST variables. Possible access attempt from a non PayBox site.
			//header('Status: 404 Not Found');
			EM_Pro::log('Error: Missing POST variables. Identification is not possible. If you are not PayBox and are visiting this page directly in your browser, this error does not indicate a problem, but simply means EM is correctly set up and ready to receive IPNs from PayBox only.');
			//exit;
		}

		if (!$ipn) {
			if ($success) {
				if ($manual_approval) {
					//redirect to reservation success but waiting manual approval
					wp_redirect(get_option('em_'. $this->gateway . "_manual_approval_return" ));
				} else {
					//redirect to reservation success automatic approval
					wp_redirect(get_option('em_'. $this->gateway . "_return" ));
				}
			} else {
				wp_redirect(get_option('em_'. $this->gateway . "_cancel_return" ));
			}
		}
		exit;
	}

	/**
	 * Fixes SSL issues with wamp and outdated server installations combined with curl requests by forcing a custom pem file, generated from - http://curl.haxx.se/docs/caextract.html
	 * @param resource $handle
	 */
//	public static function payment_return_local_ca_curl( $handle ){
//	    curl_setopt($handle, CURLOPT_CAINFO, dirname(__FILE__).DIRECTORY_SEPARATOR.'gateway.paybox.pem');
//	}

	/*
	 * --------------------------------------------------
	 * Gateway Settings Functions
	 * --------------------------------------------------
	 */

	/**
	 * Outputs custom PayBox setting fields in the settings page
	 */
	function mysettings() {
		global $EM_options;
		?>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><?php _e('Message de réussite', 'extra-events') ?></th>
				<td>
					<input type="text" name="paybox_booking_feedback" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback" )); ?>" style='width: 40em;' /><br />
					<em><?php _e("Ce message est utilisé lorsqu'un utilisateur est redirigé vers PayBox pour effectuer son paiement",'extra-events'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e("Message d'echec", 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_try_later"><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_try_later" )); ?></textarea><br />
					<em><?php _e("Ce message est utilisé lorsque PayBox est injoignable.", "extra-events"); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Message de réussite (pour un ticket gratuit)', 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_free" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_free" )); ?></textarea><br />
					<em><?php _e("En cas de ticket gratuit, l'utilisateur n'est pas rediriger vers PayBox et ce message lui est présenté.", "extra-events"); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Message de remerciement', 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_thanks" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_thanks" )); ?></textarea><br />
					<em><?php _e("Message utilisé lorsque le client revient de PayBox et a complété son paiement."); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e("Message de remerciement (suite)", 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_thanks_logout" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_thanks_logout" )); ?></textarea><br />
					<em><?php _e("Complement du message de remerciement si l'utilisateur n'a pas encore de compte"); ?></em>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Message de remerciement (validation manuelle)', 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_manual_approval_thanks" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_manual_approval_thanks" )); ?></textarea><br />
					<em><?php _e("Message utilisé lorsque le client revient de PayBox et a complété son paiement. Mais la validation manuelle est activée"); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e("Message de remerciement (validation manuelle) (suite)", 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_manual_approval_thanks_logout" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_manual_approval_thanks_logout" )); ?></textarea><br />
					<em><?php _e("Complement du message de remerciement (validation manuelle) si l'utilisateur n'a pas encore de compte"); ?></em>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e("Message de confirmation de l'annulation de la transaction", 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_cancel_thanks" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_cancel_thanks" )); ?></textarea><br />
					<em><?php _e("Message utilisé lorsque le client revient de PayBox mais en ayant annulé sa transation."); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e("Message de confirmation de l'annulation de la transaction (suite)", 'extra-events') ?></th>
				<td>
					<textarea name="paybox_booking_feedback_cancel_thanks_logout" ><?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_cancel_thanks_logout" )); ?></textarea><br />
					<em><?php _e("Complement du message de confirmation de l'annulation de la transaction si l'utilisateur n'a pas encore de compte"); ?></em>
				</td>
			</tr>
			</tbody>
		</table>

		<h3><?php echo sprintf(__('%s Options','em-pro'),'PayBox'); ?></h3>
		<p><strong><?php _e('Important:','em-pro'); ?></strong> <?php echo __('Pour connecter votre site a PayBox vous avez besoin de spécifier votre url IPN'); echo " ". sprintf(__('Your return url is %s','em-pro'),'<code>'.$this->get_payment_return_url().'</code>'); ?></p>
		<p><?php echo sprintf(__('Please visit the <a href="%s">documentation</a> for further instructions.','em-pro'), 'http://wp-events-plugin.com/documentation/'); ?></p>
		<table class="form-table">
			<tbody>
			<tr valign="top">
				<th scope="row"><?php _e('Numéro de Site (prod)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_site" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_site" )); ?>" />
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Numéro de Rang (prod)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_rank" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_rank" )); ?>" />
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Identifiant (prod)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_id" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_id" )); ?>" />
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Clé secrète HMAC (prod)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_hmac_key" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_hmac_key" )); ?>" />
					<br />
				</td>
			</tr>


			<tr valign="top">
				<th scope="row"><?php _e('Numéro de Site (test)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_site_test" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_site_test" )); ?>" />
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Numéro de Rang (test)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_rank_test" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_rank_test" )); ?>" />
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Identifiant (test)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_id_test" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_id_test" )); ?>" />
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Clé secrète HMAC (test)', 'extra-events') ?></th>
				<td><input type="text" name="paybox_hmac_key_test" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_hmac_key_test" )); ?>" />
					<br />
				</td>
			</tr>


			<tr valign="top">
				<th scope="row"><?php _e('Préfix numéro de commande', 'extra-events') ?></th>
				<td><input type="text" name="paybox_suffix_command" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_suffix_command" )); ?>" />
					<br />
				</td>
			</tr>


			<tr valign="top">
				<th scope="row"><?php _e('Devise', 'extra-events') ?></th>
				<td><?php echo esc_html(get_option('dbem_bookings_currency','USD')); ?><br /><i><?php echo sprintf(__('Set your currency in the <a href="%s">settings</a> page.','em-pro'),EM_ADMIN_URL.'&amp;page=events-manager-options#bookings'); ?></i></td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Language de la page de paiement', 'extra-events') ?></th>
				<td>
					<select name="paybox_language">
						<option value=""><?php _e('Default','extra-events'); ?></option>
						<?php
						$ccodes = array(
							'FRA' => __("Français", 'extra-events'),
							'GBR' => __("Anglais", 'extra-events'),
							'DEU' => __("Allemand", 'extra-events'),
							'ESP' => __("Espagnol", 'extra-events')
						);
						$paybox_language = get_option('em_'.$this->gateway.'_language', 'FRA');
						foreach($ccodes as $key => $value){
							if( $paybox_language == $key ){
								echo '<option value="'.$key.'" selected="selected">'.$value.'</option>';
							}else{
								echo '<option value="'.$key.'">'.$value.'</option>';
							}
						}
						?>

					</select>
					<br />
					<i><?php _e('Langage utilisé par Paybox (Par défaut Français)','extra-events') ?></i>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('PayBox Mode', 'em-pro') ?></th>
				<td>
					<select name="paybox_status">
						<option value="live" <?php if (get_option('em_'. $this->gateway . "_status" ) == 'live') echo 'selected="selected"'; ?>><?php _e('Live Site', 'em-pro') ?></option>
						<option value="test" <?php if (get_option('em_'. $this->gateway . "_status" ) == 'test') echo 'selected="selected"'; ?>><?php _e('Test Mode (Sandbox)', 'em-pro') ?></option>
					</select>
					<br />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Return URL', 'em-pro') ?></th>
				<td>
					<input type="text" name="paybox_return" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_return" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('Once a payment is completed, users will be offered a link to this URL which confirms to the user that a payment is made. If you would to customize the thank you page, create a new page and add the link here. For automatic redirect, you need to turn auto-return on in your PayBox settings.', 'em-pro'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('URL de retour (validation manuelle)', 'extra-events') ?></th>
				<td>
					<input type="text" name="paybox_manual_approval_return" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_manual_approval_return" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('URL attente une fois le paiement validé mais nécessitant une validation.', 'extra-events'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Cancel URL', 'em-pro') ?></th>
				<td>
					<input type="text" name="paybox_cancel_return" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_cancel_return" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('Whilst paying on PayBox, if a user cancels, they will be redirected to this page.', 'em-pro'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Delete Bookings Pending Payment', 'em-pro') ?></th>
				<td>
					<input type="text" name="paybox_booking_timeout" style="width:50px;" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_timeout" )); ?>" style='width: 40em;' /> <?php _e('minutes','em-pro'); ?><br />
					<em><?php _e('Once a booking is started and the user is taken to PayBox, Events Manager stores a booking record in the database to identify the incoming payment. These spaces may be considered reserved if you enable <em>Reserved unconfirmed spaces?</em> in your Events &gt; Settings page. If you would like these bookings to expire after x minutes, please enter a value above (note that bookings will be deleted, and any late payments will need to be refunded manually via PayBox).','em-pro'); ?></em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Manually approve completed transactions?', 'em-pro') ?></th>
				<td>
					<input type="checkbox" name="paybox_manual_approval" value="1" <?php echo (get_option('em_'. $this->gateway . "_manual_approval" )) ? 'checked="checked"':''; ?> /><br />
					<em><?php _e('By default, when someone pays for a booking, it gets automatically approved once the payment is confirmed. If you would like to manually verify and approve bookings, tick this box.','em-pro'); ?></em><br />
					<em><?php echo sprintf(__('Approvals must also be required for all bookings in your <a href="%s">settings</a> for this to work properly.','em-pro'),EM_ADMIN_URL.'&amp;page=events-manager-options'); ?></em>
				</td>
			</tr>
			</tbody>
		</table>
	<?php
	}

	/*
	 * Run when saving PayBox settings, saves the settings available in Extra_Gateway_Paybox::mysettings()
	 */
	function update() {
		parent::update();
		$gateway_options = array(
			$this->gateway . "_email" => $_REQUEST[ $this->gateway.'_email' ],
			$this->gateway . "_site" => $_REQUEST[ $this->gateway.'_site' ],
			$this->gateway . "_rank" => $_REQUEST[ $this->gateway.'_rank' ],
			$this->gateway . "_id" => $_REQUEST[ $this->gateway.'_id' ],
			$this->gateway . "_hmac_key" => $_REQUEST[ $this->gateway.'_hmac_key' ],

			$this->gateway . "_site_test" => $_REQUEST[ $this->gateway.'_site_test' ],
			$this->gateway . "_rank_test" => $_REQUEST[ $this->gateway.'_rank_test' ],
			$this->gateway . "_id_test" => $_REQUEST[ $this->gateway.'_id_test' ],
			$this->gateway . "_hmac_key_test" => $_REQUEST[ $this->gateway.'_hmac_key_test' ],

			$this->gateway . "_suffix_command" => $_REQUEST[ $this->gateway.'_suffix_command' ],
			$this->gateway . "_currency" => $_REQUEST[ 'currency' ],
			$this->gateway . "_language" => $_REQUEST[ $this->gateway.'_language' ],
			$this->gateway . "_status" => $_REQUEST[ $this->gateway.'_status' ],
			$this->gateway . "_tax" => $_REQUEST[ $this->gateway.'_button' ],
			$this->gateway . "_format_logo" => $_REQUEST[ $this->gateway.'_format_logo' ],
			$this->gateway . "_format_border" => $_REQUEST[ $this->gateway.'_format_border' ],
			$this->gateway . "_manual_approval" => $_REQUEST[ $this->gateway.'_manual_approval' ],
			$this->gateway . "_booking_feedback" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback' ]),
			$this->gateway . "_booking_feedback_try_later" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_try_later' ]),
			$this->gateway . "_booking_feedback_free" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_free' ]),
			$this->gateway . "_booking_feedback_thanks" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_thanks' ]),
			$this->gateway . "_booking_feedback_thanks_logout" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_thanks_logout' ]),
			$this->gateway . "_booking_feedback_manual_approval_thanks" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_manual_approval_thanks' ]),
			$this->gateway . "_booking_feedback_manual_approval_thanks_logout" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_manual_approval_thanks_logout' ]),
			$this->gateway . "_booking_feedback_cancel_thanks" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_cancel_thanks' ]),
			$this->gateway . "_booking_feedback_cancel_thanks_logout" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_cancel_thanks_logout' ]),
			$this->gateway . "_booking_timeout" => $_REQUEST[ $this->gateway.'_booking_timeout' ],
			$this->gateway . "_return" => $_REQUEST[ $this->gateway.'_return' ],
			$this->gateway . "_manual_approval_return" => $_REQUEST[ $this->gateway.'_manual_approval_return' ],
			$this->gateway . "_cancel_return" => $_REQUEST[ $this->gateway.'_cancel_return' ],
			$this->gateway . "_form" => $_REQUEST[ $this->gateway.'_form' ]
		);
		foreach($gateway_options as $key=>$option){
			update_option('em_'.$key, stripslashes($option));
		}
		//default action is to return true
		return true;

	}
}

/**
 * Deletes bookings pending payment that are more than x minutes old, defined by paybox options.
 */
function em_gateway_paybox_booking_timeout(){
	global $wpdb;
	//Get a time from when to delete
	$minutes_to_subtract = absint(get_option('em_paybox_booking_timeout'));
	if( $minutes_to_subtract > 0 ){
		//get booking IDs without pending transactions
		$cut_off_time = date('Y-m-d H:i:s', current_time('timestamp') - ($minutes_to_subtract * 60));
		$booking_ids = $wpdb->get_col('SELECT b.booking_id FROM '.EM_BOOKINGS_TABLE.' b LEFT JOIN '.EM_TRANSACTIONS_TABLE." t ON t.booking_id=b.booking_id  WHERE booking_date < '{$cut_off_time}' AND booking_status=4 AND transaction_id IS NULL" );
		if( count($booking_ids) > 0 ){
			//first delete ticket_bookings with expired bookings
			$sql = "DELETE FROM ".EM_TICKETS_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',',$booking_ids).");";
			$wpdb->query($sql);
			//then delete the bookings themselves
			$sql = "DELETE FROM ".EM_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',',$booking_ids).");";
			$wpdb->query($sql);
		}
	}
}
add_action('emp_paybox_cron', 'em_gateway_paybox_booking_timeout');