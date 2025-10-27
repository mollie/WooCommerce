document.addEventListener( 'DOMContentLoaded', function () {
	const noticeCancelButtons = document.querySelectorAll(
		'.mollie-notice button'
	);
	if ( noticeCancelButtons.length === 0 ) {
		return;
	}
	noticeCancelButtons.forEach( ( button ) => {
		button.addEventListener( 'click', function () {
			button.parentNode.remove();
		} );
	} );
} );
