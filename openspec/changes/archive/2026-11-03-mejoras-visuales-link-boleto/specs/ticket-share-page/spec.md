## ADDED Requirements

### Requirement: Presentación igual al "Ver boleto" del panel
La página pública del link (`/boletos/{token}`) SHALL presentar el boleto con la misma estructura y estilo visual que el "Ver boleto" del panel admin. Arriba va un encabezado de página con el título y las acciones, y debajo las mismas tarjetas de resumen del viaje, pasajero y detalles de venta. La página MUST mostrar los mismos datos que hoy: ruta, fechas y horarios, colectivo, asiento, pasajero y acompañantes, N° de venta, fecha de venta, precio, método de pago y vendedor.

#### Scenario: Link de un boleto
- **WHEN** alguien abre el link de un boleto individual
- **THEN** ve el título "Boleto N°{id}", el origen → destino del boleto y las tarjetas de resumen, pasajero y detalles de venta con el mismo diseño que en el panel

#### Scenario: Link de una venta con varios pasajeros
- **WHEN** alguien abre el link de una venta con varios pasajeros
- **THEN** ve el título "Boletos de la venta N°{venta}" y un bloque por boleto, cada uno con el subtítulo "Boleto N°{id} — {pasajero}" y sus tarjetas de resumen y pasajero, más una única tarjeta de detalles de venta

#### Scenario: Diferido con vuelta asignada
- **WHEN** alguien abre el link de un boleto de ida y vuelta con la vuelta asignada
- **THEN** la tarjeta de resumen muestra la ida y la vuelta con su colectivo, ruta y asiento, igual que en el panel

#### Scenario: Link inválido o boleto borrado
- **WHEN** alguien abre un link cuyo token no existe o cuyo boleto fue borrado
- **THEN** recibe un 404

### Requirement: Acciones visibles al abrir la página
Las acciones de la página SHALL estar en el encabezado, visibles sin desplazarse, en este orden: compartir por WhatsApp (una por destinatario cuando hay varios teléfonos), descargar PDF y copiar link. El botón de WhatsApp MUST usar el ícono de WhatsApp. En pantallas chicas las acciones MUST quedar debajo del título y ocupar todo el ancho disponible.

#### Scenario: Un teléfono en la venta
- **WHEN** la venta tiene un único teléfono válido
- **THEN** el encabezado muestra "Compartir por WhatsApp", "Descargar PDF" y "Copiar link", en ese orden

#### Scenario: Varios teléfonos en la venta
- **WHEN** la venta tiene varios teléfonos válidos
- **THEN** el encabezado muestra un botón "Enviar a {nombre}" por destinatario, seguido de "Descargar PDF" y "Copiar link"

#### Scenario: Sin teléfonos
- **WHEN** la venta no tiene teléfonos válidos
- **THEN** no hay botón de WhatsApp y un texto aclara que el link solo se puede compartir copiándolo

#### Scenario: Copiar link
- **WHEN** el usuario toca "Copiar link"
- **THEN** la URL de la página queda en el portapapeles y el botón confirma "¡Copiado!" durante unos segundos

### Requirement: Tema claro y oscuro
La página SHALL verse correctamente en tema claro y en tema oscuro, sin textos ilegibles por contraste. Si el visitante no eligió un tema, la página MUST seguir el tema preferido del dispositivo y cambiar al instante cuando el dispositivo cambia de tema. La página MUST ofrecer un selector de tema (claro, oscuro, sistema) y recordar la elección del visitante en ese navegador.

#### Scenario: Dispositivo en modo oscuro
- **WHEN** un visitante sin tema elegido abre la página con el dispositivo en modo oscuro
- **THEN** la página se muestra en tema oscuro y todos los títulos y textos se leen sobre el fondo oscuro

#### Scenario: El dispositivo cambia de tema con la página abierta
- **WHEN** el visitante no eligió un tema y el dispositivo pasa de claro a oscuro con la página abierta
- **THEN** la página pasa a tema oscuro sin recargar

#### Scenario: Tema elegido manualmente
- **WHEN** el visitante elige "Claro" en el selector y vuelve a abrir la página con el dispositivo en modo oscuro
- **THEN** la página se muestra en tema claro

### Requirement: Vista previa del PDF según el dispositivo
La página SHALL mostrar la vista previa del PDF embebida cuando el navegador puede mostrar PDFs en línea. Cuando no puede, por ejemplo en la mayoría de los celulares, la página MUST mostrar en lugar del visor una tarjeta con el nombre del archivo y las acciones "Abrir PDF" y "Descargar PDF".

#### Scenario: Escritorio
- **WHEN** alguien abre la página en un navegador de escritorio con visor de PDF
- **THEN** ve la vista previa del PDF embebida debajo de las tarjetas

#### Scenario: Celular
- **WHEN** alguien abre la página en un celular sin visor de PDF integrado
- **THEN** no ve un visor en blanco: ve una tarjeta con "Abrir PDF", que abre el PDF en una pestaña, y "Descargar PDF"

### Requirement: Acceso desde el detalle del boleto en el panel
El "Ver boleto" del panel admin SHALL ofrecer la acción "Ver link", que abre en una pestaña nueva la página del link de ese boleto. La acción MUST verse solo cuando el parámetro "Descargar PDF automáticamente al vender" está desactivado y el boleto no está eliminado.

#### Scenario: Parámetro desactivado
- **WHEN** el parámetro de descarga automática está desactivado y un usuario abre el detalle de un boleto vigente
- **THEN** ve la acción "Ver link" y, al tocarla, se abre la página del link de ese boleto en una pestaña nueva

#### Scenario: Parámetro activado
- **WHEN** el parámetro de descarga automática está activado y un usuario abre el detalle de un boleto
- **THEN** no ve la acción "Ver link"

#### Scenario: Parámetro sin configurar
- **WHEN** nadie cambió el parámetro y un usuario abre el detalle de un boleto
- **THEN** no ve la acción "Ver link", como con el parámetro activado

#### Scenario: Boleto eliminado
- **WHEN** el parámetro está desactivado y un usuario abre el detalle de un boleto eliminado
- **THEN** no ve la acción "Ver link", porque el link de un boleto borrado no muestra nada
