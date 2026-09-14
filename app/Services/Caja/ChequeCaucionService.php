<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Support\Caja\ChequeTerceroCaucionAnitaSupport;
use InvalidArgumentException;

final class ChequeCaucionService
{
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
        $nro = trim((string) ($cheque->nro_caucion ?? ''));
        if ($nro !== '' && $nro !== '0') {
            return false;
        }

        return true;
    }

    public function estaCaucionado(Cheque $cheque): bool
    {
        $nro = trim((string) ($cheque->nro_caucion ?? ''));

        return $nro !== '' && $nro !== '0';
    }

    /**
     * @return array{cheque_id:int, nro_caucion:string, anita_ok:bool}
     */
    public function caucionar(int $chequeId, string $nroCaucion, ?string $fecha = null): array
    {
        $cheque = Cheque::query()->find($chequeId);
        if (! $cheque) {
            throw new InvalidArgumentException('No se encontró el cheque id '.$chequeId.'.');
        }
        if (! $this->esElegible($cheque)) {
            throw new InvalidArgumentException('El cheque no es elegible para caución.');
        }

        $nro = trim($nroCaucion);
        if ($nro === '' || $nro === '0') {
            throw new InvalidArgumentException('Indique el número de caución.');
        }
        $nro = mb_substr($nro, 0, 20);
        $fechaC = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');

        $cheque->nro_caucion = $nro;
        $cheque->fecha_caucion = $fechaC;
        $cheque->save();

        $anitaOk = ChequeTerceroCaucionAnitaSupport::marcarCaucion($cheque, $nro);

        return [
            'cheque_id' => (int) $cheque->id,
            'nro_caucion' => $nro,
            'anita_ok' => $anitaOk,
        ];
    }

    /**
     * @param  list<int>  $chequeIds
     * @return array{ok:int, error:int, detalle:list<array{cheque_id:int, ok:bool, error?:string, anita_ok?:bool}>}
     */
    public function caucionarMasivo(array $chequeIds, string $nroCaucion, ?string $fecha = null): array
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
                $r = $this->caucionar($id, $nroCaucion, $fecha);
                $ok++;
                $detalle[] = ['cheque_id' => $id, 'ok' => true, 'anita_ok' => $r['anita_ok']];
            } catch (\Throwable $e) {
                $error++;
                $detalle[] = ['cheque_id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return ['ok' => $ok, 'error' => $error, 'detalle' => $detalle];
    }

    /**
     * @return array{cheque_id:int, anita_ok:bool}
     */
    public function liberar(int $chequeId): array
    {
        $cheque = Cheque::query()->find($chequeId);
        if (! $cheque) {
            throw new InvalidArgumentException('No se encontró el cheque id '.$chequeId.'.');
        }
        if (! $this->estaCaucionado($cheque)) {
            throw new InvalidArgumentException('El cheque no está caucionado.');
        }
        if (! empty($cheque->fecha_deposito) || in_array((string) ($cheque->estado ?? ''), ['*', 'R', 'A'], true)) {
            throw new InvalidArgumentException('No se puede liberar: el cheque ya salió de cartera operativa.');
        }

        $cheque->nro_caucion = null;
        $cheque->fecha_caucion = null;
        $cheque->save();

        $anitaOk = ChequeTerceroCaucionAnitaSupport::liberarCaucion($cheque);

        return ['cheque_id' => (int) $cheque->id, 'anita_ok' => $anitaOk];
    }
}
