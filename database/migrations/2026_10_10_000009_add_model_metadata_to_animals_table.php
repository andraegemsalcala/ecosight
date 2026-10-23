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
            $table->string('model_source', 20)->default('coco-ssd')->after('coco_label');
            $table->json('model_labels')->nullable()->after('model_source');
            $table->boolean('is_scannable')->default(true)->after('model_labels');
        });

        DB::table('animals')->whereNull('model_labels')->eachById(function (object $animal): void {
            DB::table('animals')->where('id', $animal->id)->update([
                'model_labels' => json_encode([$animal->coco_label]),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropColumn(['model_source', 'model_labels', 'is_scannable']);
        });
    }
};
