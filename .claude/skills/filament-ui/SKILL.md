---
name: filament-ui
description: Convenciones de UI/UX del panel Filament 4 de turismo-app (venta de boletos de colectivo). Usala SIEMPRE que crees o modifiques cualquier cosa de Filament en este proyecto: resources, clusters, páginas, relation managers, tablas, columnas, filtros, formularios, acciones, modales, notificaciones, widgets, vistas Blade en resources/views/filament o el tema CSS. También cuando el pedido hable de "pantalla", "listado", "ABM", "botón", "modal", "mensaje", "panel de administración", "color", "estilos", "PDF" o de un módulo nuevo (rutas, horarios, colectivos, boletos, ventas, pagos, clientes), aunque no diga "Filament".
---

# Filament UI — turismo-app

El panel es la herramienta diaria de vendedores y administradores de una empresa de colectivos (Argentina). Lo usan mucho desde el celular, en español rioplatense, y muchas operaciones tocan boletos ya vendidos. Estas convenciones existen para que cada pantalla nueva se sienta igual a las existentes y para que nadie rompa una venta por accidente.

Antes de usar una API de Filament que no aparezca acá, consultá `search-docs` (Boost): es Filament **v4** (`Filament\Schemas\Schema`, acciones en `Filament\Actions\*`, iconos con `Heroicon`).

Ejemplos de referencia en el código (leelos cuando necesites el patrón completo):
- `app/Filament/Clusters/Locations/Resources/Routes/RouteResource.php`: resource completo con acciones responsive, guardas de borrado y badges.
- `app/Filament/Clusters/Locations/Resources/Schedules/ScheduleResource.php`: resource reutilizado como relation manager y acción "Desactivar en vez de eliminar".
- `app/Filament/Clusters/Locations/Resources/Routes/Pages/EditRoute.php`: pantalla única con pestañas de relation managers.
- `app/Filament/Resources/Tickets/Tables/TicketsTable.php`: tabla pesada con filtros arriba del contenido.
- `tests/Feature/LineaResourceTest.php`: cómo se testea un resource.

## 1. Ubicación y navegación

- El panel usa `topNavigation()` y tema claro por defecto. El color primario **es el de la empresa** y no se fija en el código: ver sección 11, "Color de la empresa".
- Los módulos de configuración van en **clusters** (`app/Filament/Clusters/{Nombre}/`), en el grupo `'Configuración'`, con subnavegación arriba:

```php
protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;
protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
protected static string|UnitEnum|null $navigationGroup = 'Configuración';
protected static ?string $clusterBreadcrumb = 'Destinos';
protected static ?string $navigationLabel = 'Destinos';
protected static bool $hasTitleCaseModelLabel = false;
protected static ?int $navigationSort = 1;
```

- Los resources de un cluster viven en `Clusters/{Cluster}/Resources/{Plural}/` y declaran `$cluster`. Los operativos (boletos, ventas, pagos, clientes) van en `app/Filament/Resources/`.
- Generá los archivos con los comandos `php artisan make:filament-*` y `--no-interaction`.

## 2. Propiedades estáticas del resource

```php
protected static ?string $model = Route::class;
protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;
protected static ?string $cluster = LocationsCluster::class;
protected static ?string $recordTitleAttribute = 'name';
protected static ?string $modelLabel = 'ruta';          // singular, minúscula
protected static ?string $pluralModelLabel = 'Rutas';   // plural, mayúscula inicial
protected static bool $hasTitleCaseModelLabel = false;  // así Filament dice "Crear ruta", no "Crear Ruta"
protected static ?int $navigationSort = 1;
```

- Iconos: siempre con el enum `Heroicon::...`, nunca con strings en código nuevo (en código viejo vas a ver `'heroicon-m-...'`, como el menú `heroicon-m-ellipsis-vertical`; eso se puede mantener).
- Si el modelo usa SoftDeletes, agregá `getRecordRouteBindingEloquentQuery()` sin `SoftDeletingScope`, `TrashedFilter` y las acciones Restore/ForceDelete.
- Un resource que sólo se edita desde otra pantalla lleva `$shouldRegisterNavigation = false` y un PHPDoc que diga desde dónde se edita.

