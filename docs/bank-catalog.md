# Catálogo bancario y verificación antes de publicación

La lista única PHP alimenta checkout clásico, bloques, correos y página de instrucciones. Se conservan los identificadores existentes y los filtros de etiquetas/números.

Revisión documental: 30 de octubre de 2026. No se enviaron SMS ni dinero. Una referencia documental no demuestra disponibilidad de la línea para todos los operadores o clientes.

| ID | Número de servicio | Evidencia / estado |
|---|---|---|
| bn | 2627 | Confirmado documentalmente por [BN SINPE Móvil](https://www.bncr.fi.cr/hubfs/BN%20SINPE%20M%C3%B3vil.pdf?hsLang=es-cr) y [contrato BN](https://www.bncr.fi.cr/hubfs/Contrato%20Universal%20F.pdf?hsLang=es-cr). |
| bcr | 4066 | Referencia oficial de [BCR Contact Center](https://somos.bancobcr.com/blogs/dfaee4d9-cb6c-4dca-963b-a77b0945ebf3?lang=es&maxresults=25&sortby=0&tags=sinpe_movil); falta comprobación física del comando. |
| bac | 70701222 | Confirmado en [ayuda oficial BAC](https://ayuda.baccredomatic.com/transferencias_bancarias/enviar_dinero/como-funciona-sinpe-movil-de-bac). Corrige la discrepancia antigua del JS 70701212. |
| bct | 60400300 | Conservado del catálogo anterior; confirmación oficial actual pendiente. |
| caja-de-ande | 62229532 | Conservado; confirmación oficial actual pendiente. |
| coopealianza | 62229523 | Conservado; confirmación oficial actual pendiente. |
| coopecaja | 62229526 | Conservado; confirmación oficial actual pendiente. |
| coopelecheros | 60405957 | Conservado; confirmación oficial actual pendiente. |
| coocique | 46002905 | Conservado; confirmación oficial actual pendiente. |
| credecoop | 71984256 | Conservado; confirmación de entidad/canal actual pendiente. |
| davivienda | 70707474 | Conservado; confirmación de entidad/canal actual pendiente. |
| lafise | 9091 | Conservado; confirmación oficial actual pendiente. |
| mucap | 62229525 | Conservado; confirmación oficial actual pendiente. |
| mutual-alajuela | 60575079 | Conservado; confirmación oficial actual pendiente. |
| promerica | 62232450 | Conservado; confirmación oficial actual pendiente. |

Antes de publicar, completar las referencias pendientes y validar la sintaxis SMS admitida por cada entidad. Si una entidad ya no ofrece SMS, actualizar su entrada y ofrecer instrucciones de aplicación bancaria; no inventar un número sustituto ni borrar metadatos históricos.

El [BCCR](https://www.bccr.fi.cr/cr/es/noticias/listado-de-noticias/2025/usuarios-de-sinpe-movil-que-cambien-su-numero-de-telefono-celular-deben-reportarlo-a-su-entidad-fina.html) distingue el canal SMS, opcional y sujeto a autorización del cliente, de otros canales SINPE. El plugin no puede garantizar la afiliación de cada cliente a ese canal.
