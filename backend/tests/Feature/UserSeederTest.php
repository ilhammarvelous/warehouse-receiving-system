<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fr_auth_6_user_seeder_is_idempotent_and_uses_environment_credentials(): void
    {
        $usersBefore = User::query()->count();
        config([
            'app.seed_users.supervisor.email' => 'supervisor-t2@example.test',
            'app.seed_users.supervisor.password' => 'supervisor-secret',
            'app.seed_users.operator.email' => 'operator-t2@example.test',
            'app.seed_users.operator.password' => 'operator-secret',
        ]);

        $seeder = app(UserSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('users', $usersBefore + 2);

        $supervisor = User::query()->where('email', 'supervisor-t2@example.test')->firstOrFail();
        $operator = User::query()->where('email', 'operator-t2@example.test')->firstOrFail();

        $this->assertSame(Role::Supervisor, $supervisor->role);
        $this->assertSame(Role::Operator, $operator->role);
        $this->assertTrue(Hash::check('supervisor-secret', $supervisor->password));
        $this->assertTrue(Hash::check('operator-secret', $operator->password));
    }

    public function test_fr_auth_6_user_seeder_updates_existing_email_case_insensitively(): void
    {
        $usersBefore = User::query()->count();
        config([
            'app.seed_users.supervisor.email' => 'supervisor-case-t2@example.test',
            'app.seed_users.supervisor.password' => 'supervisor-secret',
            'app.seed_users.operator.email' => 'operator-case-t2@example.test',
            'app.seed_users.operator.password' => 'operator-secret',
        ]);

        User::factory()->create(['email' => 'SUPERVISOR-CASE-T2@example.test']);

        app(UserSeeder::class)->run();

        $this->assertDatabaseCount('users', $usersBefore + 2);
        $this->assertDatabaseHas('users', [
            'email' => 'supervisor-case-t2@example.test',
            'name' => 'Supervisor',
            'role' => 'SUPERVISOR',
        ]);
    }
}
