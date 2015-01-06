function checkPasswordStrength( $pass1, $pass2, $strengthResult, $submit) {
    var pass1 = $pass1.val();
    var pass2 = $pass2.val();

    $strengthResult.removeClass( 'short bad good strong' );

    // Extend our blacklist array with those from the inputs & site data
    blacklistArray = wp.passwordStrength.userInputBlacklist();

    // Get the password strength
    var strength = wp.passwordStrength.meter( pass1, blacklistArray, pass2 );

    // Add the strength meter results
    switch ( strength ) {

        case 2:
            $strengthResult.addClass( 'bad' ).html( pwsL10n.bad );
            break;

        case 3:
            $strengthResult.addClass( 'good' ).html( pwsL10n.good );
            break;

        case 4:
            $strengthResult.addClass( 'strong' ).html( pwsL10n.strong );
            break;

        case 5:
            $strengthResult.addClass( 'short' ).html( pwsL10n.mismatch );
            break;

        default:
            $strengthResult.addClass( 'short' ).html( pwsL10n.short );

    }

    console.log("coucou");

    return strength;
}

jQuery( document ).ready( function( $ ) {
    // Binding to trigger checkPasswordStrength

	var pass1 = $('input[name=pass1]'),
    	pass2 = $('input[name=pass2]'),
        strengthResult = $('#pass-strength-result'),
        submit = $("#profile-editor input[type=submit]");
    $( 'body' ).on( 'keyup', 'input[name=pass1], input[name=pass2]',
        function( event ) {
            checkPasswordStrength(pass1, pass2, strengthResult, submit);
        }
    );
});