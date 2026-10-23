<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_varieties', function (Blueprint $table) {
            $table->string('kind', 20)->default('breed')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('animal_varieties', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
