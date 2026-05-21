<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('nuevo_talento')->default(false)->after('entradas_vendidas');
        });

        // Marcar artistas emergentes del seeder
        DB::table('eventos')
            ->whereIn('artista', ['C. Tangana', 'Peso Pluma'])
            ->update(['nuevo_talento' => true]);
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('nuevo_talento');
        });
    }
};
