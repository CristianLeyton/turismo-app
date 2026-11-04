# Tasks

## 1. Idioma español

- [x] 1.1 Verificar que esté instalado el idioma español`

## 2. Pestañas e indicador de errores

- [x] 2.1 Spike: en `TicketSettings`, envolver las secciones actuales en `Tabs` con las pestañas Venta, Boleto, Calendario y Seguridad (D1), agregar la constante `TAB_FIELDS` y el `badge()` rojo que cuenta errores, y escribir un test Livewire que llame a `save` con un teléfono inválido y verifique que el badge de Boleto muestra 1. Si falla, aplicar el plan B de design.md (Risks) antes de seguir
- [x] 2.2 Agregar `persistTabInQueryString('pestana')` y verificar con un test que el orden de las pestañas es Venta, Boleto, Calendario, Seguridad
- [x] 2.3 Implementar `onValidationError()` con la notificación "No se guardaron los cambios" que nombra las pestañas con errores (D2), y verificarlo con un test que tenga errores en Boleto y Calendario y compruebe el texto de la notificación
- [x] 2.4 Agregar un test de guardado todo o nada: horas de venta cambiadas a 8 más un teléfono inválido, y verificar que `Setting::ventaLimiteHoras()` conserva el valor anterior

## 3. Textos y título

- [x] 3.1 Cambiar "Parametros" por "Parámetros" en `$navigationLabel`, `$title` y `$heading`, y actualizar el PHPDoc de la clase para que describa la página completa. Verificar con `assertSee('Parámetros')` en el test de render
- [x] 3.2 Aplicar la tabla de textos de D3: descripciones de una oración, `hintIcon` con tooltip para la información extra y labels cortos en los toggles de contraseña. Borrar el `helperText` comentado de acompañantes. Actualizar los `assertSee` de `TicketSettingsTest` que dependen de los labels viejos y verificar que el test de render pasa con los labels nuevos
- [x] 3.3 Agregar un test que verifique que la sección de contraseña muestra su explicación una sola vez y que el texto del permiso "Vender en días sin servicio" está en el tooltip y no como texto visible

## 4. Validaciones

- [x] 4.1 Horas de venta: agregar `integer()` y los mensajes de D4. Verificar con tests que 2,5 se rechaza por `integer`, que 800 se rechaza por `max` con mensaje en español y que 0 se guarda
- [x] 4.2 Mensaje de WhatsApp: agregar `maxLength(300)` y la regla de `{link}` con mensajes. Verificar con tests que un mensaje sin `{link}` se rechaza y que uno vacío guarda el default
- [x] 4.3 Mensaje de WhatsApp oculto (D6): en `save()`, escribir el setting solo si la clave llega. Verificar con un test que, con el mensaje "Tu pasaje: {link}" guardado, activar la descarga automática y guardar lo conserva
- [x] 4.4 Teléfono de contacto: unificar el texto del error en voseo ("Ingresá un teléfono válido…") y pasar a voseo los demás mensajes de líneas. Verificar que el test `test_un_telefono_con_letras_se_rechaza_sin_guardar` sigue pasando y agregar un assert sobre el texto nuevo
- [x] 4.5 Días de la semana: `required()` con "Elegí al menos un día con servicio.". Verificar con un test que guardar sin días se rechaza y no cambia `Setting::serviceWeekdays()`
- [x] 4.6 Excepciones: `minDate(today())`, `displayFormat('d/m/Y')` y mensajes para `after_or_equal`, `required` de tipo y `max` de motivo. Verificar con tests que una fecha de ayer se rechaza y la de hoy se acepta
- [x] 4.7 Pasar los tests existentes de excepciones a fechas relativas o `Carbon::setTestNow()`, y verificar que `php artisan test --compact tests/Feature/TicketSettingsTest.php` pasa completo

## 5. Excepciones vencidas

- [x] 5.1 En `getFormData()`, cargar solo las excepciones con fecha mayor o igual a hoy. En `save()`, contar las vencidas guardadas y sumar a la notificación de éxito "Se quitó 1 excepción…" o "Se quitaron N excepciones…" (D5). Verificar con un test que una excepción guardada del pasado no hace fallar el guardado, se elimina y la notificación lo informa

## 6. Skill `filament-ui`

- [x] 6.1 Agregar a `.claude/skills/filament-ui/SKILL.md` la sección "13. Parámetros" con el contenido de D8 y un ítem en el checklist final. Verificar que los ejemplos de código de la sección coinciden con lo implementado en `TicketSettings`

## 7. Verificación integral

- [x] 7.1 Ejecutar `vendor/bin/pint --dirty --format agent` y `php artisan test --compact tests/Feature/TicketSettingsTest.php`, y verificar que no hay errores de estilo ni tests fallando
- [x] 7.2 Abrir la página en el navegador, en escritorio y en ancho de celular, guardar con errores en dos pestañas y verificar que se abre la pestaña del primer error, que los badges y la notificación aparecen y que los tooltips se abren al tocar el ícono. Avisar si hace falta `npm run build`

## Workflow follow-up

- Archivar el cambio con `/opsx:archive` después de revisar la implementación, para que los deltas de `service-days`, `ticket-contact-lines` y la nueva `settings-page` pasen a las specs principales.
