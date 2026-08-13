<?php

use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\Role;
use App\Models\SourceArtifactVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function consoleAdmin(): User
{
    $role = Role::query()->create(['name' => 'Administrator', 'slug' => 'admin']);
    $admin = User::factory()->create(['email' => 'console-admin@example.com']);
    $admin->roles()->attach($role);

    return $admin;
}

it('shows quarantined artifacts on the registry, where an operator would look', function (): void {
    // Ten fetched audit reports sat quarantined for a full cycle because no
    // scanner was installed, and no screen said so. An artifact that cannot be
    // scanned is never parsed, so a silent quarantine stops the pipeline dead.
    SourceArtifactVersion::factory()->create(['is_quarantined' => true]);

    $this->actingAs(consoleAdmin())->get('/admin/sources')
        ->assertOk()
        ->assertSee('Quarantined')
        ->assertSee('unscanned files are never parsed');
});

it('reports abstained pages rather than folding them into a total', function (): void {
    // A page counted as extracted is not a page that was read. Reporting only
    // extracted pages overstates how much text the system has, which is the
    // number any analysis plan depends on.
    $run = ExtractionRun::factory()->create(['status' => 'completed']);
    ExtractionPage::factory()->create(['extraction_run_id' => $run->id, 'page_number' => 1, 'extraction_path' => 'abstained']);
    ExtractionPage::factory()->create(['extraction_run_id' => $run->id, 'page_number' => 2, 'extraction_path' => 'abstained']);
    ExtractionPage::factory()->create(['extraction_run_id' => $run->id, 'page_number' => 3, 'extraction_path' => 'native']);

    $this->actingAs(consoleAdmin())->get('/admin/sources/extraction')
        ->assertOk()
        ->assertSee('Abstained')
        ->assertSee('carry no vouched text');
});

it('says nothing was extracted rather than showing an empty table', function (): void {
    $this->actingAs(consoleAdmin())->get('/admin/sources/extraction')
        ->assertOk()
        ->assertSee('Nothing extracted yet');
});

it('keeps the extraction overview behind the registry policy', function (): void {
    $this->actingAs(User::factory()->create())->get('/admin/sources/extraction')->assertForbidden();
});

it('separates a blank page from one it could not read', function (): void {
    // Both are abstentions and they mean opposite things: one is a page with
    // nothing on it, the other is text the recognizer would not vouch for. On
    // the live corpus 184 of 337 abstentions are blank, so a single figure
    // overstates recognition failure by more than half.
    $run = ExtractionRun::factory()->create(['status' => 'completed']);
    ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id, 'page_number' => 1,
        'extraction_path' => 'abstained', 'confidence' => null,
    ]);
    ExtractionPage::factory()->create([
        'extraction_run_id' => $run->id, 'page_number' => 2,
        'extraction_path' => 'abstained', 'confidence' => 59.5,
    ]);

    $this->actingAs(consoleAdmin())->get('/admin/sources/extraction')
        ->assertOk()
        ->assertSee('recognised but below the bar')
        ->assertSee('carried nothing to read');
});
