<?php

use App\Models\ContractorCategory;
use App\Models\ContractorClassification;
use App\Models\ContractorProfile;
use App\Models\ContractorRegistrationStatus;
use App\Models\ContractorRiskLevel;
use App\Models\Country;
use App\Models\Organization;
use App\Models\OrganizationCompanyType;
use App\Models\OrganizationIndustry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contractorAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function organizationPayload(array $overrides = []): array
{
    $country = Country::factory()->create();
    $companyType = OrganizationCompanyType::factory()->create(['name' => 'Limited Company', 'slug' => 'limited-company']);
    $industry = OrganizationIndustry::factory()->create(['name' => 'Construction', 'slug' => 'construction']);

    return array_merge([
        'organization_company_type_id' => $companyType->id,
        'organization_industry_id' => $industry->id,
        'country_id' => $country->id,
        'legal_name' => 'Northstar Infrastructure Limited',
        'trade_name' => 'Northstar Infra',
        'registration_number' => 'REG-NS-001',
        'tax_identification_number' => 'TIN-NS-001',
        'website' => 'https://northstar.example.com',
        'email' => 'info@northstar.example.com',
        'phone' => '+8801000000001',
        'headquarters_address' => '10 Civic Road',
        'status' => 'active',
        'established_date' => '2015-01-15',
    ], $overrides);
}

it('restricts contractor management to administrators', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/contractors/organizations')->assertForbidden();
    $this->actingAs($user)->post('/admin/contractors/organizations', [])->assertForbidden();
});

it('lets administrators create update archive restore and inspect contractor organizations', function (): void {
    $admin = contractorAdmin();
    $payload = organizationPayload();

    $this->actingAs($admin)->post('/admin/contractors/organizations', $payload)->assertRedirect();

    $organization = Organization::query()->where('registration_number', 'REG-NS-001')->firstOrFail();

    expect($organization->created_by)->toBe($admin->id);

    $this->actingAs($admin)->get("/admin/contractors/organizations/{$organization->id}")
        ->assertOk()
        ->assertSee('Northstar Infrastructure Limited')
        ->assertSee('Contractor Intelligence');

    $this->actingAs($admin)->put("/admin/contractors/organizations/{$organization->id}", array_merge($payload, [
        'legal_name' => 'Northstar Infrastructure Updated',
    ]))->assertRedirect();

    expect($organization->fresh()->legal_name)->toBe('Northstar Infrastructure Updated')
        ->and($organization->fresh()->updated_by)->toBe($admin->id);

    $this->actingAs($admin)->patch("/admin/contractors/organizations/{$organization->id}/archive")->assertRedirect();
    expect($organization->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/contractors/organizations/{$organization->id}/restore")->assertRedirect();
    expect($organization->fresh()->archived_at)->toBeNull();
});

it('supports contractor search filters sorting and pagination', function (): void {
    $admin = contractorAdmin();
    $payload = organizationPayload();

    Organization::factory()->create([
        'legal_name' => 'Bridge Builders Limited',
        'registration_number' => 'REG-BRIDGE-123',
        'organization_company_type_id' => $payload['organization_company_type_id'],
        'organization_industry_id' => $payload['organization_industry_id'],
        'country_id' => $payload['country_id'],
        'status' => 'active',
    ]);
    Organization::factory()->create(['legal_name' => 'Water Systems Limited', 'registration_number' => 'REG-WATER-999', 'status' => 'inactive']);

    $query = http_build_query([
        'search' => 'Bridge',
        'registration_number' => 'REG-BRIDGE',
        'organization_company_type_id' => $payload['organization_company_type_id'],
        'organization_industry_id' => $payload['organization_industry_id'],
        'country_id' => $payload['country_id'],
        'status' => 'active',
        'sort' => 'legal_name',
        'direction' => 'asc',
    ]);

    $this->actingAs($admin)->get('/admin/contractors/organizations?'.$query)
        ->assertOk()
        ->assertSee('Bridge Builders Limited')
        ->assertDontSee('Water Systems Limited');
});

it('validates organization and profile relationships', function (): void {
    $admin = contractorAdmin();

    $this->actingAs($admin)->post('/admin/contractors/organizations', [
        'organization_company_type_id' => 999,
        'organization_industry_id' => 999,
        'legal_name' => '',
        'registration_number' => '',
        'email' => 'not-an-email',
        'status' => 'unknown',
    ])->assertSessionHasErrors([
        'organization_company_type_id',
        'organization_industry_id',
        'legal_name',
        'registration_number',
        'email',
        'status',
    ]);

    $organization = Organization::factory()->create();

    $this->actingAs($admin)->post("/admin/contractors/organizations/{$organization->id}/profile", [
        'contractor_category_id' => 999,
        'contractor_classification_id' => 999,
        'contractor_registration_status_id' => 999,
        'contractor_risk_level_id' => 999,
    ])->assertSessionHasErrors([
        'contractor_category_id',
        'contractor_classification_id',
        'contractor_registration_status_id',
        'contractor_risk_level_id',
    ]);
});

it('updates contractor profiles for existing organizations', function (): void {
    $admin = contractorAdmin();
    $organization = Organization::factory()->create();
    $category = ContractorCategory::factory()->create();
    $classification = ContractorClassification::factory()->create();
    $registrationStatus = ContractorRegistrationStatus::factory()->create();
    $riskLevel = ContractorRiskLevel::factory()->create();

    $this->actingAs($admin)->post("/admin/contractors/organizations/{$organization->id}/profile", [
        'contractor_category_id' => $category->id,
        'contractor_classification_id' => $classification->id,
        'contractor_registration_status_id' => $registrationStatus->id,
        'contractor_risk_level_id' => $riskLevel->id,
        'is_active' => '1',
        'is_suspended' => '0',
        'is_blacklisted' => '0',
        'is_public' => '1',
    ])->assertRedirect();

    expect(ContractorProfile::query()->where('organization_id', $organization->id)->exists())->toBeTrue();
});
