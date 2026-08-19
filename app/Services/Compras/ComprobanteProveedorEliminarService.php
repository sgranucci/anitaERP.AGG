<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Models\Compras\Comprobante_Proveedor_Articulo;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Contable\AsientoEloquentDeleteSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Borra físicamente el comprobante proveedor en ERP y Anita
 * (compra/concmov/promov/ctamov + asiento + CC + hijas).
 * Opcionalmente también la precarga asociada.
 */
class ComprobanteProveedorEliminarService
{
    public function __construct(
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

        // 2) ERP: CC, asiento, hijas y hard-delete cabecera.
        DB::transaction(function () use ($comprobante, $asientoId, $tambienPrecarga) {
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
                EloquentAuditDeleteSupport::each(
                    Proveedor_Cuentacorriente::query()->whereIn('id', $ccIds)
                );
            }

            // Soltar FKs RESTRICT antes de borrar asiento / precarga.
            $comprobante->forceFill([
                'asiento_id' => null,
                'anita_nro_interno' => null,
                'precarga_comprobante_proveedor_id' => $tambienPrecarga
                    ? $comprobante->precarga_comprobante_proveedor_id
                    : null,
            ])->save();

            AsientoEloquentDeleteSupport::eliminarPorId($asientoId);

            // Hijas (también CASCADE en MySQL; se borran explícitas por claridad).
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Recepcion::query()->where('comprobante_proveedor_id', $comprobante->id)
            );
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Concepto::query()->where('comprobante_proveedor_id', $comprobante->id)
            );
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Articulo::query()->where('comprobante_proveedor_id', $comprobante->id)
            );
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Cuota::query()->where('comprobante_proveedor_id', $comprobante->id)
            );
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Estado::query()->where('comprobante_proveedor_id', $comprobante->id)
            );
            // Filas de adjuntos en BD (el PDF en disco de Facturas_scan no se toca acá:
            // suele pertenecer a la precarga / montaje compartido).
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor_Archivo::query()->where('comprobante_proveedor_id', $comprobante->id)
            );

            if ($tambienPrecarga) {
                $comprobante->forceFill(['precarga_comprobante_proveedor_id' => null])->save();
            }

            $comprobante->delete();
        });

        $precargaBorrada = false;
        if ($tambienPrecarga && $precargaId > 0) {
            $this->eliminarPrecargaSiCorresponde($precargaId);
            $precargaBorrada = true;
        } elseif (! $tambienPrecarga && $precargaId > 0) {
            Precarga_Comprobante_Proveedor::query()
                ->whereKey($precargaId)
                ->where('estado', PrecargaComprobanteEstados::GENERADA)
                ->update(['estado' => PrecargaComprobanteEstados::PENDIENTE]);
        }

        $mensaje = 'Comprobante #'.$comprobanteId.' borrado físicamente en anitaERP'
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
        ComprobanteProveedorPagoSupport::assertSinPagosAplicados((int) $comprobante->id, 'borrar');
    }

    private function eliminarPrecargaSiCorresponde(int $precargaId): void
    {
        $otros = Comprobante_Proveedor::query()
            ->where('precarga_comprobante_proveedor_id', $precargaId)
            ->exists();

        if ($otros) {
            throw new RuntimeException(
                'No se pudo borrar la precarga #'.$precargaId
                .': hay otros comprobantes vinculados. El comprobante sí fue eliminado.'
            );
        }

        if (! Precarga_Comprobante_Proveedor::query()->find($precargaId)) {
            return;
        }

        $this->precargaRepository->delete($precargaId);
    }
}
