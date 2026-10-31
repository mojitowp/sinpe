# Mojito Sinpe

Plugin gratuito para recibir pagos manuales por SINPE Móvil en WooCommerce, con checkout clásico, bloques y HPOS.

El comercio debe comprobar la recepción del dinero. El plugin no consulta movimientos bancarios ni confirma transferencias. El comprobante y el SMS no son prueba de fondos.

## Configuración

En **WooCommerce > Ajustes > Pagos > SINPE Móvil**, habilite el método e ingrese el teléfono SINPE del comercio. Configure lista de bancos, comprobante opcional e instrucciones.

CRC usa el total del pedido sin conversión. Para USD, active una tasa manual válida o la tasa de venta de Hacienda. No se admiten otras monedas. Si Hacienda falla y no existe una tasa válida en caché, SINPE no está disponible para USD.

Se conservan configuración y pedidos existentes. Los pedidos nuevos guardan monto, moneda, tasa y destinatario; modificar total o moneda invalida las instrucciones. Para pedidos anteriores en USD sin tasa guardada, el administrador debe regenerarlas desde las acciones del pedido. Los enlaces anteriores sin clave del pedido deben reenviarse.

PHP mínimo **8.2**; recomendado **8.3 o superior**. WordPress mínimo **6.2**, WooCommerce mínimo **8.2**. Compatibilidad no significa que esas versiones antiguas reciban soporte de seguridad upstream.

- [Desarrollo, pruebas y mantenimiento](docs/maintenance.md)
- [Evidencias y pendientes de la versión](docs/qa-1.3.0.md)
- [Revisión de números bancarios](docs/bank-catalog.md)

El readme del directorio WordPress está en inglés para cumplir sus requisitos. Se incluyen catálogos PHP/JS de español de Costa Rica y España.

Los PR son bienvenidos: https://github.com/mojitowp/sinpe. No incluir números reales de clientes, comprobantes ni URLs con claves de pedidos en incidencias.
