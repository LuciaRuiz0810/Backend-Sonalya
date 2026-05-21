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
        Schema::create('canciones_favoritas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('deezer_id');
            $table->string('titulo');
            $table->string('artista');
            $table->string('imagen')->nullable();
            $table->string('duracion')->nullable();
            $table->string('preview')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'deezer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canciones_favoritas');
    }
};
