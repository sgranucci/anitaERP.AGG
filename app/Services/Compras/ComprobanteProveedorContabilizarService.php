<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\ComprobanteProveedorAnitaCompraExistenciaSupport;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorConceptogastoResolverSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use App\Support\Contable\AsientoEloquentDeleteSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ComprobanteProveedorContabilizarService
{
    /** @var list<string> */
    private const ESTADOS_PERMITIDOS = [
        ComprobanteProveedorEstados::BORRADOR,
        ComprobanteProveedorEstados::PENDIENTE_REVISION,
        ComprobanteProveedorEstados::PENDIENTE_APROBACION,
        ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA,
        ComprobanteProveedorEstados::APROBADO,
    ];

    public function __construct(
        private ComprobanteProveedorAsientoService $asientoService,
        private ComprobanteProveedorCuentacorrienteService $cuentacorrienteService,
        private ComprobanteProveedorAnitaSyncService $anitaSyncService,
    ) {}

    public function contabilizar(int $comprobanteId): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()->find($comprobanteId);
        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        if ($comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            throw new RuntimeException('El comprobante ya está contabilizado.');
        }

        if (! in_array($comprobante->estado, self::ESTADOS_PERMITIDOS, true)) {
            throw new RuntimeException('No se puede contabilizar en estado «'.$comprobante->estado.'».');
        }

        app(ContratoValidacionAbonoService::class)->assertComprobanteContabilizable($comprobante);

        if ($comprobante->comprobante_proveedor_conceptos()->count() === 0) {
            throw new RuntimeException('Agregue al menos un concepto IVA antes de contabilizar.');
        }

        ComprobanteProveedorFechaContableSupport::assertPeriodoContablePermitido(
            (int) ($comprobante->empresa_id ?? 0),
            ComprobanteProveedorFechaContableSupport::fechaYmd($comprobante)
        );

        // Antes de asentar ERP: si Anita ya tiene la misma clave fiscal, no confirmar.
        ComprobanteProveedorAnitaCompraExistenciaSupport::assertDesdeComprobante($comprobante);

        // Limpia ctamov huérfanos de intentos previos (mismo patrón que recepción COM).
        $this->asientoService->eliminarCtamovAnitaDeComprobante($comprobante);

        $numeroAsientoAnita = null;
        $anitaNroInternoEscrito = null;
        $ctamovEscrito = false;

        try {
            $resultadoErp = DB::transaction(function () use ($comprobante) {
                $comprobante->load('comprobante_proveedor_recepciones.recepcion_proveedores');

                // 1) Asiento + CC solo en MySQL (sin tocar ctamov).
                $asientoErp = $this->asientoService->generarAsientoErp($comprobante);

                $this->cuentacorrienteService->generarDesdeComprobante($comprobante);

                $comprobante->forceFill([
                    'asiento_id' => $asientoErp['asiento_id'],
                    'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                ])->save();

                // Concepto cash-flow desde COM o desde la pierna de activo/resultado del asiento.
                ComprobanteProveedorConceptogastoResolverSupport::resolverYPersistir($comprobante);

                Comprobante_Proveedor_Estado::query()->create([
                    'comprobante_proveedor_id' => $comprobante->id,
                    'fecha' => now()->format('Y-m-d'),
                    'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                    'usuario_id' => Auth::id(),
                    'observacion' => 'Contabilizado en ERP (asiento #'.$asientoErp['asiento_id']
                        .' / Anita '.$asientoErp['numeroasiento'].')',
                ]);

                return $asientoErp;
            });

            $numeroAsientoAnita = $resultadoErp['numeroasiento'];

            // 2) Anita compra/concmov/promov (fuera del TX MySQL: si falla, compensamos abajo).
            $paraAnita = $comprobante->fresh([
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'comprobante_proveedor_cuotas',
                'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
                'proveedor_condicioniva_eventual', 'condicionpagos',
                'tipotransaccion_compras', 'monedas', 'ordencompras',
            ]);
            $this->anitaSyncService->syncCreate($paraAnita);
            $anitaNroInternoEscrito = (int) ($paraAnita->fresh()->anita_nro_interno ?? 0) ?: null;

            // 3) ctamov al final: solo si compra Anita ya está OK.
            $this->asientoService->sincronizarCtamovAnita($comprobante, $resultadoErp['payload_anita']);
            $ctamovEscrito = true;

            $comprobante->forceFill([
                'anita_nro_interno' => $anitaNroInternoEscrito,
                'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::SYNC_OK,
                'anita_sync_error' => null,
                'anita_sync_at' => now(),
            ])->save();

            return $comprobante->fresh();
        } catch (Throwable $e) {
            $this->compensarAnitaTrasFallo(
                $comprobante,
                $numeroAsientoAnita,
                $anitaNroInternoEscrito,
                $ctamovEscrito,
                $e,
            );

            // Si el ERP ya había commiteado, revertir estado/asiento locales.
            $this->revertirErpTrasFalloAnita($comprobante);

            $comprobante->forceFill([
                'anita_nro_interno' => null,
                'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::ERROR,
                'anita_sync_error' => $e->getMessage(),
                'anita_sync_at' => now(),
            ])->save();

            Comprobante_Proveedor_Estado::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'fecha' => now()->format('Y-m-d'),
                'estado' => ComprobanteProveedorEstados::BORRADOR,
                'usuario_id' => Auth::id(),
                'observacion' => 'Contabilización revertida: '.mb_substr($e->getMessage(), 0, 450),
            ]);

            throw $e;
        }
    }

    private function compensarAnitaTrasFallo(
        Comprobante_Proveedor $comprobante,
        ?string $numeroAsientoAnita,
        ?int $anitaNroInternoEscrito,
        bool $ctamovEscrito,
        Throwable $original,
    ): void {
        try {
            // Siempre por clave del comprobante + número reservado (aunque ctamovEscrito sea false:
            // un intento viejo o un create sin omitir podría haber dejado líneas).
            $this->asientoService->eliminarCtamovAnitaDeComprobante($comprobante, $numeroAsientoAnita);
        } catch (Throwable $cleanupEx) {
            Log::error('comprobante_proveedor.ctamov_cleanup_fallo', [
                'comprobante_id' => $comprobante->id,
                'numeroasiento' => $numeroAsientoAnita,
                'error_original' => $original->getMessage(),
                'error_cleanup' => $cleanupEx->getMessage(),
                'ctamov_escrito' => $ctamovEscrito,
            ]);
        }

        if ($anitaNroInternoEscrito) {
            try {
                $comprobante->forceFill(['anita_nro_interno' => $anitaNroInternoEscrito]);
                $this->anitaSyncService->syncDelete($comprobante->loadMissing([
                    'proveedores', 'tipotransaccion_compras',
                ]));
            } catch (Throwable $cleanupEx) {
                Log::error('comprobante_proveedor.compra_cleanup_fallo', [
                    'comprobante_id' => $comprobante->id,
                    'anita_nro_interno' => $anitaNroInternoEscrito,
                    'error_original' => $original->getMessage(),
                    'error_cleanup' => $cleanupEx->getMessage(),
                ]);
            }
        }
    }

    /**
     * Revierte asiento ERP + CC + Anita de un CONTABILIZADO sin pagos,
     * dejando el comprobante en BORRADOR para reedición.
     */
    public function descontabilizarSinPagos(int $comprobanteId): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()
            ->with(['asientos', 'proveedores', 'tipotransaccion_compras'])
            ->find($comprobanteId);

        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        ComprobanteProveedorPagoSupport::assertSinPagosAplicados($comprobanteId, 'descontabilizar');

        if ($comprobante->estado !== ComprobanteProveedorEstados::CONTABILIZADO
            && ! $comprobante->asiento_id
            && ! $comprobante->anita_nro_interno) {
            return $comprobante;
        }

        $asientoId = (int) ($comprobante->asiento_id ?? 0);
        $numeroAsiento = $comprobante->asientos
            ? (string) ($comprobante->asientos->numeroasiento ?? '')
            : '';
        $anitaNro = (int) ($comprobante->anita_nro_interno ?? 0);

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
            Log::error('comprobante_proveedor.descontabilizar_anita_fallo', [
                'comprobante_id' => $comprobanteId,
                'anita_nro_interno' => $anitaNro,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException(
                'No se pudo revertir en Anita antes de editar: '.$e->getMessage()
            );
        }

        $this->revertirErpTrasFalloAnita($comprobante, true);

        $comprobante->refresh();
        $comprobante->forceFill([
            'anita_nro_interno' => null,
            'anita_sync_estado' => null,
            'anita_sync_error' => null,
            'anita_sync_at' => null,
            'estado' => ComprobanteProveedorEstados::BORRADOR,
            'asiento_id' => null,
        ])->save();

        Comprobante_Proveedor_Estado::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => ComprobanteProveedorEstados::BORRADOR,
            'usuario_id' => Auth::id(),
            'observacion' => 'Descontabilizado para reedición (sin pagos aplicados).',
        ]);

        return $comprobante->fresh();
    }

    /**
     * Si falló el sync Anita después del commit MySQL, no dejar el CP “contabilizado” a medias.
     */
    private function revertirErpTrasFalloAnita(Comprobante_Proveedor $comprobante, bool $lanzarSiFalla = false): void
    {
        $comprobante->refresh();
        if ($comprobante->estado !== ComprobanteProveedorEstados::CONTABILIZADO
            && ! $comprobante->asiento_id) {
            return;
        }

        $asientoId = (int) ($comprobante->asiento_id ?? 0);

        try {
            DB::transaction(function () use ($comprobante, $asientoId) {
                // Cuenta corriente generada en el intento.
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

                // Soltar FK antes de borrar asiento (RESTRICT).
                $comprobante->forceFill([
                    'asiento_id' => null,
                    'estado' => ComprobanteProveedorEstados::BORRADOR,
                ])->save();

                AsientoEloquentDeleteSupport::eliminarPorId($asientoId);

                Comprobante_Proveedor_Estado::query()
                    ->where('comprobante_proveedor_id', $comprobante->id)
                    ->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->delete();
            });
        } catch (Throwable $revertEx) {
            Log::error('comprobante_proveedor.revert_erp_tras_fallo_anita', [
                'comprobante_id' => $comprobante->id,
                'asiento_id' => $asientoId,
                'error' => $revertEx->getMessage(),
            ]);
            if ($lanzarSiFalla) {
                throw new RuntimeException(
                    'No se pudo revertir el asiento/CC en ERP: '.$revertEx->getMessage()
                );
            }
        }
    }
}
