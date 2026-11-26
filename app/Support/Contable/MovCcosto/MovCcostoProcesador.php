<?php

namespace App\Support\Contable\MovCcosto;

use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\MovCcostoListadoFiltros;
use App\Support\Contable\SumasSaldos\SumasSaldosProcesador;
use Illuminate\Support\Facades\DB;

/**
 * Motor de «Totales por centro de costo» (Anita l-movccosto) sobre los asientos del ERP.
 *
 * El programa original recorría el plan de cuentas y, por cada cuenta, acumulaba ctamov +
 * subdiario (cuenta y contrapartida) en columnas por grupo o centro de costo. En el ERP el
 * subdiario nativo ya está importado como asiento (ver anita-subdiario-asiento-erp), así
 * que una sola lectura de `asiento_movimiento` cubre las dos fuentes sin contarlas dos veces.
 *
 * Cortes de nivel: igual que l-movccosto.c. Las cuentas de título (tipocuenta 2) abren un
 * nivel que se cierra cuando aparece una cuenta con nivel menor o igual; al cerrar suman las
 * imputables que contiene («Total …»). Solo se imprimen títulos que tienen movimientos.
 */
class MovCcostoProcesador
{
    private const COL_SIN_CC = 'sin';

    /** Ver generar(): asiento primero, movimientos después (STRAIGHT_JOIN). */
    public const FROM_ASIENTO_MOVIMIENTO = 'asiento as a STRAIGHT_JOIN asiento_movimiento as am ON am.asiento_id = a.id';

