<?php

namespace App\Filament\Resources\Buses;

use App\Filament\Clusters\Buses\BusesCluster;
use App\Filament\Resources\Buses\Pages\ManageBusLayoutAreas;
use App\Models\Bus;
use App\Models\BusLayoutArea;
use BackedEnum;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BusLayoutAreaResource extends Resource
{
    protected static ?string $model = BusLayoutArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $cluster = BusesCluster::class;

    protected static ?string $modelLabel = 'área del layout';
    protected static ?string $pluralModelLabel = 'Áreas del Layout';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bus_id')
                    ->label('Colectivo')
                    ->relationship('bus', 'name')
                    ->live()
                    ->required()
                    ->searchable(),
                
                TextInput::make('floor')
                    ->label('Piso')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Número de piso donde se encuentra el área'),
                
                Select::make('area_type')
                    ->label('Tipo de Área')
                    ->options([
                        'cafeteria' => 'CAFETERA',
                        'bathroom' => 'BAÑO',
                        'luggage' => 'Equipaje',
                        'driver' => 'Conductor',
                        'other' => 'Otro',
                    ])
                    ->required()
                    ->live()
                    ->default('cafeteria'),
                
                TextInput::make('label')
                    ->label('Etiqueta')
                    ->required()
                    ->maxLength(255)
                    ->default(function (Get $get) {
                        $type = $get('area_type');
                        return match($type) {
                            'cafeteria' => 'CAFETERA',
                            'bathroom' => 'BAÑO',
                            'luggage' => 'EQUIPAJE',
                            'driver' => 'CONDUCTOR',
                            default => 'ÁREA',
                        };
                    })
                    ->helperText('Texto que se mostrará en el layout'),
                
                TextInput::make('row_start')
                    ->label('Fila Inicial')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Fila donde comienza el área (0 = arriba de todo)'),
                
                TextInput::make('row_end')
                    ->label('Fila Final')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Fila donde termina el área'),
                
                TextInput::make('column_start')
                    ->label('Columna Inicial')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Columna donde comienza el área (0 = primera columna)'),
                
                TextInput::make('column_end')
                    ->label('Columna Final')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Columna donde termina el área'),
                
                TextInput::make('span_rows')
                    ->label('Filas que Abarca')
                    ->numeric()
                    ->minValue(1)
                    ->default(function (Get $get) {
                        $rowStart = (int)($get('row_start') ?? 0);
                        $rowEnd = (int)($get('row_end') ?? 0);
                        return max(1, $rowEnd - $rowStart + 1);
                    })
                    ->helperText('Cantidad de filas que ocupa el área'),
                
                TextInput::make('span_columns')
                    ->label('Columnas que Abarca')
                    ->numeric()
                    ->minValue(1)
                    ->default(function (Get $get) {
                        $colStart = (int)($get('column_start') ?? 0);
                        $colEnd = (int)($get('column_end') ?? 0);
                        return max(1, $colEnd - $colStart + 1);
                    })
                    ->helperText('Cantidad de columnas que ocupa el área'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->columns([
                TextColumn::make('bus.name')
                    ->label('Colectivo')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('floor')
                    ->label('Piso')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('area_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'cafeteria' => 'CAFETERA',
                        'bathroom' => 'BAÑO',
                        'luggage' => 'Equipaje',
                        'driver' => 'Conductor',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match($state) {
                        'cafeteria' => 'warning',
                        'bathroom' => 'info',
                        'luggage' => 'success',
                        'driver' => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('label')
                    ->label('Etiqueta')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('row_start')
                    ->label('Fila Inicio')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('row_end')
                    ->label('Fila Fin')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('column_start')
                    ->label('Col Inicio')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('column_end')
                    ->label('Col Fin')
                    ->sortable()
                    ->alignCenter(),
                
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
                SelectFilter::make('bus')
                    ->relationship('bus', 'name')
                    ->label('Colectivo'),
                
                SelectFilter::make('floor')
                    ->label('Piso')
                    ->options(function () {
                        return BusLayoutArea::query()
                            ->distinct()
                            ->pluck('floor', 'floor')
                            ->mapWithKeys(fn ($floor) => [$floor => "Piso {$floor}"]);
                    }),
                
                SelectFilter::make('area_type')
                    ->label('Tipo de Área')
                    ->options([
                        'cafeteria' => 'CAFETERA',
                        'bathroom' => 'BAÑO',
                        'luggage' => 'Equipaje',
                        'driver' => 'Conductor',
                        'other' => 'Otro',
                    ]),
                
                TrashedFilter::make(),
            ])
            ->recordActions([
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
            'index' => ManageBusLayoutAreas::route('/'),
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
