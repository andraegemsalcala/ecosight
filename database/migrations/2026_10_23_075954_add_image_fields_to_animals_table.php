<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('image_url');
            $table->string('image_alt', 255)->nullable()->after('image_path');
            $table->string('image_credit', 255)->nullable()->after('image_alt');
            $table->string('image_license', 100)->nullable()->after('image_credit');
            $table->text('image_source_url')->nullable()->after('image_license');
            $table->timestamp('image_reviewed_at')->nullable()->after('image_source_url');

            $table->index('image_path');
            $table->index('image_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropIndex(['image_path']);
            $table->dropIndex(['image_reviewed_at']);
            $table->dropColumn([
                'image_path',
                'image_alt',
                'image_credit',
                'image_license',
                'image_source_url',
                'image_reviewed_at',
            ]);
        });
    }
};
