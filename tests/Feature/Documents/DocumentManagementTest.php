<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentStatus;
use App\Models\DocumentTag;
use App\Models\DocumentType;
use App\Models\DocumentVisibility;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function documentAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function documentPayload(array $overrides = []): array
{
    $project = Project::factory()->create();

    return array_merge([
        'document_type_id' => DocumentType::factory()->create(['name' => 'Contract', 'slug' => 'contract'])->id,
        'document_category_id' => DocumentCategory::factory()->create(['name' => 'Procurement', 'slug' => 'procurement'])->id,
        'document_status_id' => DocumentStatus::factory()->create(['name' => 'Active', 'slug' => 'active'])->id,
        'document_visibility_id' => DocumentVisibility::factory()->create(['name' => 'Internal', 'slug' => 'internal'])->id,
        'title' => 'Bridge Contract Document',
        'description' => 'Contract package for bridge works.',
        'language' => 'en',
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
        'tag_ids' => [DocumentTag::factory()->create(['name' => 'Bridge', 'slug' => 'bridge'])->id],
        'file' => UploadedFile::fake()->create('bridge-contract.pdf', 24, 'application/pdf'),
    ], $overrides);
}

it('restricts document management to authorized users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/documents')->assertForbidden();
    $this->actingAs($user)->post('/admin/documents', [])->assertForbidden();
});

it('lets administrators upload view update replace download archive and restore documents', function (): void {
    Storage::fake('local');

    $admin = documentAdmin();
    $payload = documentPayload();

    $this->actingAs($admin)->post('/admin/documents', $payload)->assertRedirect();

    $document = Document::query()->where('title', 'Bridge Contract Document')->firstOrFail();

    $this->actingAs($admin)->get("/admin/documents/{$document->id}")
        ->assertOk()
        ->assertSee('Bridge Contract Document')
        ->assertSee('Version History');

    $this->actingAs($admin)->put("/admin/documents/{$document->id}", [
        'document_type_id' => $payload['document_type_id'],
        'document_category_id' => $payload['document_category_id'],
        'document_status_id' => $payload['document_status_id'],
        'document_visibility_id' => $payload['document_visibility_id'],
        'title' => 'Bridge Contract Updated',
        'description' => 'Updated metadata.',
        'language' => 'en',
        'tag_ids' => $payload['tag_ids'],
    ])->assertRedirect();

    expect($document->fresh()->title)->toBe('Bridge Contract Updated');

    $this->actingAs($admin)->post("/admin/documents/{$document->id}/versions", [
        'file' => UploadedFile::fake()->create('bridge-contract-v2.pdf', 28, 'application/pdf'),
        'reason' => 'Signed addendum.',
    ])->assertRedirect();

    expect($document->fresh()->version_number)->toBe(2);

    $this->actingAs($admin)->get("/admin/documents/{$document->id}/download")->assertOk();

    $this->actingAs($admin)->patch("/admin/documents/{$document->id}/archive")->assertRedirect();
    expect($document->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/documents/{$document->id}/restore")->assertRedirect();
    expect($document->fresh()->archived_at)->toBeNull();
});

it('supports document search filters sorting pagination and bulk actions', function (): void {
    Storage::fake('local');

    $admin = documentAdmin();
    $payload = documentPayload();
    $this->actingAs($admin)->post('/admin/documents', $payload)->assertRedirect();
    Document::factory()->create(['title' => 'Unrelated Invoice', 'original_filename' => 'invoice.pdf']);

    $query = http_build_query([
        'search' => 'Bridge',
        'document_type_id' => $payload['document_type_id'],
        'document_category_id' => $payload['document_category_id'],
        'document_visibility_id' => $payload['document_visibility_id'],
        'file_extension' => 'pdf',
        'sort' => 'title',
        'direction' => 'asc',
    ]);

    $this->actingAs($admin)->get('/admin/documents?'.$query)
        ->assertOk()
        ->assertSee('Bridge Contract Document')
        ->assertDontSee('Unrelated Invoice');

    $document = Document::query()->where('title', 'Bridge Contract Document')->firstOrFail();
    $tag = DocumentTag::factory()->create(['name' => 'Urgent', 'slug' => 'urgent']);

    $this->actingAs($admin)->post('/admin/documents/bulk', [
        'action' => 'tag',
        'document_ids' => [$document->id],
        'tag_ids' => [$tag->id],
    ])->assertRedirect();

    expect($document->tags()->whereKey($tag->id)->exists())->toBeTrue();
});

it('validates secure uploads and required relationships', function (): void {
    $admin = documentAdmin();

    $this->actingAs($admin)->post('/admin/documents', [
        'document_type_id' => 999,
        'document_status_id' => 999,
        'document_visibility_id' => 999,
        'title' => '',
        'file' => UploadedFile::fake()->create('malware.exe', 4, 'application/x-msdownload'),
    ])->assertSessionHasErrors([
        'document_type_id',
        'document_status_id',
        'document_visibility_id',
        'title',
        'file',
    ]);
});
