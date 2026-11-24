# Spec Delta

## ADDED Requirements

### Requirement: Contacto por WhatsApp en el ingreso de clientes
Debajo del ingreso de clientes, cuando hay líneas de contacto configuradas, SHALL mostrarse cada línea con su teléfono, que abre el marcador, y un botón que abre una conversación de WhatsApp con ese número. El botón de WhatsApp MUST mostrar solo el ícono de WhatsApp de la aplicación, en blanco, con "WhatsApp" como título. Sin líneas configuradas, el bloque MUST NOT mostrarse.

#### Scenario: Líneas en el ingreso
- **GIVEN** las líneas "Oficina Orán: +54 9 (3878) 804-506" y una sin nombre "387 555-1234"
- **WHEN** alguien abre el ingreso de clientes
- **THEN** ve cada teléfono como enlace para llamar y, al lado, un botón de WhatsApp hacia 5493878804506 y 5493875551234

#### Scenario: Botón solo con ícono
- **WHEN** alguien ve el ingreso de clientes con líneas configuradas
- **THEN** el botón de WhatsApp muestra solo el ícono y se anuncia como "WhatsApp"

#### Scenario: Sin líneas
- **GIVEN** que no hay líneas de contacto configuradas
- **WHEN** alguien abre el ingreso de clientes
- **THEN** no ve el bloque de contacto
