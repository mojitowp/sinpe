(function( $ ) {
	'use strict';

	 $(document).on('change', '.mojito_sinpe_bank_selector', function(event) {
		 
		event.preventDefault();

		var link = $('.mojito-sinpe-link');
		var type = link.data('type');
		var bank = $(this).val();
		var text_container = $('.mojito-sinpe-payment-container');

		if ( bank === 'none' ){
			link.hide();
			text_container.hide();
			return;
		}

		var bank_numbers = window.mojito_sinpe_bank_phone_numbers || {};
		var bank_number = bank_numbers[bank] || '';

		if ( bank_number === '' ) {
			link.hide();
			text_container.hide();
			return;
		}

		if ( mojito_sinpe_show_text_after_banks_list === 'yes' ) {
			if ( type === 'mobile' ){
				var href = 'sms:+' + encodeURIComponent( bank_number ) + '?&body=' + encodeURIComponent( link.data('msj') );
				link.attr('href', href);
				link.show();
	
			}else{
				text_container.text('Envie un SMS al +' + bank_number + ' con el texto: ' + link.data('msj') );
				text_container.show();
			}
		}
	 })

})( jQuery );
