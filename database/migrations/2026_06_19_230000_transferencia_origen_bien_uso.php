<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tipotransaccion_stock', 'origen_bien_uso')) {
            Schema::table('tipotransaccion_stock', function (Blueprint $table) {
                $table->boolean('origen_bien_uso')->default(false)->after('destino_bien_uso');
            });
        }

        if (! Schema::hasColumn('transferencia_mercaderia', 'bien_uso_origen_id')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->unsignedBigInteger('bien_uso_origen_id')->nullable()->after('deposito_origen_id');
                $table->foreign('bien_uso_origen_id', 'fk_tm_bien_uso_origen')
                    ->references('id')->on('bien_uso')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (Schema::hasColumn('transferencia_mercaderia', 'deposito_origen_id')) {
            DB::statement('ALTER TABLE transferencia_mercaderia MODIFY deposito_origen_id BIGINT UNSIGNED NULL');
        }

        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        $tipos = [
            [
                'nombre' => 'Transferencia desde bien de uso',
                'abreviatura' => 'FBU',
                'operacion' => 'T',
                'signo' => 1,
                'estado' => 'A',
                'requiere_aprobacion' => true,
                'maneja_contabilidad' => false,
                'destino_bien_uso' => false,
                'origen_bien_uso' => true,
            ],
            [
                'nombre' => 'Transferencia entre depósitos (con aprobación)',
                'abreviatura' => 'TAP',
                'operacion' => 'T',
                'signo' => 1,
                'estado' => 'A',
                'requiere_aprobacion' => true,
                'maneja_contabilidad' => false,
                'destino_bien_uso' => false,
                'origen_bien_uso' => false,
            ],
            [
                'nombre' => 'Transferencia a bien de uso (con aprobación)',
                'abreviatura' => 'TBAP',
                'operacion' => 'T',
                'signo' => 1,
                'estado' => 'A',
                'requiere_aprobacion' => true,
                'maneja_contabilidad' => false,
                'destino_bien_uso' => true,
                'origen_bien_uso' => false,
            ],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('tipotransaccion_stock')
                ->where('abreviatura', $tipo['abreviatura'])
                ->whereNull('deleted_at')
                ->exists();

            if (! $existe) {
                DB::table('tipotransaccion_stock')->insert(array_merge($tipo, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'TBU')
            ->whereNull('deleted_at')
            ->update([
                'destino_bien_uso' => true,
                'origen_bien_uso' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        foreach (['FBU', 'TAP', 'TBAP'] as $abrev) {
            DB::table('tipotransaccion_stock')
                ->where('abreviatura', $abrev)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        if (Schema::hasColumn('transferencia_mercaderia', 'bien_uso_origen_id')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->dropForeign('fk_tm_bien_uso_origen');
                $table->dropColumn('bien_uso_origen_id');
            });
        }

        if (Schema::hasColumn('tipotransaccion_stock', 'origen_bien_uso')) {
            Schema::table('tipotransaccion_stock', function (Blueprint $table) {
                $table->dropColumn('origen_bien_uso');
            });
        }
    }
};
