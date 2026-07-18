<?php

use App\Models\Budget;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createStaffRequestUser(): User
{
    $role = Role::query()->create(['name' => 'Staff', 'slug' => config('civiclens.roles.staff')]);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->attach($role);

    return $user;
}

function createAdminRequestUser(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    $user->roles()->attach($role);

    return $user;
}

it('lets staff submit requests while admins only review the queue', function (): void {
    $staff = createStaffRequestUser();
    $admin = createAdminRequestUser();

    $project = Project::factory()->create(['name' => 'River Bridge Project']);

    $this->actingAs($staff)->get('/admin/change-requests/create')
        ->assertOk()
        ->assertSee('New Change Request');

    $this->actingAs($staff)->post('/admin/change-requests', [
        'module' => 'projects',
        'subject_type' => Project::class,
        'subject_id' => $project->id,
        'subject_label' => $project->name,
        'subject_url' => route('admin.projects.show', $project),
        'summary' => 'Fix project description',
        'current_value' => 'Old description',
        'proposed_value' => 'Updated description',
    ])->assertRedirect();

    expect(ChangeRequest::query()->where('summary', 'Fix project description')->exists())->toBeTrue();

    $this->actingAs($admin)->get('/admin/change-requests')
        ->assertOk()
        ->assertSee('Staff Requests')
        ->assertSee('Fix project description');

    $this->actingAs($admin)->get('/admin/change-requests/create')->assertForbidden();
});

it('hides request buttons from administrators on editable records', function (): void {
    $staff = createStaffRequestUser();
    $admin = createAdminRequestUser();

    $project = Project::factory()->create(['name' => 'Visibility Test Project']);

    $this->actingAs($staff)->get('/admin/projects/'.$project->id)
        ->assertOk()
        ->assertSee('Request change');

    $this->actingAs($admin)->get('/admin/projects/'.$project->id)
        ->assertOk()
        ->assertDontSee('Request change');
});

it('shows staff propose buttons on admin resource indexes for staff users', function (): void {
    $staff = createStaffRequestUser();

    $this->actingAs($staff)
        ->get(route('admin.documents.index'))
        ->assertOk()
        ->assertSee('Propose document');

    $this->actingAs($staff)
        ->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSee('Propose project');

    $this->actingAs($staff)
        ->get(route('admin.procurement.tenders.index'))
        ->assertOk()
        ->assertSee('Propose tender');

    $this->actingAs($staff)
        ->get(route('admin.contractors.organizations.index'))
        ->assertOk()
        ->assertSee('Propose organization');

    $this->actingAs($staff)
        ->get(route('admin.finance.budgets.index'))
        ->assertOk()
        ->assertSee('Propose budget');
});

it('lets staff submit budget change requests through the staff request workflow', function (): void {
    $staff = createStaffRequestUser();
    $budget = Budget::factory()->create(['current_allocation' => 10000, 'actual_expenditure' => 2500]);

    $this->actingAs($staff)
        ->post(route('admin.change-requests.store'), [
            'module' => 'budgets',
            'operation' => 'update',
            'subject_type' => Budget::class,
            'subject_id' => $budget->id,
            'subject_label' => $budget->project?->name ?? 'Budget #'.$budget->id,
            'subject_url' => route('admin.finance.budgets.show', $budget),
            'summary' => 'Adjust budget allocation',
            'current_value' => '10000.00',
            'proposed_value' => '12000.00',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('change_requests', [
        'module' => 'budgets',
        'operation' => 'update',
        'subject_id' => $budget->id,
        'subject_label' => $budget->project?->name ?? 'Budget #'.$budget->id,
        'summary' => 'Adjust budget allocation',
    ]);
});

it('shows validation errors on the page instead of silently bouncing when required fields are missing', function (): void {
    $staff = createStaffRequestUser();

    $this->actingAs($staff)
        ->from(route('admin.change-requests.create'))
        ->followingRedirects()
        ->post(route('admin.change-requests.store'), ['module' => 'projects'])
        ->assertOk()
        ->assertSee('Please fix the following before submitting');
});

it('prevents staff from directly mutating governed records', function (): void {
    $staff = createStaffRequestUser();
    $project = Project::factory()->create();

    $this->actingAs($staff)->get(route('admin.projects.create'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.projects.edit', $project))->assertForbidden();
    $this->actingAs($staff)->patch(route('admin.projects.archive', $project))->assertForbidden();
});

it('stores structured proposal data and a supporting attachment', function (): void {
    $staff = createStaffRequestUser();
    $project = Project::factory()->create();
    Storage::fake();

    $this->actingAs($staff)->post(route('admin.change-requests.store'), [
        'module' => 'projects',
        'operation' => 'archive',
        'subject_type' => Project::class,
        'subject_id' => $project->id,
        'subject_label' => $project->name,
        'summary' => 'Archive a duplicate project',
        'payload' => json_encode(['reason' => 'duplicate']),
        'attachment' => UploadedFile::fake()->create('evidence.pdf', 24, 'application/pdf'),
    ])->assertRedirect();

    $this->assertDatabaseHas('change_requests', [
        'module' => 'projects',
        'operation' => 'archive',
        'subject_id' => $project->id,
        'target_id' => $project->id,
    ]);

    $changeRequest = ChangeRequest::query()->latest('id')->firstOrFail();

    expect($changeRequest->payload)->toBe(['reason' => 'duplicate'])
        ->and($changeRequest->attachment_name)->toBe('evidence.pdf');
    Storage::assertExists($changeRequest->attachment_path);
});

it('records an audit event when an approved proposal is applied through the source workflow', function (): void {
    $admin = createAdminRequestUser();
    $request = ChangeRequest::query()->create([
        'requester_id' => $admin->id,
        'module' => 'projects',
        'operation' => 'update',
        'subject_label' => 'River Bridge Project',
        'summary' => 'Correct project description',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.change-requests.applied', $request), ['application_notes' => 'Updated through the project edit form.'])
        ->assertSessionHas('status', 'change-request-applied');

    $this->assertDatabaseHas('change_request_activities', [
        'change_request_id' => $request->id,
        'actor_id' => $admin->id,
        'event' => 'applied_to_source',
    ]);
});
