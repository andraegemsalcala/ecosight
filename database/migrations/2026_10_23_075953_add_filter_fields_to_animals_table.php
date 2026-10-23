<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->string('animal_class', 32)->nullable()->after('group');
            $table->boolean('is_domestic')->nullable()->after('animal_class');
            $table->string('status_category', 32)->nullable()->after('conservation_status');

            $table->index('animal_class');
            $table->index('is_domestic');
            $table->index('status_category');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropIndex(['animal_class']);
            $table->dropIndex(['is_domestic']);
            $table->dropIndex(['status_category']);
            $table->dropColumn(['animal_class', 'is_domestic', 'status_category']);
        });
    }
};
