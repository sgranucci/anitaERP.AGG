<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Repositories\Contable\Sicore_ConfigRepositoryInterface;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SicoreConciliacionContableService
{
    public function __construct(
        private readonly Sicore_ConfigRepositoryInterface $configRepository,
        private readonly AnitaMayorAnaliticoSupport $mayorAnaliticoSupport,
    ) {
    }

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

        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $items = [];
        foreach ($configs as $config) {
            $configId = (int) $config->id;
            $cuentaIds = $this->configRepository->cuentaIdsPorConfigEmpresa($configId, $empresaId);

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

            $movimientosErp = $this->listarMayorAnaliticoErp($empresaId, $desde, $hasta, $cuentaIds);
            $movimientosAnita = $this->listarMayorAnaliticoAnita($empresaId, $desde, $hasta, $config, $cuentaIds);

            $movimientosMayorCompleto = $movimientosErp !== [] ? $movimientosErp : $movimientosAnita;
            $fuenteMayor = $movimientosErp !== [] ? 'erp' : ($movimientosAnita !== [] ? 'anita' : 'ninguna');

            // Totales y matching 1:1 solo con generación de retención (sin pago DDJJ SICORE / reclas / compensación).
            $particion = SicoreMayorComparableSupport::particionar($movimientosMayorCompleto);
            $movimientosComparable = $particion['comparables'];
            $movimientosExcluidos = $particion['excluidos'];
            $totalMayorNeto = $particion['total_comparable'];
            $movimientosMayorCompleto = array_merge($movimientosComparable, $movimientosExcluidos);
            usort($movimientosMayorCompleto, static function (array $a, array $b): int {
                return [(string) ($a['fecha'] ?? ''), (int) ($a['asiento_id'] ?? 0)]
                    <=> [(string) ($b['fecha'] ?? ''), (int) ($b['asiento_id'] ?? 0)];
            });

            $totalMayorNetoErp = $fuenteMayor === 'erp'
                ? $totalMayorNeto
                : SicoreMayorComparableSupport::particionar($movimientosErp)['total_comparable'];
            $totalMayorNetoAnita = $fuenteMayor === 'anita'
                ? $totalMayorNeto
                : SicoreMayorComparableSupport::particionar($movimientosAnita)['total_comparable'];

            $tolerancia = SicoreFormatoV8Support::tolerancia();

            $auditoria = SicoreConciliacionAuditoriaSupport::auditarOperaciones(
                $registrosConfig,
                $movimientosComparable,
                $cuentaInversa,
                $tolerancia,
            );

            $explicacion = SicoreConciliacionAuditoriaSupport::explicacionDiferencia(
                $totalSicore,
                $totalMayorNeto,
                $auditoria['resumen'] ?? [],
            );

            $dif = (float) ($explicacion['diferencia'] ?? round($totalSicore - $totalMayorNeto, 2));

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
                'total_mayor' => $totalMayorNeto,
                'total_mayor_neto' => $totalMayorNeto,
                'total_mayor_completo' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($movimientosMayorCompleto),
                'total_mayor_excluido' => $particion['total_excluido'],
                'total_mayor_saldo_invertido' => SicoreConciliacionAuditoriaSupport::totalMayorSaldoInvertido(
                    $movimientosComparable,
                    $cuentaInversa,
                ),
                'total_mayor_erp' => $totalMayorNetoErp,
                'total_mayor_anita' => $totalMayorNetoAnita,
                'diferencia' => $dif,
                'cuadra' => SicoreFormatoV8Support::cuadra($totalSicore, $totalMayorNeto),
                'explicacion_diferencia' => $explicacion,
                'registros' => count($registrosConfig),
                'fuente_mayor' => $fuenteMayor,
                'movimientos_mayor' => $movimientosMayorCompleto,
                'movimientos_mayor_comparable' => $movimientosComparable,
                'movimientos_mayor_excluidos' => $movimientosExcluidos,
                'auditoria' => $auditoria,
            ];
        }

        return [
            'habilitada' => true,
            'items' => $items,
            'tolerancia' => SicoreFormatoV8Support::tolerancia(),
        ];
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return list<array<string, mixed>>
     */
    private function listarMayorAnaliticoErp(int $empresaId, string $desde, string $hasta, array $cuentaIds): array
    {
        if ($cuentaIds === [] || $desde === '' || $hasta === '') {
            return [];
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$desde, $hasta])
            ->whereIn('am.cuentacontable_id', $cuentaIds)
            ->orderBy('a.fecha')
            ->orderBy('am.id')
            ->get([
                'a.fecha',
                'a.id as asiento_id',
                'c.codigo as cuenta_codigo',
                'c.nombre as cuenta_nombre',
                'am.monto',
                'am.observacion',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $monto = (float) $fila->monto;
            $out[] = [
                'fecha' => (string) $fila->fecha,
                'asiento_id' => (int) $fila->asiento_id,
                'cuenta_codigo' => (string) $fila->cuenta_codigo,
                'cuenta_nombre' => (string) $fila->cuenta_nombre,
                'debe' => $monto > 0 ? round($monto, 2) : null,
                'haber' => $monto < 0 ? round(abs($monto), 2) : null,
                'neto_haber' => round(-$monto, 2),
                'detalle' => trim((string) ($fila->observacion ?? '')),
                'origen' => 'erp',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return list<array<string, mixed>>
     */
    private function listarMayorAnaliticoAnita(
        int $empresaId,
        string $desde,
        string $hasta,
        Sicore_Config $config,
        array $cuentaIds,
    ): array {
        if ($cuentaIds === [] || $desde === '' || $hasta === '') {
            return [];
        }

        $codigosCuenta = $config->cuentas
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->map(static fn ($c) => (int) preg_replace('/\D/', '', (string) ($c->cuentacontable?->codigo ?? '')))
            ->filter(static fn (int $cod) => $cod > 0)
            ->unique()
            ->values()
            ->all();

        if ($codigosCuenta === []) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $desde);
        $hastaAnita = (int) str_replace('-', '', $hasta);

        $nombresCuenta = $config->cuentas
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->mapWithKeys(static fn ($c) => [
                (int) preg_replace('/\D/', '', (string) ($c->cuentacontable?->codigo ?? '')) => (string) ($c->cuentacontable?->nombre ?? ''),
            ])
            ->all();

        $out = [];
        foreach ($this->mayorAnaliticoSupport->listarMovimientosPeriodo(
            $empresaAnita,
            $desdeAnita,
            $hastaAnita,
            $codigosCuenta,
        ) as $mov) {
            $codigoCuenta = (int) ($mov['cuenta_codigo'] ?? 0);
            $out[] = array_merge($mov, [
                'cuenta_nombre' => $nombresCuenta[$codigoCuenta] ?? (string) ($mov['cuenta_nombre'] ?? ''),
                'origen' => 'anita',
            ]);
        }

        return $out;
    }
}
