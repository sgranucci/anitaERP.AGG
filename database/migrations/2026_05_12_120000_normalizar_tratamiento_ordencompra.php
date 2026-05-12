<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ordencompra')
            ->whereNotNull('tratamiento')
            ->whereNotIn('tratamiento', ['NO ANTICIPADA', 'ANTICIPADA'])
            ->update(['tratamiento' => 'NO ANTICIPADA']);
    }

    public function down(): void
    {
        // No se revierte: valores legacy no recuperables de forma fiable.
    }
};
