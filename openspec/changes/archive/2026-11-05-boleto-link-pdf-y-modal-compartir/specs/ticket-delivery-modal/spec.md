# Spec Delta

## Purpose

Modal con el que el vendedor entrega los boletos por link al cerrar una venta o una reprogramación, con la descarga automática del PDF desactivada. Confirma la operación y deja enviar, ver, copiar o descargar cada boleto, o mandar todos juntos a un número, sin salir de la pantalla.

## ADDED Requirements

### Requirement: Modal de entrega al cerrar la venta
Con el parámetro "Descargar PDF automáticamente al vender" desactivado, al emitir correctamente una venta el sistema SHALL abrir un modal de entrega en la pantalla de venta. El modal MUST tener el título "Venta N°{venta} realizada correctamente" y el texto "Se vendió 1 boleto." o "Se vendieron {n} boletos.", donde n es la cantidad de pasajeros. No se abre ninguna pestaña ni se muestra una notificación con acciones.

#### Scenario: Venta de dos pasajeros
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** el vendedor finaliza una venta de dos pasajeros
- **THEN** ve el modal "Venta N°{venta} realizada correctamente" con el texto "Se vendieron 2 boletos." y no se abre ninguna pestaña nueva

#### Scenario: Venta de un pasajero
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** el vendedor finaliza una venta de un pasajero
- **THEN** el modal dice "Se vendió 1 boleto."

#### Scenario: Descarga automática activada
- **GIVEN** el parámetro de descarga automática activado o sin configurar
- **WHEN** el vendedor finaliza una venta
- **THEN** no se abre el modal de entrega y la venta se entrega con la descarga del PDF, como hasta ahora

### Requirement: Una fila por boleto entregado
El modal SHALL mostrar una fila por pasajero de la venta, con el título "Boleto N°{id} — {Apellido Nombre}" y el origen → destino del boleto. Cada fila corresponde al boleto de ida del pasajero. En un diferido, el link de la fila MUST ser el del boleto de ida, cuyo PDF incluye la ida y la vuelta. El modal MUST NOT mostrar filas propias para los boletos de vuelta.

#### Scenario: Diferido de dos pasajeros
- **WHEN** se cierra una venta diferida de dos pasajeros
- **THEN** el modal muestra dos filas, una por cada boleto de ida, y el PDF de cada fila contiene la ida y la vuelta de ese pasajero

#### Scenario: Venta sólo de ida
- **WHEN** se cierra una venta sólo de ida de tres pasajeros
- **THEN** el modal muestra tres filas, una por boleto

### Requirement: Acciones de cada boleto
Cada fila SHALL ofrecer, en este orden: "Enviar por WhatsApp", "Ver PDF", "Copiar link" y "Descargar PDF". "Descargar PDF" MUST usar el color de la empresa y "Copiar link" el color informativo. "Ver PDF" MUST abrir el link del boleto en una pestaña nueva. "Copiar link" MUST copiar el link del boleto al portapapeles y confirmar "¡Copiado!" durante unos segundos. "Descargar PDF" MUST descargar el PDF del boleto. Ninguna de las acciones cierra el modal.

#### Scenario: Ver PDF
- **WHEN** el vendedor toca "Ver PDF" en la fila de un boleto
- **THEN** se abre en una pestaña nueva el link de ese boleto, que muestra su PDF, y el modal sigue abierto

#### Scenario: Copiar link
- **WHEN** el vendedor toca "Copiar link" en la fila de un boleto
- **THEN** el link de ese boleto queda en el portapapeles, el botón muestra "¡Copiado!" y no se abre ni se descarga nada

#### Scenario: Descargar PDF
- **WHEN** el vendedor toca "Descargar PDF" en la fila de un boleto
- **THEN** se descarga el PDF de ese boleto con el nombre de archivo del boleto

### Requirement: Número de WhatsApp de cada boleto
Cada fila SHALL tener un selector "Enviar a" con los teléfonos de la venta: el de cada pasajero y el que el padrón tenga por su DNI, sin repetir números. Cada opción se muestra como "{nombre} — {teléfono}". Viene preseleccionado el teléfono del pasajero de la fila, si tiene, o si no el primero de la venta. La última opción, "Otro número…", MUST mostrar un campo para escribir el número.

#### Scenario: Pasajero con teléfono
- **GIVEN** una venta de dos pasajeros, cada uno con su teléfono
- **WHEN** se abre el modal
- **THEN** cada fila tiene preseleccionado el teléfono de su pasajero y el selector ofrece los dos teléfonos y "Otro número…"

#### Scenario: Pasajero sin teléfono
- **GIVEN** una venta de dos pasajeros en la que solo el primero tiene teléfono
- **WHEN** se abre el modal
- **THEN** la fila del segundo pasajero tiene preseleccionado el teléfono del primero

#### Scenario: Otro número
- **WHEN** el vendedor elige "Otro número…" en una fila
- **THEN** aparece un campo para escribir el número y el envío de esa fila usa el número escrito

#### Scenario: Teléfono repetido
- **GIVEN** un pasajero con el mismo teléfono cargado en la venta y en el padrón
- **WHEN** se abre el modal
- **THEN** el selector muestra ese número una sola vez

### Requirement: Venta sin teléfonos
Cuando la venta no tiene ningún teléfono válido, cada fila y el envío de todos SHALL mostrar directamente el campo para escribir el número, sin selector.

#### Scenario: Ningún teléfono cargado
- **GIVEN** una venta cuyos pasajeros no tienen teléfono ni en la venta ni en el padrón
- **WHEN** se abre el modal
- **THEN** cada fila muestra el campo para escribir el número y "Enviar por WhatsApp" envía al número escrito

