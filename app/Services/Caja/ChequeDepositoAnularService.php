<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Models\Contable\Asiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Caja\ChequeTerceroDepositoAnitaSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Anula una boleta de depósito CHT y devuelve los cheques a cartera.
 * borrar: elimina el asiento del depósito. revertir: graba el compensatorio.
 */
final class ChequeDepositoAnularService
{
    public const MODO_BORRAR = 'borrar';

    public const MODO_REVERTIR = 'revertir';

    public function __construct(
        private AsientoRepositoryInterface $asientoRepository,
        private AsientoReversoSupport $asientoReversoSupport,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  list<int>  $chequeIds
     * @return array{cheques:int, asientos:int, modo:string, anita_fallos:list<string>}
     */
    public function anularBoleta(array $chequeIds, string $modo): array
    {
        if (! in_array($modo, [self::MODO_BORRAR, self::MODO_REVERTIR], true)) {
            throw new InvalidArgumentException('Modo de anulación desconocido.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $chequeIds), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            throw new InvalidArgumentException('Seleccione una boleta.');
        }

        $anitaFallos = [];
        $resultado = DB::transaction(function () use ($ids, $modo) {
            $cheques = Cheque::query()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($cheques->count() !== count($ids)) {
                throw new InvalidArgumentException('Algún cheque de la boleta ya no está.');
            }

            $this->assertMismaBoleta($cheques);

            $asientoIds = [];
            foreach ($cheques as $cheque) {
                $asientoId = (int) ($cheque->asiento_acreditacion_id ?? 0);
                if ($asientoId > 0) {
                    $asientoIds[$asientoId] = $asientoId;
                }
            }

            $asientos = 0;
            foreach ($asientoIds as $asientoId) {
                $asiento = Asiento::query()->find($asientoId);
                if (! $asiento) {
                    continue;
                }
                if ($modo === self::MODO_REVERTIR) {
                    $this->asientoReversoSupport->generarDesdeAsiento(
                        $asiento,
                        date('Y-m-d'),
                        null,
                        'Anula depósito CHT',
                        false,
                        null,
                        null,
                        PeriodoContableCierreSupport::ALCANCE_CAJA,
                    );
                } else {
                    $this->asientoRepository->delete($asientoId);
                }
                $asientos++;
            }

            foreach ($cheques as $cheque) {
                $cheque->fecha_deposito = null;
                $cheque->cuentacaja_deposito_id = null;
                $cheque->nro_boleta_deposito = null;
                $cheque->asiento_acreditacion_id = null;
                $cheque->estado = ' ';
                $cheque->save();
            }

            return [
                'cheques' => $cheques->count(),
                'asientos' => $asientos,
                'ids' => $cheques->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            ];
        });

        foreach ($resultado['ids'] as $chequeId) {
            $cheque = Cheque::query()->find($chequeId);
            if (! $cheque) {
                continue;
            }
            if (! ChequeTerceroDepositoAnitaSupport::desmarcarDeposito($cheque)) {
                $anitaFallos[] = 'Anita no actualizó el cheque '.trim((string) $cheque->numerocheque).' (interno '.(int) $cheque->nro_interno_anita.').';
            }
        }

        return [
            'cheques' => (int) $resultado['cheques'],
            'asientos' => (int) $resultado['asientos'],
            'modo' => $modo,
            'anita_fallos' => $anitaFallos,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cheque>  $cheques
     */
    private function assertMismaBoleta($cheques): void
    {
        $fechas = [];
        $cuentas = [];
        $boletas = [];
        $empresas = [];

        foreach ($cheques as $cheque) {
            if ((string) ($cheque->origen ?? '') !== 'R') {
                throw new InvalidArgumentException('El cheque '.$this->etiqueta($cheque).' no es de terceros.');
            }
            if (! $this->empresaRepository->empresaIdPermitida((int) $cheque->empresa_id)) {
                throw new InvalidArgumentException('No tiene acceso a la empresa del cheque '.$this->etiqueta($cheque).'.');
            }
            if ($this->fechaYmd($cheque->fecha_deposito) === '') {
                throw new InvalidArgumentException('El cheque '.$this->etiqueta($cheque).' no está depositado.');
            }
            if ((string) ($cheque->estado ?? '') === 'R') {
                throw new InvalidArgumentException('El cheque '.$this->etiqueta($cheque).' está rechazado.');
            }
            if ((string) ($cheque->estado ?? '') === 'A') {
                throw new InvalidArgumentException('El cheque '.$this->etiqueta($cheque).' está anulado.');
            }
            if ($this->fechaYmd($cheque->fecha_acreditacion) !== '') {
                throw new InvalidArgumentException('El cheque '.$this->etiqueta($cheque).' ya está acreditado.');
            }

            $fechas[$this->fechaYmd($cheque->fecha_deposito)] = true;
            $cuentas[(int) ($cheque->cuentacaja_deposito_id ?? 0)] = true;
            $boletas[trim((string) ($cheque->nro_boleta_deposito ?? ''))] = true;
            $empresas[(int) ($cheque->empresa_id ?? 0)] = true;
        }

        if (count($fechas) !== 1 || count($cuentas) !== 1 || count($boletas) !== 1 || count($empresas) !== 1) {
            throw new InvalidArgumentException('Los cheques no forman una sola boleta.');
        }
    }

    private function etiqueta(Cheque $cheque): string
    {
        $nro = trim((string) ($cheque->numerocheque ?? ''));

        return $nro !== '' ? $nro : '#'.$cheque->id;
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $texto = substr(trim((string) $fecha), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) ? $texto : '';
    }
}
