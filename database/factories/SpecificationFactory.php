<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specification>
 */
class SpecificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'goals' => fake()->paragraph(),
            'scope' => fake()->paragraph(),
            'functional_requirements' => fake()->paragraph(),
            'non_functional_requirements' => fake()->paragraph(),
            'current_version' => 1,
            'created_by' => User::factory(),
        ];
    }
}
