## MODIFIED Requirements

### Requirement: Acciones de cada boleto
Cada fila SHALL ofrecer, en este orden: "Enviar por WhatsApp", "Ver PDF", "Copiar link" y "Descargar PDF". "Descargar PDF" MUST usar el color de la empresa. "Copiar link" MUST usar el color de la empresa con estilo de contorno, sin relleno. "Ver PDF" MUST abrir el link del boleto en una pestaña nueva. "Copiar link" MUST copiar el link del boleto al portapapeles y confirmar "¡Copiado!" durante unos segundos. "Descargar PDF" MUST descargar el PDF del boleto. Ninguna de las acciones cierra el modal.

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
