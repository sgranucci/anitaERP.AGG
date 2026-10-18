<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown KDS: prepara/no prepara (paridad AGG waitry_preparar) + snapshot en línea de cuenta.
 * El destino de estación sigue siendo area_comanda_gastronomia_id (elegible en POS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (EntornoEmpresaSupport::esCrown() || EntornoEmpresaSupport::esAgg()) {
            if (Schema::hasTable('articulo') && ! Schema::hasColumn('articulo', 'kds_preparar')) {
                Schema::table('articulo', function (Blueprint $table) {
                    $table->boolean('kds_preparar')->default(true)->after('formula');
                });
                // En AGG, alinear con waitry_preparar si existe
                if (Schema::hasColumn('articulo', 'waitry_preparar')) {
                    \Illuminate\Support\Facades\DB::table('articulo')->update([
                        'kds_preparar' => \Illuminate\Support\Facades\DB::raw('waitry_preparar'),
                    ]);
                }
            }
        }

        if (Schema::hasTable('gastro_crown_cuenta_linea')
            && ! Schema::hasColumn('gastro_crown_cuenta_linea', 'prepara')) {
            Schema::table('gastro_crown_cuenta_linea', function (Blueprint $table) {
                $table->boolean('prepara')->default(true)->after('es_separador');
            });
        }

        if (Schema::hasTable('gastro_crown_comanda_linea')
            && ! Schema::hasColumn('gastro_crown_comanda_linea', 'prepara')) {
            Schema::table('gastro_crown_comanda_linea', function (Blueprint $table) {
                $table->boolean('prepara')->default(true)->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gastro_crown_comanda_linea')
            && Schema::hasColumn('gastro_crown_comanda_linea', 'prepara')) {
            Schema::table('gastro_crown_comanda_linea', function (Blueprint $table) {
                $table->dropColumn('prepara');
            });
        }
        if (Schema::hasTable('gastro_crown_cuenta_linea')
            && Schema::hasColumn('gastro_crown_cuenta_linea', 'prepara')) {
            Schema::table('gastro_crown_cuenta_linea', function (Blueprint $table) {
                $table->dropColumn('prepara');
            });
        }
        if ((EntornoEmpresaSupport::esCrown() || EntornoEmpresaSupport::esAgg())
            && Schema::hasTable('articulo')
            && Schema::hasColumn('articulo', 'kds_preparar')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->dropColumn('kds_preparar');
            });
        }
    }
};
