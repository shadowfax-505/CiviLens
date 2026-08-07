<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

trait GuardsSeedingEnvironment
{
    protected function guardSeedingEnvironment(): void
    {
        $allowed = $this->allowedSeedingEnvironments();

        if ($allowed === [] || ! app()->environment($allowed)) {
            throw new RuntimeException(sprintf(
                'Seeding is blocked in the "%s" environment. Set CIVICLENS_SEEDING_ENVIRONMENTS to allow it deliberately.',
                app()->environment(),
            ));
        }
    }

    protected function seedingPassword(): string
    {
        $password = config('civiclens.seeding.baseline_password');

        if (is_string($password) && trim($password) !== '') {
            return $password;
        }

        $insecureDefaults = config('civiclens.seeding.insecure_default_environments', []);

        if (is_array($insecureDefaults) && $insecureDefaults !== [] && app()->environment($insecureDefaults)) {
            return 'password';
        }

        throw new RuntimeException(sprintf(
            'CIVICLENS_SEEDING_PASSWORD must be set before seeding accounts in the "%s" environment.',
            app()->environment(),
        ));
    }

    /** @return list<string> */
    private function allowedSeedingEnvironments(): array
    {
        $allowed = config('civiclens.seeding.allowed_environments', []);

        if (! is_array($allowed)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $environment): string => is_string($environment) ? trim($environment) : '', $allowed),
            static fn (string $environment): bool => $environment !== '',
        ));
    }
}