    public function __construct(
        private readonly SumasSaldosProcesador $conversorMoneda,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $empresaIds, array $filtros): array
    {
        [$fechaDesde, $fechaHasta] = MovCcostoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($fechaDesde === '' || $fechaHasta === '' || $empresaIds === []) {
            return $this->resultadoVacio();
        }

        $consolidar = ! empty($filtros['consolidar_empresas']) || count($empresaIds) === 1;
        $porGrupo = ($filtros['agrupar'] ?? MovCcostoListadoFiltros::AGRUPAR_GRUPO) === MovCcostoListadoFiltros::AGRUPAR_GRUPO;
        $incluirSinCc = ! empty($filtros['incluir_sin_cc']);

        $centros = $this->cargarCentros();
        $idsPermitidos = $this->idsCentrosPermitidos($centros, $filtros);
        $hayRestriccionCc = $idsPermitidos !== null;

        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaLocalId = CuentacontableSaldoMesSupport::monedaLocalId();
        $soloOrigen = ! empty($filtros['solo_moneda_origen']);
        $modoInclusion = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $ymdDesde = MayorPlanoCuentaSupport::ymdDesdeFecha($fechaDesde);
        $ymdHasta = MayorPlanoCuentaSupport::ymdDesdeFecha($fechaHasta);

        // Orden de join fijo: primero el rango de asientos (empresa + fecha) y después sus movimientos.
        // Con el orden que elige el optimizador recorre todo asiento_movimiento (2 M de filas).
        $query = DB::table(DB::raw(self::FROM_ASIENTO_MOVIMIENTO))
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->where('a.fecha', '>=', $fechaDesde)
            ->where('a.fecha', '<=', $fechaHasta)
            ->select([
                'a.empresa_id',
                'am.cuentacontable_id',
                'am.centrocosto_id',
                'c.codigo',
                'c.nombre',
                'a.fecha',
                'a.observacion as asiento_obs',
                'am.observacion as mov_obs',
                'am.monto',
                'am.moneda_id',
                'am.cotizacion',
                'ta.abreviatura as tipo_abrev',
            ]);

        $desdeCuenta = (int) ($filtros['cuenta_desde'] ?? 0);
        $hastaCuenta = (int) ($filtros['cuenta_hasta'] ?? 0);
        if ($desdeCuenta > 0) {
            $query->where('c.codigo', '>=', $desdeCuenta);
        }
        if ($hastaCuenta > 0) {
            $query->where('c.codigo', '<=', $hastaCuenta);
        }

        if ($soloOrigen) {
            $query->where('am.moneda_id', $monedaId);
        }

        // Solo movimientos con centro de costo (como busca_offset == -1 en el original),
        // salvo que el operador pida la columna «Sin centro de costo».
        if ($hayRestriccionCc) {
            $query->where(function ($q) use ($idsPermitidos, $incluirSinCc) {
                $q->whereIn('am.centrocosto_id', $idsPermitidos === [] ? [0] : $idsPermitidos);
                if ($incluirSinCc) {
                    $q->orWhereNull('am.centrocosto_id');
                }
            });
        } elseif (! $incluirSinCc) {
            $query->whereNotNull('am.centrocosto_id');
        }

        /** @var array<int, array<int, array{d: array<string, float>, h: array<string, float>}>> $acc seccion → codigo → columnas */
        $acc = [];
        /** @var array<int, array{nombre: string, cuentacontable_id: int}> $metaCuenta */
        $metaCuenta = [];
        /** @var array<string, array<string, mixed>> $metaColumna */
        $metaColumna = [];
        $sinCotizacion = 0;

        foreach ($query->cursor() as $row) {
            $tipo = (string) ($row->tipo_abrev ?? '');
            $descripcion = trim((string) ($row->mov_obs ?? ''));
            if ($descripcion === '') {
                $descripcion = trim((string) ($row->asiento_obs ?? ''));
            }

            $ymdFecha = MayorPlanoCuentaSupport::ymdDesdeFecha((string) $row->fecha);
            if (! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento($tipo, $modoInclusion, $descripcion)
                || MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
                    $tipo,
                    $ymdFecha,
                    $modoInclusion,
                    $ymdDesde,
                    $ymdHasta,
                )) {
                continue;
            }

            $importe = $this->conversorMoneda->importeEnMonedaReporte(
                (float) $row->monto,
                (int) $row->moneda_id,
                $monedaId,
                $monedaLocalId,
                $soloOrigen,
                isset($row->cotizacion) ? (float) $row->cotizacion : null,
                (string) $row->fecha,
            );

            if (abs($importe) < 1e-9) {
                if (! $soloOrigen && abs((float) $row->monto) > 1e-9 && (int) $row->moneda_id !== $monedaId) {
                    $sinCotizacion++;
                }

                continue;
            }

            $columna = $this->claveColumna($row->centrocosto_id !== null ? (int) $row->centrocosto_id : null, $centros, $porGrupo, $metaColumna, $idsPermitidos);
            if ($columna === null) {
                continue;
            }

            $seccion = $consolidar ? 0 : (int) $row->empresa_id;
            $codigo = (int) $row->codigo;

            if (! isset($metaCuenta[$codigo])) {
                $metaCuenta[$codigo] = [
                    'nombre' => (string) $row->nombre,
                    'cuentacontable_id' => (int) $row->cuentacontable_id,
                ];
            }

            $acc[$seccion][$codigo] ??= ['d' => [], 'h' => []];
            if ($importe > 0) {
                $acc[$seccion][$codigo]['d'][$columna] = ($acc[$seccion][$codigo]['d'][$columna] ?? 0.0) + $importe;
            } else {
                $acc[$seccion][$codigo]['h'][$columna] = ($acc[$seccion][$codigo]['h'][$columna] ?? 0.0) + abs($importe);
            }
        }

        $advertencias = [];
        if ($sinCotizacion > 0) {
            $advertencias[] = $sinCotizacion.' movimiento(s) no se pudieron expresar en la moneda del informe '
                .'(sin cotización cargada) y quedaron fuera de los totales.';
        }

        $columnas = $this->armarColumnas($metaColumna, $filtros, $centros, $idsPermitidos, $advertencias);
        if ($columnas === []) {
            $vacio = $this->resultadoVacio();
            $vacio['advertencias'] = $advertencias;

            return $vacio;
        }

