<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Caja\ChequePropioImputacionSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reclasifica cheques propios posdatados imputados a cheques diferidos → banco (asiento TES diario).
 */
class ChequeDiferidoReclasificacionService
{
    public function __construct(
        private CuentacajaRepositoryInterface $cuentacajaRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
    ) {
    }

    /**
     * @return array{procesados: int, omitidos: int, errores: list<string>}
     */
    public function reclasificarPendientes(?string $fechaCorte = null): array
    {
        if (! ChequePropioImputacionSupport::usaImputacionDiferidos()) {
            return ['procesados' => 0, 'omitidos' => 0, 'errores' => ['Imputación a cheques diferidos deshabilitada en config.']];
        }

        $fechaCorte = $fechaCorte ?: Carbon::today()->toDateString();

        $cheques = Cheque::query()
            ->where('origen', 'E')
            ->where('estado', ' ')
            ->whereDate('fechapago', '<=', $fechaCorte)
            ->whereNotNull('cuentacaja_id')
            ->whereNull('cobranza_id')
            ->orderBy('fechapago')
            ->orderBy('id')
            ->get();

        $procesados = 0;
        $omitidos = 0;
        $errores = [];

        foreach ($cheques as $cheque) {
            try {
                if ($this->reclasificarUno($cheque, $fechaCorte)) {
                    $procesados++;
                } else {
                    $omitidos++;
                }
            } catch (Exception $e) {
                $msg = 'Cheque #'.$cheque->id.': '.$e->getMessage();
                $errores[] = $msg;
                Log::warning('caja:reclasificar-cheques-diferidos — '.$msg);
            }
        }

        return compact('procesados', 'omitidos', 'errores');
    }

    private function reclasificarUno(Cheque $cheque, string $fechaAsiento): bool
    {
        $empresaId = (int) $cheque->empresa_id;
        $cuentacaja = $this->cuentacajaRepository->find((int) $cheque->cuentacaja_id);
        if ($cuentacaja === null || empty($cuentacaja->cuentacontable_id)) {
            throw new Exception('Cuenta de caja/banco sin cuenta contable.');
        }

        $diferidosId = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CAJA_CHEQUES_DIFERIDOS);
        if ($diferidosId === null || $diferidosId <= 0) {
            $codigo = (string) config('caja.cheques_diferidos_cuenta_codigo');
            $diferidos = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigo);
            $diferidosId = $diferidos?->id;
        }

        if ($diferidosId === null || $diferidosId <= 0) {
            throw new Exception('No se resolvió cuenta de cheques diferidos.');
        }

        $monto = (float) $cheque->monto;
        if ($monto <= 0) {
            return false;
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaAsiento,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');
        if ($tipoasiento === null) {
            throw new Exception('No existe tipo de asiento TES.');
        }

        $observacion = 'Reclasificación cheque diferido Nº '.$cheque->numerocheque.' (cheque #'.$cheque->id.')';

        DB::beginTransaction();
        try {
            $data = [
                'empresa_id' => $empresaId,
                'tipoasiento_id' => $tipoasiento->id,
                'fecha' => $fechaAsiento,
                'observacion' => $observacion,
                'cuentacontable_ids' => [(int) $cuentacaja->cuentacontable_id, (int) $diferidosId],
                'moneda_ids' => [(int) $cheque->moneda_id, (int) $cheque->moneda_id],
                'centrocosto_ids' => [0, 0],
                'debes' => [$monto, ''],
                'haberes' => ['', $monto],
                'cotizaciones' => [(float) $cheque->cotizacion, (float) $cheque->cotizacion],
                'observaciones' => [$observacion, $observacion],
            ];

            $asiento = $this->asientoRepository->create($data);
            if ($asiento === 'Error' || $asiento === null) {
                throw new Exception('Error al grabar asiento de reclasificación.');
            }

            $this->asientoMovimientoRepository->create($data, $asiento->id);

            $cheque->estado = '*';
            $cheque->save();

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