## 3. Páginas

- **Por defecto, `ManageRecords`** (ABM en modales, una sola página `index`). Es lo que usa casi todo el panel.
- Cuando un registro tiene hijos que se editan juntos (ruta → recorrido y horarios), usá **una sola pantalla `EditRecord` con relation managers en pestañas**, con la pestaña de datos llamada "Datos":

```php
public function hasCombinedRelationManagerTabsWithContent(): bool { return true; }
public function getContentTabLabel(): ?string { return 'Datos'; }
```

  Y el `CreateAction` de la lista redirige directo a esa pantalla (`successRedirectUrl(... getUrl('edit', ...))`).
- Relation managers: reutilizan el resource hijo con `$relatedResource` en vez de duplicar formulario y tabla. En el resource hijo, ocultá con `fn (mixed $livewire) => $livewire instanceof RelationManager` lo que fija la relación (el select de la ruta, la columna Ruta, el filtro Ruta).

## 4. Formularios

- Todo `label()` en español, aunque coincida con el nombre de la columna.
- `validationMessages([...])` en español para cada regla con mensaje propio (`required`, `unique`, `max`), por ejemplo `'unique' => 'Ya existe una línea con ese nombre.'`.
- `helperText()` cuando el campo tiene consecuencias que no se ven: explicá **qué pasa**, no qué es. Ejemplo: "Las rutas inactivas no aparecen en nuevas ventas, pero siguen funcionando para viajes y boletos ya vendidos."
- `placeholder()` con ejemplos concretos ("Ej: Mañana, Tarde, Noche") o el estado vacío ("Sin asignar").
- Selects de relación: `->relationship('bus', 'name')->preload()`.
- Horas: `TimePicker` con `->seconds(false)->displayFormat('H:i')`. Fechas: `displayFormat('d/m/Y')`.
- `Toggle::make('is_active')->label('Activa')->default(true)`, con el género concordando con el modelo (Activa / Activo).
- Para formularios grandes, extraé la clase a `Schemas/{Modelo}Form.php` y la tabla a `Tables/{Modelo}Table.php` con `configure()`, como en Tickets.

## 5. Tablas

**Rendimiento:** eager loading y conteos en `modifyQueryUsing`, nunca consultas por fila:

```php
->modifyQueryUsing(fn (Builder $query) => $query
    ->with(['bus', 'stops.location'])
    ->withCount(['stops', 'activeSchedules']))
```

**Mobile primero:** en celular se ve la columna principal y poco más. Las columnas secundarias llevan `->visibleFrom('md')`. Los textos largos llevan `->limit(30)->tooltip(fn ($state, $record) => $record->name)`.

**Vacíos:** `->placeholder('—')` (raya). No dejes celdas en blanco.

**Badges con color semántico.** Mantené estos colores para que el mismo dato se vea igual en todas las pantallas:

| Dato | Color |
|---|---|
| N° / IDs | `gray` |
| Fecha de emisión / venta | `success` |
| Fecha y hora de salida, colectivo | `info` |
| Recorrido "Origen → Destino", vendedor, pendiente / sin fecha | `warning` |
| Lo que falta o está mal ("Sin paradas", "Sin horarios") | `danger` |
| Asiento | `primary` |

**Formatos:** fecha `d/m/Y`, hora `H:i`, recorrido `"{$origen} → {$destino}"`, conteos con `->alignCenter()`.

**Otras convenciones:**
- **Por defecto, toda tabla lleva `->recordUrl(null)` y `->recordAction(null)`**: tocar la fila no abre nada, y las acciones se usan sólo desde sus botones o el menú. Evita ediciones accidentales al scrollear en el celular. Para navegar a un registro, poné `->url(...)` en una columna puntual (por ejemplo, la fecha en Boletos), no en toda la fila.

