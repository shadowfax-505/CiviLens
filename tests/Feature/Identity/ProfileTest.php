<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows authenticated users to view and update their profile', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertSee('Profile');

    $this->actingAs($user)->put('/profile', [
        'name' => 'New Name',
        'email' => $user->email,
    ])->assertRedirect('/profile');

    expect($user->fresh()->name)->toBe('New Name');
    expect($user->accountActivities()->where('event', 'profile.updated')->exists())->toBeTrue();
});

it('allows users to upload avatars and update notification preferences', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 128, 128),
    ])->assertRedirect('/profile');

    expect($user->fresh()->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);

    $this->actingAs($user)->put('/profile/notifications', [
        'email_reports' => '1',
        'security_alerts' => '1',
    ])->assertRedirect('/profile');

    expect($user->fresh()->notification_preferences)->toMatchArray([
        'email_reports' => true,
        'security_alerts' => true,
    ]);
});

it('allows users to change password with current password validation', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldSecurePass123!')]);

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'OldSecurePass123!',
        'password' => 'NewSecurePass123!',
        'password_confirmation' => 'NewSecurePass123!',
    ])->assertRedirect('/profile');

    expect(Hash::check('NewSecurePass123!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->password_changed_at)->not->toBeNull();
});
