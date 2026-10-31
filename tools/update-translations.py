#!/usr/bin/env python3
"""Update the Spanish catalogs from the POT; preserve existing translations."""
from pathlib import Path
from babel.messages.pofile import read_po, write_po
from babel.messages.mofile import write_mo
from io import StringIO
import re
root = Path(__file__).resolve().parents[1]
def load(path):
    text=path.read_text()
    text=re.sub(r'(POT-Creation-Date|PO-Revision-Date): ([0-9-]+)T([0-9]{2}:[0-9]{2})(?::[0-9]{2})?([+-][0-9]{2}):?([0-9]{2})',r'\1: \2 \3\4\5',text)
    return read_po(StringIO(text))
translations={
'Manual SINPE Móvil payments for WooCommerce, including block checkout.':'Pagos manuales con SINPE Móvil para WooCommerce, incluido el checkout de bloques.',
'Mojito Sinpe Plugin requires WooCommerce to be active.':'Mojito Sinpe requiere que WooCommerce esté activo.',
'Sinpe Number':'Número SINPE',
'Enter your voucher ID here':'Escriba aquí el número de comprobante',
'Not a valid order':'El pedido no es válido',
'Manual payment using SINPE Móvil. The store must confirm receipt of funds.':'Pago manual mediante SINPE Móvil. La tienda debe confirmar la recepción del dinero.',
'SINPE instructions will appear after the cart is ready.':'Las instrucciones SINPE aparecerán cuando el carrito esté listo.',
'Select your bank':'Seleccione su banco',
'Open SMS payment instructions':'Abrir instrucciones de pago por SMS',
'Amount to send: %s CRC. The store will confirm receipt of funds.':'Monto por enviar: %s CRC. La tienda confirmará la recepción del dinero.',
'Enter your voucher ID':'Escriba el número de comprobante',
'This order is being processed. Please try again.':'Este pedido se está procesando. Inténtelo de nuevo.',
'This order cannot be paid using SINPE.':'Este pedido no se puede pagar mediante SINPE.',
'Invalid SINPE payment details.':'Los datos de pago SINPE no son válidos.',
'Payment error: Please select your bank':'Error de pago: seleccione su banco',
'Payment error: Please enter your voucher id':'Error de pago: escriba el número de comprobante',
'SINPE Voucher id: %s':'Número de comprobante SINPE: %s',
'Awaiting SINPE payment. Receipt of funds must be confirmed manually.':'En espera del pago SINPE. La recepción del dinero debe confirmarse manualmente.',
'SINPE supports CRC and USD with a valid conversion rate.':'SINPE admite CRC y USD con un tipo de cambio válido.',
'Enable a valid USD to CRC conversion rate to use SINPE.':'Active un tipo de cambio válido de USD a CRC para utilizar SINPE.',
'The SINPE exchange rate is unavailable. Please try again later.':'El tipo de cambio para SINPE no está disponible. Inténtelo más tarde.',
'The SINPE amount is invalid.':'El monto SINPE no es válido.',
'The store SINPE phone number is invalid.':'El número SINPE de la tienda no es válido.',
'The order changed. Ask the store to regenerate SINPE instructions.':'El pedido cambió. Solicite a la tienda que regenere las instrucciones SINPE.',
'Ask the store to regenerate SINPE instructions for this older USD order.':'Solicite a la tienda que regenere las instrucciones SINPE de este pedido anterior en USD.',
'Send an SMS to %1$s with the content: %2$s':'Envíe un SMS al %1$s con el texto: %2$s',
'Send %1$s CRC to SINPE phone %2$s using your banking app.':'Envíe %1$s CRC al número SINPE %2$s desde la aplicación de su banco.',
'The store must confirm receipt of funds. Do not pay again if you already sent the transfer.':'La tienda debe confirmar la recepción del dinero. No vuelva a pagar si ya realizó la transferencia.',
'Open SINPE SMS instructions on your mobile':'Abrir instrucciones SMS de SINPE en su teléfono',
'This SINPE payment link is invalid or no longer available.':'Este enlace de pago SINPE no es válido o ya no está disponible.',
'SINPE is unavailable.':'SINPE no está disponible.',
'Regenerate SINPE instructions using the current rate':'Regenerar instrucciones SINPE con el tipo de cambio actual',
'SINPE instructions regenerated using the current total, recipient and rate. Send the updated instructions to the customer.':'Instrucciones SINPE regeneradas con el total, destinatario y tipo de cambio actuales. Envíe las nuevas instrucciones al cliente.',
'Mojito Sinpe requires PHP 8.2 or newer and WooCommerce 8.2 or newer.':'Mojito Sinpe requiere PHP 8.2 o superior y WooCommerce 8.2 o superior.',
'Please wait for the SINPE amount to update.':'Espere mientras se actualiza el monto SINPE.',
'Please send us the Sinpe Móvil voucher. Use your order ID as a payment reference.':'Envíenos el comprobante de SINPE Móvil. Utilice el número de pedido como referencia de pago.',
}
template=load(root/'languages/mojito-sinpe.pot')
for locale in ('es_CR','es_ES'):
    path=root/f'languages/mojito-sinpe-{locale}.po'
    catalog=load(path)
    catalog.update(template)
    catalog.locale=locale
    for message in catalog:
        if message.id in translations:
            message.string=translations[message.id]
            message.flags.discard('fuzzy')
    with path.open('wb') as f: write_po(f,catalog,omit_header=False,sort_output=True)
    path.write_text(path.read_text().rstrip()+'\n')
    with path.with_suffix('.mo').open('wb') as f: write_mo(f,catalog,use_fuzzy=False)
