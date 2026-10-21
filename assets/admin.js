( function () {
	'use strict';

	const enabledField = document.getElementById( 'simple-post-expiry-enabled' );
	const dateField = document.getElementById( 'simple-post-expiry-at' );

	if ( ! enabledField || ! dateField ) {
		return;
	}

	const syncFieldState = function () {
		dateField.disabled = ! enabledField.checked;
		dateField.required = enabledField.checked;
	};

	enabledField.addEventListener( 'change', syncFieldState );
	syncFieldState();
}() );
