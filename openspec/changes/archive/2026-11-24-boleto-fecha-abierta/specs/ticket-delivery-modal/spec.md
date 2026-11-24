## MODIFIED Requirements

### Requirement: Modal de entrega al reprogramar
Con el parámetro "Descargar PDF automáticamente al vender" desactivado, al reprogramar correctamente un boleto el sistema SHALL llevar al detalle del boleto y abrir el modal de entrega. El título es "Boleto N°{id} reprogramado correctamente" y hay una sola fila, la del boleto reprogramado, con las mismas acciones y elección de número que en la venta. El modal se cierra con "Cerrar" y deja al usuario en el detalle del boleto. No se abre ninguna pestaña. Al dejar un boleto con fecha abierta MUST abrirse el mismo modal, con el título "Boleto N°{id} quedó con fecha abierta".

#### Scenario: Reprogramación con el parámetro desactivado
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** el usuario reprograma un boleto
- **THEN** llega al detalle del boleto con el modal "Boleto N°{id} reprogramado correctamente", una fila con "Enviar", "Ver PDF", "Copiar link" y "Descargar PDF", y sin el bloque "Enviar todos a un número"

#### Scenario: Reprogramación con el parámetro activado
- **GIVEN** el parámetro de descarga automática activado
- **WHEN** el usuario reprograma un boleto
- **THEN** no se abre el modal de entrega y se entrega el PDF del boleto, como hasta ahora

#### Scenario: Cerrar el modal de reprogramación
- **WHEN** el usuario toca "Cerrar" en el modal de reprogramación
- **THEN** el modal se cierra y sigue en el detalle del boleto

#### Scenario: Fecha abierta con el parámetro desactivado
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** el usuario deja el boleto N°500 con fecha abierta
- **THEN** llega al detalle del boleto con el modal "Boleto N°500 quedó con fecha abierta" y una sola fila con las mismas acciones que al reprogramar
