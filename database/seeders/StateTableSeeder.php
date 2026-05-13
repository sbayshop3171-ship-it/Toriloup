<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsLocationSql;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Exception;


class StateTableSeeder extends Seeder
{
    use ImportsLocationSql;

    public function run()
    {
        $sql = database_path('locations/states.sql');

        try {
            $this->importLocationSql($sql, 'states');
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
