<?php

namespace App\Filament\Clusters\Locations\Resources\Schedules;

use App\Filament\Clusters\Locations\LocationsCluster;
use App\Filament\Clusters\Locations\Resources\Schedules\Pages\ManageSchedules;
use App\Models\Route;
use App\Models\Schedule;
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
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Clock;

    protected static ?string $cluster = LocationsCluster::class;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?string $modelLabel = 'horario';
    protected static ?string $pluralModelLabel = 'Horarios';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('route_id')
                    ->label('Ruta')
                    ->relationship('route', 'name')
                    ->required()
/*                     ->searchable() */
                    ->preload()
                    ->validationMessages([
                        'required' => 'Debe seleccionar una ruta.',
                    ]),
                TextInput::make('name')
                    ->label('Nombre del horario')
                    ->placeholder('Ej: Mañana, Tarde, Noche')
                    ->maxLength(255)
                    ->helperText('Opcional: nombre descriptivo del horario'),
                TimePicker::make('departure_time')
                    ->label('Hora de salida')
                    ->required()
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->validationMessages([
                        'required' => 'La hora de salida es obligatoria.',
                    ]),
                TimePicker::make('arrival_time')
                    ->label('Hora de llegada')
                    ->seconds(false)
                    ->displayFormat('H:i')
                    ->helperText('Hora estimada de llegada al destino final'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                TextColumn::make('route.name')
                    ->label('Ruta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('departure_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('arrival_time')
                    ->label('Llegada')
                    ->time('H:i')
                    ->placeholder('-')
                    ->alignCenter(),
                TextColumn::make('display_name')
                    ->label('Horario completo')
                    ->getStateUsing(fn (Schedule $record) => $record->display_name)
                    ->searchable(false),
                ToggleColumn::make('is_active')
                    ->label('Activo')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('trips_count')
                    ->label('Viajes')
                    ->counts('trips')
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
                SelectFilter::make('route')
                    ->relationship('route', 'name')
                    ->label('Ruta')
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activo')
                    ->falseLabel('Inactivo'),
                TrashedFilter::make(),
            ])
            ->defaultSort('route_id')
            ->defaultSort('departure_time')
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
            'index' => ManageSchedules::route('/'),
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