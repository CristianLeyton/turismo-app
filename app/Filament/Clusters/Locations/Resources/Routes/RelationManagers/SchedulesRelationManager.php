<?php

namespace App\Filament\Clusters\Locations\Resources\Routes\RelationManagers;

use App\Filament\Clusters\Locations\Resources\Schedules\ScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Pestaña "Horarios" de la ruta, con formulario y tabla compartidos con
 * ScheduleResource.
 */
class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $relatedResource = ScheduleResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Horarios';
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar horario'),
            ]);
    }
}
