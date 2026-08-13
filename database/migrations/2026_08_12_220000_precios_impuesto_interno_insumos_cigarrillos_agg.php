<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: coeficiente impuesto interno 0.7234 vigencia 2026-08-13
 * para insumos cigarrillo (I1198–I1202) en listas II 162 / 262 / 362
 * (Biyemas / Kandiko / Rebisco).
 *
 * Solo corre en AGG. En El Bierzo y otros no tiene efecto.
 */
return new class extends Migration
{
    private const FECHA_VIGENCIA = '2026-08-13';

    private const PRECIO = 0.7234;

    private const MONEDA_ID = 1;

    private const USUARIO_ID = 2;

    /** @var list<string> */
    private const SKUS = ['I1198', 'I1199', 'I1200', 'I1201', 'I1202'];

    /** @var list<string> códigos listaprecio II por empresa */
    private const LISTAS = ['162', '262', '362'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('precio') || ! Schema::hasTable('articulo') || ! Schema::hasTable('listaprecio')) {
            return;
        }

        $ahora = now();

        foreach (self::SKUS as $sku) {
            $articuloId = (int) DB::table('articulo')->where('sku', $sku)->value('id');
            if ($articuloId <= 0) {
                continue;
            }

            foreach (self::LISTAS as $codigoLista) {
                $listaId = (int) DB::table('listaprecio')->where('codigo', $codigoLista)->value('id');
                if ($listaId <= 0) {
                    continue;
                }

                $existe = DB::table('precio')
                    ->where('articulo_id', $articuloId)
                    ->where('listaprecio_id', $listaId)
                    ->whereDate('fechavigencia', self::FECHA_VIGENCIA)
                    ->exists();

                if ($existe) {
                    continue;
                }

                $precioAnterior = DB::table('precio')
                    ->where('articulo_id', $articuloId)
                    ->where('listaprecio_id', $listaId)
                    ->whereDate('fechavigencia', '<', self::FECHA_VIGENCIA)
                    ->orderByDesc('fechavigencia')
                    ->orderByDesc('id')
                    ->value('precio');

                DB::table('precio')->insert([
                    'articulo_id' => $articuloId,
                    'listaprecio_id' => $listaId,
                    'fechavigencia' => self::FECHA_VIGENCIA,
                    'moneda_id' => self::MONEDA_ID,
                    'precio' => self::PRECIO,
                    'precioanterior' => $precioAnterior !== null ? (float) $precioAnterior : 0,
                    'usuarioultcambio_id' => self::USUARIO_ID,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('precio') || ! Schema::hasTable('articulo') || ! Schema::hasTable('listaprecio')) {
            return;
        }

        $articuloIds = DB::table('articulo')->whereIn('sku', self::SKUS)->pluck('id');
        $listaIds = DB::table('listaprecio')->whereIn('codigo', self::LISTAS)->pluck('id');

        if ($articuloIds->isEmpty() || $listaIds->isEmpty()) {
            return;
        }

        DB::table('precio')
            ->whereIn('articulo_id', $articuloIds)
            ->whereIn('listaprecio_id', $listaIds)
            ->whereDate('fechavigencia', self::FECHA_VIGENCIA)
            ->where('precio', self::PRECIO)
            ->delete();
    }
};
