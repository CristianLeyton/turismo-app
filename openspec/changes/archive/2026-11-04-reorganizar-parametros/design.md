# Design

## Context

Todo vive en `app/Filament/Pages/TicketSettings.php`. Es una página con un formulario de seis `Section` apiladas, guardado manual en `save()` y lectura con los helpers de `App\Models\Setting`. La motivación está en proposal.md (Why) y los requisitos en `specs/`.

Lo que se comprobó en el código antes de diseñar:

- **Pestañas y errores (Filament v4):** cuando la validación falla, el formulario dispara `form-validation-error`. El JS de schemas busca el primer `[data-validation-error]`, manda `expand` a todos sus contenedores y desplaza hasta el campo. La `Tab` escucha `expand` y se activa (`tabs/tab.blade.php`). No hay ningún indicador para las demás pestañas con errores.
- **Gancho de validación:** `InteractsWithSchemas::validate()` llama a `onValidationError()` antes de volver a lanzar la excepción. `CreateTicket` ya lo usa para notificar.
- **Idioma:** `APP_LOCALE=es` pero no existe `lang/`. Toda regla sin `validationMessages()` muestra el mensaje de Laravel en inglés.
- **Campo oculto:** `whatsapp_mensaje` tiene `visible()` dependiente de `descarga_automatica_pdf`. Los campos ocultos no forman parte de `getState()`, así que hoy `save()` recibe la clave ausente y guarda `null`: **se pierde el mensaje cargado** cada vez que se guarda con la descarga automática activada.
- **Tests con fechas fijas:** `TicketSettingsTest` usa fechas como 13/09/2026, que ya son pasadas y van a chocar con la nueva regla de fecha mínima.

## Goals / Non-Goals

**Goals:**
- Que la estructura de la página (pestañas, niveles de texto, validación) sirva de molde para los parámetros futuros, y que quede escrita en el skill `filament-ui`.
- Que un mapa único `pestaña → campos` alimente el badge de errores y la notificación, para que no se desincronicen.

**Non-Goals:**
- Renombrar la clase `TicketSettings` o su ruta (`/ticket-settings`). El nombre de clase quedó corto, pero renombrarla rompe enlaces y tests sin beneficio para el usuario.
- Cambiar cómo se guardan los settings (`Setting::set`) o sus claves.
- Revisar los mensajes de validación de otros formularios del panel. El paquete de idiomas los cubre con mensajes en español neutro, y ajustarlos al voseo queda para cuando se toque cada formulario.

## Decisions

### D1. `Tabs` con pestañas fijas y un mapa de campos

```
Tabs::make('parametros')
  ->persistTabInQueryString('pestana')   // al recargar sigue en la misma pestaña
  ->tabs([
      Tab::make('Venta')->icon(...)->badge(fn) ->badgeColor('danger'),
      Tab::make('Boleto')     ...
      Tab::make('Calendario') ...
      Tab::make('Seguridad')  ...
  ])
```

Una constante `TAB_FIELDS` (array `pestaña => list<prefijo de campo>`) define qué campos tiene cada pestaña. A partir de ella:

- `badge()` cuenta los errores de `$livewire->getErrorBag()` cuyas claves empiezan con `data.{prefijo}`. Si no hay errores, devuelve `null` y el badge no se muestra.
- `onValidationError()` arma la lista de pestañas con errores para la notificación.

*Alternativas:* (a) secciones `aside()`, que en el celular siguen siendo una página larga, como se descartó en la exploración; (b) contar los errores recorriendo el schema en lugar de usar un mapa, que es más automático pero depende de la estructura interna de Filament. Con cuatro pestañas, el mapa es explícito y fácil de testear.

### D2. Notificación de guardado fallido

En `onValidationError()`: `Notification::make()->danger()->title('No se guardaron los cambios')->body('Revisá los campos marcados en Boleto y Calendario.')`. Se usa "en {pestaña}" para una y "en A y B" o "en A, B y C" para varias. No es persistente, porque los campos quedan marcados igual.

### D3. Niveles de texto

| Nivel | Componente | Regla |
|---|---|---|
| Visible | `Section::description()` / `helperText()` | Una oración. Solo si, sin ella, se configura mal. |
| Hint | `->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: '…')` en el campo | Información extra, nunca necesaria. |
| Nada | — | Repite lo que dicen el label o el placeholder. |

Las secciones no tienen hint propio. La información complementaria de una sección va en el hint de su primer campo.

Textos acordados (la redacción final puede pulirse en la implementación, manteniendo el nivel):

| Campo | Visible | Hint |
|---|---|---|
| Sección Confirmación de contraseña | "Se pide la contraseña de quien hace la acción. Si no coincide, la acción no se ejecuta." | — |
| Toggles de contraseña | Labels "Al borrar boletos", "Al reprogramar boletos", "Al editar boletos". Sin helperText. | — |
| Vender con acompañante | — | "Solo muestra u oculta los campos de menor y mascotas. No cambia las reglas de venta." |
| Descargar PDF automáticamente | "Desactivado: se abre la vista previa con un link para compartir." | — |
| Mensaje de WhatsApp | "Usá {link} donde va la URL del boleto." | — |
| Líneas de contacto (sección) | "Aparecen en todos los boletos, en este orden." | En el teléfono: "Cargalo como se marca desde un celular. Para clientes de otras provincias, con +54 9. Desde el celular se puede tocar para llamar." |
| Horas de venta | "0 = sin límite." | "Cuenta desde la salida en la parada donde sube el pasajero. Ej.: con 5 horas, un viaje que salió a las 08:00 se vende hasta las 13:00. Quien tenga el permiso «Vender después del límite de salida» puede vender igual." |
| Días de servicio (sección) | "En fechas sin servicio no se vende ni se crean viajes. Los boletos ya vendidos no cambian." | En los días de la semana: "Quien tenga el permiso «Vender en días sin servicio» puede operar igual. El panel de clientes nunca permite elegir esas fechas." |
| Excepciones | "Una excepción manda sobre el día de la semana." | "Ej.: «Con servicio» para un domingo especial, «Sin servicio» para un feriado." |

