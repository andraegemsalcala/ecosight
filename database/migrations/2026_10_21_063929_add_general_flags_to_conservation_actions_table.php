<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conservation_actions', function (Blueprint $table) {
            $table->foreignId('animal_id')->nullable()->change();
            $table->boolean('is_general')->default(false);
            $table->string('group', 50)->nullable();
            $table->index(['is_general', 'group']);
        });
    }

    public function down(): void
    {
        DB::table('conservation_actions')->whereNull('animal_id')->delete();

        Schema::table('conservation_actions', function (Blueprint $table) {
            $table->dropIndex(['is_general', 'group']);
            $table->dropColumn(['is_general', 'group']);
            $table->foreignId('animal_id')->nullable(false)->change();
        });
    }
};
