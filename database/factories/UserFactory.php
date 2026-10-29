<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'email' => fake()->unique()->safeEmail(),
            'google_id_sub' => fake()->unique()->numerify('google-sub-##########'),
            'user_role' => UserRole::Student,
            'avatar_link' => fake()->imageUrl(100, 100),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'user_role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the user is a lecturer.
     */
    public function lecturer(): static
    {
        return $this->state(fn () => [
            'user_role' => UserRole::Lecturer,
        ]);
    }

    /**
     * Indicate that the user is a student.
     */
    public function student(): static
    {
        return $this->state(fn () => [
            'user_role' => UserRole::Student,
        ]);
    }

    /**
     * Indicate that the user is an assistant.
     */
    public function assistant(): static
    {
        return $this->state(fn () => [
            'user_role' => UserRole::Assistant,
        ]);
    }

    /**
     * Indicate that the user is a practitioner.
     */
    public function practitioner(): static
    {
        return $this->state(fn () => [
            'user_role' => UserRole::Practitioner,
        ]);
    }

    /**
     * Indicate that the user is archived.
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => 'archived',
        ]);
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
}
