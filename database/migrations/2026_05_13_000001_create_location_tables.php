<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 2)->default('');
                $table->string('name', 100)->default('');
                $table->tinyInteger('status')->default(5)->comment('5 = Active , 10 = Inactive');
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->unique('code');
            });
        }

        if (!Schema::hasTable('states')) {
            Schema::create('states', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->unsignedInteger('country_id');
                $table->tinyInteger('status')->default(5)->comment('5 = Active, 10 = Inactive');
                $table->timestamps();
                $table->softDeletes();

                $table->index('country_id');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->unsignedBigInteger('state_id');
                $table->tinyInteger('status')->default(5)->comment('5 = Active, 10 = Inactive');
                $table->timestamps();
                $table->softDeletes();

                $table->index('state_id');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
