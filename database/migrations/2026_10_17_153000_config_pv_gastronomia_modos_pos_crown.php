<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de modo POS Crown en configuración por PC.
 * Columnas en todos los entornos (default seguro); la UI solo se muestra si esCrown().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_puntoventa_gastronomia')) {
            return;
        }

        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'modo_pos')) {
                $table->string('modo_pos', 20)->default('resto')->after('descripcion');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'politica_comanda')) {
                $table->string('politica_comanda', 30)->default('factura_y_comanda')->after('modo_pos');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'solo_marcha')) {
                $table->boolean('solo_marcha')->default(false)->after('politica_comanda');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'habilita_hotel')) {
                $table->boolean('habilita_hotel')->default(false)->after('solo_marcha');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'habilita_canje_gst')) {
                $table->boolean('habilita_canje_gst')->default(true)->after('habilita_hotel');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'pide_cubiertos')) {
                $table->boolean('pide_cubiertos')->default(true)->after('habilita_canje_gst');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'pide_mozo_manual')) {
                $table->boolean('pide_mozo_manual')->default(false)->after('pide_cubiertos');
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'sala_id')) {
                $table->unsignedBigInteger('sala_id')->nullable()->after('ubicacion_id');
                $table->foreign('sala_id', 'fk_config_pv_gastro_sala')
                    ->references('id')->on('sala')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'listaprecio_hotel_id')) {
                $table->unsignedBigInteger('listaprecio_hotel_id')->nullable()->after('listaprecio_id');
                $table->foreign('listaprecio_hotel_id', 'fk_config_pv_gastro_lista_hotel')
                    ->references('id')->on('listaprecio')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'salida_hotel_id')) {
                $table->unsignedBigInteger('salida_hotel_id')->nullable()->after('salida_factura_id');
                $table->foreign('salida_hotel_id', 'fk_config_pv_gastro_salida_hotel')
                    ->references('id')->on('salida')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'salida_comanda_b_id')) {
                $table->unsignedBigInteger('salida_comanda_b_id')->nullable()->after('salida_comanda_id');
                $table->foreign('salida_comanda_b_id', 'fk_config_pv_gastro_salida_comanda_b')
                    ->references('id')->on('salida')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('configuracion_puntoventa_gastronomia', 'salida_comanda_k_id')) {
                $table->unsignedBigInteger('salida_comanda_k_id')->nullable()->after('salida_comanda_b_id');
                $table->foreign('salida_comanda_k_id', 'fk_config_pv_gastro_salida_comanda_k')
                    ->references('id')->on('salida')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasTable('area_comanda_gastronomia')
            && ! Schema::hasColumn('area_comanda_gastronomia', 'salida_id')) {
            Schema::table('area_comanda_gastronomia', function (Blueprint $table) {
                $table->unsignedBigInteger('salida_id')->nullable()->after('empresa_id');
                $table->foreign('salida_id', 'fk_area_comanda_gastro_salida')
                    ->references('id')->on('salida')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('area_comanda_gastronomia')
            && Schema::hasColumn('area_comanda_gastronomia', 'salida_id')) {
            Schema::table('area_comanda_gastronomia', function (Blueprint $table) {
                $table->dropForeign('fk_area_comanda_gastro_salida');
                $table->dropColumn('salida_id');
            });
        }

        if (! Schema::hasTable('configuracion_puntoventa_gastronomia')) {
            return;
        }

        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            foreach ([
                'fk_config_pv_gastro_sala' => 'sala_id',
                'fk_config_pv_gastro_lista_hotel' => 'listaprecio_hotel_id',
                'fk_config_pv_gastro_salida_hotel' => 'salida_hotel_id',
                'fk_config_pv_gastro_salida_comanda_b' => 'salida_comanda_b_id',
                'fk_config_pv_gastro_salida_comanda_k' => 'salida_comanda_k_id',
            ] as $fk => $col) {
                if (Schema::hasColumn('configuracion_puntoventa_gastronomia', $col)) {
                    $table->dropForeign($fk);
                }
            }
            $cols = [
                'modo_pos', 'politica_comanda', 'solo_marcha', 'habilita_hotel', 'habilita_canje_gst',
                'pide_cubiertos', 'pide_mozo_manual', 'sala_id', 'listaprecio_hotel_id',
                'salida_hotel_id', 'salida_comanda_b_id', 'salida_comanda_k_id',
            ];
            $drop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('configuracion_puntoventa_gastronomia', $c)));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
