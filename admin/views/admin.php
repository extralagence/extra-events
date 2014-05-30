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

$events = EM_Events::get();

?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<?php if (empty($events)) : ?>
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
			<button type="submit" class="button button-primary<?php echo (empty($events)) ? ' disabled' : ''; ?>">
				<?php _e("Exporter toutes les réservations"); ?>
			</button>
		</p>
	</form>
</div>
