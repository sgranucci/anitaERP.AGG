<?php

declare(strict_types=1);

namespace App\Services\Contable\Suss;

use App\Models\Contable\Suss_Presentacion_Config;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use App\Support\Contable\Suss\SussFormatoF2004Support;
use App\Support\Contable\Suss\SussLiquidacionTipoSupport;

/**
 * Conciliación SUSS vs mayor: suma del período vs col. P (saldo ejerc.)
 * del último movimiento de la quincena/mes elegida.
 */
final class SussConciliacionContableService
{
    public function __construct(
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, mixed>
     */
    public function conciliar(array $filtros, array $registros, Suss_Presentacion_Config $config): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0 || empty($filtros['conciliar_contable'])) {
            return ['habilitada' => false, 'items' => []];
        }

        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $cuentasDetalle = $config->cuentas
            ->where('empresa_id', $empresaId)
            ->map(static fn ($c) => [
                'id' => (int) $c->cuentacontable_id,
                'codigo' => $c->cuentacontable?->codigo ?? '',
                'nombre' => $c->cuentacontable?->nombre ?? '',
                'tipocuenta' => $c->cuentacontable?->tipocuenta ?? null,
            ])
            ->values()
            ->all();

        $totalSuss = round(array_sum(array_map(
            static fn (array $r) => (float) ($r['importe'] ?? 0),
            $registros,
        )), 2);

        $cuentaInversa = SicoreConciliacionAuditoriaSupport::cuentasSonInversas($cuentasDetalle);

        // Col. P del mayor plano: saldo de ejercicio al último movimiento ≤ fecha_hasta.
        $totalMayor = $this->saldoEjercicioSupport->saldoComparable(
            $empresaId,
            $hasta,
            $cuentasDetalle,
            $cuentaInversa,
        );

        $tolerancia = SussFormatoF2004Support::tolerancia();

        // Pago de una quincena anterior registrado después del «hasta»: el mayor todavía tiene esa
        // retención sin pagar. Se separa y se avisa; no cuenta como diferencia.
        $pagoPosterior = $this->pagosPosterioresAlHasta(
            $empresaId,
            $hasta,
            array_map(static fn (array $c) => (int) preg_replace('/\D/', '', (string) ($c['codigo'] ?? '')), $cuentasDetalle),
            (string) ($filtros['periodo'] ?? ''),
        );
        $mayorEsperado = round($totalMayor - $pagoPosterior, 2);
        $dif = round($totalSuss - $totalMayor + $pagoPosterior, 2);

        return [
            'habilitada' => true,
            'items' => [[
                'config_id' => (int) $config->id,
                'codigo_impuesto' => (int) ($config->codigo_impuesto ?? SussFormatoF2004Support::IMPUESTO),
                'nombre' => $config->nombre,
                'criterio' => 'retenciones_suss',
                'concilia_con' => 'suss',
                'cuentas' => $cuentasDetalle,
                'cuenta_inversa' => $cuentaInversa,
                'total_suss' => $totalSuss,
                'total_mayor' => $totalMayor,
                'pago_posterior' => $pagoPosterior,
                'mayor_comparable' => $mayorEsperado,
                'diferencia' => $dif,
                'cuadra' => SussFormatoF2004Support::cuadra($totalSuss, $mayorEsperado),
                'registros' => count($registros),
                'tolerancia' => $tolerancia,
            ]],
            'tolerancia' => $tolerancia,
            'saldo_ejercicio_desde' => self::ymdAIso(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD),
            'saldo_ejercicio_hasta' => $hasta,
        ];
    }

    /**
     * Suma de pagos SUSS (debe) de quincenas anteriores asentados después de $hasta, hasta hoy.
     * Se excluyen los del mismo mes que se concilia (ej. el pago a cuenta de la 1Q, que cae después del hasta).
     *
     * @param  list<int>  $codigosCuenta
     */
    public function pagosPosterioresAlHasta(int $empresaId, string $hastaIso, array $codigosCuenta, string $periodoYm): float
    {
        $hoy = date('Y-m-d');
        if ($empresaId <= 0 || $hastaIso === '' || $hoy <= $hastaIso || $codigosCuenta === []) {
            return 0.0;
        }

        $desde = date('Y-m-d', strtotime($hastaIso.' +1 day'));
        $lineas = $this->saldoEjercicioSupport->lineasPeriodo($empresaId, $desde, $hoy, $codigosCuenta);
        $clave = SussLiquidacionTipoSupport::claveMes($periodoYm);
        if ($clave === null) {
            return 0.0;
        }

        // Solo cuentan pagos de períodos ANTERIORES al que se concilia: el saldo al «hasta» todavía
        // los tiene como deuda. Los del mismo período (1Q pagada después del corte) y los de períodos
        // posteriores no afectan ese saldo, así que no se suman.
        $suma = 0.0;
        foreach ($lineas as $linea) {
            $debe = (float) ($linea['debe'] ?? 0);
            $fecha = (string) ($linea['fecha'] ?? '');
            if ($debe < 0.005 || $fecha <= $hastaIso) {
                continue;
            }
            $p = SussLiquidacionTipoSupport::periodoDeDetalleSuss((string) ($linea['detalle'] ?? ''));
            if ($p === null) {
                continue;
            }
            if (($p['anio'] * 100 + $p['mes']) < $clave) {
                $suma += $debe;
            }
        }

        return round($suma, 2);
    }

    private static function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
