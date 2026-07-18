<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('exposes password recovery from the login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('password.request'))
        ->assertSee('Forgot password?');
});

it('registers a citizen user and dispatches email verification', function (): void {
    Event::fake([Registered::class]);
    Role::query()->create(['name' => 'Citizen', 'slug' => 'citizen']);

    $response = $this->post('/register', [
        'name' => 'Amina Rahman',
        'email' => 'amina@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertRedirect('/verify-email');
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'amina@example.com')->firstOrFail();
    expect($user->hasRole('citizen'))->toBeTrue();
    expect($user->email_verified_at)->toBeNull();
    Event::assertDispatched(Registered::class);
    $this->assertDatabaseHas('email_verification_otps', ['user_id' => $user->id]);
});

it('completes registration end to end without faking events and reaches the verification notice', function (): void {
    Role::query()->create(['name' => 'Citizen', 'slug' => 'citizen']);

    $response = $this->post('/register', [
        'name' => 'Kamal Hossain',
        'email' => 'kamal@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertRedirect('/verify-email');
    $this->assertAuthenticated();
    $this->followRedirects($response)->assertOk();

    $user = User::query()->where('email', 'kamal@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
    $this->assertDatabaseHas('email_verification_otps', ['user_id' => $user->id]);
});

it('verifies an authenticated user with an unexpired OTP', function (): void {
    $user = User::factory()->unverified()->create();

    DB::table('email_verification_otps')->insert([
        'user_id' => $user->id,
        'code_hash' => Hash::make('123456'),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
        'last_sent_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->post(route('verification.otp'), ['code' => '123456'])
        ->assertRedirect('/dashboard');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
});

it('authenticates active users and records login activity', function (): void {
    $user = User::factory()->create(['password' => Hash::make('SecurePass123!')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'SecurePass123!',
        'remember' => 'on',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->last_login_at)->not->toBeNull();
    expect($user->accountActivities()->where('event', 'login')->exists())->toBeTrue();
});

it('redirects unverified administrators to the email verification notice after login', function (): void {
    $user = User::factory()->unverified()->create(['password' => Hash::make('SecurePass123!')]);
    $adminRole = Role::query()->create(['name' => 'Administrator', 'slug' => config('civiclens.roles.admin')]);
    $user->roles()->attach($adminRole);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'SecurePass123!',
    ])->assertRedirect('/verify-email');

    $this->assertAuthenticatedAs($user);
});

it('rejects locked or inactive accounts during login', function (): void {
    $inactive = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('SecurePass123!'),
        'is_active' => false,
    ]);
    $locked = User::factory()->create([
        'email' => 'locked@example.com',
        'password' => Hash::make('SecurePass123!'),
        'locked_at' => now(),
    ]);

    $this->post('/login', ['email' => $inactive->email, 'password' => 'SecurePass123!'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/login', ['email' => $locked->email, 'password' => 'SecurePass123!'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs users out and records logout activity', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    expect($user->accountActivities()->where('event', 'logout')->exists())->toBeTrue();
});

it('sends password reset links and resets passwords', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ])->assertRedirect('/login');

        return Hash::check('NewSecurePass123!', $user->fresh()->password);
    });
});

it('verifies email addresses through signed verification links', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect('/dashboard');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('confirms passwords before sensitive actions', function (): void {
    $user = User::factory()->create(['password' => Hash::make('SecurePass123!')]);

    $this->actingAs($user)->post('/confirm-password', [
        'password' => 'SecurePass123!',
    ])->assertRedirect('/dashboard');

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});
