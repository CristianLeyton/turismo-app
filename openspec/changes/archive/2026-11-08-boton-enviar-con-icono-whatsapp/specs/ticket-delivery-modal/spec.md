# Spec Delta

## MODIFIED Requirements

### Requirement: Acciones de cada boleto
Cada fila SHALL ofrecer, en este orden: "Enviar", "Ver PDF", "Copiar link" y "Descargar PDF". "Enviar" MUST mostrar el ícono de WhatsApp de la aplicación y solo el texto "Enviar", ambos en blanco. "Descargar PDF" MUST usar el color de la empresa. "Copiar link" MUST usar el color de la empresa con estilo de contorno, sin relleno. "Ver PDF" MUST abrir el link del boleto en una pestaña nueva. "Copiar link" MUST copiar el link del boleto al portapapeles y confirmar "¡Copiado!" durante unos segundos. "Descargar PDF" MUST descargar el PDF del boleto. Ninguna de las acciones cierra el modal.

#### Scenario: Ver PDF
- **WHEN** el vendedor toca "Ver PDF" en la fila de un boleto
- **THEN** se abre en una pestaña nueva el link de ese boleto, que muestra su PDF, y el modal sigue abierto

#### Scenario: Copiar link
- **WHEN** el vendedor toca "Copiar link" en la fila de un boleto
- **THEN** el link de ese boleto queda en el portapapeles, el botón muestra "¡Copiado!" y no se abre ni se descarga nada

#### Scenario: Color de "Copiar link"
- **WHEN** el vendedor ve el modal de entrega
- **THEN** "Copiar link" tiene el borde y el texto del color de la empresa, sin fondo de color

#### Scenario: Descargar PDF
- **WHEN** el vendedor toca "Descargar PDF" en la fila de un boleto
- **THEN** se descarga el PDF de ese boleto con el nombre de archivo del boleto

#### Scenario: Botón Enviar
- **WHEN** el vendedor ve el modal de entrega
- **THEN** el botón "Enviar" muestra el ícono de WhatsApp de la aplicación y el texto "Enviar", ambos en blanco y sin "por WhatsApp", y sigue siendo anunciado como "Enviar por WhatsApp" por los lectores de pantalla

### Requirement: Venta sin teléfonos
Cuando la venta no tiene ningún teléfono válido, cada fila y el envío de todos SHALL mostrar directamente el campo para escribir el número, sin selector.

#### Scenario: Ningún teléfono cargado
- **GIVEN** una venta cuyos pasajeros no tienen teléfono ni en la venta ni en el padrón
- **WHEN** se abre el modal
- **THEN** cada fila muestra el campo para escribir el número y "Enviar" envía al número escrito

### Requirement: Envío por WhatsApp de un boleto
"Enviar" SHALL abrir WhatsApp en una pestaña nueva, dirigido al número elegido, con el mensaje de WhatsApp de Parámetros y `{link}` reemplazado por el link del boleto. Un número escrito a mano MUST normalizarse igual que los de la venta: solo dígitos, sin el 0 inicial y con el código de país 54 antepuesto si no lo tiene. Si el número escrito tiene menos de 8 dígitos, el botón MUST quedar deshabilitado.

#### Scenario: Envío al teléfono preseleccionado
- **GIVEN** el mensaje de Parámetros es el de por defecto y el pasajero tiene el teléfono 3881234567
- **WHEN** el vendedor toca "Enviar" en su fila
- **THEN** se abre WhatsApp hacia 543881234567 con el texto "Podés acceder a tu(s) boleto(s) desde aquí: {link del boleto}"

#### Scenario: Número escrito con 0 inicial
- **WHEN** el vendedor escribe "03881234567" en "Otro número…" y toca "Enviar"
- **THEN** WhatsApp se abre hacia 543881234567

#### Scenario: Número incompleto
- **WHEN** el vendedor escribe "38812" en el campo del número
- **THEN** "Enviar" de esa fila queda deshabilitado

### Requirement: Enviar todos los boletos a un número
Cuando el modal tiene más de una fila, SHALL ofrecer debajo de las filas el bloque "Enviar todos a un número", con el mismo selector o campo de número que las filas y el botón "Enviar todos", con el ícono de WhatsApp de la aplicación. El botón MUST abrir un único mensaje de WhatsApp en el que `{link}` se reemplaza por un salto de línea y una línea por boleto: "{Apellido Nombre} (N°{id}): {link del boleto}". Con una sola fila, el bloque no se muestra.

#### Scenario: Dos boletos a un número
- **GIVEN** el mensaje de Parámetros es el de por defecto
- **WHEN** en el modal de una venta de Pérez Juan (N°501) y Gómez Ana (N°502) el vendedor toca "Enviar todos"
- **THEN** se abre WhatsApp hacia el número elegido con el texto "Podés acceder a tu(s) boleto(s) desde aquí:", seguido de las líneas "Pérez Juan (N°501): {link 501}" y "Gómez Ana (N°502): {link 502}"

#### Scenario: Un solo boleto
- **WHEN** se abre el modal de una venta de un pasajero
- **THEN** no aparece el bloque "Enviar todos a un número"

#### Scenario: Todos a un número escrito
- **GIVEN** una venta de dos pasajeros sin teléfonos
- **WHEN** el vendedor escribe un número en el bloque "Enviar todos a un número" y toca "Enviar todos"
- **THEN** se abre un único mensaje de WhatsApp hacia ese número con los links de los dos boletos

### Requirement: Modal de entrega al reprogramar
Con el parámetro "Descargar PDF automáticamente al vender" desactivado, al reprogramar correctamente un boleto el sistema SHALL llevar al detalle del boleto y abrir el modal de entrega. El título es "Boleto N°{id} reprogramado correctamente" y hay una sola fila, la del boleto reprogramado, con las mismas acciones y elección de número que en la venta. El modal se cierra con "Cerrar" y deja al usuario en el detalle del boleto. No se abre ninguna pestaña.

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
