<?php

namespace Database\Factories;

use App\Models\AcceptanceCriterion;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcceptanceCriterion>
 */
class AcceptanceCriterionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_story_id' => UserStory::factory(),
            'description' => fake()->sentence(),
            'status' => AcceptanceCriterion::STATUS_NOT_MET,
            'created_by' => User::factory(),
        ];
    }
}