        $claves = array_map(static fn (array $c): string => (string) $c['key'], $columnas);
        $plan = $this->cargarPlan($empresaIds);
        $nombresEmpresa = DB::table('empresa')->whereIn('id', $empresaIds)->pluck('nombre', 'id')->all();
        $conTotales = ($filtros['con_totales'] ?? true) !== false;

        $secciones = [];
        $granDebe = array_fill(0, count($claves), 0.0);
        $granHaber = array_fill(0, count($claves), 0.0);
        $cuentasTotal = 0;

        foreach ($acc as $seccionId => $cuentas) {
            $orden = $seccionId > 0
                ? array_values(array_unique(array_merge([$seccionId], $empresaIds)))
                : $empresaIds;

            $armada = $this->armarSeccion($cuentas, $claves, $plan, $orden, $metaCuenta, $conTotales, (int) $seccionId);
            if ($armada['cuentas'] === 0) {
                continue;
            }

            $cuentasTotal += $armada['cuentas'];
            foreach ($claves as $i => $_) {
                $granDebe[$i] += $armada['total']['debe'][$i];
                $granHaber[$i] += $armada['total']['haber'][$i];
            }

            $secciones[] = [
                'empresa_id' => (int) $seccionId,
                'empresa_nombre' => $seccionId > 0
                    ? (string) ($nombresEmpresa[$seccionId] ?? ('#'.$seccionId))
                    : (count($empresaIds) > 1 ? 'Consolidado' : (string) ($nombresEmpresa[$empresaIds[0]] ?? '')),
                'filas' => $armada['filas'],
                'total' => $armada['total'],
            ];
        }

        usort($secciones, static fn (array $a, array $b): int => strcasecmp((string) $a['empresa_nombre'], (string) $b['empresa_nombre']));

        $totalGeneral = $this->filaTotal('total_general', 'Total general', 0, $granDebe, $granHaber);

