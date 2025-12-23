<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Dom\Text;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Model;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $modelLabel = 'usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static bool $hasTitleCaseModelLabel = false;
    protected static string | UnitEnum | null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->validationMessages(['required' => 'El campo nombre es obligatorio.']),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->validationMessages([
                        'required' => 'El campo email es obligatorio.',
                        'email' => 'El campo email debe ser una dirección de correo electrónico válida.',
                    ]),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->hiddenOn('edit')
                    ->validationMessages(['required' => 'El campo contraseña es obligatorio.']),
                Toggle::make('is_admin')
                    ->label('Es administrador')
                    ->aboveLabel('Permisos de administrador')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('User')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->visibleFrom('md')
                    ,
                /*                 ToggleColumn::make('is_admin')
                    ->label('Es administrador')
                    ->disabled(fn(Model $record): bool => $record->id === 1)
                    ->sortable(), */
                TextColumn::make('is_admin')
                    ->label('Es administrador')
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'info')
                    ->sortable(),
                    /* IconColumn::make('is_admin')
                    ->label('Es administrador')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(), */
/*                     ->extraAttributes([
                        'class' => 'flex justify-center',
                        'style' => 'display: flex; justify-content: center; align-items: center;',
                    ]), */
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
                EditAction::make()->disabled(fn(User $record): bool => $record->id === 1)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Editar',
                ]),
                Action::make('resetPassword')
                    ->label('Restablecer contraseña')
                    ->icon(Heroicon::Key)
                    ->color('info')
                    ->action(function (User $record) {
                        $newPassword = explode('@', $record->email)[0];
                        $record->password = bcrypt($newPassword);
                        $record->save();

                        // Aquí puedes agregar lógica para notificar al usuario sobre su nueva contraseña
                        Notification::make()
                            ->title('Contraseña restablecida')
                            ->body('Contraseña restablecida a la parte antes del "@" de su correo electrónico.')
                            ->success()
                            ->icon('heroicon-o-key')
                            ->iconColor('info')
                            ->duration(3000)
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->disabled(fn(User $record): bool => $record->id === 1)
                    ->button()
                    ->hiddenLabel()
                    ->extraAttributes([
                        'title' => 'Restablecer contraseña',
                    ]),

                DeleteAction::make()->disabled(fn(User $record): bool => $record->id === 1)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar',
                ]),
                ForceDeleteAction::make()->disabled(fn(User $record): bool => $record->id === 1)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Eliminar permanentemente',
                ]),
                RestoreAction::make()->disabled(fn(User $record): bool => $record->id === 1)->button()->hiddenLabel()->extraAttributes([
                    'title' => 'Restaurar',
                ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    //oculto el recurso para usuarios que no son administradores
    /*     public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin == true;
    } */
}
