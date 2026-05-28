( function() {
	'use strict';

	const settings = window.wc.wcSettings.getSetting(
		'mojito-sinpe_data',
		window.wc.wcSettings.getSetting( 'woocommerce_mojito_sinpe_gateway_data', {} )
	);

	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { createElement, useEffect, useState } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const { __, sprintf } = window.wp.i18n;

	const i18n = settings.i18n || {};
	const label = decodeEntities( settings.title || __( 'Mojito Sinpe', 'mojito-sinpe' ) );
	const banks = Array.isArray( settings.banks ) ? settings.banks : [];

	const getBankNumber = ( bankId ) => {
		const selectedBank = banks.find( ( bank ) => bank.id === bankId );

		return selectedBank && selectedBank.number ? selectedBank.number : '';
	};

	const getSmsHref = ( bankNumber ) => {
		const message = settings.message || '';

		return 'sms:+' + encodeURIComponent( bankNumber ) + '?&body=' + encodeURIComponent( message );
	};

	const getPaymentMethodData = ( bank, voucher ) => ( {
		mojito_sinpe_bank: bank || '',
		mojito_sinpe_voucher_id: voucher ? voucher.trim() : '',
	} );

	const Label = ( props = {} ) => {
		if ( props.components && props.components.PaymentMethodLabel ) {
			return createElement( props.components.PaymentMethodLabel, { text: label } );
		}

		return label;
	};

	const Content = ( props = {} ) => {
		const { eventRegistration, emitResponse } = props;
		const responseTypes = emitResponse && emitResponse.responseTypes
			? emitResponse.responseTypes
			: {
				ERROR: 'error',
				SUCCESS: 'success',
			};
		const [ bank, setBank ] = useState( settings.show_banks_list ? 'none' : '' );
		const [ voucher, setVoucher ] = useState( '' );
		const selectedBankNumber = getBankNumber( bank );

		useEffect( () => {
			if ( ! eventRegistration || ! eventRegistration.onPaymentProcessing ) {
				return undefined;
			}

			const unsubscribe = eventRegistration.onPaymentProcessing( async () => {
				if ( settings.show_banks_list && ! selectedBankNumber ) {
					return {
						type: responseTypes.ERROR,
						message: i18n.bank_required || __( 'Payment error: Please select your bank', 'mojito-sinpe' ),
					};
				}

				if ( settings.ask_voucher_id && ! voucher.trim() ) {
					return {
						type: responseTypes.ERROR,
						message: i18n.voucher_required || __( 'Payment error: Please enter your voucher id', 'mojito-sinpe' ),
					};
				}

				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: getPaymentMethodData( bank, voucher ),
					},
				};
			} );

			return () => {
				unsubscribe();
			};
		}, [
			bank,
			eventRegistration,
			responseTypes.ERROR,
			responseTypes.SUCCESS,
			selectedBankNumber,
			voucher,
		] );

		const children = [];
		const description = decodeEntities( settings.description || '' );

		if ( description ) {
			children.push(
				createElement(
					'p',
					{
						className: 'mojito-sinpe-block-description',
						key: 'description',
					},
					description
				)
			);
		}

		if ( settings.show_banks_list ) {
			children.push(
				createElement(
					'p',
					{
						className: 'mojito-sinpe-block-bank-field',
						key: 'bank',
					},
					createElement(
						'label',
						{
							htmlFor: 'mojito_sinpe_bank_block',
						},
						i18n.select_bank || __( 'Select your bank', 'mojito-sinpe' )
					),
					createElement(
						'select',
						{
							id: 'mojito_sinpe_bank_block',
							className: 'mojito_sinpe_bank_selector',
							value: bank,
							onChange: ( event ) => setBank( event.target.value ),
						},
						banks.map( ( option ) => createElement(
							'option',
							{
								key: option.id,
								value: option.id,
							},
							decodeEntities( option.label || '' )
						) )
					)
				)
			);
		}

		if ( ! settings.show_in_checkout ) {
			children.push(
				createElement(
					'p',
					{
						className: 'mojito-sinpe-block-email-note',
						key: 'email-note',
					},
					i18n.receive_link_in_email || __( 'You will receive the SINPE Payment link in the order confirmation email. Open it on your mobile.', 'mojito-sinpe' )
				)
			);
		} else if ( settings.show_text_after_banks_list === 'yes' && selectedBankNumber ) {
			const message = settings.message || '';

			children.push(
				createElement(
					'p',
					{
						className: 'mojito-sinpe-block-payment-instructions',
						key: 'instructions',
					},
					settings.is_mobile
						? createElement(
							'a',
							{
								className: 'mojito-sinpe-block-link',
								href: getSmsHref( selectedBankNumber ),
							},
							sprintf( i18n.pay_now || __( 'Pay now: %s', 'mojito-sinpe' ), settings.amount || '' )
						)
						: sprintf( i18n.send_sms || __( 'Send a SMS to %s with the content: %s', 'mojito-sinpe' ), selectedBankNumber, message )
				)
			);
		}

		if ( settings.ask_voucher_id ) {
			children.push(
				createElement(
					'p',
					{
						className: 'mojito-sinpe-block-voucher-field',
						key: 'voucher',
					},
					createElement(
						'label',
						{
							htmlFor: 'mojito_sinpe_voucher_id_block',
						},
						i18n.voucher_label || __( 'Enter your voucher ID', 'mojito-sinpe' )
					),
					createElement(
						'input',
						{
							id: 'mojito_sinpe_voucher_id_block',
							className: 'mojito_sinpe_voucher_id',
							type: 'text',
							required: true,
							value: voucher,
							placeholder: i18n.voucher_placeholder || __( 'Enter your voucher ID here', 'mojito-sinpe' ),
							onChange: ( event ) => setVoucher( event.target.value ),
						}
					)
				)
			);
		}

		return createElement(
			'div',
			{
				className: 'mojito-sinpe-block',
			},
			children
		);
	};

	registerPaymentMethod( {
		name: 'mojito-sinpe',
		label: createElement( Label, null ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: () => !! settings.enabled,
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
}() );
