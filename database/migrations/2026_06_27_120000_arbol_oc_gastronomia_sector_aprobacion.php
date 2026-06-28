<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arbolaprobacion')) {
            Schema::table('arbolaprobacion', function (Blueprint $table) {
                if (! Schema::hasColumn('arbolaprobacion', 'oc_gastronomia_centrocosto_id')) {
                    $table->unsignedBigInteger('oc_gastronomia_centrocosto_id')->nullable()->after('estado');
                    $table->foreign('oc_gastronomia_centrocosto_id', 'fk_arbolaprobacion_oc_gastro_cc')
                        ->references('id')->on('centrocosto')->nullOnDelete();
                }
                if (! Schema::hasColumn('arbolaprobacion', 'oc_sector_destino_aprobacion_id')) {
                    $table->unsignedBigInteger('oc_sector_destino_aprobacion_id')->nullable()->after('oc_gastronomia_centrocosto_id');
                    if (Schema::hasTable('sector_legajocompra')) {
                        $table->foreign('oc_sector_destino_aprobacion_id', 'fk_arbolaprobacion_oc_sector_destino')
                            ->references('id')->on('sector_legajocompra')->nullOnDelete();
                    }
                }
            });
        }

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && ! Schema::hasColumn('arbolaprobacion_movimiento', 'circuito_oc')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->string('circuito_oc', 20)->nullable()->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'circuito_oc')) {
            Schema::table('arbolaprobacion_movimiento', function (Blueprint $table) {
                $table->dropColumn('circuito_oc');
            });
        }

        if (Schema::hasTable('arbolaprobacion')) {
            Schema::table('arbolaprobacion', function (Blueprint $table) {
                if (Schema::hasColumn('arbolaprobacion', 'oc_sector_destino_aprobacion_id')) {
                    $table->dropForeign('fk_arbolaprobacion_oc_sector_destino');
                    $table->dropColumn('oc_sector_destino_aprobacion_id');
                }
                if (Schema::hasColumn('arbolaprobacion', 'oc_gastronomia_centrocosto_id')) {
                    $table->dropForeign('fk_arbolaprobacion_oc_gastro_cc');
                    $table->dropColumn('oc_gastronomia_centrocosto_id');
                }
            });
        }
    }
};
