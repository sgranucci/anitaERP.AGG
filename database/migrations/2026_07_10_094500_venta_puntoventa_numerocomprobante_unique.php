<?php

use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Unicidad activa de numerocomprobante por punto de venta (evita duplicados CAEA gastro + estacionamiento).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        $this->corregirDuplicadosActivos();

        Schema::table('venta', function (Blueprint $table): void {
            if (! $this->indexExists('venta', VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX)) {
                $table->unique(
                    ['puntoventa_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX,
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table): void {
            if ($this->indexExists('venta', VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX)) {
                $table->dropUnique(VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX);
            }
        });
    }

    private function corregirDuplicadosActivos(): void
    {
        $grupos = DB::table('venta')
            ->select('puntoventa_id', 'numerocomprobante', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->groupBy('puntoventa_id', 'numerocomprobante')
            ->having('total', '>', 1)
            ->get();

        foreach ($grupos as $grupo) {
            $puntoventaId = (int) $grupo->puntoventa_id;
            $numerocomprobante = (int) $grupo->numerocomprobante;

            $filas = DB::table('venta')
                ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
                ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
                ->where('venta.puntoventa_id', $puntoventaId)
                ->where('venta.numerocomprobante', $numerocomprobante)
                ->whereNull('venta.deleted_at')
                ->orderBy('venta.id')
                ->get([
                    'venta.id',
                    'venta.codigo',
                    'puntoventa.codigo as pv_codigo',
                    'tipotransaccion.abreviatura as tipo_anita',
                ]);

            $conservar = $filas->first();
            if ($conservar === null) {
                continue;
            }

            foreach ($filas->slice(1) as $fila) {
                $maxActual = (int) (DB::table('venta')
                    ->where('puntoventa_id', $puntoventaId)
                    ->whereNull('deleted_at')
                    ->max('numerocomprobante') ?? 0);

                $nuevoNumero = $maxActual + 1;
                $codigoParts = explode(' ', (string) $fila->codigo, 2);
                $tipoAnita = trim((string) ($codigoParts[0] ?? $fila->tipo_anita ?? 'FAC'));
                $resto = trim((string) ($codigoParts[1] ?? ''));
                $letra = 'B';
                if (preg_match('/^([A-Z])\s*-/', $resto, $m)) {
                    $letra = $m[1];
                }

                $codigoNuevo = VentaNumeracionEmpresaSupport::formatearCodigoVenta(
                    $tipoAnita,
                    $letra,
                    (string) $fila->pv_codigo,
                    $nuevoNumero,
                );

                DB::table('venta')->where('id', (int) $fila->id)->update([
                    'numerocomprobante' => $nuevoNumero,
                    'codigo' => $codigoNuevo,
                    'updated_at' => now(),
                ]);

                Log::warning('venta.migracion.duplicado_renumerado', [
                    'venta_id' => (int) $fila->id,
                    'puntoventa_id' => $puntoventaId,
                    'numerocomprobante_viejo' => $numerocomprobante,
                    'numerocomprobante_nuevo' => $nuevoNumero,
                    'conserva_venta_id' => (int) $conservar->id,
                ]);
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }
};
