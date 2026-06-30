<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bien_uso')
            ->where('tipo_bien', 'M')
            ->update(['hostname' => null]);
    }

    public function down(): void
    {
        DB::statement('UPDATE bien_uso SET hostname = uid WHERE tipo_bien = ? AND uid IS NOT NULL', ['M']);
    }
};
