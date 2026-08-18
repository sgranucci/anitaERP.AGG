<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reporte_sueldos_definible', 'owner_id')) {
            return;
        }

        if (! Schema::hasTable('reporte_sueldos_definible_version')) {
            return;
        }

        // Query Builder (no UPDATE…JOIN): portable MySQL / PostgreSQL.
        $owners = DB::table('reporte_sueldos_definible_version')
            ->whereNotNull('usuario_id')
            ->groupBy('reporte_sueldos_definible_id')
            ->selectRaw('reporte_sueldos_definible_id, MIN(usuario_id) as uid')
            ->pluck('uid', 'reporte_sueldos_definible_id');

        foreach ($owners as $reporteId => $uid) {
            DB::table('reporte_sueldos_definible')
                ->where('id', $reporteId)
                ->whereNull('owner_id')
                ->update(['owner_id' => $uid]);
        }
    }

    public function down(): void
    {
        // No revierte el backfill: owner_id queda como evidencia de gobierno.
    }
};
