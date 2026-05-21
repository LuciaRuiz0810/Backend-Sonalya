<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canciones_favoritas', function (Blueprint $table) {
            $table->text('preview')->nullable()->change();
        });

        Schema::table('historial_reproducciones', function (Blueprint $table) {
            $table->text('preview')->nullable()->change();
        });

        Schema::table('playlist_canciones', function (Blueprint $table) {
            $table->text('preview')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('canciones_favoritas', function (Blueprint $table) {
            $table->string('preview')->nullable()->change();
        });

        Schema::table('historial_reproducciones', function (Blueprint $table) {
            $table->string('preview')->nullable()->change();
        });

        Schema::table('playlist_canciones', function (Blueprint $table) {
            $table->string('preview')->nullable()->change();
        });
    }
};
