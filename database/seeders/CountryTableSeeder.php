<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsLocationSql;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Exception;


class CountryTableSeeder extends Seeder
{
    use ImportsLocationSql;

    public function run()
    {
        $sql = database_path('locations/countries.sql');

        try {
            $this->importLocationSql($sql, 'countries');
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
