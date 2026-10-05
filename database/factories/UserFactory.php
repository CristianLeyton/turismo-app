<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'surname' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuario administrador (rol Administrador + flag is_admin).
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['is_admin' => true])->saveQuietly();
            $user->assignRole(Permissions::ROLE_ADMIN);
        });
    }

    /**
     * Usuario vendedor (rol Vendedor).
     */
    public function vendedor(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['is_admin' => false])->saveQuietly();
            $user->assignRole(Permissions::ROLE_SELLER);
        });
    }

    /**
     * Super Administrador (bypass total; se usa para el usuario fundador).
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['is_admin' => true])->saveQuietly();
            $user->assignRole(Permissions::ROLE_SUPER);
        });
    }
}
