<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->text('habitat')->nullable()->change();
            $table->text('climate_impact')->nullable()->change();
            $table->string('wikidata_qid', 20)->nullable()->unique();
            $table->string('source_url', 500)->nullable();
            $table->text('source_note')->nullable();
            $table->string('origin', 20)->default('seeded');
            $table->boolean('is_reviewed')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['origin', 'is_reviewed']);
        });
    }

    public function down(): void
    {
        DB::table('animals')->whereNull('habitat')->update(['habitat' => '']);
        DB::table('animals')->whereNull('climate_impact')->update(['climate_impact' => '']);

        Schema::table('animals', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropUnique(['wikidata_qid']);
            $table->dropIndex(['origin', 'is_reviewed']);
            $table->dropColumn([
                'wikidata_qid',
                'source_url',
                'source_note',
                'origin',
                'is_reviewed',
                'added_by',
            ]);
            $table->text('habitat')->nullable(false)->change();
            $table->text('climate_impact')->nullable(false)->change();
        });
    }
};
