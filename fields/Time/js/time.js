jQuery(function($) {
	/*********************
	 *
	 * HOUR SLIDER
	 *
	 ********************/
	var timeInputs = $(".input-extra-time > .input");
	var _slider = $('<div class="slider"></div>');
	timeInputs.each(function(){
		var input = $(this);
		var slider = _slider.clone();
		input.parent().addClass("input-time input-slider");
		var $slider = slider.insertBefore(input).slider({
			min: parseInt(extra_booking_datas[input.attr("name")+"_min"]),
			max: parseInt(extra_booking_datas[input.attr("name")+"_max"]),
			value: parseInt(extra_booking_datas[input.attr("name")+"_min"]),
			slide: function(event, ui){
				input.val(ui.value+"h00");
			}
		});
		input.val(extra_booking_datas[input.attr("name")+"_min"]+"h00");
	});
});