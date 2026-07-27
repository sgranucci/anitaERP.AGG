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
use App\Support\Contable\Sicore\SicoreProveedorErpSupport;
use App\Support\Contable\Sicore\SicoreSaldoEjercicioSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SicoreConciliacionContableService
{
    public function __construct(
        private readonly Sicore_ConfigRepositoryInterface $configRepository,
        private readonly AnitaMayorAnaliticoSupport $mayorAnaliticoSupport,
        private readonly SicoreSaldoEjercicioSupport $saldoEjercicioSupport,
        private readonly SicoreProveedorErpSupport $proveedorSupport = new SicoreProveedorErpSupport(),
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

        // Precarga saldos de ejercicio (col. O) de todas las cuentas del proceso en un solo mayor plano.
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

            $esSueldos = (string) $config->criterio === 'sueldos';

            // 4ta categoría: mayor = ctamov con ctav_tipo_asiento='PER' sobre la cuenta configurada.
            // Compras/ventas: mayor analítico habitual (subdiario+ctamov o ERP).
            if ($esSueldos) {
                $movimientosErp = [];
                $movimientosAnita = $this->listarMayorAnitaAsientosPer($empresaId, $desde, $hasta, $config, $cuentaIds);
                $movimientosMayorCompleto = $movimientosAnita;
                $fuenteMayor = $movimientosAnita !== [] ? 'anita' : 'ninguna';
            } else {
                $movimientosErp = $this->listarMayorAnaliticoErp($empresaId, $desde, $hasta, $cuentaIds);
                $movimientosAnita = $this->listarMayorAnaliticoAnita($empresaId, $desde, $hasta, $config, $cuentaIds);
                $movimientosMayorCompleto = $movimientosErp !== [] ? $movimientosErp : $movimientosAnita;
                $fuenteMayor = $movimientosErp !== [] ? 'erp' : ($movimientosAnita !== [] ? 'anita' : 'ninguna');
            }

            // Compras: retención OPP/AOP y devoluciones CHP con subd_emisor en maestro.
            $emisoresProveedor = null;
            if (in_array((string) $config->criterio, ['compras_ganancias', 'compras_iva'], true)) {
                $emisoresProveedor = $this->resolverEmisoresProveedor($movimientosMayorCompleto);
            }

            // Totales/matching: generación-anulación retención (sin pago DDJJ / reclas / no-OPP).
            $particion = SicoreMayorComparableSupport::particionar(
                $movimientosMayorCompleto,
                $esSueldos ? null : $emisoresProveedor,
            );
            $movimientosComparable = $particion['comparables'];
            $movimientosExcluidos = $particion['excluidos'];
            $totalMayorNeto = $particion['total_comparable'];
            $movimientosMayorCompleto = array_merge($movimientosComparable, $movimientosExcluidos);
            usort($movimientosMayorCompleto, static function (array $a, array $b): int {
                return [(string) ($a['fecha'] ?? ''), (int) ($a['asiento_id'] ?? 0)]
                    <=> [(string) ($b['fecha'] ?? ''), (int) ($b['asiento_id'] ?? 0)];
            });

            // Matching ±1 día solo compras (AOP contable al día siguiente). Sueldos PER: mismo pool.
            $movimientosComparableMatch = $esSueldos
                ? $movimientosComparable
                : $this->movimientosComparablesParaMatch(
                    $empresaId,
                    $desde,
                    $hasta,
                    $config,
                    $cuentaIds,
                    $fuenteMayor,
                    $movimientosComparable,
                    $emisoresProveedor,
                );

            $totalMayorNetoErp = $fuenteMayor === 'erp'
                ? $totalMayorNeto
                : SicoreMayorComparableSupport::particionar($movimientosErp, $emisoresProveedor)['total_comparable'];
            $totalMayorNetoAnita = $fuenteMayor === 'anita'
                ? $totalMayorNeto
                : SicoreMayorComparableSupport::particionar($movimientosAnita, $emisoresProveedor)['total_comparable'];

            $tolerancia = SicoreFormatoV8Support::tolerancia();

            $auditoria = SicoreConciliacionAuditoriaSupport::auditarOperaciones(
                $registrosConfig,
                $movimientosComparable,
                $cuentaInversa,
                $tolerancia,
                $movimientosComparableMatch,
            );

            $explicacion = SicoreConciliacionAuditoriaSupport::explicacionDiferencia(
                $totalSicore,
                $totalMayorNeto,
                $auditoria['resumen'] ?? [],
            );

            $dif = (float) ($explicacion['diferencia'] ?? round($totalSicore - $totalMayorNeto, 2));

            // Saldo de ejercicio (col. O mayor plano): 01/01/2026 → fecha_hasta.
            // Independiente del Total mayor del período; no altera el badge Cuadra.
            $saldoEjercicio = $this->saldoEjercicioSupport->saldoComparable(
                $empresaId,
                $hasta,
                $cuentasDetalle,
                $cuentaInversa,
            );
            $difSaldo = round($totalSicore - $saldoEjercicio, 2);

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
                'saldo_ejercicio' => $saldoEjercicio,
                'diferencia_sicore_saldo' => $difSaldo,
                'cuadra_saldo' => SicoreFormatoV8Support::cuadra($totalSicore, $saldoEjercicio),
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
            'saldo_ejercicio_desde' => '2026-01-01',
            'saldo_ejercicio_hasta' => $hasta,
        ];
    }

    /**
     * Pool de mayor para matching: incluye ±1 día calendario respecto del filtro,
     * sin alterar los totales del período.
     *
     * @param  list<int>  $cuentaIds
     * @param  list<array<string, mixed>>  $movimientosComparablePeriodo
     * @param  array<string, true>|null  $emisoresProveedor
     * @return list<array<string, mixed>>
     */
    private function movimientosComparablesParaMatch(
        int $empresaId,
        string $desde,
        string $hasta,
        Sicore_Config $config,
        array $cuentaIds,
        string $fuenteMayor,
        array $movimientosComparablePeriodo,
        ?array $emisoresProveedor = null,
    ): array {
        if ($desde === '' || $hasta === '' || $fuenteMayor === 'ninguna') {
            return $movimientosComparablePeriodo;
        }

        try {
            $desdeMatch = (new \DateTimeImmutable($desde))->modify('-1 day')->format('Y-m-d');
            $hastaMatch = (new \DateTimeImmutable($hasta))->modify('+1 day')->format('Y-m-d');
        } catch (\Exception) {
            return $movimientosComparablePeriodo;
        }

        if ($desdeMatch === $desde && $hastaMatch === $hasta) {
            return $movimientosComparablePeriodo;
        }

        $ampliado = $fuenteMayor === 'erp'
            ? $this->listarMayorAnaliticoErp($empresaId, $desdeMatch, $hastaMatch, $cuentaIds)
            : $this->listarMayorAnaliticoAnita($empresaId, $desdeMatch, $hastaMatch, $config, $cuentaIds);

        if ($emisoresProveedor === null && in_array((string) $config->criterio, ['compras_ganancias', 'compras_iva'], true)) {
            $emisoresProveedor = $this->resolverEmisoresProveedor($ampliado);
        }

        return SicoreMayorComparableSupport::particionar($ampliado, $emisoresProveedor)['comparables'];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, true>
     */
    private function resolverEmisoresProveedor(array $movimientos): array
    {
        $codigos = [];
        foreach ($movimientos as $mov) {
            $emisor = trim((string) ($mov['subd_emisor'] ?? ''));
            if ($emisor !== '') {
                $codigos[] = $emisor;
            }
        }

        return $this->proveedorSupport->indicesExistentes($codigos);
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
     * 4ta categoría: asientos de liquidación de personal (ctav_tipo_asiento = PER)
     * sobre la cuenta de retención configurada.
     *
     * @param  list<int>  $cuentaIds
     * @return list<array<string, mixed>>
     */
    private function listarMayorAnitaAsientosPer(
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
        foreach ($this->mayorAnaliticoSupport->listarMovimientosCtamovTipoAsiento(
            $empresaAnita,
            $desdeAnita,
            $hastaAnita,
            $codigosCuenta,
            'PER',
        ) as $mov) {
            $codigoCuenta = (int) ($mov['cuenta_codigo'] ?? 0);
            $out[] = array_merge($mov, [
                'cuenta_nombre' => $nombresCuenta[$codigoCuenta] ?? (string) ($mov['cuenta_nombre'] ?? ''),
                'origen' => 'anita',
            ]);
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
