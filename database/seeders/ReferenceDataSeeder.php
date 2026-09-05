<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Static lookup data: selling countries, the world country picker, Malaysian
 * states and the postcode index. Sourced from `database/data/` so a fresh
 * clone can seed without reaching the source database — see that directory's
 * README for provenance.
 *
 * Idempotent: every table is upserted on its natural key, so re-running only
 * refreshes values. The `state` table is deliberately NOT seeded; its
 * shipping_zone drives postage and COD fees and must come from live data.
 */
class ReferenceDataSeeder extends Seeder
{
    private const CHUNK = 1000;

    public function run(): void
    {
        $this->seedListCountry();
        $this->seedAllCountry();
        $this->seedStateMy();
        $this->seedPostcodeMy();
    }

    private function seedListCountry(): void
    {
        $rows = array_map(fn (array $r) => $r + [
            'created_at' => now(),
            'updated_at' => now(),
        ], $this->json('list_country.json'));

        DB::table('list_country')->upsert($rows, ['id'], ['name', 'sign', 'rate', 'phone_code', 'status']);

        $this->command?->info('  list_country   '.count($rows).' rows');
    }

    private function seedAllCountry(): void
    {
        $rows = $this->json('all_country.json');

        DB::table('all_country')->upsert($rows, ['id'], ['name', 'sign', 'phone_code']);

        $this->command?->info('  all_country    '.count($rows).' rows');
    }

    private function seedStateMy(): void
    {
        $rows = $this->json('state_my.json');

        DB::table('state_my')->upsert($rows, ['state_code'], ['state_name']);

        $this->command?->info('  state_my       '.count($rows).' rows');
    }

    /**
     * postcode_my has no unique key — a postcode maps to many areas and the
     * same area name recurs across post offices — so it is replaced wholesale
     * rather than upserted.
     */
    private function seedPostcodeMy(): void
    {
        $path = database_path('data/postcode_my.csv.gz');

        if (! is_file($path)) {
            throw new RuntimeException("Missing reference data file: {$path}");
        }

        // delete(), not truncate(): TRUNCATE implicitly commits in MySQL, which
        // would break the transaction RefreshDatabase wraps each test in.
        DB::table('postcode_my')->delete();

        $handle = gzopen($path, 'rb');
        gzgets($handle); // header

        $buffer = [];
        $total = 0;

        while (($line = gzgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            [$postcode, $areaName, $postOffice, $stateCode] = str_getcsv($line, ',', '"', '\\');

            $buffer[] = [
                'postcode' => $postcode,
                'area_name' => $areaName,
                'post_office' => $postOffice,
                'state_code' => $stateCode,
            ];

            if (count($buffer) >= self::CHUNK) {
                DB::table('postcode_my')->insert($buffer);
                $total += count($buffer);
                $buffer = [];
            }
        }

        gzclose($handle);

        if ($buffer !== []) {
            DB::table('postcode_my')->insert($buffer);
            $total += count($buffer);
        }

        $this->command?->info('  postcode_my    '.$total.' rows');
    }

    /** @return list<array<string, mixed>> */
    private function json(string $file): array
    {
        $path = database_path("data/{$file}");

        if (! is_file($path)) {
            throw new RuntimeException("Missing reference data file: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || $decoded === []) {
            throw new RuntimeException("Reference data file is empty or malformed: {$path}");
        }

        return $decoded;
    }
}
