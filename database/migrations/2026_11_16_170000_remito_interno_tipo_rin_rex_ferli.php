<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remito interno Facturación Local: tipo RIN / REX (series distintas por local).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('remito_interno')) {
            return;
        }

        if (! Schema::hasColumn('remito_interno', 'tipo')) {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->string('tipo', 3)->default('RIN')->after('numero');
            });
        }

        DB::table('remito_interno')
            ->where(function ($q) {
                $q->whereNull('tipo')->orWhere('tipo', '');
            })
            ->update(['tipo' => 'RIN']);

        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->dropUnique('uq_ri_local_numero');
            });
        } catch (\Throwable $e) {
            // índice ausente o ya migrado
        }

        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->unique(['local_venta_id', 'tipo', 'numero'], 'uq_ri_local_tipo_numero');
            });
        } catch (\Throwable $e) {
            // unique ya existe
        }

        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->index(['tipo', 'local_venta_id'], 'idx_ri_tipo_local');
            });
        } catch (\Throwable $e) {
            // índice ya existe
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('remito_interno')) {
            return;
        }

        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->dropIndex('idx_ri_tipo_local');
            });
        } catch (\Throwable $e) {
            //
        }

        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->dropUnique('uq_ri_local_tipo_numero');
            });
        } catch (\Throwable $e) {
            //
        }

        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->unique(['local_venta_id', 'numero'], 'uq_ri_local_numero');
            });
        } catch (\Throwable $e) {
            //
        }

        if (Schema::hasColumn('remito_interno', 'tipo')) {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }
    }
};
