<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Support\Caja\ChequeTerceroDepositoAnitaSupport;
use InvalidArgumentException;

final class ChequeDepositoService
{
    public function __construct(
        private ChequeAcreditacionAsientoService $acreditacionAsientoService,
    ) {
    }
    public function esElegible(Cheque $cheque): bool
    {
        if ((string) ($cheque->origen ?? '') !== 'R') {
            return false;
        }
        $estado = (string) ($cheque->estado ?? ' ');
        if (in_array($estado, ['R', 'A', '*'], true)) {
            return false;
        }
        if (! empty($cheque->fecha_deposito)) {
            return false;
        }
        if (! empty($cheque->pagoproveedor_id)) {
            return false;
        }
        $nroCaucion = trim((string) ($cheque->nro_caucion ?? ''));
        if ($nroCaucion !== '' && $nroCaucion !== '0') {
            return false;
        }

        return true;
    }

    public function esElegibleAcreditar(Cheque $cheque): bool
    {
        if ((string) ($cheque->origen ?? '') !== 'R') {
            return false;
        }
        if (empty($cheque->fecha_deposito) && (string) ($cheque->estado ?? '') !== '*') {
            return false;
        }
        if ((string) ($cheque->estado ?? '') === 'R' || (string) ($cheque->estado ?? '') === 'A') {
            return false;
        }
        if (! empty($cheque->fecha_acreditacion)) {
            return false;
        }

        return true;
    }

    /**
     * Marca acreditación bancaria del depósito (sale de "en tránsito").
     * Opcionalmente genera asiento TES: Debe banco / Haber valores a depositar.
     *
     * @return array{cheque_id:int, fecha_acreditacion:string, asiento_id:?int, asiento_omitido?:bool, asiento_motivo?:string}
     */
    public function acreditar(int $chequeId, ?string $fecha = null): array
    {
        $cheque = Cheque::query()->find($chequeId);
        if (! $cheque) {
            throw new InvalidArgumentException('No se encontró el cheque id '.$chequeId.'.');
        }
        if (! $this->esElegibleAcreditar($cheque)) {
            throw new InvalidArgumentException('El cheque no es elegible para acreditar.');
        }

        $fechaAc = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
        $cheque->fecha_acreditacion = $fechaAc;
        if ((string) ($cheque->estado ?? '') !== '*') {
            $cheque->estado = '*';
        }
        $cheque->save();

        $asientoId = null;
        $asientoOmitido = true;
        $asientoMotivo = null;
        try {
            $asiento = $this->acreditacionAsientoService->generarSiCorresponde($cheque->fresh(), $fechaAc);
            $asientoId = $asiento['asiento_id'] ?? null;
            $asientoOmitido = (bool) ($asiento['omitido'] ?? false);
            $asientoMotivo = $asiento['motivo'] ?? null;
        } catch (\Throwable $e) {
            // La acreditación ERP ya quedó; el asiento se puede reintentar.
            $asientoOmitido = true;
            $asientoMotivo = $e->getMessage();
        }

        return [
            'cheque_id' => (int) $cheque->id,
            'fecha_acreditacion' => $fechaAc,
            'asiento_id' => $asientoId,
            'asiento_omitido' => $asientoOmitido,
            'asiento_motivo' => $asientoMotivo,
        ];
    }

    /**
     * @param  list<int>  $chequeIds
     * @return array{ok:int, error:int, detalle:list<array{cheque_id:int, ok:bool, error?:string}>}
     */
    public function acreditarMasivo(array $chequeIds, ?string $fecha = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $chequeIds), static fn ($id) => $id > 0)));
        if ($ids === []) {
            throw new InvalidArgumentException('Seleccione al menos un cheque.');
        }

        $ok = 0;
        $error = 0;
        $detalle = [];
        foreach ($ids as $id) {
            try {
                $this->acreditar($id, $fecha);
                $ok++;
                $detalle[] = ['cheque_id' => $id, 'ok' => true];
            } catch (\Throwable $e) {
                $error++;
                $detalle[] = ['cheque_id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return ['ok' => $ok, 'error' => $error, 'detalle' => $detalle];
    }

    /**
     * @return array{cheque_id:int, fecha_deposito:string, anita_ok:bool}
     */
    public function depositar(
        int $chequeId,
        int $cuentacajaId,
        ?string $fecha = null,
        ?string $nroBoleta = null,
    ): array {
        $cheque = Cheque::query()->find($chequeId);
        if (! $cheque) {
            throw new InvalidArgumentException('No se encontró el cheque id '.$chequeId.'.');
        }
        if (! $this->esElegible($cheque)) {
            throw new InvalidArgumentException('El cheque no es elegible para depósito.');
        }

        $cuenta = Cuentacaja::query()->find($cuentacajaId);
        if (! $cuenta) {
            throw new InvalidArgumentException('Cuenta de caja id '.$cuentacajaId.' no encontrada.');
        }
        if ((int) ($cuenta->empresa_id ?? 0) > 0
            && (int) $cheque->empresa_id > 0
            && (int) $cuenta->empresa_id !== (int) $cheque->empresa_id) {
            throw new InvalidArgumentException('La cuenta de caja no pertenece a la empresa del cheque.');
        }

        $fechaDep = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
        $boleta = trim((string) $nroBoleta);
        if ($boleta === '') {
            $boleta = null;
        }

        $cheque->fecha_deposito = $fechaDep;
        $cheque->cuentacaja_deposito_id = (int) $cuenta->id;
        $cheque->nro_boleta_deposito = $boleta;
        $cheque->estado = '*';
        $cheque->save();

        $anitaOk = ChequeTerceroDepositoAnitaSupport::marcarDeposito($cheque, $fechaDep, $cuenta, $boleta);

        return [
            'cheque_id' => (int) $cheque->id,
            'fecha_deposito' => $fechaDep,
            'anita_ok' => $anitaOk,
        ];
    }

    /**
     * @param  list<int>  $chequeIds
     * @return array{ok:int, error:int, detalle:list<array{cheque_id:int, ok:bool, error?:string, anita_ok?:bool}>}
     */
    public function depositarMasivo(
        array $chequeIds,
        int $cuentacajaId,
        ?string $fecha = null,
        ?string $nroBoleta = null,
    ): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $chequeIds), static fn ($id) => $id > 0)));
        if ($ids === []) {
            throw new InvalidArgumentException('Seleccione al menos un cheque.');
        }

        $ok = 0;
        $error = 0;
        $detalle = [];
        foreach ($ids as $id) {
            try {
                $r = $this->depositar($id, $cuentacajaId, $fecha, $nroBoleta);
                $ok++;
                $detalle[] = ['cheque_id' => $id, 'ok' => true, 'anita_ok' => $r['anita_ok']];
            } catch (\Throwable $e) {
                $error++;
                $detalle[] = ['cheque_id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return ['ok' => $ok, 'error' => $error, 'detalle' => $detalle];
    }
}
