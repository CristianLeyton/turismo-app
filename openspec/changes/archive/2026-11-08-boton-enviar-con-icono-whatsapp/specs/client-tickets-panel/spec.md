# Spec Delta

## MODIFIED Requirements

### Requirement: Ayuda con las líneas de contacto
"Mis boletos" SHALL mostrar un bloque "¿Necesitás ayuda?" con las líneas de contacto configuradas en Parámetros, en el orden cargado. Cada línea MUST mostrar su nombre, si lo tiene, y su teléfono, con un botón que abre el marcador con ese número y un botón que abre una conversación de WhatsApp con ese número. Los dos botones MUST mostrar solo su ícono, y llevar "Llamar" y "WhatsApp" como título. Sin líneas configuradas, el bloque MUST NOT mostrarse.

#### Scenario: Dos líneas
- **GIVEN** las líneas "Orán: 3878-804506" y "Tartagal: 3873-309893"
- **WHEN** el cliente abre "Mis boletos"
- **THEN** ve las dos líneas, cada una con su botón de llamar y su botón de WhatsApp

#### Scenario: Sin líneas
- **GIVEN** que no hay líneas de contacto configuradas
- **WHEN** el cliente abre "Mis boletos"
- **THEN** no ve el bloque de ayuda

#### Scenario: Línea sin nombre
- **GIVEN** una línea con solo el teléfono
- **WHEN** el cliente abre "Mis boletos"
- **THEN** ve el teléfono sin nombre ni dos puntos, con sus dos botones

#### Scenario: Botones solo con ícono
- **GIVEN** una línea de contacto configurada
- **WHEN** el cliente abre "Mis boletos"
- **THEN** los botones de llamar y de WhatsApp muestran solo su ícono, con el de WhatsApp en blanco, y al pasar el cursor o con un lector de pantalla se anuncian como "Llamar" y "WhatsApp"
