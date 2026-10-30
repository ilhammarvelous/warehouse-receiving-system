<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_fr_ops_1_health_check_reports_database_is_healthy(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([]);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'database' => 'ok',
            ]);
    }

    public function test_fr_ops_1_health_check_reports_unreachable_database(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new PDOException('Database unavailable.'));

        $this->getJson('/api/v1/health')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'error',
                'database' => 'unreachable',
            ]);
    }
}
