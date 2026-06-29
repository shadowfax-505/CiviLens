<?php

namespace Database\Factories;

use App\Models\CommitteeMember;
use App\Models\EvaluationCommittee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeMember>
 */
class CommitteeMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'evaluation_committee_id' => EvaluationCommittee::factory(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'role' => 'Evaluator',
            'email' => fake()->safeEmail(),
        ];
    }
}
