<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\EditTicket\EditTicketForm;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Los formularios de venta y de edición de boletos no aceptan datos
 * autocompletados por el navegador: se escriben a mano o vienen del padrón
 * (spec ticket-form-autofill).
 *
 * Se recorre el schema completo, incluidos los hijos del Repeater y los
 * formularios de las acciones que se abren desde él, para que un campo
 * nuevo sin la marca haga fallar el test.
 */
class TicketFormAutofillTest extends TestCase
{
    public function test_ningun_campo_de_texto_de_la_venta_se_autocompleta(): void
    {
        $fields = $this->textFields(TicketForm::configure(Schema::make()));

        $this->assertContainsFieldsOf($fields, ['dni', 'phone_number', 'last_name', 'first_name', 'child_data.dni', 'pet_data.pet_names', 'price', 'observation']);
        $this->assertSame([], $this->withBrowserAutofill($fields), 'Campos de la venta que el navegador puede autocompletar.');
    }

    public function test_el_recorrido_incluye_los_formularios_que_se_abren_desde_la_venta(): void
    {
        $fields = $this->textFields(TicketForm::configure(Schema::make()));

        // "Crear cliente" y "Nuevo método de pago".
        $this->assertContainsFieldsOf($fields, ['apellido', 'nombre', 'telefono', 'label']);
    }

    public function test_ningun_campo_de_texto_de_la_edicion_se_autocompleta(): void
    {
        $fields = $this->textFields(EditTicketForm::configure(Schema::make()));

        $this->assertContainsFieldsOf($fields, ['dni', 'phone_number', 'last_name', 'first_name', 'child_data.dni', 'pet_data.pet_names', 'price', 'observation', 'edit_reason']);
        $this->assertSame([], $this->withBrowserAutofill($fields), 'Campos de la edición que el navegador puede autocompletar.');
    }

    // ====================== Helpers ======================

    /**
     * Campos de texto del schema (TextInput y Textarea), recorriendo hijos,
     * acciones con formulario y el formulario de "crear opción" de los selects.
     *
     * @return list<Field>
     */
    private function textFields(Schema $schema): array
    {
        return $this->collect($this->property($schema, 'components'));
    }

    /**
     * @return list<Field>
     */
    private function collect(mixed $node): array
    {
        if ($node instanceof Closure || $node === null || is_string($node)) {
            return [];
        }

        if ($node instanceof Schema) {
            return $this->textFields($node);
        }

        if (is_array($node)) {
            return array_merge(...array_map(fn (mixed $child): array => $this->collect($child), array_values($node)) ?: [[]]);
        }

        $found = [];

        if ($node instanceof TextInput || $node instanceof Textarea) {
            $found[] = $node;
        }

        if ($node instanceof Component) {
            foreach ($this->property($node, 'childComponents') ?? [] as $children) {
                $found = [...$found, ...$this->collect($children)];
            }
        }

        if ($node instanceof Select) {
            $found = [...$found, ...$this->collect($this->property($node, 'createOptionActionForm'))];
        }

        if (method_exists($node, 'getExtraItemActions')) {
            $found = [...$found, ...$this->collect($node->getExtraItemActions())];
        }

        if ($node instanceof Action) {
            $found = [...$found, ...$this->collect($this->property($node, 'schema'))];
        }

        return $found;
    }

    /**
     * Nombres de los campos que no le piden al navegador que no autocomplete.
     *
     * @param  list<Field>  $fields
     * @return list<string>
     */
    private function withBrowserAutofill(array $fields): array
    {
        return array_values(array_map(
            fn (Field $field): string => $field->getName(),
            array_filter($fields, fn (Field $field): bool => ! $this->blocksAutofill($field)),
        ));
    }

    private function blocksAutofill(Field $field): bool
    {
        if ($field instanceof TextInput) {
            return $this->property($field, 'autocomplete') === false;
        }

        $attributes = [];
        foreach ($this->property($field, 'extraInputAttributes') ?? [] as $set) {
            if (is_array($set)) {
                $attributes = [...$attributes, ...$set];
            }
        }

        return ($attributes['autocomplete'] ?? null) === 'off';
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $names
     */
    private function assertContainsFieldsOf(array $fields, array $names): void
    {
        $found = array_map(fn (Field $field): string => $field->getName(), $fields);

        foreach ($names as $name) {
            $this->assertContains($name, $found, "El recorrido del schema no encontró el campo «{$name}».");
        }
    }

    private function property(object $object, string $name): mixed
    {
        $class = $object::class;

        while ($class && ! property_exists($class, $name)) {
            $class = get_parent_class($class);
        }

        if (! $class) {
            return null;
        }

        return (new ReflectionProperty($class, $name))->getValue($object);
    }
}
