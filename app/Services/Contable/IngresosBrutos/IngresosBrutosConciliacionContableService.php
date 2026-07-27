<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Contable\Iibb_Presentacion_Config;
use App\Repositories\Contable\Iibb_Presentacion_ConfigRepositoryInterface;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación IIBB vs mayor (período) + saldo ejercicio (col. O/P mayor plano).
 *
 * Total mayor: movimientos de la quincena excluyendo pago de liquidación ARBA
 * (ídem SICORE con pago DDJJ). Saldo ejerc.: columna Saldo ejerc. del mayor plano
 * al cierre de fecha_hasta (debe−haber, tal cual en pantalla).
 */
final class IngresosBrutosConciliacionContableService
{
    public function __construct(
        private readonly Iibb_Presentacion_ConfigRepositoryInterface $configRepository,
        private readonly AnitaMayorAnaliticoSupport $mayorAnaliticoSupport,
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, mixed>
     */
    public function conciliar(array $filtros, array $registros, Iibb_Presentacion_Config $config): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0 || empty($filtros['conciliar_contable'])) {
            return ['habilitada' => false, 'items' => []];
        }

        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $cuentaIds = $this->configRepository->cuentaIdsPorConfigEmpresa((int) $config->id, $empresaId);

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

        $totalIibb = round(array_sum(array_map(
            static fn (array $r) => (float) ($r['importe'] ?? 0),
            $registros,
        )), 2);

        $cuentaInversa = SicoreConciliacionAuditoriaSupport::cuentasSonInversas($cuentasDetalle);

        $movimientosErp = $this->listarMayorAnaliticoErp($empresaId, $desde, $hasta, $cuentaIds);
        $movimientosAnita = $this->listarMayorAnaliticoAnita($empresaId, $desde, $hasta, $config, $cuentaIds);
        $movimientosMayorCompleto = $movimientosErp !== [] ? $movimientosErp : $movimientosAnita;
        $fuenteMayor = $movimientosErp !== [] ? 'erp' : ($movimientosAnita !== [] ? 'anita' : 'ninguna');

        // Excluye pago liquidación ARBA / DDJJ / reclas (patrones compartidos con SICORE).
        $particion = SicoreMayorComparableSupport::particionar($movimientosMayorCompleto, null);
        $movimientosComparable = $particion['comparables'];
        $movimientosExcluidos = $particion['excluidos'];
        $totalMayorNeto = $particion['total_comparable'];

        $tolerancia = IngresosBrutosFormatoArbaSupport::tolerancia();

        $auditoria = SicoreConciliacionAuditoriaSupport::auditarOperaciones(
            $registros,
            $movimientosComparable,
            $cuentaInversa,
            $tolerancia,
        );
        $explicacion = SicoreConciliacionAuditoriaSupport::explicacionDiferencia(
            $totalIibb,
            $totalMayorNeto,
            $auditoria['resumen'] ?? [],
        );
        $dif = (float) ($explicacion['diferencia'] ?? round($totalIibb - $totalMayorNeto, 2));

        // Col. O/P del mayor plano a fecha_hasta (debe − haber), tal cual en pantalla.
        // saldoComparable() invierte a neto haber (SICORE); acá mostramos el signo del mayor.
        $saldoComparable = $this->saldoEjercicioSupport->saldoComparable(
            $empresaId,
            $hasta,
            $cuentasDetalle,
            $cuentaInversa,
        );
        $saldoEjercicio = round(-$saldoComparable, 2);
        // Dif. vs saldo en convención comparable (Total IIBB − |saldo plano| / neto haber).
        $difSaldo = round($totalIibb - $saldoComparable, 2);

        $movimientosMayorCompletoMarcado = array_merge($movimientosComparable, $movimientosExcluidos);

        return [
            'habilitada' => true,
            'items' => [[
                'config_id' => (int) $config->id,
                'codigo_impuesto' => (int) ($config->codigo_actividad_arba ?? 0),
                'nombre' => $config->nombre,
                'criterio' => $config->tipo,
                'concilia_con' => 'ingresos_brutos',
                'cuentas' => $cuentasDetalle,
                'cuenta_inversa' => $cuentaInversa,
                'total_sicore' => $totalIibb,
                'total_iibb' => $totalIibb,
                'total_mayor' => $totalMayorNeto,
                'total_mayor_neto' => $totalMayorNeto,
                'total_mayor_excluido' => $particion['total_excluido'],
                'diferencia' => $dif,
                'cuadra' => IngresosBrutosFormatoArbaSupport::cuadra($totalIibb, $totalMayorNeto),
                'saldo_ejercicio' => $saldoEjercicio,
                'saldo_ejercicio_comparable' => $saldoComparable,
                'diferencia_sicore_saldo' => $difSaldo,
                'diferencia_iibb_saldo' => $difSaldo,
                'cuadra_saldo' => IngresosBrutosFormatoArbaSupport::cuadra($totalIibb, $saldoComparable),
                'explicacion_diferencia' => $explicacion,
                'registros' => count($registros),
                'fuente_mayor' => $fuenteMayor,
                'movimientos_mayor' => $movimientosMayorCompletoMarcado,
                'movimientos_mayor_comparable' => $movimientosComparable,
                'movimientos_mayor_excluidos' => $movimientosExcluidos,
                'auditoria' => $auditoria,
                'tolerancia' => $tolerancia,
            ]],
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
        Iibb_Presentacion_Config $config,
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
