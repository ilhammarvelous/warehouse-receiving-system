<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\ReceivingDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivingDocument>
 */
class ReceivingDocumentFactory extends Factory
{
    protected $model = ReceivingDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => 'RCV-20991231-'.$this->faker->unique()->numerify('####'),
            'supplier_name' => $this->faker->company(),
            'expected_date' => '2099-12-31',
            'status' => DocumentStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