### Requirement: Envío por WhatsApp de un boleto
"Enviar por WhatsApp" SHALL abrir WhatsApp en una pestaña nueva, dirigido al número elegido, con el mensaje de WhatsApp de Parámetros y `{link}` reemplazado por el link del boleto. Un número escrito a mano MUST normalizarse igual que los de la venta: solo dígitos, sin el 0 inicial y con el código de país 54 antepuesto si no lo tiene. Si el número escrito tiene menos de 8 dígitos, el botón MUST quedar deshabilitado.

#### Scenario: Envío al teléfono preseleccionado
- **GIVEN** el mensaje de Parámetros es el de por defecto y el pasajero tiene el teléfono 3881234567
- **WHEN** el vendedor toca "Enviar por WhatsApp" en su fila
- **THEN** se abre WhatsApp hacia 543881234567 con el texto "Podés acceder a tu(s) boleto(s) desde aquí: {link del boleto}"

#### Scenario: Número escrito con 0 inicial
- **WHEN** el vendedor escribe "03881234567" en "Otro número…" y toca "Enviar por WhatsApp"
- **THEN** WhatsApp se abre hacia 543881234567

#### Scenario: Número incompleto
- **WHEN** el vendedor escribe "38812" en el campo del número
- **THEN** "Enviar por WhatsApp" de esa fila queda deshabilitado

### Requirement: Enviar todos los boletos a un número
Cuando el modal tiene más de una fila, SHALL ofrecer debajo de las filas el bloque "Enviar todos a un número", con el mismo selector o campo de número que las filas y el botón "Enviar todos por WhatsApp". El botón MUST abrir un único mensaje de WhatsApp en el que `{link}` se reemplaza por un salto de línea y una línea por boleto: "{Apellido Nombre} (N°{id}): {link del boleto}". Con una sola fila, el bloque no se muestra.

#### Scenario: Dos boletos a un número
- **GIVEN** el mensaje de Parámetros es el de por defecto
- **WHEN** en el modal de una venta de Pérez Juan (N°501) y Gómez Ana (N°502) el vendedor toca "Enviar todos por WhatsApp"
- **THEN** se abre WhatsApp hacia el número elegido con el texto "Podés acceder a tu(s) boleto(s) desde aquí:", seguido de las líneas "Pérez Juan (N°501): {link 501}" y "Gómez Ana (N°502): {link 502}"

#### Scenario: Un solo boleto
- **WHEN** se abre el modal de una venta de un pasajero
- **THEN** no aparece el bloque "Enviar todos a un número"

#### Scenario: Todos a un número escrito
- **GIVEN** una venta de dos pasajeros sin teléfonos
- **WHEN** el vendedor escribe un número en el bloque "Enviar todos a un número" y toca "Enviar todos por WhatsApp"
- **THEN** se abre un único mensaje de WhatsApp hacia ese número con los links de los dos boletos

### Requirement: Salidas del modal de venta
El modal de entrega de una venta SHALL cerrarse con "Nueva venta", que deja la pantalla de venta lista para una venta nueva, o con "Ir al inicio", que lleva al inicio del panel. El modal de entrega, de venta o de reprogramación, MUST cerrarse solo con los botones de su pie o con la X, que equivale a "Nueva venta" en la venta y a "Cerrar" en la reprogramación. No se cierra con Escape ni tocando afuera. El modal MUST NOT volver a abrirse al recargar la página.

#### Scenario: Nueva venta
- **WHEN** el vendedor toca "Nueva venta"
- **THEN** el modal se cierra y la pantalla de venta queda en el primer paso, vacía

#### Scenario: Ir al inicio
- **WHEN** el vendedor toca "Ir al inicio"
- **THEN** se lo lleva al inicio del panel

#### Scenario: Cerrar con la X
- **WHEN** el vendedor toca la X del modal de una venta
- **THEN** el modal se cierra y la pantalla de venta queda vacía, como con "Nueva venta"

#### Scenario: Click sin querer afuera del modal
- **WHEN** el vendedor toca afuera del modal o presiona Escape
- **THEN** el modal sigue abierto

#### Scenario: Recargar después de cerrar
- **WHEN** el vendedor cierra el modal y recarga la página de venta
- **THEN** el modal no vuelve a aparecer

### Requirement: Modal de entrega al reprogramar
Con el parámetro "Descargar PDF automáticamente al vender" desactivado, al reprogramar correctamente un boleto el sistema SHALL llevar al detalle del boleto y abrir el modal de entrega. El título es "Boleto N°{id} reprogramado correctamente" y hay una sola fila, la del boleto reprogramado, con las mismas acciones y elección de número que en la venta. El modal se cierra con "Cerrar" y deja al usuario en el detalle del boleto. No se abre ninguna pestaña.

#### Scenario: Reprogramación con el parámetro desactivado
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** el usuario reprograma un boleto
- **THEN** llega al detalle del boleto con el modal "Boleto N°{id} reprogramado correctamente", una fila con "Enviar por WhatsApp", "Ver PDF", "Copiar link" y "Descargar PDF", y sin el bloque "Enviar todos a un número"

#### Scenario: Reprogramación con el parámetro activado
- **GIVEN** el parámetro de descarga automática activado
- **WHEN** el usuario reprograma un boleto
- **THEN** no se abre el modal de entrega y se entrega el PDF del boleto, como hasta ahora

#### Scenario: Cerrar el modal de reprogramación
- **WHEN** el usuario toca "Cerrar" en el modal de reprogramación
- **THEN** el modal se cierra y sigue en el detalle del boleto
