jQuery(function($) {
	var oldIE = $("html").hasClass("lte7") ? true : false;
	/*********************
	 *
	 * CONDITIONAL
	 *
	 ********************/
	$("#extra-booking-content .conditional").each(function(){
		var elmt = $(this);
		var input = elmt.prev(".input-radio, .input-checkbox").first().find('input[type="radio"], input[type="checkbox"]').last();
		var inputs = elmt.parent().find('input[name="'+input.attr("name")+'"]');

		function update() {
			if(input.is(":checked")){
				if(oldIE) {
					elmt.show();
				} else {
					elmt.show(300);
				}
			} else {
				if(oldIE) {
					elmt.hide();
				} else {
					elmt.hide(300);
				}
			}
		}

		update();
		inputs.change(function(){
			update();
		});

	});
});