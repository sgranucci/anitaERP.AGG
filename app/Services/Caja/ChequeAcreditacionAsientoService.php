<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Caja\ChequePropioImputacionSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Asiento TES al acreditar depósito CHT: Debe banco / Haber valores a depositar.
 */
final class ChequeAcreditacionAsientoService
{
    public function __construct(
        private CuentacajaRepositoryInterface $cuentacajaRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
    ) {
    }

    public function habilitado(): bool
    {
        return (bool) config('cheque.acreditacion_asiento.habilitado', true);
    }

    /**
     * @return array{asiento_id:?int, omitido:bool, motivo?:string}
     */
    public function generarSiCorresponde(Cheque $cheque, string $fechaAcreditacion): array
    {
        if (! $this->habilitado()) {
            return ['asiento_id' => null, 'omitido' => true, 'motivo' => 'Asiento de acreditación deshabilitado'];
        }
        if ((int) ($cheque->asiento_acreditacion_id ?? 0) > 0) {
            return ['asiento_id' => (int) $cheque->asiento_acreditacion_id, 'omitido' => true, 'motivo' => 'Ya tiene asiento'];
        }

        $empresaId = (int) ($cheque->empresa_id ?? 0);
        if ($empresaId <= 0) {
            throw new Exception('Cheque sin empresa para asiento de acreditación.');
        }

        $cuentaCajaId = (int) ($cheque->cuentacaja_deposito_id ?? 0);
        if ($cuentaCajaId <= 0) {
            throw new Exception('Cheque sin cuenta de depósito; no se puede armar el asiento.');
        }

        /** @var Cuentacaja|null $cuentacaja */
        $cuentacaja = $this->cuentacajaRepository->find($cuentaCajaId);
        $bancoCcId = (int) ($cuentacaja->cuentacontable_id ?? 0);
        if ($bancoCcId <= 0) {
            throw new Exception('La cuenta de caja del depósito no tiene cuenta contable.');
        }

        $valoresId = ChequePropioImputacionSupport::resolverCuentacontableIdValoresADepositar(
            $empresaId,
            $this->cuentacontableRepository
        );
        if ($valoresId === null || $valoresId <= 0) {
            throw new Exception('No se resolvió la cuenta de valores a depositar.');
        }

        $monto = round((float) $cheque->monto, 2);
        if ($monto <= 0) {
            return ['asiento_id' => null, 'omitido' => true, 'motivo' => 'Monto cero'];
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaAcreditacion,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');
        if ($tipoasiento === null) {
            throw new Exception('No existe tipo de asiento TES.');
        }

        $obs = 'Acreditación depósito CHT Nº '.$cheque->numerocheque
            .(trim((string) ($cheque->nro_boleta_deposito ?? '')) !== ''
                ? ' boleta '.$cheque->nro_boleta_deposito
                : '')
            .' (cheque #'.$cheque->id.')';

        $cotizacion = (float) ($cheque->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }
        $monedaId = (int) ($cheque->moneda_id ?? 1) ?: 1;

        DB::beginTransaction();
        try {
            $data = [
                'empresa_id' => $empresaId,
                'tipoasiento_id' => $tipoasiento->id,
                'fecha' => $fechaAcreditacion,
                'observacion' => $obs,
                'cuentacontable_ids' => [$bancoCcId, (int) $valoresId],
                'moneda_ids' => [$monedaId, $monedaId],
                'centrocosto_ids' => [0, 0],
                'debes' => [$monto, ''],
                'haberes' => ['', $monto],
                'cotizaciones' => [$cotizacion, $cotizacion],
                'observaciones' => [$obs, $obs],
            ];

            $asiento = $this->asientoRepository->create($data);
            if ($asiento === 'Error' || $asiento === null) {
                throw new Exception('Error al grabar asiento de acreditación.');
            }

            $this->asientoMovimientoRepository->create($data, $asiento->id);

            $cheque->asiento_acreditacion_id = (int) $asiento->id;
            $cheque->save();

            DB::commit();

            return ['asiento_id' => (int) $asiento->id, 'omitido' => false];
        } catch (Exception $e) {
            DB::rollBack();
            Log::warning('Asiento acreditación CHT falló', [
                'cheque_id' => $cheque->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
