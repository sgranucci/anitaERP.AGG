<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        Schema::table('arbolaprobacion', function (Blueprint $table) {
            if (! Schema::hasColumn('arbolaprobacion', 'oc_disparar_arbol_al_alta')) {
                $table->char('oc_disparar_arbol_al_alta', 1)->default('N')->after('estado');
            }
        });

        if (Schema::hasColumn('arbolaprobacion', 'oc_gastronomia_centrocosto_id')
            && ! Schema::hasColumn('arbolaprobacion', 'oc_sector_cambio_centrocosto_id')) {
            MigrationDialectSupport::statementPorDriver(
                'ALTER TABLE arbolaprobacion DROP FOREIGN KEY fk_arbolaprobacion_oc_gastro_cc',
                'ALTER TABLE arbolaprobacion DROP CONSTRAINT IF EXISTS fk_arbolaprobacion_oc_gastro_cc'
            );
            MigrationDialectSupport::renombrarColumna(
                'arbolaprobacion',
                'oc_gastronomia_centrocosto_id',
                'oc_sector_cambio_centrocosto_id',
                'BIGINT UNSIGNED NULL'
            );
            MigrationDialectSupport::statementPorDriver(
                'ALTER TABLE arbolaprobacion ADD CONSTRAINT fk_arbolaprobacion_oc_sector_cambio_cc FOREIGN KEY (oc_sector_cambio_centrocosto_id) REFERENCES centrocosto(id) ON DELETE SET NULL',
                'ALTER TABLE arbolaprobacion ADD CONSTRAINT fk_arbolaprobacion_oc_sector_cambio_cc FOREIGN KEY (oc_sector_cambio_centrocosto_id) REFERENCES centrocosto(id) ON DELETE SET NULL'
            );
        }

        Schema::table('arbolaprobacion', function (Blueprint $table) {
            if (! Schema::hasColumn('arbolaprobacion', 'oc_sector_cambio_centrocosto_id')) {
                $table->unsignedBigInteger('oc_sector_cambio_centrocosto_id')->nullable()->after('oc_disparar_arbol_al_alta');
                $table->foreign('oc_sector_cambio_centrocosto_id', 'fk_arbolaprobacion_oc_sector_cambio_cc')
                    ->references('id')->on('centrocosto')->nullOnDelete();
            }
            if (! Schema::hasColumn('arbolaprobacion', 'oc_sector_disparo_aprobacion_id')) {
                $after = Schema::hasColumn('arbolaprobacion', 'oc_sector_cambio_centrocosto_id')
                    ? 'oc_sector_cambio_centrocosto_id'
                    : 'oc_disparar_arbol_al_alta';
                $table->unsignedBigInteger('oc_sector_disparo_aprobacion_id')->nullable()->after($after);
                if (Schema::hasTable('sector_legajocompra')) {
                    $table->foreign('oc_sector_disparo_aprobacion_id', 'fk_arbolaprobacion_oc_sector_disparo')
                        ->references('id')->on('sector_legajocompra')->nullOnDelete();
                }
            }
        });

        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'circuito_oc')) {
            DB::table('arbolaprobacion_movimiento')
                ->where('circuito_oc', 'gastronomia')
                ->update(['circuito_oc' => 'sector']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion_movimiento')
            && Schema::hasColumn('arbolaprobacion_movimiento', 'circuito_oc')) {
            DB::table('arbolaprobacion_movimiento')
                ->where('circuito_oc', 'sector')
                ->update(['circuito_oc' => 'gastronomia']);
        }

        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        Schema::table('arbolaprobacion', function (Blueprint $table) {
            if (Schema::hasColumn('arbolaprobacion', 'oc_sector_disparo_aprobacion_id')) {
                $table->dropForeign('fk_arbolaprobacion_oc_sector_disparo');
                $table->dropColumn('oc_sector_disparo_aprobacion_id');
            }
            if (Schema::hasColumn('arbolaprobacion', 'oc_sector_cambio_centrocosto_id')) {
                $table->renameColumn('oc_sector_cambio_centrocosto_id', 'oc_gastronomia_centrocosto_id');
            }
            if (Schema::hasColumn('arbolaprobacion', 'oc_disparar_arbol_al_alta')) {
                $table->dropColumn('oc_disparar_arbol_al_alta');
            }
        });
    }
};
