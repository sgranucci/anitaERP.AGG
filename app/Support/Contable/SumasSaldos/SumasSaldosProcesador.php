<?php

namespace App\Support\Contable\SumasSaldos;

use App\Services\Contable\AnitaAsientoImportService;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\SumasSaldosListadoFiltros;
use Illuminate\Support\Facades\DB;

/**
 * Motor del Balance de Sumas y Saldos (Anita l-sumsal) solo sobre anitaERP.
 *
 * Columnas (por cuenta imputable):
 *  - débitos / créditos del período
 *  - saldo período (= debe − haber)
 *  - saldo al mes anterior (neto desde inicio ejercicio hasta antes del desde)
 *  - saldo del ejercicio (= mes anterior + período)
 */
class SumasSaldosProcesador
{
    /** @var array<string, float> Cache cotización venta por fecha|moneda_id */
    private array $cacheCotizacionDia = [];

    /**
     * Saldo neto al inicio de un período (suma de cuentacontable_saldo_mes desde
     * ejercicio hasta el mes anterior), con el mismo recorte de asientos excluidos
     * que el Balance de Sumas y Saldos. Pensado para reutilizar desde el mayor, etc.
     *
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros  modo_inclusion_asientos, moneda_id,
     *                                         solo_moneda_origen, cuenta_desde/hasta,
     *                                         cuentas (list), excluir_origen_subdiario
     * @return array{
     *   por_codigo: array<int, float>,
     *   fuente: string,
     *   advertencias: list<string>,
     *   movimientos_restados: int
     * }
     */
    public function saldosInicialesPorCodigo(array $empresaIds, int $periodoDesde, array $filtros = []): array
    {
        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds), fn (int $id) => $id > 0)));
        if ($empresaIds === [] || $periodoDesde <= 0) {
            return [
                'por_codigo' => [],
                'fuente' => 'ninguna',
                'advertencias' => [],
                'movimientos_restados' => 0,
            ];
        }

        $filtrosBase = array_merge([
            'modo_periodo' => SumasSaldosListadoFiltros::MODO_PERIODOS,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoDesde,
            'consolidar_empresas' => true,
            'filtro_cuentas' => SumasSaldosListadoFiltros::CUENTAS_CON_MOVIMIENTO,
            'modo_inclusion_asientos' => 'todos',
            'moneda_id' => CuentacontableSaldoMesSupport::monedaLocalId(),
            'solo_moneda_origen' => false,
            'cuenta_desde' => 0,
            'cuenta_hasta' => 0,
            'excluir_origen_subdiario' => false,
        ], $filtros, [
            // Forzamos períodos: solo necesitamos saldo_mes_anterior.
            'modo_periodo' => SumasSaldosListadoFiltros::MODO_PERIODOS,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoDesde,
            'consolidar_empresas' => true,
        ]);

        if ($this->requiereAsientosPorMoneda($filtrosBase)) {
            return [
                'por_codigo' => [],
                'fuente' => 'asientos',
                'advertencias' => ['Saldo inicial por moneda extranjera no usa agregado mensual; el mayor debe acumular movimientos.'],
                'movimientos_restados' => 0,
            ];
        }

        $resultado = $this->generarDesdeSaldosMes($empresaIds, $filtrosBase);
        $porCodigo = [];
        foreach ($resultado['filas'] ?? [] as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'cuenta') {
                continue;
            }
            $codigo = (int) ($fila['codigo'] ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $porCodigo[$codigo] = round((float) ($fila['saldo_mes_anterior'] ?? 0), 2);
        }

        $cuentas = array_values(array_filter(array_map('intval', $filtrosBase['cuentas'] ?? []), fn (int $c) => $c > 0));
        if ($cuentas !== []) {
            $allow = array_fill_keys($cuentas, true);
            $porCodigo = array_filter($porCodigo, fn (float $v, int $c) => isset($allow[$c]), ARRAY_FILTER_USE_BOTH);
        }

        $movRestados = 0;
        foreach ($resultado['advertencias'] ?? [] as $adv) {
            if (preg_match('/Se restaron\s+(\d+)/', (string) $adv, $m)) {
                $movRestados = (int) $m[1];
                break;
            }
        }

        return [
            'por_codigo' => $porCodigo,
            'fuente' => (string) ($resultado['fuente'] ?? 'saldos_mes'),
            'advertencias' => $resultado['advertencias'] ?? [],
            'movimientos_restados' => $movRestados,
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, float|int>,
     *   fuente: string,
     *   advertencias: list<string>
     * }
     */
    public function generar(array $empresaIds, array $filtros): array
    {
        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds), fn (int $id) => $id > 0)));
        if ($empresaIds === []) {
            return $this->resultadoVacio('ninguna');
        }

        $modo = (string) ($filtros['modo_periodo'] ?? SumasSaldosListadoFiltros::MODO_PERIODOS);
        if ($modo === SumasSaldosListadoFiltros::MODO_RANGO) {
            return $this->generarDesdeAsientos($empresaIds, $filtros);
        }

        // Reexpresar todo en moneda extranjera exige cotización por asiento (no está en el agregado).
        if ($this->requiereAsientosPorMoneda($filtros)) {
            $periodoDesde = (int) ($filtros['periodo_desde'] ?? 0);
            $periodoHasta = (int) ($filtros['periodo_hasta'] ?? 0);
            $fechas = SumasSaldosListadoFiltros::fechasDesdePeriodos($periodoDesde, $periodoHasta);
            if ($fechas === null) {
                return $this->resultadoVacio('asientos');
            }

            $resultado = $this->generarDesdeAsientos($empresaIds, array_merge($filtros, [
                'modo_periodo' => SumasSaldosListadoFiltros::MODO_RANGO,
                'fecha_desde' => $fechas[0],
                'fecha_hasta' => $fechas[1],
            ]));
            $resultado['advertencias'] = array_values(array_unique(array_merge(
                $resultado['advertencias'] ?? [],
                ['Expresar en moneda extranjera (todas las monedas) lee asientos para aplicar la cotización de cada transacción.'],
            )));

            return $resultado;
        }

        return $this->generarDesdeSaldosMes($empresaIds, $filtros);
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarDesdeSaldosMes(array $empresaIds, array $filtros): array
    {
        $periodoDesde = (int) ($filtros['periodo_desde'] ?? 0);
        $periodoHasta = (int) ($filtros['periodo_hasta'] ?? 0);
        $periodoEjercicio = SumasSaldosListadoFiltros::periodoInicioEjercicio($periodoDesde);
        $advertencias = [];

        // Aviso solo si el observer está apagado (los saldos no se actualizan al grabar asientos).
        if (! CuentacontableSaldoMesSupport::observerHabilitado()) {
            $advertencias[] = 'El mantenimiento on-line de saldos mensuales está apagado '
                .'(CONTABLE_SALDOS_CUENTA_MES_OBSERVER=false). '
                .'Tras grabar asientos nuevos conviene reconstruir o activar el observer.';
        }

        $usarLocal = $this->usarMontosLocales($filtros);
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $soloOrigen = ! empty($filtros['solo_moneda_origen']);

        $colDebe = $usarLocal ? 'debe_local' : 'debe';
        $colHaber = $usarLocal ? 'haber_local' : 'haber';
        $colNeto = $usarLocal ? 'monto_local' : 'monto';

        $queryPeriodo = DB::table('cuentacontable_saldo_mes as s')
            ->join('cuentacontable as c', 'c.id', '=', 's.cuentacontable_id')
            ->whereIn('s.empresa_id', $empresaIds)
            ->where('c.tipocuenta', 1)
            ->whereBetween('s.anio_mes', [$periodoDesde, $periodoHasta])
            ->select([
                's.empresa_id',
                's.cuentacontable_id',
                'c.codigo',
                'c.nombre',
                DB::raw("SUM(s.{$colDebe}) as debe"),
                DB::raw("SUM(s.{$colHaber}) as haber"),
                DB::raw("SUM(s.{$colNeto}) as saldo_periodo"),
            ])
            ->groupBy('s.empresa_id', 's.cuentacontable_id', 'c.codigo', 'c.nombre');

        $this->aplicarFiltroMonedaSaldo($queryPeriodo, $usarLocal, $soloOrigen, $monedaId);
        $this->aplicarFiltroCodigoCuenta($queryPeriodo, $filtros, 'c.codigo');

        $porPeriodo = [];
        $sumaDebeHaber = 0.0;
        $sumaNetoAbs = 0.0;
        foreach ($queryPeriodo->get() as $row) {
            $key = $this->claveCuenta((int) $row->empresa_id, (int) $row->cuentacontable_id, $filtros);
            if (! isset($porPeriodo[$key])) {
                $porPeriodo[$key] = [
                    'empresa_id' => (int) $row->empresa_id,
                    'cuentacontable_id' => (int) $row->cuentacontable_id,
                    'codigo' => (int) $row->codigo,
                    'nombre' => (string) $row->nombre,
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'saldo_periodo' => 0.0,
                ];
            }
            $debe = (float) $row->debe;
            $haber = (float) $row->haber;
            $neto = (float) $row->saldo_periodo;
            $porPeriodo[$key]['debe'] += $debe;
            $porPeriodo[$key]['haber'] += $haber;
            $porPeriodo[$key]['saldo_periodo'] += $neto;
            $sumaDebeHaber += abs($debe) + abs($haber);
            $sumaNetoAbs += abs($neto);
        }

        // Sin brutos poblados o agregado incompleto → completar período desde asientos.
        $periodoSinFilas = $porPeriodo === [];
        $necesitaFallbackAsientos = ($sumaDebeHaber < 0.005 && $sumaNetoAbs > 0.005) || $periodoSinFilas;
        if ($necesitaFallbackAsientos) {
            $fechas = SumasSaldosListadoFiltros::fechasDesdePeriodos($periodoDesde, $periodoHasta);
            $desdeAsientosPorCuenta = $fechas === null
                ? []
                : $this->periodoDesdeAsientos($empresaIds, $filtros, $fechas);

            if ($desdeAsientosPorCuenta !== []) {
                $porPeriodo = $desdeAsientosPorCuenta;
                $advertencias[] = $periodoSinFilas
                    ? 'No hay saldos mensuales suficientes para el período. '
                        .'Se calcularon débitos/créditos desde asientos; '
                        .'ejecute contable:reconstruir-saldos-cuenta-mes para acelerar.'
                    : 'Los saldos mensuales aún no tienen débitos/créditos brutos. '
                        .'Se completaron desde asientos del período; ejecute contable:reconstruir-saldos-cuenta-mes '
                        .'para acelerar consultas futuras.';
            } elseif (! $periodoSinFilas) {
                // Hay netos mensuales pero ni el agregado ni los asientos aportan brutos.
                $advertencias[] = 'Los saldos mensuales del período no tienen débitos/créditos brutos '
                    .'y no se encontraron asientos para reconstruirlos; '
                    .'ejecute contable:reconstruir-saldos-cuenta-mes.';
            }
            // Período sin saldos mensuales y sin asientos = simplemente no hay movimientos: sin aviso.
        }

        $queryAnt = DB::table('cuentacontable_saldo_mes as s')
            ->join('cuentacontable as c', 'c.id', '=', 's.cuentacontable_id')
            ->whereIn('s.empresa_id', $empresaIds)
            ->where('c.tipocuenta', 1)
            ->where('s.anio_mes', '>=', $periodoEjercicio)
            ->where('s.anio_mes', '<', $periodoDesde)
            ->select([
                's.empresa_id',
                's.cuentacontable_id',
                'c.codigo',
                'c.nombre',
                DB::raw("SUM(s.{$colNeto}) as saldo_mes_anterior"),
            ])
            ->groupBy('s.empresa_id', 's.cuentacontable_id', 'c.codigo', 'c.nombre');

        $this->aplicarFiltroMonedaSaldo($queryAnt, $usarLocal, $soloOrigen, $monedaId);
        $this->aplicarFiltroCodigoCuenta($queryAnt, $filtros, 'c.codigo');

        $porAnterior = [];
        foreach ($queryAnt->get() as $row) {
            $codigo = (int) $row->codigo;
            $key = ! empty($filtros['consolidar_empresas'])
                ? 'c:'.$codigo
                : $this->claveCuenta((int) $row->empresa_id, (int) $row->cuentacontable_id, $filtros);
            if (! isset($porAnterior[$key])) {
                $porAnterior[$key] = [
                    'empresa_id' => (int) $row->empresa_id,
                    'cuentacontable_id' => (int) $row->cuentacontable_id,
                    'codigo' => $codigo,
                    'nombre' => (string) $row->nombre,
                    'saldo_mes_anterior' => 0.0,
                ];
            }
            $porAnterior[$key]['saldo_mes_anterior'] += (float) $row->saldo_mes_anterior;
        }

        // Si el período vino consolidado por código, unificar también porPeriodo keys.
        if (! empty($filtros['consolidar_empresas'])) {
            $porPeriodo = $this->reindexarPorCodigo($porPeriodo);
        }

        $claves = array_unique(array_merge(array_keys($porPeriodo), array_keys($porAnterior)));
        $acumulado = [];

        foreach ($claves as $key) {
            $base = $porPeriodo[$key] ?? $porAnterior[$key] ?? null;
            if ($base === null) {
                continue;
            }
            $debe = (float) ($porPeriodo[$key]['debe'] ?? 0);
            $haber = (float) ($porPeriodo[$key]['haber'] ?? 0);
            $saldoPeriodo = (float) ($porPeriodo[$key]['saldo_periodo'] ?? ($debe - $haber));
            $saldoAnt = (float) ($porAnterior[$key]['saldo_mes_anterior'] ?? 0);

            $acumulado[$key] = [
                'empresa_id' => (int) $base['empresa_id'],
                'cuentacontable_id' => (int) $base['cuentacontable_id'],
                'codigo' => (int) $base['codigo'],
                'nombre' => (string) $base['nombre'],
                'debe' => $debe,
                'haber' => $haber,
                'saldo_periodo' => $saldoPeriodo,
                'saldo_mes_anterior' => $saldoAnt,
                'saldo_ejercicio' => $saldoAnt + $saldoPeriodo,
            ];
        }

        if (($filtros['filtro_cuentas'] ?? '') === SumasSaldosListadoFiltros::CUENTAS_TODAS) {
            $acumulado = $this->completarCuentasSinMovimiento($acumulado, $empresaIds, $filtros);
        }

        $modoInclusion = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $debeRestarExcluidos = $modoInclusion !== 'todos' || ! empty($filtros['excluir_origen_subdiario']);
        if ($debeRestarExcluidos) {
            $restados = $this->restarAsientosExcluidos(
                $acumulado,
                $empresaIds,
                $filtros,
                $periodoDesde,
                $periodoHasta,
                $periodoEjercicio,
            );
            $acumulado = $restados['acumulado'];
            if ($restados['movimientos'] > 0) {
                $advertencias[] = 'Se restaron '.$restados['movimientos']
                    .' movimiento(s) de asientos excluidos leídos de asiento/asiento_movimiento.';
            }
        }

        return $this->armarResultado($acumulado, $filtros, 'saldos_mes', $advertencias);
    }

    /**
     * Débitos/créditos del período leídos de asientos, indexados igual que el agregado mensual.
     * Devuelve [] cuando el período no tiene movimientos (sin datos, no un agregado desactualizado).
     *
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @param  array{0: string, 1: string}  $fechas
     * @return array<string, array<string, mixed>>
     */
    private function periodoDesdeAsientos(array $empresaIds, array $filtros, array $fechas): array
    {
        $desdeAsientos = $this->generarDesdeAsientos($empresaIds, array_merge($filtros, [
            'modo_periodo' => SumasSaldosListadoFiltros::MODO_RANGO,
            'fecha_desde' => $fechas[0],
            'fecha_hasta' => $fechas[1],
            'modo_inclusion_asientos' => 'todos',
            // El recorte por movimiento se aplica al final, sobre el acumulado combinado.
            'filtro_cuentas' => SumasSaldosListadoFiltros::CUENTAS_CON_MOVIMIENTO,
        ]));

        $porPeriodo = [];
        foreach ($desdeAsientos['filas'] ?? [] as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'cuenta') {
                continue;
            }
            $empresaIdFila = (int) ($fila['empresa_id'] ?? 0);
            $cuentaIdFila = (int) ($fila['cuentacontable_id'] ?? 0);
            $codigoFila = (int) ($fila['codigo'] ?? 0);
            // Filas ya consolidadas traen empresa_id=0 → clave por código.
            $key = ! empty($filtros['consolidar_empresas']) || $empresaIdFila <= 0
                ? 'c:'.$codigoFila
                : $this->claveCuenta($empresaIdFila, $cuentaIdFila, $filtros);
            $porPeriodo[$key] = [
                'empresa_id' => $empresaIdFila,
                'cuentacontable_id' => $cuentaIdFila,
                'codigo' => $codigoFila,
                'nombre' => (string) ($fila['nombre'] ?? ''),
                'debe' => (float) ($fila['debe'] ?? 0),
                'haber' => (float) ($fila['haber'] ?? 0),
                'saldo_periodo' => (float) ($fila['saldo_periodo'] ?? 0),
            ];
        }

        return $porPeriodo;
    }

    /**
     * Resta del agregado mensual los movimientos de tipos excluidos (cierre / inflación).
     * Lee asiento + asiento_movimiento desde inicio de ejercicio hasta fin del período.
     *
     * @param  array<string, array<string, mixed>>  $acumulado
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array{acumulado: array<string, array<string, mixed>>, movimientos: int}
     */
    private function restarAsientosExcluidos(
        array $acumulado,
        array $empresaIds,
        array $filtros,
        int $periodoDesde,
        int $periodoHasta,
        int $periodoEjercicio,
    ): array {
        $fechasPeriodo = SumasSaldosListadoFiltros::fechasDesdePeriodos($periodoDesde, $periodoHasta);
        if ($fechasPeriodo === null) {
            return ['acumulado' => $acumulado, 'movimientos' => 0];
        }

        [$fechaDesdePeriodo, $fechaHasta] = $fechasPeriodo;
        $anioEj = intdiv($periodoEjercicio, 100);
        $mesEj = $periodoEjercicio % 100;
        $fechaEjercicio = sprintf('%04d-%02d-01', $anioEj, $mesEj);

        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaLocalId = CuentacontableSaldoMesSupport::monedaLocalId();
        $soloOrigen = ! empty($filtros['solo_moneda_origen']);
        $modoInclusion = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $excluirSubdiario = ! empty($filtros['excluir_origen_subdiario']);
        $consolidar = ! empty($filtros['consolidar_empresas']);

        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->where('c.tipocuenta', 1)
            ->whereNotNull('am.cuentacontable_id')
            ->where('a.fecha', '>=', $fechaEjercicio)
            ->where('a.fecha', '<=', $fechaHasta)
            ->select([
                'a.empresa_id',
                'am.cuentacontable_id',
                'c.codigo',
                'c.nombre',
                'a.fecha',
                'a.observacion as asiento_obs',
                'am.monto',
                'am.moneda_id',
                'am.cotizacion',
                'ta.abreviatura as tipo_abrev',
            ]);

        $this->aplicarFiltroCodigoCuenta($query, $filtros, 'c.codigo');
        if ($soloOrigen) {
            $query->where('am.moneda_id', $monedaId);
        }

        $movimientos = 0;

        foreach ($query->cursor() as $row) {
            $tipo = (string) ($row->tipo_abrev ?? '');
            $restarPorTipo = ! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento($tipo, $modoInclusion);
            $restarPorSubdiario = $excluirSubdiario && $this->esOrigenSubdiarioObservacion((string) ($row->asiento_obs ?? ''));
            // Solo restar lo que el filtro de inclusión / subdiario NO muestra.
            if (! $restarPorTipo && ! $restarPorSubdiario) {
                continue;
            }

            $importe = $this->importeEnMonedaReporte(
                (float) $row->monto,
                (int) $row->moneda_id,
                $monedaId,
                $monedaLocalId,
                $soloOrigen,
                isset($row->cotizacion) ? (float) $row->cotizacion : null,
                (string) $row->fecha,
            );

            if (abs($importe) < 1e-9) {
                continue;
            }

            $empresaId = (int) $row->empresa_id;
            $cuentaId = (int) $row->cuentacontable_id;
            $codigo = (int) $row->codigo;
            $key = $consolidar
                ? 'c:'.$codigo
                : $this->claveCuenta($empresaId, $cuentaId, $filtros);

            if (! isset($acumulado[$key])) {
                $acumulado[$key] = [
                    'empresa_id' => $consolidar ? 0 : $empresaId,
                    'cuentacontable_id' => $cuentaId,
                    'codigo' => $codigo,
                    'nombre' => (string) $row->nombre,
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'saldo_periodo' => 0.0,
                    'saldo_mes_anterior' => 0.0,
                    'saldo_ejercicio' => 0.0,
                ];
            }

            $fecha = (string) $row->fecha;
            $enPeriodo = $fecha >= $fechaDesdePeriodo && $fecha <= $fechaHasta;
            $antesPeriodo = $fecha < $fechaDesdePeriodo;

            if ($enPeriodo) {
                if ($importe > 0) {
                    $acumulado[$key]['debe'] -= $importe;
                } else {
                    $acumulado[$key]['haber'] -= abs($importe);
                }
                $acumulado[$key]['saldo_periodo'] -= $importe;
                $acumulado[$key]['saldo_ejercicio'] -= $importe;
                $movimientos++;
            } elseif ($antesPeriodo) {
                $acumulado[$key]['saldo_mes_anterior'] -= $importe;
                $acumulado[$key]['saldo_ejercicio'] -= $importe;
                $movimientos++;
            }
        }

        return ['acumulado' => $acumulado, 'movimientos' => $movimientos];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function reindexarPorCodigo(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $codigo = (int) ($item['codigo'] ?? 0);
            $key = 'c:'.$codigo;
            if (! isset($out[$key])) {
                $out[$key] = $item;
                $out[$key]['debe'] = (float) ($item['debe'] ?? 0);
                $out[$key]['haber'] = (float) ($item['haber'] ?? 0);
                $out[$key]['saldo_periodo'] = (float) ($item['saldo_periodo'] ?? 0);
                continue;
            }
            $out[$key]['debe'] += (float) ($item['debe'] ?? 0);
            $out[$key]['haber'] += (float) ($item['haber'] ?? 0);
            $out[$key]['saldo_periodo'] += (float) ($item['saldo_periodo'] ?? 0);
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarDesdeAsientos(array $empresaIds, array $filtros): array
    {
        [$fechaDesde, $fechaHasta] = SumasSaldosListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        if ($fechaDesde === '' || $fechaHasta === '') {
            return $this->resultadoVacio('asientos');
        }

        $periodoDesde = SumasSaldosListadoFiltros::periodoDesdeFecha($fechaDesde);
        $periodoEjercicio = SumasSaldosListadoFiltros::periodoInicioEjercicio($periodoDesde);
        $anioEj = intdiv($periodoEjercicio, 100);
        $mesEj = $periodoEjercicio % 100;
        $fechaEjercicio = sprintf('%04d-%02d-01', $anioEj, $mesEj);

        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaLocalId = CuentacontableSaldoMesSupport::monedaLocalId();
        $soloOrigen = ! empty($filtros['solo_moneda_origen']);
        $modoInclusion = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');

        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->where('c.tipocuenta', 1)
            ->whereNotNull('am.cuentacontable_id')
            ->where('a.fecha', '>=', $fechaEjercicio)
            ->where('a.fecha', '<=', $fechaHasta)
            ->select([
                'a.empresa_id',
                'am.cuentacontable_id',
                'c.codigo',
                'c.nombre',
                'a.fecha',
                'am.monto',
                'am.moneda_id',
                'am.cotizacion',
                'ta.abreviatura as tipo_abrev',
            ]);

        $this->aplicarFiltroCodigoCuenta($query, $filtros, 'c.codigo');

        if ($soloOrigen) {
            $query->where('am.moneda_id', $monedaId);
        }

        /** @var array<string, array<string, mixed>> $acumulado */
        $acumulado = [];

        foreach ($query->cursor() as $row) {
            $tipo = (string) ($row->tipo_abrev ?? '');
            if (! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento($tipo, $modoInclusion)) {
                continue;
            }

            $importe = $this->importeEnMonedaReporte(
                (float) $row->monto,
                (int) $row->moneda_id,
                $monedaId,
                $monedaLocalId,
                $soloOrigen,
                isset($row->cotizacion) ? (float) $row->cotizacion : null,
                (string) $row->fecha,
            );

            if (abs($importe) < 1e-9) {
                continue;
            }

            $key = $this->claveCuenta((int) $row->empresa_id, (int) $row->cuentacontable_id, $filtros);
            if (! isset($acumulado[$key])) {
                $acumulado[$key] = [
                    'empresa_id' => (int) $row->empresa_id,
                    'cuentacontable_id' => (int) $row->cuentacontable_id,
                    'codigo' => (int) $row->codigo,
                    'nombre' => (string) $row->nombre,
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'saldo_periodo' => 0.0,
                    'saldo_mes_anterior' => 0.0,
                    'saldo_ejercicio' => 0.0,
                ];
            }

            $fecha = (string) $row->fecha;
            $enPeriodo = $fecha >= $fechaDesde && $fecha <= $fechaHasta;
            $antesPeriodo = $fecha < $fechaDesde;

            if ($enPeriodo) {
                if ($importe > 0) {
                    $acumulado[$key]['debe'] += $importe;
                } else {
                    $acumulado[$key]['haber'] += abs($importe);
                }
                $acumulado[$key]['saldo_periodo'] += $importe;
                $acumulado[$key]['saldo_ejercicio'] += $importe;
            } elseif ($antesPeriodo) {
                $acumulado[$key]['saldo_mes_anterior'] += $importe;
                $acumulado[$key]['saldo_ejercicio'] += $importe;
            }
        }

        if (($filtros['filtro_cuentas'] ?? '') === SumasSaldosListadoFiltros::CUENTAS_TODAS) {
            $acumulado = $this->completarCuentasSinMovimiento($acumulado, $empresaIds, $filtros);
        }

        return $this->armarResultado($acumulado, $filtros, 'asientos', []);
    }

    /**
     * @param  array<string, array<string, mixed>>  $acumulado
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function completarCuentasSinMovimiento(array $acumulado, array $empresaIds, array $filtros): array
    {
        $query = DB::table('cuentacontable as c')
            ->whereIn('c.empresa_id', $empresaIds)
            ->where('c.tipocuenta', 1)
            ->select(['c.id', 'c.empresa_id', 'c.codigo', 'c.nombre']);

        $this->aplicarFiltroCodigoCuenta($query, $filtros, 'c.codigo');

        foreach ($query->cursor() as $row) {
            $key = $this->claveCuenta((int) $row->empresa_id, (int) $row->id, $filtros);
            if (isset($acumulado[$key])) {
                continue;
            }
            $acumulado[$key] = [
                'empresa_id' => (int) $row->empresa_id,
                'cuentacontable_id' => (int) $row->id,
                'codigo' => (int) $row->codigo,
                'nombre' => (string) $row->nombre,
                'debe' => 0.0,
                'haber' => 0.0,
                'saldo_periodo' => 0.0,
                'saldo_mes_anterior' => 0.0,
                'saldo_ejercicio' => 0.0,
            ];
        }

        return $acumulado;
    }

    /**
     * @param  array<string, array<string, mixed>>  $acumulado
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $advertencias
     * @return array<string, mixed>
     */
    private function armarResultado(array $acumulado, array $filtros, string $fuente, array $advertencias): array
    {
        $soloMovimiento = ($filtros['filtro_cuentas'] ?? '') === SumasSaldosListadoFiltros::CUENTAS_CON_MOVIMIENTO;
        $consolidar = ! empty($filtros['consolidar_empresas']);

        if ($consolidar) {
            $acumulado = $this->consolidarPorCodigo($acumulado);
        }

        $filas = [];
        $totDebe = $totHaber = $totPeriodo = $totAnt = $totEj = 0.0;

        foreach ($acumulado as $item) {
            $debe = round((float) $item['debe'], 2);
            $haber = round((float) $item['haber'], 2);
            if ($soloMovimiento && abs($debe) < 0.005 && abs($haber) < 0.005) {
                continue;
            }

            $saldoPeriodo = round((float) $item['saldo_periodo'], 2);
            $saldoAnt = round((float) $item['saldo_mes_anterior'], 2);
            $saldoEj = round((float) $item['saldo_ejercicio'], 2);

            $filas[] = [
                'tipo_fila' => 'cuenta',
                'empresa_id' => (int) ($item['empresa_id'] ?? 0),
                'cuentacontable_id' => (int) ($item['cuentacontable_id'] ?? 0),
                'codigo' => (int) $item['codigo'],
                'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta((int) $item['codigo']),
                'nombre' => (string) $item['nombre'],
                'debe' => $debe,
                'haber' => $haber,
                'saldo_periodo' => $saldoPeriodo,
                'saldo_mes_anterior' => $saldoAnt,
                'saldo_ejercicio' => $saldoEj,
            ];

            $totDebe += $debe;
            $totHaber += $haber;
            $totPeriodo += $saldoPeriodo;
            $totAnt += $saldoAnt;
            $totEj += $saldoEj;
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = ($a['codigo'] <=> $b['codigo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($a['empresa_id'] ?? 0) <=> ($b['empresa_id'] ?? 0);
        });

        return [
            'filas' => $filas,
            'totales' => [
                'cuentas' => count($filas),
                'lineas' => count($filas),
                'debe' => round($totDebe, 2),
                'haber' => round($totHaber, 2),
                'saldo_periodo' => round($totPeriodo, 2),
                'saldo_mes_anterior' => round($totAnt, 2),
                'saldo_ejercicio' => round($totEj, 2),
            ],
            'fuente' => $fuente,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $acumulado
     * @return array<string, array<string, mixed>>
     */
    private function consolidarPorCodigo(array $acumulado): array
    {
        $out = [];
        foreach ($acumulado as $item) {
            $codigo = (int) $item['codigo'];
            $key = 'c:'.$codigo;
            if (! isset($out[$key])) {
                $out[$key] = [
                    'empresa_id' => 0,
                    'cuentacontable_id' => (int) $item['cuentacontable_id'],
                    'codigo' => $codigo,
                    'nombre' => (string) $item['nombre'],
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'saldo_periodo' => 0.0,
                    'saldo_mes_anterior' => 0.0,
                    'saldo_ejercicio' => 0.0,
                ];
            }
            $out[$key]['debe'] += (float) $item['debe'];
            $out[$key]['haber'] += (float) $item['haber'];
            $out[$key]['saldo_periodo'] += (float) $item['saldo_periodo'];
            $out[$key]['saldo_mes_anterior'] += (float) $item['saldo_mes_anterior'];
            $out[$key]['saldo_ejercicio'] += (float) $item['saldo_ejercicio'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function claveCuenta(int $empresaId, int $cuentaId, array $filtros): string
    {
        if (! empty($filtros['consolidar_empresas'])) {
            // Se consolida después por código; mientras tanto por empresa+cuenta.
            return $empresaId.'|'.$cuentaId;
        }

        return $empresaId.'|'.$cuentaId;
    }

    /**
     * El agregado mensual solo sirve en pesos (monto_local ya convertido por asiento)
     * o en “solo moneda origen”. Reexpresar todo a USD/EUR exige leer cada asiento.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function requiereAsientosPorMoneda(array $filtros): bool
    {
        if (! empty($filtros['solo_moneda_origen'])) {
            return false;
        }

        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaLocalId = CuentacontableSaldoMesSupport::monedaLocalId();

        return $monedaId !== $monedaLocalId;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function usarMontosLocales(array $filtros): bool
    {
        // Solo cuando el informe se expresa en moneda local: monto_local ya trae
        // cada movimiento convertido con la cotización de su asiento.
        return (int) ($filtros['moneda_id'] ?? 1) === CuentacontableSaldoMesSupport::monedaLocalId();
    }

    private function aplicarFiltroMonedaSaldo($query, bool $usarLocal, bool $soloOrigen, int $monedaId): void
    {
        if ($usarLocal) {
            return;
        }

        // Submayor en moneda origen: solo filas de esa moneda (monto nativo).
        if ($soloOrigen || $monedaId > 0) {
            $query->where('s.moneda_id', $monedaId);
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltroCodigoCuenta($query, array $filtros, string $columna): void
    {
        $cuentas = array_values(array_filter(array_map('intval', $filtros['cuentas'] ?? []), fn (int $c) => $c > 0));
        if ($cuentas !== []) {
            $query->whereIn($columna, $cuentas);

            return;
        }

        $desde = (int) ($filtros['cuenta_desde'] ?? 0);
        $hasta = (int) ($filtros['cuenta_hasta'] ?? 0);
        if ($desde > 0) {
            $query->where($columna, '>=', $desde);
        }
        if ($hasta > 0) {
            $query->where($columna, '<=', $hasta);
        }
    }

    private function esOrigenSubdiarioObservacion(string $observacion): bool
    {
        foreach ([
            AnitaAsientoImportService::TAG_SUBHIST,
            AnitaAsientoImportService::TAG_SUBDIARIO,
            '[subhist]',
            '[subdiario]',
        ] as $tag) {
            if (str_contains($observacion, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convierte el monto firmado del movimiento a la moneda del informe.
     *
     * Reglas:
     * - Misma moneda → monto nativo.
     * - A pesos → cotización del propio movimiento (am.cotizacion).
     * - A moneda extranjera desde otra → primero a pesos con cotiz. del movimiento;
     *   luego a la moneda de informe con la cotización del día de la transacción
     *   (fecha del asiento) para esa moneda.
     */
    private function importeEnMonedaReporte(
        float $montoFirmado,
        int $monedaMovimientoId,
        int $monedaReporteId,
        int $monedaLocalId,
        bool $soloOrigen,
        ?float $cotizacionMovimiento,
        string $fechaAsiento = '',
    ): float {
        if (abs($montoFirmado) < 1e-12) {
            return 0.0;
        }

        if ($soloOrigen) {
            return $monedaMovimientoId === $monedaReporteId ? $montoFirmado : 0.0;
        }

        if ($monedaMovimientoId === $monedaReporteId) {
            return $montoFirmado;
        }

        // 1) A pesos con la cotización de la transacción (si el mov. ya es pesos, queda igual).
        $enPesos = CuentacontableSaldoMesSupport::convertirMontoLocal(
            $montoFirmado,
            $monedaMovimientoId,
            $cotizacionMovimiento,
        );

        if ($monedaReporteId === $monedaLocalId) {
            return $enPesos;
        }

        // 2) Pesos → moneda de informe con cotización del día del asiento.
        $cotizReporte = $this->cotizacionVentaParaFecha($fechaAsiento, $monedaReporteId);
        if ($cotizReporte < 0.01) {
            // Sin TC del día: si el movimiento trae cotización y era de esa moneda extranjera
            // distinta, no aplica; mejor no inventar.
            return 0.0;
        }

        return $enPesos * calculaCoeficienteMoneda($monedaReporteId, $monedaLocalId, $cotizReporte);
    }

    /**
     * Cotización venta de la moneda en la fecha del asiento (día de la transacción).
     */
    private function cotizacionVentaParaFecha(string $fecha, int $monedaId): float
    {
        $fecha = trim($fecha);
        if ($fecha === '' || $monedaId <= 1) {
            return 1.0;
        }

        $fechaKey = strlen($fecha) >= 10 ? substr($fecha, 0, 10) : $fecha;
        $cacheKey = $fechaKey.'|'.$monedaId;
        if (array_key_exists($cacheKey, $this->cacheCotizacionDia)) {
            return $this->cacheCotizacionDia[$cacheKey];
        }

        $valor = (float) (DB::table('cotizacion_moneda as cm')
            ->join('cotizacion as c', 'c.id', '=', 'cm.cotizacion_id')
            ->where('cm.moneda_id', $monedaId)
            ->whereDate('c.fecha', '<=', $fechaKey)
            ->orderByDesc('c.fecha')
            ->value('cm.cotizacionventa') ?? 0);

        $this->cacheCotizacionDia[$cacheKey] = $valor;

        return $valor;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultadoVacio(string $fuente): array
    {
        return [
            'filas' => [],
            'totales' => [
                'cuentas' => 0,
                'lineas' => 0,
                'debe' => 0.0,
                'haber' => 0.0,
                'saldo_periodo' => 0.0,
                'saldo_mes_anterior' => 0.0,
                'saldo_ejercicio' => 0.0,
            ],
            'fuente' => $fuente,
            'advertencias' => [],
        ];
    }
}
