# Spec Delta

## MODIFIED Requirements

### Requirement: Validación del mensaje de WhatsApp
El mensaje de WhatsApp SHALL ser opcional: vacío, se usa el texto por defecto "Podés acceder a tu(s) boleto(s) desde aquí: {link}". Si se carga, MUST incluir `{link}` y tener como máximo 300 caracteres. Mientras el campo está oculto porque la descarga automática está activada, guardar MUST NOT modificar el mensaje guardado.

#### Scenario: Mensaje sin el link
- **WHEN** el administrador carga "Gracias por viajar con nosotros" y guarda
- **THEN** el campo muestra que el mensaje tiene que incluir {link} y no se guarda nada

#### Scenario: Mensaje vacío
- **WHEN** el administrador borra el mensaje y guarda
- **THEN** se guarda y al compartir se usa el texto por defecto "Podés acceder a tu(s) boleto(s) desde aquí: {link}"

#### Scenario: Campo oculto
- **GIVEN** el mensaje guardado es "Tu pasaje: {link}"
- **WHEN** el administrador activa la descarga automática del PDF y guarda
- **THEN** el mensaje guardado sigue siendo "Tu pasaje: {link}" y vuelve a verse al desactivar la descarga automática
