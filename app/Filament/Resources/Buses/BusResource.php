<?php

namespace App\Filament\Resources\Buses;

use App\Filament\Clusters\Buses\BusesCluster;
use App\Filament\Resources\Buses\Pages\DesignBusLayout;
use App\Filament\Resources\Buses\Pages\ManageBuses;
use App\Models\Bus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BusResource extends Resource
{
    protected static ?string $model = Bus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Truck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $cluster = BusesCluster::class;

    protected static ?string $modelLabel = 'colectivo';
    protected static ?string $pluralModelLabel = 'Colectivos';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'El campo nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                    ]),
                TextInput::make('plate')
                    ->label('Patente'),
                TextInput::make('floors')
                    ->label('Cantidad de pisos')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3)
                    ->validationMessages([
                        'required' => 'El campo cantidad de pisos es obligatorio.',
                        'numeric' => 'El campo cantidad de pisos debe ser un número.',
                        'min' => 'El colectivo debe tener al menos :min piso.',
                        'max' => 'El colectivo no debe tener más de :max pisos.',
                    ]),
                TextInput::make('seat_count')
                    ->label('Cantidad de asientos')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->validationMessages([
                        'required' => 'El campo cantidad de asientos es obligatorio.',
                        'numeric' => 'El campo cantidad de asientos debe ser un número.',
                        'min' => 'El colectivo debe tener al menos :min asientos.',
                        'max' => 'El colectivo no debe tener más de :max asientos.',
                    ]),

                // Sólo al crear: partir de una plantilla o copiar otro colectivo.
                // La lógica de siembra vive en ManageBuses (CreateAction::using).
                Select::make('layout_seed')
                    ->label('Partir de un diseño existente')
                    ->helperText('Opcional: copiá los asientos y zonas de una plantilla o de otro colectivo. Podés rediseñarlo después con "Diseñar layout".')
                    ->options(function (): array {
                        $options = [];

                        foreach (app(\App\Services\BusLayoutService::class)->templatesForSelect() as $t) {
                            $label = $t['name']
                                ." — {$t['seats']} asientos, {$t['areas']} zonas"
                                .($t['floors'] > 1 ? " · {$t['floors']} pisos" : '');
                            $options["template:{$t['id']}"] = $label;
                        }

                        foreach (Bus::query()->orderBy('name')->get(['id', 'name']) as $bus) {
                            $options["bus:{$bus->id}"] = "Duplicar: {$bus->name}";
                        }

                        return $options;
                    })
                    ->placeholder('Empezar de cero (diseño en blanco)')
                    ->visibleOn(['create']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                ->label('Nombre')
                    ->searchable(),
                TextColumn::make('plate')
                ->label('Patente')
                    ->searchable(),
                TextColumn::make('seat_count')
                ->label('Cantidad de asientos')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('floors')
                ->label('Cantidad de pisos')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('designLayout')
                    ->label('Diseñar layout')
                    ->icon(Heroicon::Squares2x2)
                    ->color('info')
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes(['title' => 'Diseñar layout'])
                    ->url(fn (Bus $record): string => BusResource::getUrl('design', ['record' => $record])),
                EditAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                ]),
                DeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                ]),
                ForceDeleteAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                ]),
                RestoreAction::make()->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBuses::route('/'),
            'design' => DesignBusLayout::route('/{record}/design'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