### D4. Validaciones

| Campo | Reglas | Mensajes (voseo) |
|---|---|---|
| `venta_limite_horas` | `required`, `integer`, `minValue(0)`, `maxValue(720)` | "Ingresá la cantidad de horas." / "La cantidad de horas tiene que ser un número entero." / "…no puede ser negativa." / "…no puede ser mayor a 720." |
| `whatsapp_mensaje` | `maxLength(300)`, closure: si está cargado debe contener `{link}` | "El mensaje tiene que incluir {link}…" / "…más de :max caracteres." |
| `contact_lines.*.phone` | Las de hoy. El texto del error se define una sola vez y lo usan la regex y la closure. | "Ingresá un teléfono válido (solo números, espacios, guiones, paréntesis y +)." |
| `contact_lines.*.name` | `maxLength(30)` | Voseo. |
| `service_weekdays` | `required` | "Elegí al menos un día con servicio." |
| `service_exceptions.*.date` | `required`, `distinct`, `minDate(today())` + `displayFormat('d/m/Y')` | "Ingresá la fecha." / "Hay más de una excepción para la misma fecha." / "La fecha no puede ser anterior a hoy." |
| `service_exceptions.*.has_service` | `required` | "Elegí el tipo." |
| `service_exceptions.*.reason` | `maxLength(100)` | "El motivo no puede tener más de :max caracteres." |

`minDate()` de Filament agrega la regla `after_or_equal`. El mensaje se define con esa clave. Si la implementación muestra otra cosa, se usa `->rule('after_or_equal:today')` explícito.

### D5. Excepciones vencidas

`getFormData()` carga solo las excepciones con fecha `>= hoy`, así una fila vieja nunca entra al formulario ni falla la validación. En `save()`, antes de guardar, se cuentan las excepciones guardadas con fecha pasada. Como el formulario no las trae, quedan eliminadas al guardar. Si eran más de cero, el cuerpo de la notificación de éxito suma "Se quitó 1 excepción de una fecha que ya pasó." / "Se quitaron N excepciones…".

*Alternativa descartada:* validar `minDate` solo en filas nuevas. Exige distinguir filas nuevas de existentes en el repeater y deja historial vencido sin valor operativo.

### D6. Mensaje de WhatsApp oculto

En `save()`, `WHATSAPP_MENSAJE` se escribe solo si `array_key_exists('whatsapp_mensaje', $data)`. Cuando el campo está oculto, la clave no viene y el valor guardado no se toca. El mismo criterio aplica a cualquier campo futuro con `visible()` condicional, y queda en la sección del skill.

### D7. Paquete de idiomas

`composer require laravel-lang/common --dev` y después `php artisan lang:add es`. Genera `lang/es/*.php` y `lang/es.json`, que se commitean. En producción no se instala el paquete, pero los archivos están. Es la red de seguridad: la regla de la página sigue siendo mensaje propio para cada regla.

### D8. Sección en el skill `filament-ui`

Nueva sección "13. Parámetros (Configuración > Parámetros)" con:
- La tabla de pestañas y la pregunta para ubicar un parámetro nuevo ("¿a qué afecta?").
- Los tres niveles de texto y la prueba de "si no lo ve, ¿se equivoca?".
- Las reglas de validación: mensaje propio con voseo para cada regla, `integer()` en enteros, `maxLength()` en textos y validación de placeholders en plantillas.
- Cómo agregar el campo a `TAB_FIELDS` para el badge y la notificación.
- Campos condicionales: no sobrescribir un setting cuya clave no llega en el estado.

También se suma un ítem al checklist final del skill.

## Risks / Trade-offs

- [El badge podría no actualizarse después de un error, si Filament cachea el schema antes de que se cargue el error bag] → La primera tarea de implementación es un spike con un test Livewire (`assertSee` del badge tras un `call('save')` fallido). Si no se actualiza, el plan B es `->badge()` con `->live()` sobre el contenedor o un `View` chico en el label de la pestaña. La notificación de D2 cubre el caso igual.
- [En el celular el tooltip del hint se abre al tocar y es poco evidente] → Por diseño (D3), el hint nunca lleva información necesaria.
- [`laravel-lang/common` trae muchos idiomas o archivos de más] → Solo se agrega `es`. Si el paquete molesta, se puede quitar después y los archivos de `lang/es` siguen funcionando.
- [Los tests con fechas fijas ya pasadas fallan con `minDate`] → Usar fechas relativas (`today()->addMonth()`) o `Carbon::setTestNow()` en los tests de excepciones.
- [Los labels cambiados rompen `assertSee` existentes] → Actualizar esos asserts en el mismo cambio. Con pestañas, Filament renderiza el contenido de todas en el HTML, así que `assertSee` sigue sirviendo para pestañas inactivas.

## Migration Plan

Sin migraciones de base de datos. Al primer guardado después del deploy se eliminan las excepciones vencidas, por ejemplo el default 13/09/2026. El rollback es revertir el commit. Los settings guardados siguen siendo compatibles con la versión anterior.
