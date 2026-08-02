<?php

use App\Models\District;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows authenticated users to view and update their profile', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)->get('/settings')
        ->assertOk()
        ->assertSee('Settings');

    $this->actingAs($user)->get('/profile')
        ->assertRedirect('/settings');

    $this->actingAs($user)->put('/profile', [
        'name' => 'New Name',
        'email' => $user->email,
    ])->assertRedirect('/settings');

    expect($user->fresh()->name)->toBe('New Name');
    expect($user->accountActivities()->where('event', 'profile.updated')->exists())->toBeTrue();
});

it('allows users to upload avatars and update notification preferences', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 128, 128),
    ])->assertRedirect('/settings');

    expect($user->fresh()->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);

    $this->actingAs($user)->put('/profile/notifications', [
        'email_reports' => '1',
        'security_alerts' => '1',
        'appearance' => 'dark',
    ])->assertRedirect('/settings');

    expect($user->fresh()->notification_preferences)->toMatchArray([
        'email_reports' => true,
        'security_alerts' => true,
        'appearance' => 'dark',
        'major_changes' => true,
    ]);

    expect($user->notificationPreferences()->where('category', 'major_changes')->value('enabled'))->toBeTrue();
});

it('stores only selected district ids as account preferences', function (): void {
    $user = User::factory()->create();
    $districts = District::factory()->count(3)->create();

    $this->actingAs($user)->put(route('profile.districts'), [
        'district_ids' => [$districts[0]->id, $districts[2]->id],
    ])->assertRedirect(route('settings.show'));

    expect($user->fresh()->preferredDistricts()->pluck('districts.id')->all())
        ->toEqualCanonicalizing([$districts[0]->id, $districts[2]->id]);
});

it('allows citizens to customize major-change notification categories', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.notifications'), [
        'email_reports' => '0',
        'security_alerts' => '1',
        'appearance' => 'system',
        'major_changes' => '0',
        'procurement_updates' => '1',
        'budget_updates' => '0',
        'audit_updates' => '1',
        'agency_publications' => '0',
    ])->assertRedirect(route('settings.show'));

    expect($user->fresh()->notification_preferences)->toMatchArray([
        'major_changes' => false,
        'procurement_updates' => true,
        'budget_updates' => false,
        'audit_updates' => true,
        'agency_publications' => false,
    ]);
});

it('allows users to change password with current password validation', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldSecurePass123!')]);

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'OldSecurePass123!',
        'password' => 'NewSecurePass123!',
        'password_confirmation' => 'NewSecurePass123!',
    ])->assertRedirect('/settings');

    expect(Hash::check('NewSecurePass123!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->password_changed_at)->not->toBeNull();
});
