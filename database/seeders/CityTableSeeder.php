<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsLocationSql;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Exception;


class CityTableSeeder extends Seeder
{
    use ImportsLocationSql;

    public function run()
    {
        $sql = database_path('locations/cities.sql');

        try {
            $this->importLocationSql($sql, 'cities');
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
