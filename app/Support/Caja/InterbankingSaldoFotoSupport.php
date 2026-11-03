<?php

namespace App\Support\Caja;

use App\Models\Caja\InterbankingSaldoDiario;
use Carbon\Carbon;

/**
 * Una foto de saldos es real solo si trae todas las cuentas del último día
 * completo. Un día a medias (faltan bancos) no se usa como cierre.
 */
final class InterbankingSaldoFotoSupport
{
    /** @var list<int> */
    public const EMPRESAS = [1, 2, 3];

    /** Por debajo de esto no hay una foto histórica contra la cual comparar. */
    private const MIN_CUENTAS_REFERENCIA = 10;

    public function fechaHabilAnterior(?Carbon $hoy = null): string
    {
        $fecha = ($hoy ?? Carbon::today())->copy()->startOfDay()->subDay();
        while ($fecha->isWeekend()) {
            $fecha->subDay();
        }

        return $fecha->toDateString();
    }

    /**
     * @param  list<int>  $empresaIds
     */
    public function esReal(string $fecha, array $empresaIds = self::EMPRESAS): bool
    {
        $fotos = $this->fotosAntesDe(
            Carbon::parse($fecha)->addDay()->toDateString(),
            $empresaIds,
        );

        return in_array($fecha, $this->fechasReales($fotos), true);
    }

    /**
     * Último día con foto real estrictamente anterior a la posición.
     *
     * @param  list<int>  $empresaIds
     */
    public function fechaCierreRealAnterior(string $fechaPosicion, array $empresaIds = self::EMPRESAS): ?string
    {
        $reales = $this->fechasReales($this->fotosAntesDe($fechaPosicion, $empresaIds));
        if ($reales === []) {
            return null;
        }

        return $reales[array_key_last($reales)];
    }

    /**
     * @param  array<string, array<string, true>>  $clavesPorFecha
     * @return list<string>
     */
    public function fechasReales(array $clavesPorFecha): array
    {
        $referencia = $this->clavesReferencia($clavesPorFecha);
        if ($referencia === []) {
            return [];
        }

        $reales = [];
        foreach ($clavesPorFecha as $fecha => $claves) {
            if ($fecha !== '' && $this->cubre($claves, $referencia)) {
                $reales[] = $fecha;
            }
        }
        sort($reales);

        return $reales;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, array<string, true>>
     */
    public function fotosAntesDe(string $fechaExclusiva, array $empresaIds = self::EMPRESAS, int $dias = 45): array
    {
        $desde = Carbon::parse($fechaExclusiva)->subDays($dias)->toDateString();
        $rows = InterbankingSaldoDiario::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<', $fechaExclusiva)
            ->whereIn('empresa_id', $empresaIds)
            ->get(['fecha', 'empresa_id', 'bank_number', 'account_number', 'currency']);

        $fotos = [];
        foreach ($rows as $row) {
            $fecha = $row->fecha?->toDateString() ?? '';
            if ($fecha === '') {
                continue;
            }
            $fotos[$fecha][$this->clave($row)] = true;
        }

        return $fotos;
    }

    /**
     * @param  array<string, array<string, true>>  $clavesPorFecha
     * @return array<string, true>
     */
    private function clavesReferencia(array $clavesPorFecha): array
    {
        $max = 0;
        foreach ($clavesPorFecha as $claves) {
            $max = max($max, count($claves));
        }
        if ($max < self::MIN_CUENTAS_REFERENCIA) {
            return [];
        }

        $elegida = null;
        foreach ($clavesPorFecha as $fecha => $claves) {
            if (count($claves) === $max && ($elegida === null || $fecha > $elegida)) {
                $elegida = $fecha;
            }
        }

        return $elegida === null ? [] : $clavesPorFecha[$elegida];
    }

    /**
     * @param  array<string, true>  $presentes
     * @param  array<string, true>  $referencia
     */
    private function cubre(array $presentes, array $referencia): bool
    {
        foreach ($referencia as $clave => $_) {
            if (! isset($presentes[$clave])) {
                return false;
            }
        }

        return true;
    }

    private function clave(InterbankingSaldoDiario $row): string
    {
        return $row->empresa_id.'|'.$row->bank_number.'|'.$row->account_number.'|'.$row->currency;
    }
}
