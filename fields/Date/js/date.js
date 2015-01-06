jQuery(function($) {
	/*********************
	 *
	 * DATEPICKER
	 *
	 ********************/
	// DATEPICKER FR
	$.datepicker.regional["fr"];
	var dateInputs = $(".input-extra-date > .input");
	dateInputs.each(function(){
		var input = $(this);
		var name = input.attr("name");
		input.parent().addClass("input-date");
		input.datepicker({
			minDate: extra_booking_datas[name+"_min"],
			maxDate: extra_booking_datas[name+"_max"],
			showOn: "both",
			buttonImage: extra_booking_datas.template_url+"/assets/img/visu/calendar.png"
		});
	});
});