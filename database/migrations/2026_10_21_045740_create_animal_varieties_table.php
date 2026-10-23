<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->json('clip_names')->nullable();
            $table->timestamps();
            $table->unique(['animal_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_varieties');
    }
};