```php
return $table
    ->recordUrl(null)
    ->recordAction(null)
    ->columns([...]);
```
- `ToggleColumn::make('is_active')` para activar o desactivar directo desde la tabla.
- `TernaryFilter::make('is_active')->label('Activa')->default(true)`: por defecto se ven sólo los activos.
- Tablas con muchos filtros (boletos, ventas): `->filtersLayout(FiltersLayout::AboveContent)`, `->filtersFormColumns(6)`, `->deferFilters(false)`, `->persistFiltersInSession()`, e `indicateUsing()` con textos tipo `'Salida: 04/11/2026'`.
- Paginación en listados grandes: `->paginated([5, 10, 25, 50, 100])->defaultPaginationPageOption(50)`.

## 6. Acciones de fila: patrón responsive (obligatorio)

Cada acción de fila se declara **dos veces**: un menú de tres puntitos sólo en celular, con labels visibles, y botones de icono sólo en escritorio, con `title` para el tooltip. Si falta el menú, en el celular la fila queda sin acciones.

```php
->recordActions([
    // Menú de tres puntitos (sólo celular): los ítems conservan su label.
    ActionGroup::make([
        EditAction::make()->button(),
        DeleteAction::make()->button(),
        RestoreAction::make()->button(),
    ])->icon('heroicon-m-ellipsis-vertical')
        ->hiddenLabel()
        ->size('sm')
        ->extraAttributes(['class' => 'md:hidden']),

    EditAction::make()->button()->hiddenLabel()->extraAttributes([
        'title' => 'Editar', 'class' => 'hidden md:inline-flex',
    ]),
    DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
        'title' => 'Eliminar', 'class' => 'hidden md:inline-flex',
    ]),
    RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
        'title' => 'Restaurar', 'class' => 'hidden md:inline-flex',
    ]),
])
->toolbarActions([
    BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()]),
])
```

Cuando una acción lleva configuración (guardas, modales), envolvela en un método estático y llamalo en las dos variantes, así no se desincronizan.

## 7. Acciones destructivas: proteger lo vendido

Borrar o cambiar algo que afecta boletos ya vendidos (ruta, horario, parada, ubicación) **no se bloquea en silencio**. El patrón tiene tres partes:

1. **Contar lo afectado** con `App\Support\AffectedTickets` (hoy tiene `forRoute` y `forSchedule`; si hace falta otro caso, agregá ahí un método `forX`), memorizado por registro con un `WeakMap` estático, porque el modal lo consulta varias veces.
2. **El modal explica y ofrece una salida.** Si está bloqueado: `modalHeading('No se puede eliminar la ruta')`, una descripción con la cantidad y el motivo, `modalSubmitAction(false)`, `modalCancelActionLabel('Cerrar')` y, en `extraModalFooterActions`, una acción alternativa ("Desactivar ruta", color `warning`, `cancelParentActions()`). Otra opción es ocultar Eliminar y mostrar en su lugar una acción "Desactivar".
3. **Volver a chequear en `before()`**, por si el estado cambió con el modal abierto: Notification `danger` y `$action->cancel()`. Las bulk actions hacen lo mismo y nombran los registros bloqueados.

Para las ediciones con impacto (por ejemplo, cambiar la hora de un horario), usá `modalDescription(fn ($record) => AffectedTickets::warning($count))` para avisar antes de guardar.

Mensaje modelo: *"Esta ruta tiene 3 boletos vendidos en viajes que todavía no partieron, por eso no se puede eliminar. Podés desactivarla para que no se use en nuevas ventas."* Es decir: qué pasa, por qué, y qué podés hacer.

## 8. Notificaciones

```php
Notification::make()
    ->title('Ruta desactivada')      // corto: sustantivo + participio
    ->body('Ya no se ofrece en nuevas ventas. Los boletos ya vendidos siguen siendo válidos.')
    ->success()                     // success | danger | warning | info
    ->send();
```

- Título corto. En el cuerpo va la consecuencia para el negocio, no el detalle técnico.
- `danger` cuando algo no se hizo, siempre explicando por qué y qué hacer.
- Nunca muestres excepciones ni IDs internos al usuario.

## 9. Textos (UX copy)

