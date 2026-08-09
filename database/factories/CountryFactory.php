<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Codes handed out in this process but not necessarily written yet.
     *
     * count(40) builds every model before saving any, so each definition sees
     * the same empty table and a check against the database alone hands the
     * same code out repeatedly. That is how the first version of this fix
     * failed CI while passing locally.
     *
     * Bounded by the 676 two-letter combinations, and the suite creates a few
     * dozen countries per process, so this cannot grow to matter. Exhaustion
     * throws rather than wrapping around.
     *
     * @var list<string>
     */
    private static array $issued = [];

    /**
     * Forget the codes handed out so far.
     *
     * Called between tests. Without it the memory outlives the database it was
     * tracking: a refreshed database holds none of those codes, but they would
     * still be treated as taken, so the usable space would shrink with every
     * test until it ran out.
     */
    public static function forgetIssuedCodes(): void
    {
        self::$issued = [];
    }

    public function definition(): array
    {
        $iso2 = $this->unusedIso2();

        return [
            // name is unique too, and unique() is no more aware of the seeded
            // "Bangladesh" row here than it was of the seeded BD code. The
            // country code is already unique, so appending it makes the name
            // unique by construction rather than by retrying and hoping.
            'name' => fake()->country().' '.$iso2,
            'iso2' => $iso2,
            'iso3' => $this->unusedIso3($iso2),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geojson' => null,
        ];
    }

    /**
     * A country code no row is already using.
     *
     * Faker's unique() only remembers what it generated itself, so it happily
     * returns a code the seeder inserted: the suite seeds Bangladesh as BD, and
     * a run that drew BD from the 676 two-letter combinations failed on the
     * unique index. That is one run in a few hundred — frequent enough to fail
     * CI, rare enough to look like an unrelated fluke.
     */
    private function unusedIso2(): string
    {
        return $this->pick(
            $this->codes(),
            Country::query()->pluck('iso2')->all(),
            'iso2',
        );
    }

    /**
     * Derived from the chosen iso2 so the two agree, as real codes do.
     */
    private function unusedIso3(string $iso2): string
    {
        return $this->pick(
            array_map(static fn (string $letter): string => $iso2.$letter, range('A', 'Z')),
            Country::query()->pluck('iso3')->all(),
            'iso3',
        );
    }

    /**
     * @param  list<string>  $space
     * @param  list<string>  $taken
     */
    private function pick(array $space, array $taken, string $column): string
    {
        $available = array_values(array_diff(
            $space,
            array_map(static fn (mixed $code): string => strtoupper((string) $code), $taken),
            self::$issued,
        ));

        if ($available === []) {
            // Silently reusing a code would trade a clear failure here for a
            // confusing constraint violation later.
            throw new RuntimeException("No unused {$column} remains for CountryFactory.");
        }

        $code = $available[array_rand($available)];
        self::$issued[] = $code;

        return $code;
    }

    /** @return list<string> */
    private function codes(): array
    {
        $codes = [];

        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $codes[] = $first.$second;
            }
        }

        return $codes;
    }
}
