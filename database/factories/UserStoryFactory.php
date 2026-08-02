<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStory>
 */
class UserStoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'current_version' => 1,
            'created_by' => User::factory(),
        ];
    }
}
