<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            // Artista de la plataforma que creó este evento (null = creado por admin)
            $table->unsignedBigInteger('artista_user_id')->nullable()->after('entradas_vendidas');
            $table->foreign('artista_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropForeign(['artista_user_id']);
            $table->dropColumn('artista_user_id');
        });
    }
};
