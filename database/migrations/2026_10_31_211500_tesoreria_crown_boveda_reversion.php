<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La reversa de bóveda es otro movimiento (revierte_id) con el motivo escrito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_boveda')) {
            return;
        }

        Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_crown_boveda', 'motivo')) {
                $table->string('motivo', 500)->nullable()->after('comentario');
            }
            if (! Schema::hasColumn('tesoreria_crown_boveda', 'revierte_id')) {
                $table->unsignedBigInteger('revierte_id')->nullable()->after('comprobante_id');
                $table->foreign('revierte_id', 'tc_boveda_revierte_fk')
                    ->references('id')->on('tesoreria_crown_boveda');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_boveda')) {
            return;
        }

        Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_crown_boveda', 'revierte_id')) {
                $table->dropForeign('tc_boveda_revierte_fk');
                $table->dropColumn('revierte_id');
            }
            if (Schema::hasColumn('tesoreria_crown_boveda', 'motivo')) {
                $table->dropColumn('motivo');
            }
        });
    }
};