        return [
            'columnas' => $columnas,
            'secciones' => $secciones,
            'total_general' => $totalGeneral,
            'totales' => [
                'cuentas' => $cuentasTotal,
                'columnas' => count($columnas),
                'debe' => $totalGeneral['tot_debe'],
                'haber' => $totalGeneral['tot_haber'],
                'saldo' => round($totalGeneral['tot_debe'] - $totalGeneral['tot_haber'], 2),
            ],
            'advertencias' => $advertencias,
            'fuente' => 'asientos',
        ];
    }

    /**
     * @return array<int, object{id: int, codigo: string, nombre: string, abreviatura: ?string, grupo_codigo: ?string, grupo_nombre: ?string}>
     */
    private function cargarCentros(): array
    {
        $out = [];
        foreach (DB::table('centrocosto')->get(['id', 'codigo', 'nombre', 'abreviatura', 'grupo_codigo', 'grupo_nombre']) as $cc) {
            $out[(int) $cc->id] = $cc;
        }

        return $out;
    }

    /**
     * IDs de centros que pasan los filtros de lista/rango y de grupo. null = sin restricción.
     *
     * @param  array<int, object>  $centros
     * @param  array<string, mixed>  $filtros
     * @return list<int>|null
     */
    private function idsCentrosPermitidos(array $centros, array $filtros): ?array
    {
        $soporte = new MayorPlanoCuentaCentrocostoFiltroSupport(
            $filtros['centrocostos_codigo'] ?? '',
            $filtros['cc_desde'] ?? '',
            $filtros['cc_hasta'] ?? '',
            false,
        );
        $grupos = MovCcostoListadoFiltros::parsearGrupos($filtros['grupos_codigo'] ?? '');

        if (! $soporte->tieneFiltro() && $grupos === []) {
            return null;
        }

        $ids = [];
        foreach ($centros as $id => $cc) {
            if ($soporte->tieneFiltro() && ! $soporte->pasaFiltro((string) $cc->codigo)) {
                continue;
            }
            if ($grupos !== [] && ! in_array(MovCcostoListadoFiltros::normalizarGrupo($cc->grupo_codigo), $grupos, true)) {
                continue;
            }
            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param  array<int, object>  $centros
     * @param  array<string, array<string, mixed>>  $metaColumna
     * @param  list<int>|null  $idsPermitidos
     */
    private function claveColumna(?int $centroId, array $centros, bool $porGrupo, array &$metaColumna, ?array $idsPermitidos): ?string
    {
        if ($centroId === null || ! isset($centros[$centroId])) {
            $metaColumna[self::COL_SIN_CC] ??= [
                'key' => self::COL_SIN_CC,
                'tipo' => 'sin',
                'codigo' => '',
                'nombre' => 'Sin centro de costo',
                'centrocosto_id' => null,
                'codigos_cc' => [],
            ];

            return self::COL_SIN_CC;
        }

        $cc = $centros[$centroId];
        if (! $porGrupo) {
            $clave = 'c:'.$centroId;
            $metaColumna[$clave] ??= [
                'key' => $clave,
                'tipo' => 'ccosto',
                'codigo' => (string) $cc->codigo,
                'nombre' => (string) $cc->nombre,
                'centrocosto_id' => $centroId,
                'codigos_cc' => [(string) $cc->codigo],
            ];

            return $clave;
        }

        $grupo = MovCcostoListadoFiltros::normalizarGrupo($cc->grupo_codigo);
        $clave = 'g:'.$grupo;
        if (! isset($metaColumna[$clave])) {
            $codigosCc = [];
            foreach ($centros as $otroId => $otro) {
                if (MovCcostoListadoFiltros::normalizarGrupo($otro->grupo_codigo) !== $grupo) {
                    continue;
                }
                if ($idsPermitidos !== null && ! in_array((int) $otroId, $idsPermitidos, true)) {
                    continue;
                }
                $codigosCc[] = (string) $otro->codigo;
            }
            usort($codigosCc, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

            $metaColumna[$clave] = [
                'key' => $clave,
                'tipo' => 'grupo',
                'codigo' => $grupo,
                'nombre' => trim((string) $cc->grupo_nombre) !== '' ? trim((string) $cc->grupo_nombre) : 'Sin grupo',
                'centrocosto_id' => null,
                'codigos_cc' => $codigosCc,
            ];
        }

        return $clave;
    }

    /**
     * @param  array<string, array<string, mixed>>  $metaColumna
     * @param  array<string, mixed>  $filtros
     * @param  array<int, object>  $centros
     * @param  list<int>|null  $idsPermitidos
     * @param  list<string>  $advertencias
     * @return list<array<string, mixed>>
     */
    private function armarColumnas(array $metaColumna, array $filtros, array $centros, ?array $idsPermitidos, array &$advertencias): array
    {
        $columnas = array_values($metaColumna);
        usort($columnas, static function (array $a, array $b): int {
            // «Sin centro de costo» siempre al final.
            if ($a['tipo'] === 'sin' || $b['tipo'] === 'sin') {
                return ($a['tipo'] === 'sin' ? 1 : 0) <=> ($b['tipo'] === 'sin' ? 1 : 0);
            }

            return strnatcasecmp((string) $a['codigo'], (string) $b['codigo']);
        });

        $max = MovCcostoListadoFiltros::maxColumnas($filtros);
        if (count($columnas) > $max) {
            $total = count($columnas);
            $columnas = array_slice($columnas, 0, $max);
            $porGrupo = ($filtros['agrupar'] ?? '') === MovCcostoListadoFiltros::AGRUPAR_GRUPO;
            $advertencias[] = 'Hay '.$total.' columnas con movimiento y se muestran las primeras '.$max
                .' (tope de Anita para este formato). Acote por '
                .($porGrupo ? 'grupo' : 'centro de costo').' o use «Solo saldo» para ver más columnas. '
                .'Los movimientos de las columnas omitidas no entran en los totales.';
        }

        return $columnas;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, array<int, array{id: int, nombre: string, nivel: int, tipo: int}>> empresa → codigo
     */
    private function cargarPlan(array $empresaIds): array
    {
        $plan = [];
        foreach (DB::table('cuentacontable')
            ->whereIn('empresa_id', $empresaIds)
            ->get(['id', 'empresa_id', 'codigo', 'nombre', 'nivel', 'tipocuenta']) as $c) {
            $plan[(int) $c->empresa_id][(int) $c->codigo] = [
                'id' => (int) $c->id,
                'nombre' => (string) $c->nombre,
                'nivel' => (int) $c->nivel,
                'tipo' => (int) $c->tipocuenta,
            ];
        }

        return $plan;
    }

    /**
     * @param  array<int, array{d: array<string, float>, h: array<string, float>}>  $cuentas
     * @param  list<string>  $claves
     * @param  array<int, array<int, array{id: int, nombre: string, nivel: int, tipo: int}>>  $plan
     * @param  list<int>  $ordenEmpresas
     * @param  array<int, array{nombre: string, cuentacontable_id: int}>  $metaCuenta
     * @return array{filas: list<array<string, mixed>>, total: array<string, mixed>, cuentas: int}
     */
    private function armarSeccion(
        array $cuentas,
        array $claves,
        array $plan,
        array $ordenEmpresas,
        array $metaCuenta,
        bool $conTotales,
        int $empresaSeccion,
    ): array {
        $n = count($claves);

        $buscarPlan = static function (int $codigo) use ($plan, $ordenEmpresas): ?array {
            foreach ($ordenEmpresas as $empresaId) {
                if (isset($plan[$empresaId][$codigo])) {
                    return $plan[$empresaId][$codigo];
                }
            }

            return null;
        };

        // Ítems: títulos del plan + cuentas con movimiento, en orden de código.
        $items = [];
        $titulos = [];
        foreach ($ordenEmpresas as $empresaId) {
            foreach ($plan[$empresaId] ?? [] as $codigo => $c) {
                if ($c['tipo'] === 2 && ! isset($titulos[$codigo])) {
                    $titulos[$codigo] = $c;
                }
            }
        }
        foreach ($titulos as $codigo => $c) {
            $items[] = ['codigo' => (int) $codigo, 'tipo' => 'titulo', 'nivel' => $c['nivel'], 'nombre' => $c['nombre'], 'id' => $c['id']];
        }

        foreach ($cuentas as $codigo => $datos) {
            $p = $buscarPlan((int) $codigo);
            $items[] = [
                'codigo' => (int) $codigo,
                'tipo' => 'cuenta',
                'nivel' => $p['nivel'] ?? 99,
                'nombre' => $p['nombre'] ?? ($metaCuenta[$codigo]['nombre'] ?? ''),
                'id' => $p['id'] ?? ($metaCuenta[$codigo]['cuentacontable_id'] ?? 0),
                'datos' => $datos,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = $a['codigo'] <=> $b['codigo'];

            return $cmp !== 0 ? $cmp : (($a['tipo'] === 'titulo' ? 0 : 1) <=> ($b['tipo'] === 'titulo' ? 0 : 1));
        });

        $filas = [];
        $stack = [];
        $totalDebe = array_fill(0, $n, 0.0);
        $totalHaber = array_fill(0, $n, 0.0);
        $cantCuentas = 0;

        $cerrarHasta = function (int $nivel) use (&$stack, &$filas, $conTotales, $n): void {
            while ($stack !== [] && $stack[count($stack) - 1]['nivel'] >= $nivel) {
                $nivelCerrado = array_pop($stack);
                if ($nivelCerrado['emitido'] && $conTotales) {
                    $filas[] = $this->filaTotal(
                        'total_nivel',
                        'Total '.$nivelCerrado['nombre'],
                        $nivelCerrado['nivel'],
                        $nivelCerrado['debe'],
                        $nivelCerrado['haber'],
                        $nivelCerrado['codigo'],
                    );
                }
            }
        };

        foreach ($items as $item) {
            $cerrarHasta($item['nivel']);

            if ($item['tipo'] === 'titulo') {
                $stack[] = [
                    'codigo' => $item['codigo'],
                    'nombre' => $item['nombre'],
                    'nivel' => $item['nivel'],
                    'id' => $item['id'],
                    'emitido' => false,
                    'debe' => array_fill(0, $n, 0.0),
                    'haber' => array_fill(0, $n, 0.0),
                ];

                continue;
            }

            $debe = [];
            $haber = [];
            $hayDato = false;
            foreach ($claves as $i => $clave) {
                $debe[$i] = (float) ($item['datos']['d'][$clave] ?? 0.0);
                $haber[$i] = (float) ($item['datos']['h'][$clave] ?? 0.0);
                if ($debe[$i] !== 0.0 || $haber[$i] !== 0.0) {
                    $hayDato = true;
                }
            }
            if (! $hayDato) {
                continue;
            }

            foreach ($stack as &$abierto) {
                if (! $abierto['emitido']) {
                    $filas[] = [
                        'tipo_fila' => 'titulo',
                        'empresa_id' => $empresaSeccion,
                        'cuentacontable_id' => $abierto['id'],
                        'codigo' => $abierto['codigo'],
                        'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($abierto['codigo']),
                        'nombre' => $abierto['nombre'],
                        'nivel' => $abierto['nivel'],
                        'debe' => [],
                        'haber' => [],
                        'tot_debe' => 0.0,
                        'tot_haber' => 0.0,
                    ];
                    $abierto['emitido'] = true;
                }
                foreach ($claves as $i => $_) {
                    $abierto['debe'][$i] += $debe[$i];
                    $abierto['haber'][$i] += $haber[$i];
                }
            }
            unset($abierto);

            $fila = $this->filaTotal('cuenta', (string) $item['nombre'], (int) $item['nivel'], $debe, $haber, $item['codigo']);
            $fila['empresa_id'] = $empresaSeccion;
            $fila['cuentacontable_id'] = (int) $item['id'];
            $filas[] = $fila;
            $cantCuentas++;

            foreach ($claves as $i => $_) {
                $totalDebe[$i] += $debe[$i];
                $totalHaber[$i] += $haber[$i];
            }
        }

        $cerrarHasta(0);

        return [
            'filas' => $filas,
            'total' => $this->filaTotal('total_general', 'Total', 0, $totalDebe, $totalHaber),
            'cuentas' => $cantCuentas,
        ];
    }

    /**
     * @param  list<float>  $debe
     * @param  list<float>  $haber
     * @return array<string, mixed>
     */
    private function filaTotal(string $tipo, string $nombre, int $nivel, array $debe, array $haber, int $codigo = 0): array
    {
        $debe = array_map(static fn ($v): float => round((float) $v, 2), array_values($debe));
        $haber = array_map(static fn ($v): float => round((float) $v, 2), array_values($haber));

        return [
            'tipo_fila' => $tipo,
            'empresa_id' => 0,
            'cuentacontable_id' => 0,
            'codigo' => $codigo,
            'codigo_fmt' => $codigo > 0 ? MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo) : '',
            'nombre' => $nombre,
            'nivel' => $nivel,
            'debe' => $debe,
            'haber' => $haber,
            'tot_debe' => round(array_sum($debe), 2),
            'tot_haber' => round(array_sum($haber), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultadoVacio(): array
    {
        return [
            'columnas' => [],
            'secciones' => [],
            'total_general' => null,
            'totales' => ['cuentas' => 0, 'columnas' => 0, 'debe' => 0.0, 'haber' => 0.0, 'saldo' => 0.0],
            'advertencias' => [],
            'fuente' => 'asientos',
        ];
    }
}
