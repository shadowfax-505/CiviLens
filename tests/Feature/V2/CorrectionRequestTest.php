<?php

use App\Models\CorrectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('accepts an objection without asking who is making it', function (): void {
    // Requiring a name in order to be heard is its own privacy problem, and an
    // anonymous objection to a personal detail is exactly the objection most
    // worth hearing.
    $this->post(route('public.corrections.store'), [
        'kind' => 'withdrawal',
        'about' => 'Page 76 of the cash incentive report shows my telephone number.',
        'reason' => 'It is my personal number and I did not expect it to be searchable.',
    ])->assertRedirect();

    $request = CorrectionRequest::query()->sole();

    expect($request->contact)->toBeNull()
        ->and($request->status)->toBe(CorrectionRequest::RECEIVED)
        ->and($request->reference)->not->toBeEmpty();
});

it('gives the requester a reference they can follow it by', function (): void {
    // The only way an anonymous requester can ask what was decided.
    $this->post(route('public.corrections.store'), [
        'kind' => 'correction',
        'about' => 'A figure on page 12.',
        'reason' => 'The page says something else.',
    ])->assertSessionHas('correction_reference');
});

it('will not record a decision without a reason', function (): void {
    // A refusal with no reason is the thing this route exists to stop anyone
    // doing quietly.
    $request = CorrectionRequest::query()->create([
        'reference' => (string) Str::uuid(),
        'about' => 'Something',
        'reason' => 'Some reason',
        'kind' => 'withdrawal',
        'status' => CorrectionRequest::RECEIVED,
    ]);

    $this->artisan('civiclens:corrections', [
        '--decide' => $request->reference,
        '--as' => 'refused',
    ])->assertFailed();

    expect($request->fresh()->status)->toBe(CorrectionRequest::RECEIVED);
});

it('records a refusal as fully as a grant', function (): void {
    // A route that kept only the requests it agreed with could not be audited,
    // and being auditable is the whole claim this project makes about itself.
    $request = CorrectionRequest::query()->create([
        'reference' => (string) Str::uuid(),
        'about' => 'An auditor is named on page 8.',
        'reason' => 'I do not want to be named.',
        'kind' => 'withdrawal',
        'status' => CorrectionRequest::RECEIVED,
    ]);

    $this->artisan('civiclens:corrections', [
        '--decide' => $request->reference,
        '--as' => 'refused',
        '--because' => 'The name appears as the signing auditor in an official capacity, which the publisher itself published.',
    ])->assertSuccessful();

    $decided = $request->fresh();

    expect($decided->status)->toBe(CorrectionRequest::REFUSED)
        ->and($decided->decision)->toContain('official capacity')
        ->and($decided->decided_at)->not->toBeNull();
});

it('treats waiting requests as a state somebody must act on', function (): void {
    // A queue nobody looks at is the failure mode this route exists to avoid.
    CorrectionRequest::query()->create([
        'reference' => (string) Str::uuid(),
        'about' => 'Something',
        'reason' => 'Some reason',
        'kind' => 'correction',
        'status' => CorrectionRequest::RECEIVED,
    ]);

    $this->artisan('civiclens:corrections')->assertFailed();
});

it('says on the page that refusals are recorded too', function (): void {
    $this->get(route('public.corrections.create'))
        ->assertOk()
        ->assertSee('including the ones we refuse');
});
