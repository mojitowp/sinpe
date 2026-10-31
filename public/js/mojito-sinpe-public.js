/* global jQuery, mojitoSinpe, wp */
(function ($) {
    'use strict';
    function refresh() {
        const field = $('#mojito_sinpe_bank');
        const number = mojitoSinpe.bankNumbers[field.val()] || '';
        const box = $('.mojito-sinpe-classic-instructions');
        const link = box.find('.mojito-sinpe-link');
        const message = box.attr('data-message') || '';
        link.prop('hidden', !number).removeAttr('href');
        box.find('.mojito-sinpe-payment-container').text(number ? wp.i18n.sprintf(wp.i18n.__('Send an SMS to %1$s with the content: %2$s', 'mojito-sinpe'), number, message) : '');
        if (number) link.attr('href', 'sms:' + number + (/iPhone|iPad|iPod/i.test(navigator.userAgent) ? '&' : '?') + 'body=' + encodeURIComponent(message));
    }
    let savedBank = 'none';
    let savedVoucher = '';
    $(document.body).on('change', '#mojito_sinpe_bank', function () { savedBank = $(this).val(); refresh(); });
    $(document.body).on('input', '#mojito_sinpe_voucher_id', function () { savedVoucher = $(this).val(); });
    $(document.body).on('updated_checkout', function () { $('#mojito_sinpe_bank').val(savedBank); $('#mojito_sinpe_voucher_id').val(savedVoucher); refresh(); });
    $(document.body).on('update_checkout', function () { $('.mojito-sinpe-link').prop('hidden', true).removeAttr('href'); });
    $(refresh);
}(jQuery));
