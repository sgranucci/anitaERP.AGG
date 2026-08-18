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
        if (! Schema::hasColumn('tipotransaccion_stock', 'destino_bien_uso')) {
            Schema::table('tipotransaccion_stock', function (Blueprint $table) {
                $table->boolean('destino_bien_uso')->default(false)->after('maneja_contabilidad');
            });
        }

        if (! Schema::hasColumn('articulo_movimiento', 'bien_uso_id')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('bien_uso_id')->nullable()->after('deposito_id');
                $table->foreign('bien_uso_id', 'fk_articulo_movimiento_bien_uso')
                    ->references('id')->on('bien_uso')->restrictOnDelete()->cascadeOnUpdate();
                $table->index('bien_uso_id', 'ix_articulo_movimiento_bien_uso');
            });
        }

        if (Schema::hasColumn('articulo_movimiento', 'deposito_id')) {
            MigrationDialectSupport::statementPorDriver(
                'ALTER TABLE articulo_movimiento MODIFY deposito_id BIGINT UNSIGNED NULL',
                'ALTER TABLE articulo_movimiento ALTER COLUMN deposito_id DROP NOT NULL'
            );
        }

        if (! Schema::hasColumn('transferencia_mercaderia', 'bien_uso_destino_id')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->unsignedBigInteger('bien_uso_destino_id')->nullable()->after('deposito_destino_id');
                $table->foreign('bien_uso_destino_id', 'fk_tm_bien_uso_destino')
                    ->references('id')->on('bien_uso')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (Schema::hasColumn('transferencia_mercaderia', 'deposito_destino_id')) {
            MigrationDialectSupport::statementPorDriver(
                'ALTER TABLE transferencia_mercaderia MODIFY deposito_destino_id BIGINT UNSIGNED NULL',
                'ALTER TABLE transferencia_mercaderia ALTER COLUMN deposito_destino_id DROP NOT NULL'
            );
        }

        if (Schema::hasTable('tipotransaccion_stock') && Schema::hasTable('bien_uso')) {
            $existe = DB::table('tipotransaccion_stock')
                ->where('abreviatura', 'TBU')
                ->whereNull('deleted_at')
                ->exists();

            if (! $existe) {
                DB::table('tipotransaccion_stock')->insert([
                    'nombre' => 'Transferencia a bien de uso',
                    'operacion' => 'T',
                    'abreviatura' => 'TBU',
                    'signo' => 1,
                    'estado' => 'A',
                    'requiere_aprobacion' => false,
                    'maneja_contabilidad' => false,
                    'destino_bien_uso' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transferencia_mercaderia', 'bien_uso_destino_id')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->dropForeign('fk_tm_bien_uso_destino');
                $table->dropColumn('bien_uso_destino_id');
            });
        }

        if (Schema::hasColumn('articulo_movimiento', 'bien_uso_id')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_articulo_movimiento_bien_uso');
                $table->dropColumn('bien_uso_id');
            });
        }

        if (Schema::hasColumn('tipotransaccion_stock', 'destino_bien_uso')) {
            Schema::table('tipotransaccion_stock', function (Blueprint $table) {
                $table->dropColumn('destino_bien_uso');
            });
        }

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'TBU')
            ->where('destino_bien_uso', true)
            ->update(['deleted_at' => now()]);
    }
};
