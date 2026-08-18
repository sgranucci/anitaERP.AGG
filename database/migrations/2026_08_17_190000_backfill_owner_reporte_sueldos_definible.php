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

        DB::statement("
            UPDATE reporte_sueldos_definible r
            LEFT JOIN (
                SELECT reporte_sueldos_definible_id, MIN(usuario_id) AS uid
                FROM reporte_sueldos_definible_version
                WHERE usuario_id IS NOT NULL
                GROUP BY reporte_sueldos_definible_id
            ) v ON v.reporte_sueldos_definible_id = r.id
            SET r.owner_id = v.uid
            WHERE r.owner_id IS NULL AND v.uid IS NOT NULL
        ");
    }

    public function down(): void
    {
        // No revierte el backfill: owner_id queda como evidencia de gobierno.
    }
};
