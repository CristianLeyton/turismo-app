# ticket-contact-lines Specification

## Purpose
Permitir que boletería configure los números de contacto a los que pueden llamar los clientes y que aparezcan en todos los boletos, de forma legible y ocupando el menor espacio posible.

## Requirements

### Requirement: Configuración de las líneas de contacto
La página de configuración de boletos SHALL ofrecer la sección "Líneas de contacto", dentro de la pestaña "Boleto", a quien puede gestionar la configuración. Cada línea MUST tener un teléfono y MAY tener un nombre, por ejemplo "Orán". La sección MUST permitir como máximo 3 líneas. Sin configuración guardada no hay líneas. Al guardar, se descartan las líneas sin teléfono y se respeta el orden cargado.

#### Scenario: Guardar dos líneas
- **WHEN** el administrador carga "Orán" con "3878-804506" y "Tartagal" con "3873-309893" y guarda
- **THEN** las dos líneas quedan guardadas en ese orden y la página las muestra al recargar

#### Scenario: Máximo de líneas
- **WHEN** el administrador ya cargó 3 líneas
- **THEN** la sección no permite agregar una cuarta

#### Scenario: Línea sin nombre
- **WHEN** el administrador carga una línea solo con el teléfono "3878-804506"
- **THEN** la línea se guarda sin nombre

#### Scenario: Sin configuración
- **WHEN** nunca se guardaron líneas de contacto
- **THEN** la sección aparece vacía y no hay líneas configuradas

### Requirement: Validación del teléfono de contacto
El teléfono de una línea MUST tener entre 6 y 20 caracteres, solo dígitos, espacios, guiones, paréntesis y un "+" inicial, con al menos 6 dígitos. El nombre MUST tener como máximo 30 caracteres. Si algún dato no cumple, la configuración no se guarda y el campo muestra el error.

#### Scenario: Teléfono con letras
- **WHEN** el administrador carga el teléfono "llamar al 3878"
- **THEN** el campo muestra "Ingresá un teléfono válido (solo números, espacios, guiones, paréntesis y +)." y no se guarda nada

#### Scenario: Teléfono con formato habitual
- **WHEN** el administrador carga "+54 (3878) 804-506"
- **THEN** el teléfono se acepta y se guarda tal como se cargó, sin espacios al inicio ni al final

### Requirement: Líneas de contacto en el boleto
Todo PDF de boleto SHALL mostrar las líneas de contacto configuradas debajo del asiento, en un bloque compacto de una fila: el título "Líneas de contacto:" seguido de cada línea como "Nombre: teléfono", o solo el teléfono si no tiene nombre, separadas por " · ". El bloque MUST ser legible con un tamaño de letra menor que los datos del pasajero y MUST NOT desbordar el alto del boleto. Sin líneas configuradas, el PDF MUST NOT mostrar el bloque.

#### Scenario: Boleto con dos líneas
- **WHEN** están configuradas "Orán: 3878-804506" y "Tartagal: 3873-309893" y se genera el PDF de un boleto
- **THEN** debajo del asiento aparece "Líneas de contacto: Orán: 3878-804506 · Tartagal: 3873-309893"

#### Scenario: Línea sin nombre en el boleto
- **WHEN** una línea configurada tiene solo el teléfono "3878-804506"
- **THEN** el boleto muestra "3878-804506" sin nombre ni dos puntos

#### Scenario: Sin líneas configuradas
- **WHEN** no hay líneas de contacto configuradas
- **THEN** el PDF del boleto no muestra el bloque "Líneas de contacto"

#### Scenario: Todos los PDF del boleto
- **WHEN** se genera el PDF al vender, al reprogramar, desde el link compartido o desde el panel de clientes
- **THEN** todos muestran las mismas líneas de contacto

#### Scenario: Cambio de configuración
- **WHEN** el administrador cambia un teléfono y se vuelve a generar el PDF de un boleto ya vendido
- **THEN** el PDF muestra el teléfono nuevo

### Requirement: Teléfonos clickeables en el boleto
Cada teléfono del bloque "Líneas de contacto" del PDF SHALL ser un enlace `tel:` que, al tocarlo desde un celular, abre el marcador con ese número. El destino del enlace MUST contener solo los dígitos del teléfono, con el "+" inicial si lo tiene, sin espacios, guiones ni paréntesis. El texto visible MUST seguir siendo el teléfono tal como se cargó, en negrita y subrayado para que se reconozca como enlace. El nombre de la línea MUST NOT ser parte del enlace.

#### Scenario: Teléfono con guion
- **WHEN** la línea configurada es "Orán" con "3878-804506"
- **THEN** el PDF muestra "3878-804506" como enlace a `tel:3878804506`

#### Scenario: Teléfono con prefijo internacional
- **WHEN** el teléfono configurado es "+54 9 (3878) 804-506"
- **THEN** el enlace apunta a `tel:+5493878804506` y el texto visible queda "+54 9 (3878) 804-506"

#### Scenario: Tocar el número desde el celular
- **WHEN** el cliente abre el PDF del boleto en el celular y toca un teléfono de contacto
- **THEN** se abre el marcador del teléfono con ese número listo para llamar
