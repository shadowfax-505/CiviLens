<?php

use App\Jobs\NotifyCitizenReportSubmitted;
use App\Mail\CitizenReportAcknowledgement;
use App\Models\CitizenReport;
use App\Models\CitizenReportCategory;
use App\Models\CitizenReportStatus;
use App\Models\Document;
use App\Models\DocumentStatus;
use App\Models\DocumentVisibility;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Project;
use App\Models\Role;
use App\Models\SearchIndex;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function citizenUser(): User
{
    $role = Role::query()->firstOrCreate(['slug' => config('civiclens.roles.citizen')], ['name' => 'Citizen']);
    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user;
}

function citizenReportAdmin(): User
{
    $group = PermissionGroup::query()->firstOrCreate(
        ['slug' => 'civic-data'],
        ['name' => 'Civic Data'],
    );
    $permission = Permission::query()->firstOrCreate(
        ['slug' => config('civiclens.permissions.citizen_reports_manage')],
        ['name' => 'Manage Citizen Reports', 'permission_group_id' => $group->id],
    );
    $role = Role::query()->firstOrCreate(['slug' => 'staff'], ['name' => 'Government Staff']);
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user;
}

it('renders the CivicLens public home from the root route', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('public.home')
        ->assertSee('CivicLens');

    $this->get('/public')
        ->assertOk()
        ->assertViewIs('public.home');
});

it('shows only active public projects in the public project explorer', function (): void {
    Project::factory()->create(['name' => 'Public Bridge Upgrade', 'is_public' => true, 'is_active' => true]);
    Project::factory()->create(['name' => 'Internal Drainage Plan', 'is_public' => false, 'is_active' => true]);
    Project::factory()->create(['name' => 'Archived Road Work', 'is_public' => true, 'is_active' => false]);

    $this->get(route('public.projects.index'))
        ->assertOk()
        ->assertSee('Public Bridge Upgrade')
        ->assertDontSee('Internal Drainage Plan')
        ->assertDontSee('Archived Road Work');
});

it('renders public project detail without private procurement or document records', function (): void {
    Storage::fake('local');

    $project = Project::factory()->create(['name' => 'Public Ferry Terminal', 'is_public' => true, 'is_active' => true]);
    $project->forceFill(['latitude' => '23.8103000', 'longitude' => '90.4125000'])->save();
    Tender::factory()->create(['project_id' => $project->id, 'agency_id' => $project->agency_id, 'title' => 'Public Jetty Tender', 'is_public' => true]);
    Tender::factory()->create(['project_id' => $project->id, 'agency_id' => $project->agency_id, 'title' => 'Private Evaluation Tender', 'is_public' => false]);

    $publicVisibility = DocumentVisibility::factory()->create(['name' => 'Public', 'slug' => 'public']);
    $privateVisibility = DocumentVisibility::factory()->create(['name' => 'Internal', 'slug' => 'internal']);
    $status = DocumentStatus::factory()->create(['name' => 'Active', 'slug' => 'active']);

    Document::factory()->create([
        'title' => 'Public Terminal Drawing',
        'document_visibility_id' => $publicVisibility->id,
        'document_status_id' => $status->id,
        'storage_path' => 'documents/public-terminal.pdf',
    ])->documentables()->create([
        'documentable_type' => Project::class,
        'documentable_id' => $project->id,
        'relationship_type' => 'source',
    ]);

    Document::factory()->create([
        'title' => 'Internal Cost Review',
        'document_visibility_id' => $privateVisibility->id,
        'document_status_id' => $status->id,
    ])->documentables()->create([
        'documentable_type' => Project::class,
        'documentable_id' => $project->id,
        'relationship_type' => 'source',
    ]);

    $this->get(route('public.projects.show', $project))
        ->assertOk()
        ->assertSee('Public Ferry Terminal')
        ->assertSee('Location Map')
        ->assertSee('Public Jetty Tender')
        ->assertSee('Public Terminal Drawing')
        ->assertDontSee('Private Evaluation Tender')
        ->assertDontSee('Internal Cost Review')
        ->assertDontSee('documents/public-terminal.pdf');
});

