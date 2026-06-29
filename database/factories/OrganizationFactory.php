<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_company_type_id' => OrganizationCompanyType::factory(),
            'organization_industry_id' => OrganizationIndustry::factory(),
            'country_id' => Country::factory(),
            'legal_name' => fake()->unique()->company().' Limited',
            'trade_name' => fake()->company(),
            'registration_number' => 'REG-'.fake()->unique()->bothify('????-####'),
            'tax_identification_number' => 'TIN-'.fake()->unique()->numerify('########'),
            'website' => fake()->url(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'headquarters_address' => fake()->address(),
            'status' => 'active',
            'established_date' => fake()->dateTimeBetween('-20 years', '-1 year')->format('Y-m-d'),
        ];
    }
}
