# Spec Delta

## MODIFIED Requirements

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