it('lists and downloads only public documents through application routes', function (): void {
    Storage::fake('local');

    $publicVisibility = DocumentVisibility::factory()->create(['name' => 'Public', 'slug' => 'public']);
    $privateVisibility = DocumentVisibility::factory()->create(['name' => 'Internal', 'slug' => 'internal']);
    $status = DocumentStatus::factory()->create(['name' => 'Active', 'slug' => 'active']);

    $publicDocument = Document::factory()->create([
        'title' => 'Public Procurement Notice',
        'document_visibility_id' => $publicVisibility->id,
        'document_status_id' => $status->id,
        'storage_path' => 'documents/public-notice.txt',
        'mime_type' => 'text/plain',
        'original_filename' => 'public-notice.txt',
    ]);
    Storage::disk('local')->put('documents/public-notice.txt', 'public notice');

    $privateDocument = Document::factory()->create([
        'title' => 'Internal Procurement Memo',
        'document_visibility_id' => $privateVisibility->id,
        'document_status_id' => $status->id,
    ]);

    $this->get(route('public.documents.index'))
        ->assertOk()
        ->assertSee('Public Procurement Notice')
        ->assertDontSee('Internal Procurement Memo');

    $this->get(route('public.documents.download', $publicDocument))->assertOk();
    $this->get(route('public.documents.download', $privateDocument))->assertForbidden();
});

it('restricts public search results to public indexed records', function (): void {
    SearchIndex::query()->create([
        'searchable_type' => Project::class,
        'searchable_id' => Project::factory()->create(['is_public' => true, 'is_active' => true])->id,
        'module' => 'projects',
        'title' => 'Public School Works',
        'description' => 'Public school project.',
        'url' => '/public/projects/public-school-works',
        'visibility' => 'public',
        'status' => 'active',
        'search_text' => 'Public School Works',
        'metadata' => ['route_module' => 'projects'],
        'indexed_at' => now(),
    ]);
    SearchIndex::query()->create([
        'searchable_type' => Project::class,
        'searchable_id' => Project::factory()->create(['is_public' => false, 'is_active' => true])->id,
        'module' => 'projects',
        'title' => 'Private School Works',
        'description' => 'Private project.',
        'url' => '/admin/projects/1',
        'visibility' => 'internal',
        'status' => 'active',
        'search_text' => 'Private School Works',
        'metadata' => ['route_module' => 'projects'],
        'indexed_at' => now(),
    ]);

    $this->get(route('public.search', ['q' => 'School']))
        ->assertOk()
        ->assertSee('Public School Works')
        ->assertDontSee('Private School Works');
});

it('keeps authenticated citizens on the public-safe search and dashboard', function (): void {
    $citizen = citizenUser();

    $this->actingAs($citizen)
        ->get(route('public.search', ['q' => 'school']))
        ->assertOk()
        ->assertViewIs('public.search.index');

    $this->actingAs($citizen)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('citizen.dashboard')
        ->assertDontSee('Executive Command Center');
});

it('requires authentication for citizen report submission and tracks by public uuid', function (): void {
    $category = CitizenReportCategory::factory()->create(['name' => 'Safety Concern', 'slug' => 'safety-concern']);
    CitizenReportStatus::factory()->create(['name' => 'Submitted', 'slug' => 'submitted', 'is_default' => true]);

    $this->post(route('public.reports.store'), [])->assertRedirect(route('login'));

    $citizen = citizenUser();

    $this->actingAs($citizen)
        ->post(route('public.reports.store'), [
            'citizen_report_category_id' => $category->id,
            'title' => 'Broken culvert near school',
            'description' => 'The culvert edge is damaged and needs review.',
            'location_text' => 'Ward 7 near the primary school',
            'contact_preference' => 'email',
        ])
        ->assertRedirect();

    $report = CitizenReport::query()->where('title', 'Broken culvert near school')->firstOrFail();

    $this->assertDatabaseHas('citizen_reports', ['id' => $report->id, 'submitter_id' => $citizen->id]);
    $this->actingAs($citizen)->get(route('citizen.reports.index', ['submitted' => $report->id]))
        ->assertOk()
        ->assertSee('Broken culvert near school')
        ->assertSee('Newly submitted');

    $admin = citizenReportAdmin();
    $this->actingAs($admin)->get(route('admin.citizen-reports.index'))
        ->assertOk()
        ->assertSee('Broken culvert near school');

    expect($report->submitter_id)->toBe($citizen->id)
        ->and($report->public_uuid)->not->toBe((string) $report->id)
        ->and($report->activities()->where('event', 'submitted')->exists())->toBeTrue();

    $this->get(route('public.reports.show', $report->public_uuid))
        ->assertOk()
        ->assertSee('Broken culvert near school');
});

