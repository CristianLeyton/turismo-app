# brand-color Specification

## Purpose

Define el color principal de la empresa que usa la instalación, la paleta que se genera a partir de él y dónde se aplica (paneles, link público del boleto y PDF), para que cada empresa vea su color de marca en todo el sistema sin tocar el código.

## Requirements

### Requirement: Color principal de la instalación
La instalación SHALL tener un único color principal, guardado como un color hex de 6 dígitos (`#RRGGBB`). Sin configurar, el color principal MUST ser el fucsia actual (`#c026d3`), de modo que una instalación existente se vea igual que antes del cambio.

#### Scenario: Instalación sin configurar
- **WHEN** nadie configuró el color principal y un usuario abre el panel de administración
- **THEN** los botones primarios se ven fucsia, igual que antes del cambio

#### Scenario: Color configurado
- **GIVEN** el color principal guardado es `#10288c`
- **WHEN** un usuario abre cualquier página del panel de administración
- **THEN** los botones primarios se ven en `#10288c`

### Requirement: Paleta generada desde el color principal
A partir del color principal, el sistema SHALL generar una paleta de once escalones (50, 100, 200, 300, 400, 500, 600, 700, 800, 900 y 950), ordenada del más claro al más oscuro. El escalón 600 MUST ser exactamente el color principal; los escalones menores MUST ser progresivamente más claros y los mayores progresivamente más oscuros, conservando el tono del color. Un color sin saturación (gris) MUST generar una paleta gris.

#### Scenario: Color exacto en el escalón 600
- **WHEN** el color principal es `#10288c`
- **THEN** el escalón 600 de la paleta es `#10288c`

#### Scenario: Escalones ordenados por luminosidad
- **WHEN** se genera la paleta de cualquier color válido que no sea negro ni blanco puros
- **THEN** cada escalón es más claro que el siguiente, del 50 al 950

#### Scenario: Color oscuro
- **WHEN** el color principal es muy oscuro, como `#0a0a2a`
- **THEN** la paleta sigue teniendo once escalones distintos y ordenados, y el escalón 50 es un tono casi blanco

### Requirement: El color principal se aplica en todo el sitio
El color principal SHALL aplicarse, sin recompilar estilos, en los dos paneles, en el modal de entrega de boletos y en los PDFs del sistema (boleto, ventas, viaje y pagos), incluido el PDF que muestra el link público del boleto. Todo elemento que hoy usa el color de marca (fucsia) MUST tomar el escalón correspondiente de la paleta.

#### Scenario: Cambio inmediato en los paneles
- **WHEN** un usuario con permiso cambia el color principal a `#0e7490` y guarda
- **THEN** al recargar, el panel de administración y el panel de clientes usan la nueva paleta, sin compilar estilos

#### Scenario: Página pública del boleto
- **GIVEN** el color principal guardado es `#0e7490`
- **WHEN** un pasajero abre el link de su boleto
- **THEN** ve el PDF del boleto con los acentos de marca en la nueva paleta y no en fucsia

#### Scenario: PDF del boleto
- **GIVEN** el color principal guardado es `#0e7490`
- **WHEN** se genera el PDF de un boleto
- **THEN** los acentos de marca del PDF usan colores de la nueva paleta y el PDF no contiene los colores fucsia anteriores

#### Scenario: Asiento seleccionado
- **GIVEN** el color principal guardado es `#0e7490`
- **WHEN** el vendedor selecciona un asiento al vender
- **THEN** el asiento y su referencia "Seleccionado" se ven en un tono más oscuro de la paleta de la empresa, distinto del color principal

#### Scenario: Modo oscuro
- **WHEN** un usuario con el modo oscuro activo abre el panel después de cambiar el color principal
- **THEN** los acentos usan la nueva paleta en sus escalones de modo oscuro

### Requirement: Colores de estado y neutros independientes
Los colores que indican un estado (error, éxito, aviso, información, asiento ocupado o reservado), los que distinguen categorías (por ejemplo, las áreas del diseñador de colectivos) y los grises neutros MUST NOT cambiar con el color principal.

#### Scenario: Colores de estado
- **WHEN** el color principal cambia
- **THEN** los mensajes de error siguen en rojo y los estados de pagado o confirmado siguen en verde

### Requirement: Texto legible sobre el color principal
El texto y los íconos que se muestran sobre un fondo del color principal (por ejemplo, el logo y los botones primarios) SHALL ser blancos cuando el contraste con el color principal sea suficiente, y oscuros en caso contrario, de modo que el texto nunca quede ilegible por un color principal claro. Guardar un color claro MUST NOT rechazarse por su contraste.

#### Scenario: Color oscuro
- **WHEN** el color principal es `#10288c`
- **THEN** el texto del logo y de los botones primarios es blanco

#### Scenario: Color claro
- **WHEN** el color principal es `#fde047` (amarillo claro) y se guarda
- **THEN** el color se guarda sin error y el texto del logo y de los botones primarios es oscuro

### Requirement: Sin colores de marca fijos en las vistas
Las vistas del sistema MUST NOT usar colores de marca fijos (las clases de color fucsia ni los hex de la paleta fucsia anterior); todo color de marca MUST salir de la paleta del color principal.

#### Scenario: Color de marca fijo nuevo
- **WHEN** una vista nueva o modificada usa una clase `text-fuchsia-600` o el hex `#c026d3`
- **THEN** la verificación automática de colores de marca falla y señala el archivo
