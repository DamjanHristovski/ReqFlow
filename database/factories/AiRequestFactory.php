<?php

namespace Database\Factories;

use App\Models\AiRequest;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRequest>
 */
class AiRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'specification_id' => Specification::factory(),
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => 'description',
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => fake()->sentence(),
        ];
    }
}