it('queues an acknowledgement email after a citizen report is submitted', function (): void {
    Queue::fake();
    Mail::fake();

    $category = CitizenReportCategory::factory()->create();
    CitizenReportStatus::factory()->create(['is_default' => true]);
    $citizen = citizenUser();

    $this->actingAs($citizen)
        ->post(route('public.reports.store'), [
            'citizen_report_category_id' => $category->id,
            'title' => 'Unsafe pedestrian crossing',
            'description' => 'The crossing markings have faded and require an urgent safety review.',
            'contact_preference' => 'email',
        ])
        ->assertRedirect();

    $report = CitizenReport::query()->where('title', 'Unsafe pedestrian crossing')->firstOrFail();

    Queue::assertPushed(NotifyCitizenReportSubmitted::class, function (NotifyCitizenReportSubmitted $job) use ($report): bool {
        $job->handle();

        return $job->report->is($report);
    });

    Mail::assertQueued(CitizenReportAcknowledgement::class, function (CitizenReportAcknowledgement $mail) use ($citizen, $report): bool {
        return $mail->hasTo($citizen->email) && $mail->report->is($report);
    });
});

it('protects citizen report attachments while allowing the submitter to download them', function (): void {
    Storage::fake('local');

    $category = CitizenReportCategory::factory()->create();
    CitizenReportStatus::factory()->create(['is_default' => true]);
    $citizen = citizenUser();

    $this->actingAs($citizen)
        ->post(route('public.reports.store'), [
            'citizen_report_category_id' => $category->id,
            'title' => 'Damaged drainage cover',
            'description' => 'The drainage cover is damaged beside the market and needs replacement.',
            'contact_preference' => 'email',
            'attachment' => UploadedFile::fake()->image('drainage-cover.jpg'),
        ])
        ->assertRedirect();

    $report = CitizenReport::query()->where('title', 'Damaged drainage cover')->firstOrFail();

    expect($report->attachment_disk)->toBe('local')
        ->and($report->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($report->attachment_path);

    $this->actingAs($citizen)
        ->get(route('citizen.reports.attachment', $report))
        ->assertOk()
        ->assertDownload('drainage-cover.jpg');

    $this->actingAs(citizenUser())
        ->get(route('citizen.reports.attachment', $report))
        ->assertForbidden();
});

it('protects citizen report moderation and records status activities', function (): void {
    $submitted = CitizenReportStatus::factory()->create(['name' => 'Submitted', 'slug' => 'submitted', 'is_default' => true]);
    $accepted = CitizenReportStatus::factory()->create(['name' => 'Accepted', 'slug' => 'accepted']);
    $report = CitizenReport::factory()->create(['citizen_report_status_id' => $submitted->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.citizen-reports.index'))
        ->assertForbidden();

    $admin = citizenReportAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.citizen-reports.status', $report), [
            'citizen_report_status_id' => $accepted->id,
            'moderation_notes' => 'Accepted for field verification.',
        ])
        ->assertRedirect(route('admin.citizen-reports.show', $report));

    expect($report->refresh()->status->slug)->toBe('accepted')
        ->and($report->moderation_notes)->toBe('Accepted for field verification.')
        ->and($report->activities()->where('event', 'status_changed')->exists())->toBeTrue();
});
