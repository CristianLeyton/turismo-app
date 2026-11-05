## MODIFIED Requirements

### Requirement: Entrega de los boletos al cerrar la venta
Tras emitir correctamente, el sistema SHALL entregar los boletos según el parámetro "Descargar PDF automáticamente al vender" (activado por defecto). Activado, abre la descarga en una pestaña nueva y vuelve al inicio del panel. Desactivado, presenta la previsualización con link compartible en lugar de descargar. Con el parámetro desactivado, el vendedor MUST ver una notificación con estas acciones sobre ese link: abrir el link, copiar el link, descargar el PDF y, si la venta tiene teléfonos, compartir por WhatsApp.

#### Scenario: Descarga automática activada
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está activado
- **THEN** se abre la descarga de los boletos en una pestaña nueva y el vendedor vuelve al inicio del panel

#### Scenario: Descarga automática desactivada
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está desactivado
- **THEN** no se descarga ningún PDF y se presenta la previsualización con link compartible

#### Scenario: Parámetro sin configurar
- **WHEN** la emisión termina sin errores y nadie cambió el parámetro de descarga automática
- **THEN** se abre la descarga de los boletos en una pestaña nueva, como con el parámetro activado

#### Scenario: Notificación de la entrega por link
- **WHEN** la emisión termina sin errores con el parámetro desactivado
- **THEN** el vendedor ve una notificación de venta completada con "Abrir link", "Copiar link" y "Descargar PDF" del link entregado, y "Compartir por WhatsApp" solo si la venta tiene algún teléfono válido

#### Scenario: Copiar el link desde la notificación
- **WHEN** el vendedor toca "Copiar link" en la notificación
- **THEN** la URL del link de la venta o del boleto queda en el portapapeles, sin abrir ni descargar nada
