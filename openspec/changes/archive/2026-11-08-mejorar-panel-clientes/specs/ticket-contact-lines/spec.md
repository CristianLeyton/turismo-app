# Spec Delta

## ADDED Requirements

### Requirement: Contacto por WhatsApp
Cada línea de contacto SHALL poder usarse también para escribir por WhatsApp desde el panel de clientes. El destino MUST contener solo dígitos con el código de país: si el teléfono cargado empieza con "+", se usan sus dígitos tal cual; si no, se lo trata como un número argentino de celular, sin el "0" inicial ni el "15" después del código de área, y se le antepone el prefijo "549". El destino MUST NOT cambiar el teléfono guardado ni el que muestran el boleto y la configuración.

#### Scenario: Teléfono con prefijo internacional
- **WHEN** el teléfono configurado es "+54 9 (3878) 804-506"
- **THEN** el botón de WhatsApp apunta al número 5493878804506

#### Scenario: Teléfono local
- **WHEN** el teléfono configurado es "3878-804506"
- **THEN** el botón de WhatsApp apunta al número 5493878804506

#### Scenario: Teléfono con cero inicial
- **WHEN** el teléfono configurado es "03878-804506"
- **THEN** el botón de WhatsApp apunta al número 5493878804506

#### Scenario: El teléfono guardado no cambia
- **WHEN** se genera el enlace de WhatsApp de una línea
- **THEN** el teléfono guardado y el que se ve en el PDF del boleto siguen como se cargaron

### Requirement: Líneas de contacto en el panel de clientes
El panel de clientes SHALL mostrar las líneas de contacto configuradas en "Mis boletos", con un acceso para llamar y otro para WhatsApp por cada línea. Las líneas MUST ser las mismas, en el mismo orden, que las del boleto y las del ingreso de clientes. Sin líneas configuradas, el panel MUST NOT mostrar ese bloque.

#### Scenario: Mismas líneas que el boleto
- **GIVEN** las líneas "Orán" y "Tartagal" configuradas en ese orden
- **WHEN** el cliente abre "Mis boletos"
- **THEN** ve esas dos líneas en ese orden

#### Scenario: Cambio de configuración
- **WHEN** el administrador cambia un teléfono y el cliente vuelve a abrir "Mis boletos"
- **THEN** ve el teléfono nuevo
