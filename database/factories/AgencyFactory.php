<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Office';

        return [
            'agency_type_id' => AgencyType::factory(),
            'country_id' => Country::factory(),
            'name' => $name,
            'short_name' => strtoupper(fake()->unique()->lexify('???')),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->sentence(),
            'contact_person' => fake()->name(),
            'website' => fake()->url(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => fake()->randomElement(Agency::STATUSES),
        ];
    }
}
