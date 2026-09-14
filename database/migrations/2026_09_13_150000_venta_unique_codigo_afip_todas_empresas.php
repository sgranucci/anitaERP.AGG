<?php

use App\Support\Database\MigrationDialectSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Aplica el unique fiscal (codigo_afip, puntoventa_id, numerocomprobante) en entornos
 * donde 185500/190500 ya corrieron como no-op (guard El Bierzo) y dejaron el unique
 * gastro (puntoventa_id, numerocomprobante).
 *
 * Idempotente: seguro en AGG, Ferli, El Bierzo e instalaciones nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table): void {
            if (! Schema::hasColumn('venta', 'codigo_afip')) {
                $table->unsignedSmallInteger('codigo_afip')->nullable()->after('numerocomprobante');
            }
        });

        $this->backfillCodigoAfip();
        $this->resolverDuplicadosMismaSerieFiscal();

        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX,
        );
        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO,
        );

        Schema::table('venta', function (Blueprint $table): void {
            if (! MigrationDialectSupport::tieneIndice(
                'venta',
                VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_AFIP
            )) {
                $table->unique(
                    ['codigo_afip', 'puntoventa_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_AFIP,
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_AFIP,
        );

        Schema::table('venta', function (Blueprint $table): void {
            if (! MigrationDialectSupport::tieneIndice(
                'venta',
                VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX
            )) {
                $table->unique(
                    ['puntoventa_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX,
                );
            }
        });
    }

    private function backfillCodigoAfip(): void
    {
        $filas = DB::table('venta')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->whereNull('venta.codigo_afip')
            ->select('venta.id', 'venta.codigo', 'tt.codigo as tt_codigo')
            ->orderBy('venta.id')
            ->get();

        foreach ($filas as $fila) {
            $afip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                (string) ($fila->tt_codigo ?? ''),
                (string) ($fila->codigo ?? ''),
            );
            DB::table('venta')->where('id', (int) $fila->id)->update([
                'codigo_afip' => $afip > 0 ? $afip : null,
            ]);
        }
    }

    /**
     * Duplicados históricos (mismo codigo_afip + PV + número): conserva el menor id
     * y renumera el resto al final de esa serie fiscal (no altera el comprobante “real”).
     */
    private function resolverDuplicadosMismaSerieFiscal(): void
    {
        $grupos = DB::table('venta')
            ->select('codigo_afip', 'puntoventa_id', 'numerocomprobante', DB::raw('COUNT(*) as total'))
            ->whereNotNull('codigo_afip')
            ->groupBy('codigo_afip', 'puntoventa_id', 'numerocomprobante')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($grupos as $grupo) {
            $filas = DB::table('venta')
                ->where('codigo_afip', $grupo->codigo_afip)
                ->where('puntoventa_id', $grupo->puntoventa_id)
                ->where('numerocomprobante', $grupo->numerocomprobante)
                ->orderBy('id')
                ->get(['id', 'codigo']);

            $conservar = $filas->first();
            if ($conservar === null) {
                continue;
            }

            foreach ($filas->slice(1) as $fila) {
                $maxActual = (int) (DB::table('venta')
                    ->where('codigo_afip', $grupo->codigo_afip)
                    ->where('puntoventa_id', $grupo->puntoventa_id)
                    ->max('numerocomprobante') ?? 0);
                $nuevo = $maxActual + 1;
                $codigoNuevo = preg_replace(
                    '/(\d+)$/',
                    str_pad((string) $nuevo, 8, '0', STR_PAD_LEFT),
                    (string) $fila->codigo
                );

                DB::table('venta')->where('id', (int) $fila->id)->update([
                    'numerocomprobante' => $nuevo,
                    'codigo' => $codigoNuevo ?: $fila->codigo,
                    'updated_at' => now(),
                ]);

                Log::warning('venta.migracion.duplicado_serie_fiscal_renumerado', [
                    'venta_id' => (int) $fila->id,
                    'codigo_afip' => (int) $grupo->codigo_afip,
                    'puntoventa_id' => (int) $grupo->puntoventa_id,
                    'numerocomprobante_viejo' => (int) $grupo->numerocomprobante,
                    'numerocomprobante_nuevo' => $nuevo,
                    'conserva_venta_id' => (int) $conservar->id,
                ]);
            }
        }
    }
};
