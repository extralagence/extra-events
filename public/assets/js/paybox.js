//add paybox redirection
$(document).bind('em_booking_gateway_add_paybox', function(event, response){

//	console.log(response);

	// called by EM if return JSON contains gateway key, notifications messages are shown by now.
	if(response.result && response.paybox_url != null) {
		var ppForm = $('<form action="'+response.paybox_url+'" method="POST" id="em-paybox-redirect-form"></form>');
		$.each( response.paybox_vars, function(index,value){
			ppForm.append('<input type="hidden" name="'+index+'" value="'+value+'" />');
		});
		ppForm.append('<input id="em-paybox-submit" type="submit" style="display:none" />');
		ppForm.appendTo('body').trigger('submit');
//		console.log('Stop send to paybox !');
	} else {
		//TODO change message + Add button to "gérer ma réservation"

	}
});