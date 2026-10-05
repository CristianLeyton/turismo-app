<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Support\Permissions;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'phone',
        'email',
        'username',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Sincroniza el flag legacy `is_admin` a partir de los roles del usuario.
     *
     * `is_admin` se mantiene como columna (y como red de seguridad para
     * rollback) pero su valor canónico salen de los roles: el usuario es
     * admin si tiene el rol "Administrador" o "Super Administrador".
     *
     * Llamar después de cambiar roles (ej: al guardar el formulario de
     * usuarios). Usa saveQuietly() para no re-disparar el UserObserver.
     */
    public function syncIsAdminFlag(): static
    {
        $isAdmin = $this->hasRole(Permissions::ROLE_ADMIN)
            || $this->hasRole(Permissions::ROLE_SUPER);

        if ($isAdmin) {
            // Un administrador no necesita el rol vendedor (lo incluye).
            if ($this->hasRole(Permissions::ROLE_SELLER)) {
                $this->removeRole(Permissions::ROLE_SELLER);
            }
        } elseif (! $this->roles()->exists()) {
            // Todo usuario sin roles sigue siendo, como mínimo, vendedor
            // (paridad con el sistema anterior).
            $this->assignRole(Permissions::ROLE_SELLER);
        }

        if ((bool) $this->is_admin !== $isAdmin) {
            $this->forceFill(['is_admin' => $isAdmin])->saveQuietly();
        }

        return $this;
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
