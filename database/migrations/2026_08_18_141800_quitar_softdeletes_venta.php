<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SoftDeletes en venta nunca se usó (0 filas). Libro IVA “anulados” leía onlyTrashed y
 * siempre salía vacío. Los 249 venta_impuesto blandos son recálculos viejos; las facturas
 * ya tienen líneas vivas. Baja física + audits. Unique de numeración no incluía deleted_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_impuesto') && Schema::hasColumn('venta_impuesto', 'deleted_at')) {
            $bajas = (int) DB::table('venta_impuesto')->whereNotNull('deleted_at')->count();
            DB::table('venta_impuesto')->whereNotNull('deleted_at')->delete();
            Log::info('venta_impuesto: purga soft-deletes', ['filas' => $bajas]);
            Schema::table('venta_impuesto', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('venta_exportacion') && Schema::hasColumn('venta_exportacion', 'deleted_at')) {
            Schema::table('venta_exportacion', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('venta') && Schema::hasColumn('venta', 'deleted_at')) {
            $this->reemplazarIndiceFechaVenta();
            Schema::table('venta', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venta') && ! Schema::hasColumn('venta', 'deleted_at')) {
            Schema::table('venta', function (Blueprint $table) {
                $table->softDeletes();
            });
            if (MigrationDialectSupport::tieneIndice('venta', 'idx_venta_fecha')) {
                Schema::table('venta', function (Blueprint $table) {
                    $table->dropIndex('idx_venta_fecha');
                });
            }
            if (! MigrationDialectSupport::tieneIndice('venta', 'idx_venta_fecha_deleted')) {
                Schema::table('venta', function (Blueprint $table) {
                    $table->index(['fecha', 'deleted_at'], 'idx_venta_fecha_deleted');
                });
            }
        }

        if (Schema::hasTable('venta_impuesto') && ! Schema::hasColumn('venta_impuesto', 'deleted_at')) {
            Schema::table('venta_impuesto', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('venta_exportacion') && ! Schema::hasColumn('venta_exportacion', 'deleted_at')) {
            Schema::table('venta_exportacion', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    private function reemplazarIndiceFechaVenta(): void
    {
        if (MigrationDialectSupport::tieneIndice('venta', 'idx_venta_fecha_deleted')) {
            Schema::table('venta', function (Blueprint $table) {
                $table->dropIndex('idx_venta_fecha_deleted');
            });
        }
        if (! MigrationDialectSupport::tieneIndice('venta', 'idx_venta_fecha')) {
            Schema::table('venta', function (Blueprint $table) {
                $table->index('fecha', 'idx_venta_fecha');
            });
        }
    }
};
