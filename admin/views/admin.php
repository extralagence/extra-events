<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   Extra_Events
 * @author    Vincent Saïsset <vs@extralagence.com>
 * @license   GPL-2.0+
 * @link      http://www.extralagence.com
 * @copyright 2014 Extra l'agence
 */

global $extra_options;

?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<?php if (empty($this->events)) : ?>
		<div class="error"><p><?php _e("Il n'y a aucun évévenement depuis lequel exporter des inscriptions.", $this->plugin_slug); ?></p></div>
	<?php endif; ?>

	<form class="" method="GET" action="<?php echo get_admin_url(null, 'edit.php?post_type=event&page=extra-events'); ?>">
		<input name="action" type="hidden" value="export_xls">
		<p>
			<select name="filter">
<!--				<option value="all">--><?php //_e('Toutes les informations', $this->plugin_slug); ?><!--</option>-->
				<?php if ($extra_options['extra_events_export_filter_enable_1']  == true) : ?>
					<option value="extra_events_export_filter_1"><?php echo $extra_options['extra_events_export_filter_name_1']; ?></option>
				<?php endif; ?>
				<?php if ($extra_options['extra_events_export_filter_enable_2']  == true) : ?>
					<option value="extra_events_export_filter_2"><?php echo $extra_options['extra_events_export_filter_name_2']; ?></option>
				<?php endif; ?>
				<?php if ($extra_options['extra_events_export_filter_enable_3']  == true) : ?>
					<option value="extra_events_export_filter_3"><?php echo $extra_options['extra_events_export_filter_name_3']; ?></option>
				<?php endif; ?>
			</select>
			<button type="submit" class="button button-primary<?php echo (empty($this->events)) ? ' disabled' : ''; ?>">
				<?php _e("Exporter toutes les réservations"); ?>
			</button>
		</p>
	</form>


	<?php if (defined('EXTRA_EVENTS_BILL') && EXTRA_EVENTS_BILL == true) : ?>
		<h2><?php _e("Exporter les factures", 'extra-admin'); ?></h2>
		<?php if (empty($this->events)) : ?>
			<div class="error"><p><?php _e("Il n'y a aucun évévenement depuis lequel exporter des factures.", $this->plugin_slug); ?></p></div>
		<?php endif; ?>

		<?php if (empty($this->all_booking_ids_by_bill_events)) : ?>
			<div class="error"><p><?php _e("Il n'y a aucune reservation depuis lequel exporter des factures.", $this->plugin_slug); ?></p></div>
		<?php endif; ?>

		<?php foreach ($this->all_booking_ids_by_bill_events as $all_booking_id_by_event) : ?>
			<h3><?php  echo $all_booking_id_by_event['name']; ?></h3>
			<div id="export-bill-bloc-<?php echo $all_booking_id_by_event['id']; ?>" class="export-bill-bloc" data-event-id="<?php echo $all_booking_id_by_event['id']; ?>">
				<p>
					<input type="checkbox" name="force_refresh" id="export-bill-force-refresh-<?php echo $all_booking_id_by_event['id']; ?>" class="export-bill-force-refresh" value="1">
					<label for="export-bill-force-refresh-<?php echo $all_booking_id_by_event['id']; ?>"><?php _e("Regénérer toutes les factures", 'extra-events'); ?></label>
				</p>
				<a href="#export-bill-console-<?php echo $all_booking_id_by_event['id']; ?>" type="submit" id="export-bill-button-<?php echo $all_booking_id_by_event['id']; ?>" class="export-bill-button button button-primary<?php echo (empty($this->events) || empty($this->all_booking_ids_by_bill_events)) ? ' disabled' : ''; ?>">
					<?php _e("Exporter "); ?>
				</a>
				<span class="export-bill-count">
					<?php echo count($all_booking_id_by_event['bookingIds']); ?> <?php _e('facture(s) a exporter.'); ?>
				</span>
				<img id="export-bill-loader-<?php echo $all_booking_id_by_event['id']; ?>" class="export-bill-loader" src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/img/ajax-loader.gif' ?>" />
				<div id="export-bill-console-<?php echo $all_booking_id_by_event['id']; ?>" class="export-bill-console"></div>
				<ul class="export-bill-download-links"></ul>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

</div>
