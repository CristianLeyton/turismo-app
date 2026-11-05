<?php

namespace App\Filament\Resources\Lineas;

use App\Filament\Clusters\Buses\BusesCluster;
use App\Filament\Resources\Lineas\Pages\ManageLineas;
use App\Models\Linea;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LineaResource extends Resource
{
    protected static ?string $model = Linea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $cluster = BusesCluster::class;

    protected static ?string $modelLabel = 'línea';

    protected static ?string $pluralModelLabel = 'Líneas';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'El campo nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                        'unique' => 'Ya existe una línea con ese nombre.',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buses_count')
                    ->label('Colectivos')
                    ->counts('buses')
                    ->alignCenter(),
            ])
            ->recordActions([
                // Menú de tres puntitos (sólo celular): los ítems conservan
                // su label para que el menú abierto muestre el texto.
                ActionGroup::make([
                    EditAction::make()->button(),
                    DeleteAction::make()->button(),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLineas::route('/'),
        ];
    }
}
