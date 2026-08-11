<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * empresa_id nullable en maestro proveedor.
 * Uso operativo gated por config('proveedor.filtro_empresa') (default false = AGG sin cambio).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proveedor')) {
            return;
        }

        Schema::table('proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('codigo');
            }
        });

        if (Schema::hasColumn('proveedor', 'empresa_id')) {
            try {
                Schema::table('proveedor', function (Blueprint $table) {
                    $table->foreign('empresa_id', 'fk_proveedor_empresa')
                        ->references('id')
                        ->on('empresa')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // FK ya existe
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('proveedor') || ! Schema::hasColumn('proveedor', 'empresa_id')) {
            return;
        }

        try {
            Schema::table('proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_proveedor_empresa');
            });
        } catch (\Throwable $e) {
        }

        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn('empresa_id');
        });
    }
};