- Español rioplatense con **voseo**: "Podés", "Elegí", "Ingresá". Nunca "Puede usted" ni "Puedes".
- Vocabulario del negocio: **colectivo** (no "bus" ni "autobús"), **boleto** (no "ticket"), **pasajero**, **parada**, **recorrido**, **horario**, **viaje**, **salida**, **emisión**, **vendedor**.
- Labels de botones: verbo en infinitivo ("Agregar parada", "Desactivar ruta", "Descargar"). Confirmaciones de cierre: "Cerrar".
- El género concuerda con el modelo: "Activa" (ruta), "Activo" (horario), "No se eliminó ninguna ruta" / "ningún horario".

## 10. Permisos

- Cada resource tiene una Policy en `app/Policies/` con el trait `AuthorizesModule` y `permissionModule()` (`'routes'`, `'lineas'`, ...). Filament la usa sola para mostrar u ocultar Create, Edit y Delete.
- Los permisos se definen sólo en `App\Support\Permissions::catalog()` con el formato `{modulo}.{accion}`. Un módulo nuevo agrega su entrada con `self::standardActions()`, y después se corre `php artisan permissions:sync`. No lo agregues a `defaultsFor()` de Administrador o Vendedor salvo que se pida: los módulos de configuración se habilitan desde la matriz de roles.
- Las acciones custom se autorizan con `->visible(fn (): bool => (bool) Auth::user()?->can('update', $record))` o `can('clients.ban')`. Las acciones extra de una policy (por ejemplo `reorder`) delegan en un permiso existente.

## 11. Vistas Blade y estilos

- Las vistas custom van en `resources/views/filament/...` y se inyectan con render hooks, `ViewColumn` o `ViewField`.
- Tailwind v4 con **soporte dark obligatorio**. Paleta usada: tarjetas `rounded-lg bg-gray-50 p-2.5 dark:bg-white/5`; destacados `bg-primary-500/10 text-primary-700 dark:text-primary-400`; montos y avisos `amber`/`yellow`; negativos `red`. Etiquetas chicas `text-[11px] text-gray-500 dark:text-gray-400`, valores `text-base font-bold`.

**Color de la empresa (obligatorio).** El sistema se instala para varias empresas (una instalación por empresa) y cada una elige su color principal en Parámetros > Apariencia. Todo lo que sea "color de marca" sale de esa paleta, nunca de un color fijo, o esa pantalla queda fucsia en la instalación de otra empresa.

| Dónde | Usá | Nunca |
|---|---|---|
| Componentes Filament (acciones, badges, columnas, toggles) | `->color('primary')` | `Color::Fuchsia`, `Color::hex(...)` |
| Clases Tailwind en Blade | `text-primary-600`, `bg-primary-50`, `border-primary-300`, `dark:text-primary-400` | `fuchsia-*`, `purple-*`, `violet-*`, `pink-*` como color de marca |
| CSS en `theme.css` | `var(--primary-600)` | hex de marca (`#c026d3`, `#d946ef`…) |
| Texto sobre un fondo `primary-600` | `text-[var(--primary-text)]` (blanco u oscuro según el contraste) | `text-white` fijo: con un color claro queda ilegible |
| PDFs (dompdf: no entiende `var()` ni `oklch`) | hex de `App\Support\BrandPalette::currentAsHex()`, pasados por el servicio del PDF a la vista (`{{ $brand[600] }}`) | hex de marca escritos en la vista |
| Páginas fuera de un panel (como `boletos/share`) | imprimir las variables `--primary-*` y `--primary-text` con `BrandPalette::current()` | asumir que Filament las inyecta |

