<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Clients;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientsResource extends Resource
{
    protected static ?string $model = Clients::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserCircle;

    protected static ?string $modelLabel = 'cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static bool $hasTitleCaseModelLabel = false;
    /*     protected static string | UnitEnum | null $navigationGroup = 'Sistema'; */
    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'dni';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->minLength(2)
                    ->maxLength(255)
                    ->required()
                    ->regex('/^[\pL\s\'\-\x{2019}]+$/u') // Letras, acentos, espacios, apóstrofo (') y guión
                    ->validationMessages([
                        'min' => 'El nombre debe tener al menos :min caracteres.',
                        'required' => 'El nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                        'regex' => 'El nombre solo puede contener letras y espacios.',
                    ]),
                TextInput::make('apellido')
                    ->label('Apellido')
                    ->minLength(2)
                    ->maxLength(255)
                    ->required()
                    ->regex('/^[\pL\s\'\-\x{2019}]+$/u') // Letras, acentos, espacios, apóstrofo (') y guión
                    ->validationMessages([
                        'min' => 'El apellido debe tener al menos :min caracteres.',
                        'required' => 'El apellido es obligatorio.',
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                        'regex' => 'El apellido solo puede contener letras y espacios.',
                    ]),
                TextInput::make('dni')
                    ->required()
                    ->label('DNI')
                    ->unique()
                    ->numeric()
                    ->minLength(6)
                    ->maxLength(9)
                    ->rules([
                        'digits_between:7,8',
                    ])
                    ->validationMessages([
                        'min' => 'El DNI debe tener al menos :min caracteres.',
                        'required' => 'El DNI es obligatorio.',
                        'max' => 'El DNI no debe exceder los :max caracteres.',
                        'unique' => 'El DNI ya está en uso.',
                        'numeric' => 'El DNI debe ser un número.',
                        'digits_between' => 'El DNI debe tener entre 7 y 8 dígitos.',
                    ]),
                TextInput::make('telefono')
                    ->label('Teléfono')
                    ->numeric()
                    ->minLength(6)
                    ->maxLength(20)
                    ->rules([
                        'digits_between:7,12',
                    ])
                    ->validationMessages([
                        'min' => 'El teléfono debe tener al menos :min caracteres.',
                        'max' => 'El teléfono no debe exceder los :max caracteres.',
                        'numeric' => 'El teléfono debe ser un número.',
                        'digits_between' => 'El teléfono debe tener entre 7 y 12 dígitos.',
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nombre'),
                TextEntry::make('apellido'),
                TextEntry::make('dni'),
                TextEntry::make('telefono'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('dni')
            ->columns([
                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable(),
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('apellido')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable(),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->filters([/* 
                TrashedFilter::make(), */])
            ->recordActions([/* 
                ViewAction::make(), */
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
                    /*                     ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(), */
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
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
