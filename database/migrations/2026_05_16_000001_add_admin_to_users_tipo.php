<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY tipo ENUM('oyente', 'artista', 'admin') DEFAULT 'oyente'");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET tipo = 'oyente' WHERE tipo = 'admin'");
        DB::statement("ALTER TABLE users MODIFY tipo ENUM('oyente', 'artista') DEFAULT 'oyente'");
    }
};
