/* global wc, wp */
(function () {
    'use strict';
    const settings = wc.wcSettings.getSetting('mojito-sinpe_data', {});
    const {createElement: h, useEffect, useState} = wp.element;
    const {__, sprintf} = wp.i18n;
    const label = wp.htmlEntities.decodeEntities(settings.title || __('SINPE Móvil', 'mojito-sinpe'));
    const banks = settings.banks || [];
    const smsHref = (number, message) => 'sms:' + number + (/iPhone|iPad|iPod/i.test(navigator.userAgent) ? '&' : '?') + 'body=' + encodeURIComponent(message);
    const Content = (props) => {
        const [bank, setBank] = useState('none');
        const [voucher, setVoucher] = useState('');
        const data = (props.cartData?.extensions || {})['mojito-sinpe'] || {};
        const current = !!data.available && Number(props.billing?.cartTotal?.value) === Number(data.cart_total);
        const calculating = props.checkoutStatus?.isCalculating || !current;
        const selected = banks.find((item) => item.id === bank);
        const register = props.eventRegistration?.onPaymentSetup || props.eventRegistration?.onPaymentProcessing;
        const types = props.emitResponse?.responseTypes;
        useEffect(() => {
            if (!register || !types) return undefined;
            return register(() => {
                let error = '';
                if (calculating) error = data.error || __('Please wait for the SINPE amount to update.', 'mojito-sinpe');
                else if (settings.showBanks && !selected?.number) error = __('Payment error: Please select your bank', 'mojito-sinpe');
                else if (settings.askVoucher && !voucher.trim()) error = __('Payment error: Please enter your voucher id', 'mojito-sinpe');
                if (error) return {type: types.ERROR, message: error};
                return {type: types.SUCCESS, meta: {paymentMethodData: {mojito_sinpe_bank: settings.showBanks ? bank : '', mojito_sinpe_voucher_id: voucher.trim()}}};
            });
        }, [register, types, bank, voucher, calculating, data.error, selected]);
        const children = [h('p', {key: 'description'}, wp.htmlEntities.decodeEntities(settings.description || ''))];
        if (settings.showBanks) children.push(h('p', {key: 'bank'},
            h('label', {htmlFor: 'mojito-sinpe-bank-block'}, __('Select your bank', 'mojito-sinpe')),
            h('select', {id: 'mojito-sinpe-bank-block', value: bank, onChange: (event) => setBank(event.target.value)},
                banks.map((item) => h('option', {key: item.id, value: item.id}, item.label)))));
        if (settings.askVoucher) children.push(h('p', {key: 'voucher'},
            h('label', {htmlFor: 'mojito-sinpe-voucher-block'}, __('Enter your voucher ID', 'mojito-sinpe')),
            h('input', {id: 'mojito-sinpe-voucher-block', value: voucher, maxLength: 100, required: true, placeholder: settings.voucherPlaceholder, onChange: (event) => setVoucher(event.target.value)})));
        children.push(h('p', {key: 'amount', 'aria-live': 'polite'}, calculating
            ? (data.error || __('SINPE instructions will appear after the cart is ready.', 'mojito-sinpe'))
            : sprintf(__('Amount to send: %s CRC. The store will confirm receipt of funds.', 'mojito-sinpe'), data.amount)));
        if (!calculating && !settings.showLink && settings.askVoucher && data.recipient) {
            children.push(h('p', {key: 'manual'}, sprintf(__('Send %1$s CRC to SINPE phone %2$s using your banking app.', 'mojito-sinpe'), data.amount, data.recipient)));
        }
        if (!calculating && settings.showLink && selected?.number) {
            children.push(h('p', {key: 'instructions'}, sprintf(__('Send an SMS to %1$s with the content: %2$s', 'mojito-sinpe'), selected.number, data.message)));
            children.push(h('a', {key: 'sms', href: smsHref(selected.number, data.message)}, __('Open SMS payment instructions', 'mojito-sinpe')));
        }
        return h('div', {className: 'mojito-sinpe-block', 'aria-busy': !!calculating}, children);
    };
    wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'mojito-sinpe',
        label: h('span', null, label),
        content: h(Content),
        edit: h('div', null, h('p', null, label), h('p', null, settings.description || ''),
            settings.showBanks ? h('select', {disabled: true, 'aria-label': __('Select your bank', 'mojito-sinpe')}, banks.map((item) => h('option', {key: item.id}, item.label))) : null,
            settings.askVoucher ? h('input', {disabled: true, placeholder: settings.voucherPlaceholder, 'aria-label': __('Enter your voucher ID', 'mojito-sinpe')}) : null),
        canMakePayment: ({cart}) => !!cart?.extensions?.['mojito-sinpe']?.available,
        ariaLabel: label,
        supports: {features: settings.supports || ['products']}
    });
}());
