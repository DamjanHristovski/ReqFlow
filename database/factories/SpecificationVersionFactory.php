<?php

namespace Database\Factories;

use App\Models\Specification;
use App\Models\SpecificationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecificationVersion>
 */
class SpecificationVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'specification_id' => Specification::factory(),
            'version_number' => 1,
            'content' => [
                'title' => fake()->sentence(4),
                'description' => fake()->paragraph(),
                'goals' => fake()->paragraph(),
                'scope' => fake()->paragraph(),
                'functional_requirements' => fake()->paragraph(),
                'non_functional_requirements' => fake()->paragraph(),
            ],
            'changed_by' => User::factory(),
        ];
    }
}
