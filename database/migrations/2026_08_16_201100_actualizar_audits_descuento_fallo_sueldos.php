<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tras renombrar modelos de descuento por fallo, alinear audits.auditable_type.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('audits')
            ->where('auditable_type', 'App\\Models\\Sueldos\\Dtofallo_Sueldos')
            ->update(['auditable_type' => 'App\\Models\\Sueldos\\DescuentoFallo_Sueldos']);

        DB::table('audits')
            ->where('auditable_type', 'App\\Models\\Sueldos\\Cierrefallo_Sueldos')
            ->update(['auditable_type' => 'App\\Models\\Sueldos\\CierreDescuentoFallo_Sueldos']);
    }

    public function down(): void
    {
        DB::table('audits')
            ->where('auditable_type', 'App\\Models\\Sueldos\\DescuentoFallo_Sueldos')
            ->update(['auditable_type' => 'App\\Models\\Sueldos\\Dtofallo_Sueldos']);

        DB::table('audits')
            ->where('auditable_type', 'App\\Models\\Sueldos\\CierreDescuentoFallo_Sueldos')
            ->update(['auditable_type' => 'App\\Models\\Sueldos\\Cierrefallo_Sueldos']);
    }
};
