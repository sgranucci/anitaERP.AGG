<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Borra comprobante proveedor en ERP y Anita (compra/concmov/promov/ctamov + asiento + CC).
 * Opcionalmente también la precarga asociada.
 */
class ComprobanteProveedorEliminarService
{
    public function __construct(
        private Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
        private Precarga_Comprobante_ProveedorRepositoryInterface $precargaRepository,
        private ComprobanteProveedorAnitaSyncService $anitaSyncService,
        private ComprobanteProveedorAsientoService $asientoService,
    ) {}

    /**
     * @return array{mensaje: string, precarga_borrada: bool}
     */
    public function eliminar(int $comprobanteId, bool $tambienPrecarga = false): array
    {
        $comprobante = Comprobante_Proveedor::query()
            ->with([
                'tipotransaccion_compras',
                'proveedores',
                'comprobante_proveedor_cuotas',
                'asientos',
            ])
            ->find($comprobanteId);

        if (! $comprobante) {
            throw new RuntimeException('Comprobante no encontrado.');
        }

        $this->assertPuedeBorrar($comprobante);

        $precargaId = (int) ($comprobante->precarga_comprobante_proveedor_id ?? 0);
        $asientoId = (int) ($comprobante->asiento_id ?? 0);
        $numeroAsiento = $comprobante->asientos
            ? (string) ($comprobante->asientos->numeroasiento ?? '')
            : '';
        $anitaNro = (int) ($comprobante->anita_nro_interno ?? 0);

        // 1) Anita contable + compra (fuera del TX MySQL: API Informix).
        try {
            if ($asientoId > 0 || $anitaNro > 0) {
                $this->asientoService->eliminarCtamovAnitaDeComprobante(
                    $comprobante,
                    $numeroAsiento !== '' ? $numeroAsiento : null
                );
            }
            if ($anitaNro > 0) {
                $this->anitaSyncService->syncDelete($comprobante);
            }
        } catch (Throwable $e) {
            Log::error('comprobante_proveedor.eliminar_anita_fallo', [
                'comprobante_id' => $comprobanteId,
                'anita_nro_interno' => $anitaNro,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException(
                'No se pudo borrar en Anita: '.$e->getMessage()
            );
        }

        // 2) ERP: CC, asiento, vínculos COM, soft-delete cabecera.
        DB::transaction(function () use ($comprobante, $asientoId) {
            $ccIds = DB::table('comprobante_proveedor_cuota')
                ->where('comprobante_proveedor_id', $comprobante->id)
                ->whereNotNull('proveedor_cuentacorriente_id')
                ->pluck('proveedor_cuentacorriente_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            DB::table('comprobante_proveedor_cuota')
                ->where('comprobante_proveedor_id', $comprobante->id)
                ->update(['proveedor_cuentacorriente_id' => null]);

            if ($ccIds !== []) {
                DB::table('proveedor_cuentacorriente')->whereIn('id', $ccIds)->delete();
            }

            Comprobante_Proveedor_Recepcion::query()
                ->where('comprobante_proveedor_id', $comprobante->id)
                ->delete();

            // Primero soltar FK asiento_id; si no, MySQL RESTRICT impide borrar asiento.
            $comprobante->forceFill([
                'asiento_id' => null,
                'anita_nro_interno' => null,
            ])->save();

            if ($asientoId > 0) {
                DB::table('asiento_movimiento')->where('asiento_id', $asientoId)->delete();
                DB::table('asiento')->where('id', $asientoId)->delete();
            }

            $this->comprobanteRepository->delete($comprobante->id);
        });

        $precargaBorrada = false;
        if ($tambienPrecarga && $precargaId > 0) {
            $this->eliminarPrecargaSiCorresponde($precargaId, $comprobanteId);
            $precargaBorrada = true;
        }

        $mensaje = 'Comprobante #'.$comprobanteId.' borrado en anitaERP'
            .($anitaNro > 0 || $asientoId > 0 ? ' y Anita' : '')
            .'.';
        if ($precargaBorrada) {
            $mensaje .= ' También se eliminó la precarga #'.$precargaId.'.';
        }

        return [
            'mensaje' => $mensaje,
            'precarga_borrada' => $precargaBorrada,
        ];
    }

    private function assertPuedeBorrar(Comprobante_Proveedor $comprobante): void
    {
        $cuotaIds = $comprobante->comprobante_proveedor_cuotas
            ->pluck('proveedor_cuentacorriente_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($cuotaIds === []) {
            return;
        }

        $conPago = DB::table('proveedor_cuentacorriente')
            ->whereIn('id', $cuotaIds)
            ->whereNotNull('pagoproveedor_id')
            ->where('pagoproveedor_id', '>', 0)
            ->exists();

        if ($conPago) {
            throw new RuntimeException(
                'No se puede borrar: hay pagos aplicados sobre la cuenta corriente del comprobante.'
            );
        }
    }

    private function eliminarPrecargaSiCorresponde(int $precargaId, int $comprobanteIdExcluido): void
    {
        $otros = Comprobante_Proveedor::query()
            ->where('precarga_comprobante_proveedor_id', $precargaId)
            ->where('id', '!=', $comprobanteIdExcluido)
            ->whereNull('deleted_at')
            ->exists();

        if ($otros) {
            throw new RuntimeException(
                'No se pudo borrar la precarga #'.$precargaId
                .': hay otros comprobantes vinculados. El comprobante sí fue eliminado.'
            );
        }

        $precarga = Precarga_Comprobante_Proveedor::query()->find($precargaId);
        if (! $precarga) {
            return;
        }

        $this->precargaRepository->delete($precargaId);
    }
}
