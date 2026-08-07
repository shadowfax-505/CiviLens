<?php

use App\Models\User;
use Database\Seeders\Concerns\GuardsSeedingEnvironment;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCivicLensSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function seedingProbe(): Seeder
{
    return new class extends Seeder
    {
        use GuardsSeedingEnvironment;

        public function guard(): void
        {
            $this->guardSeedingEnvironment();
        }

        public function password(): string
        {
            return $this->seedingPassword();
        }
    };
}

it('blocks the baseline seeder outside allowed environments', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DatabaseSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'Seeding is blocked in the "production" environment.');

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

it('blocks the demo seeder outside allowed environments', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DemoCivicLensSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'Seeding is blocked in the "production" environment.');

    expect(User::query()->where('email', 'admin@civiclens.test')->exists())->toBeFalse();
});

it('blocks seeding when no environment is allow-listed', function (): void {
    config()->set('civiclens.seeding.allowed_environments', []);

    expect(fn () => seedingProbe()->guard())->toThrow(RuntimeException::class);
});

it('allows seeding in a deliberately allow-listed environment', function (): void {
    app()->detectEnvironment(fn (): string => 'staging');
    config()->set('civiclens.seeding.allowed_environments', ['local', 'testing', 'staging']);

    expect(fn () => seedingProbe()->guard())->not->toThrow(RuntimeException::class);
});

it('refuses the insecure default password outside local and testing', function (): void {
    app()->detectEnvironment(fn (): string => 'staging');
    config()->set('civiclens.seeding.allowed_environments', ['staging']);
    config()->set('civiclens.seeding.baseline_password');

    expect(fn () => seedingProbe()->password())
        ->toThrow(RuntimeException::class, 'CIVICLENS_SEEDING_PASSWORD must be set');

    config()->set('civiclens.seeding.baseline_password', '   ');

    expect(fn () => seedingProbe()->password())
        ->toThrow(RuntimeException::class, 'CIVICLENS_SEEDING_PASSWORD must be set');
});

it('uses the configured seeding password when one is provided', function (): void {
    config()->set('civiclens.seeding.baseline_password', 'a-deliberately-configured-secret');

    expect(seedingProbe()->password())->toBe('a-deliberately-configured-secret');
});

it('seeds the baseline administrator with the configured password in testing', function (): void {
    config()->set('civiclens.seeding.baseline_password', 'a-deliberately-configured-secret');

    $this->seed();

    $baseline = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect(Hash::check('a-deliberately-configured-secret', $baseline->password))->toBeTrue()
        ->and(Hash::check('password', $baseline->password))->toBeFalse();
});
