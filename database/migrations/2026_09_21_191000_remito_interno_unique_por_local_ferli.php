<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remito interno: número por local (serie RIN Anita), no unique global.
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

        Schema::table('remito_interno', function (Blueprint $table) {
            // unique original sobre numero
            try {
                $table->dropUnique(['numero']);
            } catch (\Throwable $e) {
                // nombre de índice puede variar
            }
        });

        // Intentar drop por nombre típico MySQL/PG
        try {
            Schema::table('remito_interno', function (Blueprint $table) {
                $table->dropUnique('remito_interno_numero_unique');
            });
        } catch (\Throwable $e) {
            // ya dropeado o otro nombre
        }

        Schema::table('remito_interno', function (Blueprint $table) {
            $table->unique(['local_venta_id', 'numero'], 'uq_ri_local_numero');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('remito_interno')) {
            return;
        }

        Schema::table('remito_interno', function (Blueprint $table) {
            try {
                $table->dropUnique('uq_ri_local_numero');
            } catch (\Throwable $e) {
                //
            }
        });

        Schema::table('remito_interno', function (Blueprint $table) {
            $table->unique(['numero']);
        });
    }
};
