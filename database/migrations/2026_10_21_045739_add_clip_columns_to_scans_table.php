<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('model_source', 20)->nullable()->after('detected_label');
            $table->string('top2_label', 100)->nullable()->after('model_source');
            $table->decimal('top2_confidence', 5, 4)->nullable()->after('top2_label');
            $table->boolean('user_confirmed')->default(false)->after('top2_confidence');
            $table->foreignId('variety_id')->nullable()->constrained('animal_varieties')->nullOnDelete()->after('user_confirmed');
            $table->string('variety_label', 100)->nullable()->after('variety_id');
            $table->decimal('variety_confidence', 5, 4)->nullable()->after('variety_label');
            $table->boolean('variety_confirmed')->default(false)->after('variety_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn([
                'model_source',
                'top2_label',
                'top2_confidence',
                'user_confirmed',
                'variety_id',
                'variety_label',
                'variety_confidence',
                'variety_confirmed',
            ]);
        });
    }
};