- **Escalones:** el 600 es el color exacto de la empresa (botones, links, acentos fuertes). Para un estado "de la marca" que tiene que distinguirse del principal (asiento seleccionado), usá un tono más oscuro: `bg-primary-800 border-primary-900 hover:bg-primary-900`. 50–200 para fondos suaves, 300 para bordes, 700–800 para texto sobre fondos claros, y 400 para acentos en modo oscuro.
- **Modo oscuro:** todo `text-primary-500…900` lleva `dark:text-primary-400` (con un color de marca oscuro, como un azul marino, el 600 desaparece sobre fondo negro), y los tonos claros (50–300) quedan chillones sobre fondo oscuro. En oscuro usá el 400 para texto y borde, y fondos translúcidos (`dark:bg-primary-400/15` o `color-mix(in oklab, var(--primary-400) 15%, transparent)` en CSS).
- **Lo que no es de marca no cambia:** estados (`success`, `danger`, `warning`, `info`, y `red`/`green`/`amber` en Blade) y grises neutros siguen con su color fijo. Usá `primary` solo para lo que identifica a la empresa, no para comunicar un estado.
- **Guardia automática:** `BrandColorUsageTest` falla si una vista, `theme.css` o `app/Filament` usa `fuchsia-*`, `purple-*` (salvo las categorías del diseñador de colectivos) o un hex de la paleta fucsia anterior, y también si un `text-primary-500…900` no tiene su `dark:text-primary-*` en la misma línea. Si falla, migrá a `primary`; no agregues excepciones.
- `BrandPalette`, `--primary-text`, la pestaña Apariencia y la guardia llegan con el cambio OpenSpec `configurable-primary-color`. Mientras no esté implementado, `primary` ya funciona en Filament y en Tailwind (hoy es fucsia), así que el código nuevo igual se escribe con `primary`.
- Grillas responsive con `gap`: `grid grid-cols-2 gap-2 sm:grid-cols-4`.
- Los estilos globales van en `resources/css/filament/admin/theme.css`. Si creás vistas en una carpeta nueva, agregá su `@source`. Después de tocar clases Tailwind, avisá que hay que correr `npm run build` (o `npm run dev`).
- Los inputs numéricos no tienen flechas ni cambian con la rueda del mouse (ya resuelto globalmente: no lo dupliques).

## 12. Comentarios y tests

- Comentarios breves en español que expliquen el **porqué** de una decisión de UI ("Sin horarios no hay contra qué medir las horas"). PHPDoc en español en los métodos helpers.
- Todo cambio de UI lleva un test PHPUnit en `tests/Feature/`, con nombres en español snake_case (`test_vendedor_no_puede_acceder_a_las_lineas`):

```php
protected function setUp(): void
{
    parent::setUp();
    Permissions::syncToDatabase();
    $this->actingAs(User::factory()->superAdmin()->create());
}

Livewire::test(ManageLineas::class)
    ->callAction(CreateAction::class, data: ['name' => 'Oran - Salta'])
    ->assertHasNoActionErrors();

Livewire::test(ManageLineas::class)
    ->callTableAction(EditAction::class, $linea, data: ['name' => '...'])
    ->assertHasNoTableActionErrors();
```

  Para verificar el menú de celular, inspeccioná la configuración de la tabla, no el HTML: `md:hidden` aparece en todo el layout de Filament y un `assertSeeHtml` pasaría igual sin el menú. Ver `test_las_acciones_de_fila_tienen_menu_para_celular` en `LineaResourceTest`.

  Cubrí el caso feliz, la validación, los permisos (vendedor y administrador sin acceso, un rol con sólo `view_any` que no ve Create, Edit ni Delete) y las guardas de borrado.
- Al terminar: `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter=...`.

## 13. Parámetros (Configuración > Parámetros)

La página `app/Filament/Pages/TicketSettings.php` junta los parámetros del sistema. Se usa también desde el celular, así que cada parámetro nuevo sigue estas reglas para no volver a llenarla de texto.

**Dónde va.** Preguntate *a qué afecta* y ubicalo en su pestaña:

| Pestaña | Afecta a | Hoy tiene |
|---|---|---|
| Venta | Lo que ve y puede hacer el vendedor al vender | Acompañantes, venta fuera de término, selectores de origen y destino (sección con permiso `settings.sale_selectors`) |
| Boleto | Lo que recibe el pasajero | Emisión al finalizar la venta: PDF automático y mensaje de WhatsApp (sección con permiso `settings.ticket_emission`), líneas de contacto |
| Calendario (permiso `settings.calendar`) | Cuándo hay viajes | Días de la semana, excepciones por fecha |
| Seguridad (permiso `settings.security`) | Qué tan protegidas están las acciones | Confirmación de contraseña |
| Clientes (permiso `settings.client_panel`) | El panel de pasajeros | Panel de clientes activo |
| Apariencia (permiso `settings.appearance`) | Cómo se ve la marca de la empresa | Color principal |

