<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vigencia de tipos/subtipos de bóveda + signo opcional en subtipo (BINGO mixto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            Schema::table('tesoreria_crown_boveda_tipo', function (Blueprint $table) {
                if (! Schema::hasColumn('tesoreria_crown_boveda_tipo', 'vigente_desde')) {
                    $table->date('vigente_desde')->nullable()->after('activo');
                }
                if (! Schema::hasColumn('tesoreria_crown_boveda_tipo', 'vigente_hasta')) {
                    $table->date('vigente_hasta')->nullable()->after('vigente_desde');
                }
            });
        }

        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            Schema::table('tesoreria_crown_boveda_subtipo', function (Blueprint $table) {
                if (! Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'vigente_desde')) {
                    $table->date('vigente_desde')->nullable()->after('mueve_billetaje');
                }
                if (! Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'vigente_hasta')) {
                    $table->date('vigente_hasta')->nullable()->after('vigente_desde');
                }
                if (! Schema::hasColumn('tesoreria_crown_boveda_subtipo', 'signo')) {
                    $table->string('signo', 1)->nullable()->after('vigente_hasta');
                }
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            Schema::table('tesoreria_crown_boveda_tipo', function (Blueprint $table) {
                if (Schema::hasColumn('tesoreria_crown_boveda_tipo', 'vigente_hasta')) {
                    $table->dropColumn('vigente_hasta');
                }
                if (Schema::hasColumn('tesoreria_crown_boveda_tipo', 'vigente_desde')) {
                    $table->dropColumn('vigente_desde');
                }
            });
        }

        if (Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            Schema::table('tesoreria_crown_boveda_subtipo', function (Blueprint $table) {
                foreach (['signo', 'vigente_hasta', 'vigente_desde'] as $col) {
                    if (Schema::hasColumn('tesoreria_crown_boveda_subtipo', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
