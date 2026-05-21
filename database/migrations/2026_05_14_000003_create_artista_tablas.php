<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artista_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->string('genero')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('sitio_web')->nullable();
            $table->string('imagen_portada')->nullable();
            $table->timestamps();
        });

        Schema::create('albumes_artista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artista_id')->constrained('users')->onDelete('cascade');
            $table->string('titulo');
            $table->string('imagen')->nullable();
            $table->string('genero')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('publicado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('canciones_artista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artista_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('album_id')->nullable()->constrained('albumes_artista')->onDelete('set null');
            $table->string('titulo');
            $table->string('duracion')->default('0:00');
            $table->string('imagen')->nullable();
            $table->text('audio_url')->nullable();
            $table->string('genero')->nullable();
            $table->unsignedBigInteger('reproducciones')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('seguidores_artista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artista_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seguidor_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['artista_id', 'seguidor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguidores_artista');
        Schema::dropIfExists('canciones_artista');
        Schema::dropIfExists('albumes_artista');
        Schema::dropIfExists('artista_perfiles');
    }
};
