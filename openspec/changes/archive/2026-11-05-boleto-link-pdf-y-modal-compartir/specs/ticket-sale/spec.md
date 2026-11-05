# Spec Delta

## MODIFIED Requirements

### Requirement: Cierre de una venta exitosa
Tras emitir correctamente, el sistema SHALL liberar las reservas temporales de asientos de la sesión del vendedor en los viajes de esa venta.

#### Scenario: Venta completada con descarga automática
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está activado
- **THEN** las reservas de la sesión en los viajes de la venta se eliminan y se abre la descarga de los boletos en otra pestaña

#### Scenario: Venta completada sin descarga automática
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está desactivado
- **THEN** las reservas de la sesión en los viajes de la venta se eliminan, no se descarga ningún PDF y se abre el modal de entrega

#### Scenario: Falla la generación del PDF
- **WHEN** la emisión se confirmó pero falla la generación del PDF
- **THEN** la venta y sus boletos quedan emitidos y el vendedor ve que no se pudo generar el PDF

#### Scenario: Otra venta en curso
- **WHEN** el vendedor emite una venta mientras tiene en otra pestaña una venta con reservas en un viaje distinto
- **THEN** las reservas de la otra venta se conservan

### Requirement: Entrega de los boletos al cerrar la venta
Tras emitir correctamente, el sistema SHALL entregar los boletos según el parámetro "Descargar PDF automáticamente al vender" (activado por defecto). Activado, abre la descarga en una pestaña nueva y vuelve al inicio del panel. Desactivado, se queda en la pantalla de venta y abre el modal de entrega con el link de cada boleto, sin abrir pestañas.

#### Scenario: Descarga automática activada
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está activado
- **THEN** se abre la descarga de los boletos en una pestaña nueva y el vendedor vuelve al inicio del panel

#### Scenario: Descarga automática desactivada
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está desactivado
- **THEN** no se descarga ningún PDF, no se abre ninguna pestaña y el vendedor ve el modal de entrega en la pantalla de venta

#### Scenario: Parámetro sin configurar
- **WHEN** la emisión termina sin errores y nadie cambió el parámetro de descarga automática
- **THEN** se abre la descarga de los boletos en una pestaña nueva, como con el parámetro activado

### Requirement: Falla de la entrega sin revertir la venta
La entrega de los boletos MUST ocurrir después de confirmada la emisión. Si la entrega falla, la venta y sus boletos siguen emitidos y el vendedor ve el error de la entrega.

#### Scenario: Falla la generación del PDF al entregar
- **WHEN** la emisión se confirmó pero falla la generación del PDF de los boletos
- **THEN** la venta y sus boletos quedan emitidos y el vendedor ve que no se pudo generar el PDF

#### Scenario: Falla al preparar los links
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** la emisión se confirmó pero falla al preparar los links de los boletos
- **THEN** la venta y sus boletos quedan emitidos, no se abre el modal de entrega y el vendedor ve un aviso de que puede descargar el PDF desde el detalle del boleto

## REMOVED Requirements

### Requirement: Notificación de la entrega por link
**Reason**: La notificación con acciones se reemplaza por el modal de entrega (capacidad `ticket-delivery-modal`). El modal confirma la venta y ofrece las acciones por boleto y el envío de todos a un número.
**Migration**: "Abrir link" pasa a ser "Ver PDF" en cada fila del modal. "Copiar link", "Descargar PDF" y "Enviar por WhatsApp" pasan a cada fila del modal.
