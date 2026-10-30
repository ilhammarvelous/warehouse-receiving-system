<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $supervisorEmail = strtolower(trim($this->requiredConfiguration('app.seed_users.supervisor.email')));
        $operatorEmail = strtolower(trim($this->requiredConfiguration('app.seed_users.operator.email')));

        if (filter_var($supervisorEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('SEED_SUPERVISOR_EMAIL must be a valid email address.');
        }

        if (filter_var($operatorEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('SEED_OPERATOR_EMAIL must be a valid email address.');
        }

        if ($supervisorEmail === $operatorEmail) {
            throw new RuntimeException('Supervisor and Operator seed accounts must use different email addresses.');
        }

        $this->seedUser(
            'Supervisor',
            $supervisorEmail,
            $this->requiredConfiguration('app.seed_users.supervisor.password'),
            Role::Supervisor,
        );
        $this->seedUser(
            'Operator',
            $operatorEmail,
            $this->requiredConfiguration('app.seed_users.operator.password'),
            Role::Operator,
        );
    }

    private function requiredConfiguration(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Required seed configuration [{$key}] is missing.");
        }

        return $value;
    }

    private function seedUser(string $name, string $email, string $password, Role $role): void
    {
        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($user === null) {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
            ]);

            return;
        }

        $user->fill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ])->save();
    }
}
