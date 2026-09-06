<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use Illuminate\Support\Collection;

/**
 * Conciliación SICORE vs mayor: suma del período vs col. P (saldo ejerc.)
 * del último movimiento de la quincena/mes elegida.
 */
final class SicoreConciliacionContableService
{
    public function __construct(
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $registros
     * @param  Collection<int, Sicore_Config>  $configs
     * @return array<string, mixed>
     */
    public function conciliar(array $filtros, array $registros, Collection $configs): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0 || empty($filtros['conciliar_contable'])) {
            return ['habilitada' => false, 'items' => []];
        }

        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $tolerancia = SicoreFormatoV8Support::toleranciaConciliacion();

        // Precarga saldos de ejercicio (col. P) de todas las cuentas del proceso en un solo mayor plano.
        $codigosSaldo = [];
        foreach ($configs as $configPrecarga) {
            foreach ($configPrecarga->cuentas->where('empresa_id', $empresaId) as $c) {
                $codigo = (int) preg_replace('/\D/', '', (string) ($c->cuentacontable?->codigo ?? ''));
                if ($codigo > 0) {
                    $codigosSaldo[$codigo] = $codigo;
                }
            }
        }
        $this->saldoEjercicioSupport->precargar($empresaId, $hasta, array_values($codigosSaldo));

        $items = [];
        foreach ($configs as $config) {
            $configId = (int) $config->id;

            $registrosConfig = array_values(array_filter(
                $registros,
                static fn (array $r) => (int) ($r['sicore_config_id'] ?? 0) === $configId,
            ));

            $totalSicore = round(array_sum(array_map(
                static fn (array $r) => (float) ($r['importe'] ?? 0),
                $registrosConfig,
            )), 2);

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

            $cuentaInversa = SicoreConciliacionAuditoriaSupport::cuentasSonInversas($cuentasDetalle);

            // Col. P del mayor plano: saldo de ejercicio al último movimiento ≤ fecha_hasta.
            $totalMayor = $this->saldoEjercicioSupport->saldoComparable(
                $empresaId,
                $hasta,
                $cuentasDetalle,
                $cuentaInversa,
            );

            $dif = round($totalSicore - $totalMayor, 2);

            $items[] = [
                'config_id' => $configId,
                'codigo_impuesto' => (int) $config->codigo_impuesto,
                'codigo_regimen' => (int) ($config->codigo_regimen ?? 0),
                'nombre' => $config->nombre,
                'criterio' => $config->criterio,
                'concilia_con' => $config->concilia_con,
                'cuentas' => $cuentasDetalle,
                'cuenta_inversa' => $cuentaInversa,
                'total_sicore' => $totalSicore,
                'total_mayor' => $totalMayor,
                'diferencia' => $dif,
                'cuadra' => SicoreFormatoV8Support::cuadraConciliacion($totalSicore, $totalMayor),
                'registros' => count($registrosConfig),
                'tolerancia' => $tolerancia,
            ];
        }

        return [
            'habilitada' => true,
            'items' => $items,
            'tolerancia' => $tolerancia,
            'saldo_ejercicio_desde' => self::ymdAIso(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD),
            'saldo_ejercicio_hasta' => $hasta,
        ];
    }

    private static function ymdAIso(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
