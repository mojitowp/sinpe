# Mantenimiento de Mojito Sinpe

## Compatibilidad y política

La versión 1.3.0 requiere PHP >=8.2, WordPress >=6.2 y WooCommerce >=8.2.
Se recomienda PHP >=8.3; PHP 8.2 termina soporte de seguridad el 31-12-2026.
La compatibilidad con versiones antiguas no garantiza su mantenimiento por sus autores.
El plugin sigue siendo gratuito y de pagos manuales; no verifica fondos ni crea comisiones del plugin.

## Desarrollo y verificación

1. Crear una rama `codex/...` desde `master` y preservar cambios ajenos.
2. `composer install`; `composer validate --strict`; `composer check`.
3. `node --test tests/blocks.test.cjs` y `node --check includes/checkout.js`.
4. Para integración, ejecutar `tools/test-integration.sh` con `WP_VERSION`, `WC_VERSION` y credenciales de una instancia **desechable** MySQL. El script crea exclusivamente `sinpe_qa_ci`; los fixtures rechazan bases cuyo nombre no empieza por `sinpe_qa_`. Nunca usar una base de tienda real.
5. Comparar versiones mínimas, intermedias y actuales con CPT y HPOS sin sincronización. El script sincroniza los datos ficticios antes de alternar el almacenamiento, no durante el caso HPOS.
6. Probar navegador en checkout clásico y bloques: banco y comprobante, cupones, cantidades, envío/impuestos, URL simple/amigable y pedido recibido. No enviar dinero ni SMS.
7. Probar teléfonos físicos Android/iPhone por separado. Una URL `sms:` correcta o un navegador de escritorio no prueba que el banco acepte el mensaje.

PHPStan usa stubs mantenidos de WordPress y WooCommerce. `tests/woocommerce-corrections.stub` corrige únicamente el tipo documentado erróneamente de `PaymentResult::set_redirect_url`: el código real de WooCommerce 8.2/11.2 espera una URL. `treatPhpDocTypesAsCertain: false` conserva las comprobaciones defensivas de objetos de WooCommerce aún no inicializados; no se usa baseline.

Los dos avisos de nonce en el adaptador clásico se documentan localmente porque WooCommerce valida el nonce antes de llamar `process_payment`. El acceso SQL acotado solo elimina por comparación una opción de bloqueo vencida; los pedidos y sus metadatos usan CRUD.

## Importes e instrucciones

- CRC no se convierte; USD requiere tasa válida y positiva. Se redondea una vez a colones enteros.
- Se conservan `mojito_sinpe_exchange_rate` y `mojito_sinpe_amount`. Los valores finales deben ser finitos y no negativos.
- La cotización del carrito se publica en `extensions["mojito-sinpe"]` de Store API. El cliente no decide el monto del pedido. Si se exige comprobante con el enlace SMS oculto, se muestra el destinatario para pagar desde la aplicación bancaria antes de completar el formulario.
- El pedido guarda `_mojito_sinpe_payment`: moneda, total original, tasa, monto CRC y destinatario. Los cambios posteriores en configuración no modifican esa cotización.
- Cambiar total/moneda invalida las instrucciones. La acción administrativa de regeneración requiere permiso de edición del pedido y la protección de WooCommerce para sus acciones. No envía un correo automáticamente: el comercio revisa y reenvía las instrucciones.
- Los pedidos CRC anteriores pueden mostrar instrucciones sin migración masiva. Para USD sin tasa histórica se exige regeneración explícita.
- Los enlaces incluyen `order` y `key`, verifican método/estado y no registran esas claves. La página no envía SMS automáticamente; requiere una acción del usuario y muestra instrucciones legibles.
- Las claves son credenciales del pedido: no incluir URLs reales de clientes en incidencias ni capturas públicas. Un servidor/proxy que registra la cadena de consulta debe ocultar `key` en sus registros de acceso.
- Hacienda se consulta por HTTP de WordPress, cinco segundos, seis horas de caché válida y un minuto de caché de error. Sin tasa válida no se sustituye por 1. Se conserva TLS. Ningún dato de pedido o cliente se envía al proveedor.

## API y extensiones existentes

Se conservan el gateway `mojito-sinpe`, la opción real `woocommerce_mojito-sinpe_settings`, el metadato `mojito_sinpe_bank`, los filtros de bancos, importes, textos y placeholder, y la acción clásica `mojito_sinpe_after_fields`.
El filtro heredado `mojito_sinpe_banks_numbers` mantiene su contrato de etiquetas pese a su nombre. `mojito_sinpe_bank_phone_numbers` permite configurar números de servicio adicionales.
La acción clásica de campos PHP no genera componentes React: las extensiones de terceros deben implementar su propia integración de bloques.
Los números bancarios se resuelven siempre en servidor a partir del ID; el cliente no puede elegir un número de servicio arbitrario.
La integración experimental Product Vendors permanece inactiva y fuera del paquete de producción.

## Traducciones y empaquetado

`wp i18n make-pot . languages/mojito-sinpe.pot --exclude=vendor,tests,tools --domain=mojito-sinpe`

`python3 tools/update-translations.py` requiere Babel (`python3-babel`). Después: `wp i18n make-php languages` y `wp i18n make-json languages --no-purge`.
Las cadenas nuevas deben traducirse en los dos catálogos; comprobar placeholders y textos de JavaScript.

`python3 tools/build.py /tmp/mojito-sinpe-1.3.0.zip` genera un ZIP con orden, permisos y fechas constantes y un manifiesto SHA-256 externo.
No necesita Composer en producción. El paquete solo contiene PHP activo, JS, imágenes, traducciones, readme y licencia GPL; no incluye `.git`, herramientas, tests ni dependencias de desarrollo.
Ejecutar Plugin Check sobre el **directorio extraído del ZIP**, instalarlo en una tienda ficticia sin `vendor`, y comparar los archivos con el manifiesto.

## Revisión periódica y publicación

Revisar mensualmente dependencias, avisos, incidencias, cambios de bancos y soporte upstream de PHP; repetir pruebas antes de cambios estables/candidatos de WordPress/WooCommerce.
GitHub Actions ejecuta la matriz al recibir PR y cambios de `master`; no es una automatización mensual ni despliega.

Antes de publicar: resolver pendientes del informe QA, comprobar números/SMS con fuentes oficiales, revisión del PR y confirmación explícita de merge/publicación. Actualizar `Tested up to` solo con evidencia funcional. Conservar el ZIP/manifiesto del candidato y comparar con el paquete público tras una publicación autorizada.

No se hace merge, auto-merge, publicación en WordPress.org ni cambios en tiendas de clientes automáticamente.

Las herramientas PHPCompatibility se fijan a versiones preliminares explícitas únicamente en `require-dev`, porque la rama estable antigua no cubre PHP 8.x suficientemente. No actualizar esas versiones sin ejecutar la matriz de calidad. El job `upcoming` prueba las betas publicadas WordPress 7.2-beta2/WooCommerce 11.3-beta1 y debe actualizarse al avanzar el ciclo de prereleases.