Si no entra en ninguna, consultalo antes de crear una pestaña nueva. Además de la constante en `Setting` y el campo, sumalo a `TicketSettings::TAB_FIELDS`: de ahí salen el badge rojo de errores de la pestaña y la notificación de guardado fallido.

**Tres niveles de texto.** Para decidir, preguntate *"si no lo lee, ¿configura algo mal?"*:

| Nivel | Cómo | Cuándo |
|---|---|---|
| Visible | `Section::description()` o `helperText()`, **una oración** | Sí, configura mal. Ej.: "0 = sin límite.", "Usá {link} donde va la URL del boleto." |
| Hint | `->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: '…')` en el campo | No, solo entiende menos: ejemplos, formato de carga, permisos que dejan saltear la regla |
| Nada | — | Repite el label o el placeholder |

En el celular el tooltip se abre al tocar el ícono y es fácil no verlo: **un hint nunca lleva información necesaria**. Las secciones no tienen hint propio; su información extra va en el hint del primer campo. Si varios campos comparten la misma explicación (los toggles de contraseña), va una vez en la sección y los labels quedan cortos ("Al borrar boletos").

**Validación.**
- Toda regla lleva su mensaje en `validationMessages()` con voseo ("Ingresá…", "Elegí…"). `lang/es` existe como red de seguridad, pero sus mensajes son neutros y genéricos.
- Enteros con `->integer()` (no solo `numeric()`, que acepta decimales y después se redondean al guardar). Textos con `->maxLength()`.
- Las plantillas con placeholders (`{link}`) validan con una closure que el placeholder esté.
- Fechas que no pueden ser pasadas: `->minDate(fn (): string => today()->format('Y-m-d'))`, con mensaje para `after_or_equal`. Lo ya vencido se filtra en `getFormData()` para que no bloquee el guardado.
- Si el mismo mensaje lo usan dos reglas (regex y closure), definilo una vez en una constante.

**Guardado.** Es todo o nada: un error en cualquier pestaña no guarda nada. Filament abre solo la pestaña del primer error; las demás muestran el badge, y `onValidationError()` avisa qué pestañas revisar. Un campo con `visible()` condicional **no llega en `getState()` cuando está oculto**: en `save()` escribilo solo si `array_key_exists($campo, $data)`, o lo vas a borrar sin querer.

**Tests** (en `tests/Feature/TicketSettingsTest.php`): con pestañas, el contenido de todas está en el HTML, así que `assertSee` sigue funcionando. Para los badges, leé `getBadge()` de cada `Tab` (ver `tabBadges()` en el test). Las fechas se fijan con `Carbon::setTestNow()`.

## Checklist antes de terminar

- [ ] Labels, mensajes y notificaciones en español con voseo y vocabulario del negocio
- [ ] `modelLabel` en minúscula y `hasTitleCaseModelLabel = false`
- [ ] Acciones de fila en sus dos variantes (menú en celular y botones en escritorio)
- [ ] Tabla con `->recordUrl(null)` y `->recordAction(null)`
- [ ] Columnas secundarias con `visibleFrom('md')` y vacíos con `'—'`
- [ ] Eager loading y conteos en `modifyQueryUsing`
- [ ] Borrados que afectan boletos vendidos protegidos (modal, alternativa y `before()`)
- [ ] Policy y entrada en `Permissions::catalog()`
- [ ] Vistas Blade con `dark:`
- [ ] Colores de marca con `primary` (Filament, Tailwind, `var(--primary-*)`, `$brand` en PDFs): ningún `fuchsia`/`purple` ni hex de marca fijo
- [ ] Parámetro nuevo: en su pestaña y en `TAB_FIELDS`, texto en el nivel que corresponde y cada regla con mensaje en voseo
- [ ] Test feature, Pint y tests corridos
