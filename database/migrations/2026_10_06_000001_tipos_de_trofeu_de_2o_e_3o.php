<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trofeus MODIFY tipo ENUM('vitoria', 'empate', 'segundo', 'terceiro') NOT NULL");
    }

    public function down(): void
    {
        DB::table('trofeus')->whereIn('tipo', ['segundo', 'terceiro'])->delete();
        DB::statement("ALTER TABLE trofeus MODIFY tipo ENUM('vitoria', 'empate') NOT NULL");
    }
};
