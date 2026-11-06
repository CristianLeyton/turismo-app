## MODIFIED Requirements

### Requirement: Copiar el link desde la vista previa
Con el parámetro "Descargar PDF automáticamente al vender" desactivado y el boleto vigente, la sección "Vista previa" SHALL ofrecer "Copiar link", que copia al portapapeles el link público del boleto, el mismo que se envía por WhatsApp, y confirma "¡Copiado!" durante unos segundos. Con el visor embebido, el botón va en el encabezado de la sección, en la esquina opuesta al título. Con la tarjeta, va en la tarjeta. En los dos lugares, el botón MUST usar el color de la empresa con estilo de contorno, sin relleno.

#### Scenario: Escritorio
- **GIVEN** el parámetro desactivado y un boleto vigente
- **WHEN** el usuario abre el detalle en un navegador de escritorio con visor de PDF
- **THEN** ve "Copiar link" a la altura del título "Vista previa", en la otra esquina, y al tocarlo el link del boleto queda en el portapapeles

#### Scenario: Celular
- **GIVEN** el parámetro desactivado y un boleto vigente
- **WHEN** el usuario abre el detalle en un celular
- **THEN** ve la tarjeta con "Abrir PDF" y "Copiar link"

#### Scenario: Color del botón
- **GIVEN** el parámetro desactivado y un boleto vigente
- **WHEN** el usuario abre el detalle
- **THEN** "Copiar link" tiene el borde y el texto del color de la empresa, sin fondo de color

#### Scenario: Descarga automática activada
- **WHEN** el parámetro está activado o sin configurar
- **THEN** la vista previa no muestra "Copiar link"
