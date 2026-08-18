<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Support\Sueldos\EmpleadoEstados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor listgen: filas empleado × columnas (campo / suma conceptos / fórmula).
 */
class ReporteSueldosDefinibleProcesador
{
    public function __construct(
        private ?ReporteSueldosDefinibleSeguridadSupport $seguridad = null,
    ) {}

    private int $omitidosConfidencial = 0;

    /**
     * @param  array{
     *   liquidacion_id?: int|null,
     *   liquidacion_id_comparar?: int|null,
     *   origen?: string,
     *   empresa_id?: int|null,
     *   filtro_estado?: string,
     *   agrupacion?: string,
     *   agrupaciones?: list<string>,
     *   resumido?: bool,
     *   lugartrabajo_ids?: list<int>,
     *   centrocosto_ids?: list<int>,
     *   agrupamiento_ids?: list<int>,
     *   incluir_confidencial?: bool
     * }  $filtros
     * @return array{
     *   columnas: list<array{nro:int,descripcion:string,contenido:string,numerica:bool}>,
     *   filas: list<array<string, mixed>>,
     *   totales: array<int, float>,
     *   meta: array<string, mixed>
     * }
     */
    public function ejecutar(ReporteSueldosDefinible $reporte, array $filtros): array
    {
        $this->omitidosConfidencial = 0;
        $seguridad = $this->seguridad ?? app(ReporteSueldosDefinibleSeguridadSupport::class);
        if (isset($filtros['empresa_id'])) {
            $seguridad->assertEmpresaAutorizada(
                ((int) $filtros['empresa_id']) > 0 ? (int) $filtros['empresa_id'] : null
            );
        }
        $reporte->load(['columnas.conceptos']);
        $origen = $filtros['origen'] ?? ReporteSueldosDefinibleSupport::ORIGEN_LIQUIDACION;
        $agrupacion = $filtros['agrupacion'] ?? ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO;
        $agrupaciones = $this->normalizarAgrupaciones($filtros['agrupaciones'] ?? [$agrupacion]);
        $resumido = (bool) ($filtros['resumido'] ?? false);
        $empresaId = isset($filtros['empresa_id']) ? (int) $filtros['empresa_id'] : null;
        $liquidacionId = isset($filtros['liquidacion_id']) ? (int) $filtros['liquidacion_id'] : null;
        $liquidacionCmp = isset($filtros['liquidacion_id_comparar']) ? (int) $filtros['liquidacion_id_comparar'] : null;

        $columnasMeta = [];
        foreach ($reporte->columnas as $col) {
            $numerica = ! in_array($col->contenido, [
                ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO,
            ], true);
            $columnasMeta[] = [
                'nro' => (int) $col->nro_columna,
                'descripcion' => $col->descripcion,
                'contenido' => $col->contenido,
                'numerica' => $numerica,
                'id' => (int) $col->id,
            ];
        }

        if ($origen === ReporteSueldosDefinibleSupport::ORIGEN_ABM) {
            $filasBase = $this->filasDesdeAbm($reporte, $empresaId, $filtros);
        } else {
            if ($liquidacionId === null || $liquidacionId <= 0) {
                return [
                    'columnas' => $columnasMeta,
                    'filas' => [],
                    'totales' => [],
                    'meta' => ['error' => 'Debe indicar liquidación.'],
                ];
            }
            $filasBase = $this->filasDesdeLiquidacion($reporte, $liquidacionId, $filtros);
        }

        if ($liquidacionCmp > 0 && $origen !== ReporteSueldosDefinibleSupport::ORIGEN_ABM) {
            $filasCmp = $this->filasDesdeLiquidacion($reporte, $liquidacionCmp, $filtros);
            $filasBase = $this->fusionarVariacion($filasBase, $filasCmp, $columnasMeta);
            $extra = [];
            foreach ($columnasMeta as $cm) {
                if (! $cm['numerica']) {
                    continue;
                }
                $extra[] = [
                    'nro' => (int) $cm['nro'] + 1000,
                    'descripcion' => $cm['descripcion'].' Δ',
                    'contenido' => 'var',
                    'numerica' => true,
                    'id' => 0,
                ];
            }
            $columnasMeta = array_merge($columnasMeta, $extra);
        }

        $filasParaTotales = $filasBase;
        if (count($agrupaciones) > 1) {
            $filasBase = $this->agruparMultinivel($filasBase, $columnasMeta, $agrupaciones);
        } elseif ($agrupacion !== ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO || $resumido) {
            $filasBase = $this->agrupar($filasBase, $columnasMeta, $agrupacion, $resumido);
        }

        $cantidadAntesPresentacion = count($filasBase);
        $advertencias = [];
        $ordenColumna = max(0, (int) ($filtros['orden_columna'] ?? 0));
        $ordenDireccion = strtolower((string) ($filtros['orden_direccion'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $topN = max(0, min(10000, (int) ($filtros['top_n'] ?? 0)));
        if ($ordenColumna > 0 && count($agrupaciones) > 1) {
            $advertencias[] = 'Ordenamiento/top-N omitido para preservar la jerarquía multinivel.';
        } elseif ($ordenColumna > 0) {
            $keyOrden = 'c'.$ordenColumna;
            usort($filasBase, function (array $a, array $b) use ($keyOrden, $ordenDireccion): int {
                $valorA = $a[$keyOrden] ?? 0;
                $valorB = $b[$keyOrden] ?? 0;
                $comparacion = is_numeric($valorA) && is_numeric($valorB)
                    ? ((float) $valorA <=> (float) $valorB)
                    : strnatcasecmp((string) $valorA, (string) $valorB);

                return $ordenDireccion === 'asc' ? $comparacion : -$comparacion;
            });
            if ($topN > 0) {
                $filasBase = array_slice($filasBase, 0, $topN);
            }
        }

        $totales = [];
        foreach ($columnasMeta as $cm) {
            if (! $cm['numerica']) {
                continue;
            }
            $key = 'c'.$cm['nro'];
            $totales[$cm['nro']] = round(array_sum(array_map(
                fn ($f) => (float) ($f[$key] ?? 0),
                $filasParaTotales
            )), 2);
        }

        return $seguridad->proyectarResultado([
            'columnas' => $columnasMeta,
            'filas' => $filasBase,
            'totales' => $totales,
            'meta' => [
                'origen' => $origen,
                'agrupacion' => $agrupacion,
                'agrupaciones' => $agrupaciones,
                'resumido' => $resumido,
                'cantidad_filas' => count($filasBase),
                'cantidad_filas_total' => $cantidadAntesPresentacion,
                'liquidacion_id' => $liquidacionId,
                'liquidacion_id_comparar' => $liquidacionCmp,
                'orden_columna' => $ordenColumna ?: null,
                'orden_direccion' => $ordenDireccion,
                'top_n' => $topN ?: null,
                'advertencias' => $advertencias,
                'omitidos_confidencial' => $this->omitidosConfidencial,
            ],
        ], $reporte);
    }

    /**
     * Drill: líneas de detalle que alimentan una celda numérica.
     *
     * @return list<array<string, mixed>>
     */
    public function drillCelda(
        ReporteSueldosDefinible $reporte,
        int $columnaId,
        int $liquidacionId,
        int $legajo
    ): array {
        $columna = $reporte->columnas()->with('conceptos')->where('id', $columnaId)->first();
        if (! $columna || $columna->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO) {
            return [];
        }
        if ($columna->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA) {
            return [];
        }

        $codigos = $columna->conceptos->pluck('concepto_codigo')->map(fn ($v) => (int) $v)->all();
        if ($codigos === []) {
            return [];
        }

        $recibo = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacionId)
            ->where('legajo', $legajo)
            ->first();
        if (! $recibo) {
            return [];
        }

        $signos = [];
        foreach ($columna->conceptos as $concepto) {
            $signos[(int) $concepto->concepto_codigo] = $concepto->signo === '-' ? -1.0 : 1.0;
        }

        return Liquidacion_Detalle_Sueldos::query()
            ->where('recibo_id', $recibo->id)
            ->whereIn('concepto_codigo', $codigos)
            ->orderBy('nro_linea')
            ->get()
            ->map(function ($d) use ($signos) {
                $signo = $signos[(int) $d->concepto_codigo] ?? 1.0;

                return [
                    'concepto_codigo' => (int) $d->concepto_codigo,
                    'concepto_descripcion' => $d->concepto_descripcion,
                    'signo' => $signo < 0 ? '-' : '+',
                    'cantidad' => $signo * (float) $d->cantidad,
                    'valor' => $signo * (float) $d->valor,
                    'importe' => $signo * (float) $d->importe,
                    'columna' => $d->columna,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function filasDesdeLiquidacion(ReporteSueldosDefinible $reporte, int $liquidacionId, array $filtros): array
    {
        $liq = Liquidacion_Sueldos::query()->find($liquidacionId);
        if (! $liq) {
            return [];
        }

        $empresaId = isset($filtros['empresa_id']) ? (int) $filtros['empresa_id'] : null;
        if ($empresaId !== null && $empresaId > 0 && (int) $liq->empresa_id !== $empresaId) {
            return [];
        }

        $recibos = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacionId)
            ->orderBy('legajo')
            ->get();

        $empleadoIds = $recibos->pluck('empleado_id')->filter()->unique()->all();
        $empleados = Empleado_Sueldos::query()
            ->with(['categoria:id,descripcion', 'centrocosto:id,nombre,codigo', 'lugartrabajo:id,nombre', 'sindicato:id,descripcion,codigo', 'obrasocial:id,descripcion,codigo', 'agrupamiento:id,descripcion', 'motivoegreso:id,descripcion', 'leyendas:id,empleado_id,linea,leyenda'])
            ->whereIn('id', $empleadoIds)
            ->get()
            ->keyBy('id');
        foreach ($this->centrosCostoAnteriores($reporte, $empleadoIds) as $empleadoId => $nombre) {
            $empleados->get($empleadoId)?->setAttribute('centrocosto_anterior_nombre', $nombre);
        }

        $detalles = Liquidacion_Detalle_Sueldos::query()
            ->where('liquidacion_id', $liquidacionId)
            ->get()
            ->groupBy('recibo_id');

        $filas = [];
        $incluirConfidencial = (bool) ($filtros['incluir_confidencial'] ?? false)
            && ($this->seguridad ?? app(ReporteSueldosDefinibleSeguridadSupport::class))
                ->puedeIncluirConfidencial($reporte);
        foreach ($recibos as $recibo) {
            /** @var Empleado_Sueldos|null $emp */
            $emp = $empleados->get($recibo->empleado_id);
            if ($emp && (bool) ($emp->confidencial ?? false) && ! $incluirConfidencial) {
                $this->omitidosConfidencial++;
                continue;
            }
            if (! $this->pasaFiltrosTipo($reporte, $recibo, $emp, $filtros)) {
                continue;
            }
            $detRecibo = $detalles->get($recibo->id, collect());
            $valores = $this->valoresColumnas($reporte, $recibo, $emp, $detRecibo);
            if (! $this->tieneMovimientoRelevante($reporte, $valores)) {
                continue;
            }
            $filas[] = array_merge([
                'legajo' => (int) $recibo->legajo,
                'nombre' => (string) $recibo->apellido_nombre,
                'empleado_id' => (int) $recibo->empleado_id,
                'recibo_id' => (int) $recibo->id,
                'grupo_key' => $this->grupoKey($filtros['agrupacion'] ?? 'empleado', $recibo, $emp),
                'grupo_label' => $this->grupoLabel($filtros['agrupacion'] ?? 'empleado', $recibo, $emp),
                'centrocosto_id' => (int) ($emp?->centrocosto_id ?? 0),
                'lugartrabajo_id' => (int) ($recibo->lugartrabajo_id ?? $emp?->lugartrabajo_id ?? 0),
                'agrupamiento_id' => (int) ($recibo->agrupamiento_id ?? $emp?->agrupamiento_id ?? 0),
                'dimension_keys' => $this->dimensionKeys($recibo, $emp),
                'dimension_labels' => $this->dimensionLabels($recibo, $emp),
            ], $valores);
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function filasDesdeAbm(ReporteSueldosDefinible $reporte, ?int $empresaId, array $filtros): array
    {
        $q = Empleado_Sueldos::query()
            ->with(['categoria:id,descripcion', 'centrocosto:id,nombre,codigo', 'lugartrabajo:id,nombre', 'sindicato:id,descripcion,codigo', 'obrasocial:id,descripcion,codigo', 'agrupamiento:id,descripcion', 'motivoegreso:id,descripcion', 'leyendas:id,empleado_id,linea,leyenda'])
            ->orderBy('legajo');
        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }
        $estado = $filtros['filtro_estado'] ?? 'activo';
        if ($estado === 'activo') {
            $q->where('estado', EmpleadoEstados::ACTIVO);
        } elseif ($estado === 'baja') {
            $q->where('estado', '!=', EmpleadoEstados::ACTIVO);
        }

        $centrosAnteriores = $this->centrosCostoAnteriores(
            $reporte,
            (clone $q)->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $filas = [];
        $incluirConfidencial = (bool) ($filtros['incluir_confidencial'] ?? false)
            && ($this->seguridad ?? app(ReporteSueldosDefinibleSeguridadSupport::class))
                ->puedeIncluirConfidencial($reporte);
        foreach ($q->cursor() as $emp) {
            if ((bool) ($emp->confidencial ?? false) && ! $incluirConfidencial) {
                $this->omitidosConfidencial++;
                continue;
            }
            if (isset($centrosAnteriores[(int) $emp->id])) {
                $emp->setAttribute('centrocosto_anterior_nombre', $centrosAnteriores[(int) $emp->id]);
            }
            if (! $this->pasaFiltrosTipoAbm($reporte, $emp, $filtros)) {
                continue;
            }
            $valores = $this->valoresColumnas($reporte, null, $emp, collect());
            $filas[] = array_merge([
                'legajo' => (int) $emp->legajo,
                'nombre' => (string) $emp->nombre,
                'empleado_id' => (int) $emp->id,
                'recibo_id' => 0,
                'grupo_key' => $this->grupoKey($filtros['agrupacion'] ?? 'empleado', null, $emp),
                'grupo_label' => $this->grupoLabel($filtros['agrupacion'] ?? 'empleado', null, $emp),
                'centrocosto_id' => (int) ($emp->centrocosto_id ?? 0),
                'lugartrabajo_id' => (int) ($emp->lugartrabajo_id ?? 0),
                'agrupamiento_id' => (int) ($emp->agrupamiento_id ?? 0),
                'dimension_keys' => $this->dimensionKeys(null, $emp),
                'dimension_labels' => $this->dimensionLabels(null, $emp),
            ], $valores);
        }

        return $filas;
    }

    /**
     * @param  Collection<int, Liquidacion_Detalle_Sueldos>  $detalles
     * @return array<string, float|string>
     */
    private function valoresColumnas(
        ReporteSueldosDefinible $reporte,
        ?Liquidacion_Recibo_Sueldos $recibo,
        ?Empleado_Sueldos $emp,
        Collection $detalles
    ): array {
        $ctx = [
            'categoria_desc' => $recibo->categoria_desc ?? $emp?->categoria?->descripcion,
            'centrocosto_nombre' => $emp?->centrocosto?->nombre,
            'centrocosto_codigo' => $emp?->centrocosto?->codigo,
            'lugartrabajo_nombre' => $emp?->lugartrabajo?->nombre,
            'sindicato_nombre' => $emp?->sindicato?->descripcion,
            'obrasocial_nombre' => $emp?->obrasocial?->descripcion,
            'obrasocial_codigo' => $emp?->obrasocial?->codigo,
            'agrupamiento_nombre' => $emp?->agrupamiento?->descripcion,
            'motivoegreso_nombre' => $emp?->motivoegreso?->descripcion,
        ];

        $porCodigo = [];
        foreach ($detalles as $d) {
            $cod = (int) $d->concepto_codigo;
            if (! isset($porCodigo[$cod])) {
                $porCodigo[$cod] = ['importe' => 0.0, 'cantidad' => 0.0, 'valor' => 0.0];
            }
            $porCodigo[$cod]['importe'] += (float) $d->importe;
            $porCodigo[$cod]['cantidad'] += (float) $d->cantidad;
            $porCodigo[$cod]['valor'] += (float) $d->valor;
        }

        $out = [];
        $numericos = [];
        foreach ($reporte->columnas as $col) {
            $nro = (int) $col->nro_columna;
            $key = 'c'.$nro;
            if ($col->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO) {
                $out[$key] = ReporteSueldosDefinibleCampoEmpleadoSupport::resolver(
                    (int) ($col->campo_empleado ?? 0),
                    $recibo,
                    $emp,
                    $ctx,
                    $col->largo
                );
                continue;
            }
            if ($col->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA) {
                $out[$key] = 0.0;
                $numericos[$nro] = true;
                continue;
            }

            $suma = 0.0;
            foreach ($col->conceptos as $con) {
                $cod = (int) $con->concepto_codigo;
                $signo = $con->signo === '-' ? -1.0 : 1.0;
                $base = $porCodigo[$cod] ?? ['importe' => 0.0, 'cantidad' => 0.0, 'valor' => 0.0];
                $suma += $signo * match ($col->contenido) {
                    ReporteSueldosDefinibleSupport::CONTENIDO_CANTIDAD => $base['cantidad'],
                    ReporteSueldosDefinibleSupport::CONTENIDO_VALOR => $base['valor'],
                    // Anita contenido 5: conceptos de ganancias; en el recibo ERP viven como importe.
                    ReporteSueldosDefinibleSupport::CONTENIDO_CONCEPTO_GANANCIAS,
                    ReporteSueldosDefinibleSupport::CONTENIDO_IMPORTE => $base['importe'],
                    default => $base['importe'],
                };
            }
            $out[$key] = round($suma, 2);
            $numericos[$nro] = true;
        }

        // Fórmulas en segunda pasada
        foreach ($reporte->columnas as $col) {
            if ($col->contenido !== ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA) {
                continue;
            }
            $map = [];
            foreach ($reporte->columnas as $c2) {
                $v = $out['c'.$c2->nro_columna] ?? 0;
                $valor = is_numeric($v) ? (float) $v : $v;
                $nro = (int) $c2->nro_columna;
                $map[$nro] = $valor;
                $map['C'.$nro] = $valor;
                $map['c'.$nro] = $valor;
            }
            $out['c'.$col->nro_columna] = ReporteSueldosDefinibleFormulaSupport::evaluar(
                (string) ($col->formula ?? ''),
                $map
            );
        }

        return $out;
    }

    /**
     * @param  array<string, float|string>  $valores
     */
    private function tieneMovimientoRelevante(ReporteSueldosDefinible $reporte, array $valores): bool
    {
        $tieneConcepto = false;
        foreach ($reporte->columnas as $col) {
            if (in_array($col->contenido, [
                ReporteSueldosDefinibleSupport::CONTENIDO_IMPORTE,
                ReporteSueldosDefinibleSupport::CONTENIDO_CANTIDAD,
                ReporteSueldosDefinibleSupport::CONTENIDO_VALOR,
                ReporteSueldosDefinibleSupport::CONTENIDO_CONCEPTO_GANANCIAS,
            ], true) && $col->conceptos->isNotEmpty()) {
                $tieneConcepto = true;
                $v = $valores['c'.$col->nro_columna] ?? 0;
                if (is_numeric($v) && abs((float) $v) > 0.009) {
                    return true;
                }
            }
        }

        return ! $tieneConcepto;
    }

    private function pasaFiltrosTipo(
        ReporteSueldosDefinible $reporte,
        Liquidacion_Recibo_Sueldos $recibo,
        ?Empleado_Sueldos $emp,
        array $filtros
    ): bool {
        if ($reporte->tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL && $reporte->asociado_codigo) {
            if (! $this->coincideAsociado(
                (int) ($emp?->obrasocial?->codigo ?? 0),
                (int) ($recibo->obrasocial_id ?? $emp?->obrasocial_id ?? 0),
                (int) $reporte->asociado_codigo
            )) {
                return false;
            }
        }
        if ($reporte->tipo === ReporteSueldosDefinibleSupport::TIPO_SINDICATO && $reporte->asociado_codigo) {
            if (! $this->coincideAsociado(
                (int) ($emp?->sindicato?->codigo ?? 0),
                (int) ($recibo->sindicato_id ?? $emp?->sindicato_id ?? 0),
                (int) $reporte->asociado_codigo
            )) {
                return false;
            }
        }
        $lugares = $filtros['lugartrabajo_ids'] ?? [];
        if (is_array($lugares) && $lugares !== []) {
            $lt = (int) ($recibo->lugartrabajo_id ?? $emp?->lugartrabajo_id ?? 0);
            if (! in_array($lt, array_map('intval', $lugares), true)) {
                return false;
            }
        }
        $centros = $filtros['centrocosto_ids'] ?? [];
        if (is_array($centros) && $centros !== []
            && ! in_array((int) ($emp?->centrocosto_id ?? 0), array_map('intval', $centros), true)) {
            return false;
        }
        $agrupamientos = $filtros['agrupamiento_ids'] ?? [];
        if (is_array($agrupamientos) && $agrupamientos !== []) {
            $agrupamientoId = (int) ($recibo->agrupamiento_id ?? $emp?->agrupamiento_id ?? 0);
            if (! in_array($agrupamientoId, array_map('intval', $agrupamientos), true)) {
                return false;
            }
        }

        return true;
    }

    private function pasaFiltrosTipoAbm(ReporteSueldosDefinible $reporte, Empleado_Sueldos $emp, array $filtros): bool
    {
        if ($reporte->tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL && $reporte->asociado_codigo) {
            if (! $this->coincideAsociado(
                (int) ($emp->obrasocial?->codigo ?? 0),
                (int) ($emp->obrasocial_id ?? 0),
                (int) $reporte->asociado_codigo
            )) {
                return false;
            }
        }
        if ($reporte->tipo === ReporteSueldosDefinibleSupport::TIPO_SINDICATO && $reporte->asociado_codigo) {
            if (! $this->coincideAsociado(
                (int) ($emp->sindicato?->codigo ?? 0),
                (int) ($emp->sindicato_id ?? 0),
                (int) $reporte->asociado_codigo
            )) {
                return false;
            }
        }
        $lugares = $filtros['lugartrabajo_ids'] ?? [];
        if (is_array($lugares) && $lugares !== []) {
            if (! in_array((int) $emp->lugartrabajo_id, array_map('intval', $lugares), true)) {
                return false;
            }
        }
        $centros = $filtros['centrocosto_ids'] ?? [];
        if (is_array($centros) && $centros !== []
            && ! in_array((int) $emp->centrocosto_id, array_map('intval', $centros), true)) {
            return false;
        }
        $agrupamientos = $filtros['agrupamiento_ids'] ?? [];
        if (is_array($agrupamientos) && $agrupamientos !== []
            && ! in_array((int) $emp->agrupamiento_id, array_map('intval', $agrupamientos), true)) {
            return false;
        }

        return true;
    }

    private function grupoKey(string $agrupacion, ?Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp): string
    {
        return match ($agrupacion) {
            ReporteSueldosDefinibleSupport::AGRUPACION_CCOSTO => 'cc:'.(int) ($emp?->centrocosto_id ?? 0),
            ReporteSueldosDefinibleSupport::AGRUPACION_LUGAR => 'lt:'.(int) ($recibo?->lugartrabajo_id ?? $emp?->lugartrabajo_id ?? 0),
            ReporteSueldosDefinibleSupport::AGRUPACION_AGRUPAMIENTO => 'ag:'.(int) ($recibo?->agrupamiento_id ?? $emp?->agrupamiento_id ?? 0),
            default => 'leg:'.(int) ($recibo?->legajo ?? $emp?->legajo ?? 0),
        };
    }

    private function grupoLabel(string $agrupacion, ?Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp): string
    {
        return match ($agrupacion) {
            ReporteSueldosDefinibleSupport::AGRUPACION_CCOSTO => (string) ($emp?->centrocosto?->nombre ?? 'Sin c.costo'),
            ReporteSueldosDefinibleSupport::AGRUPACION_LUGAR => (string) ($emp?->lugartrabajo?->nombre ?? 'Sin lugar'),
            ReporteSueldosDefinibleSupport::AGRUPACION_AGRUPAMIENTO => (string) ($emp?->agrupamiento?->descripcion ?? 'Sin agrupamiento'),
            default => (string) ($recibo?->apellido_nombre ?? $emp?->nombre ?? ''),
        };
    }

    /**
     * @param  mixed  $agrupaciones
     * @return list<string>
     */
    private function normalizarAgrupaciones(mixed $agrupaciones): array
    {
        $validas = array_keys(ReporteSueldosDefinibleSupport::agrupaciones());
        $out = [];
        foreach ((array) $agrupaciones as $agrupacion) {
            $agrupacion = (string) $agrupacion;
            if ($agrupacion === ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO
                || ! in_array($agrupacion, $validas, true)
                || in_array($agrupacion, $out, true)) {
                continue;
            }
            $out[] = $agrupacion;
            if (count($out) === 3) {
                break;
            }
        }

        return $out === [] ? [ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO] : $out;
    }

    /**
     * @return array<string, string>
     */
    private function dimensionKeys(?Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp): array
    {
        return [
            ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO => 'emp:'.(int) ($emp?->legajo ?? $recibo?->legajo ?? 0),
            ReporteSueldosDefinibleSupport::AGRUPACION_CCOSTO => 'cc:'.(int) ($emp?->centrocosto_id ?? 0),
            ReporteSueldosDefinibleSupport::AGRUPACION_LUGAR => 'lt:'.(int) ($recibo?->lugartrabajo_id ?? $emp?->lugartrabajo_id ?? 0),
            ReporteSueldosDefinibleSupport::AGRUPACION_AGRUPAMIENTO => 'ag:'.(int) ($recibo?->agrupamiento_id ?? $emp?->agrupamiento_id ?? 0),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dimensionLabels(?Liquidacion_Recibo_Sueldos $recibo, ?Empleado_Sueldos $emp): array
    {
        $legajo = (int) ($emp?->legajo ?? $recibo?->legajo ?? 0);
        $nombre = trim((string) ($emp?->nombre ?? ''));

        return [
            ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO => $nombre !== ''
                ? $nombre.' ('.$legajo.')'
                : 'Legajo '.$legajo,
            ReporteSueldosDefinibleSupport::AGRUPACION_CCOSTO => (string) ($emp?->centrocosto?->nombre ?? 'Sin c.costo'),
            ReporteSueldosDefinibleSupport::AGRUPACION_LUGAR => (string) ($emp?->lugartrabajo?->nombre ?? 'Sin lugar'),
            ReporteSueldosDefinibleSupport::AGRUPACION_AGRUPAMIENTO => (string) ($emp?->agrupamiento?->descripcion ?? 'Sin agrupamiento'),
        ];
    }

    /**
     * Agrega subtotales por cada prefijo dimensional, preservando hasta tres niveles.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{nro:int,numerica:bool}>  $columnasMeta
     * @param  list<string>  $agrupaciones
     * @return list<array<string, mixed>>
     */
    private function agruparMultinivel(array $filas, array $columnasMeta, array $agrupaciones): array
    {
        $grupos = [];
        foreach ($filas as $fila) {
            $keys = (array) ($fila['dimension_keys'] ?? []);
            $labels = (array) ($fila['dimension_labels'] ?? []);
            $prefijo = [];
            $etiquetas = [];

            foreach ($agrupaciones as $nivel => $dimension) {
                $prefijo[] = (string) ($keys[$dimension] ?? $dimension.':0');
                $etiquetas[] = (string) ($labels[$dimension] ?? 'Sin dato');
                $groupKey = implode('|', $prefijo);

                if (! isset($grupos[$groupKey])) {
                    $grupos[$groupKey] = [
                        'legajo' => 0,
                        'nombre' => str_repeat('— ', $nivel).end($etiquetas),
                        'empleado_id' => 0,
                        'recibo_id' => 0,
                        'grupo_key' => $groupKey,
                        'grupo_label' => end($etiquetas),
                        'cantidad_empleados' => 0,
                        '_nivel' => $nivel + 1,
                        '_dimension' => $dimension,
                        '_es_subtotal' => true,
                    ];
                    foreach ($columnasMeta as $cm) {
                        $grupos[$groupKey]['c'.$cm['nro']] = $cm['numerica'] ? 0.0 : '';
                    }
                }

                $grupos[$groupKey]['cantidad_empleados']++;
                foreach ($columnasMeta as $cm) {
                    if (! $cm['numerica']) {
                        continue;
                    }
                    $key = 'c'.$cm['nro'];
                    $grupos[$groupKey][$key] = round(
                        (float) $grupos[$groupKey][$key] + (float) ($fila[$key] ?? 0),
                        2
                    );
                }
            }
        }

        ksort($grupos, SORT_NATURAL);

        return array_values($grupos);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{nro:int,numerica:bool}>  $columnasMeta
     * @return list<array<string, mixed>>
     */
    private function agrupar(array $filas, array $columnasMeta, string $agrupacion, bool $resumido): array
    {
        if ($agrupacion === ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO && ! $resumido) {
            return $filas;
        }
        $groups = [];
        foreach ($filas as $f) {
            $gk = (string) ($f['grupo_key'] ?? 'x');
            if (! isset($groups[$gk])) {
                $groups[$gk] = [
                    'legajo' => $resumido ? 0 : (int) ($f['legajo'] ?? 0),
                    'nombre' => $resumido
                        ? (string) ($f['grupo_label'] ?? '')
                        : (string) ($f['nombre'] ?? ''),
                    'empleado_id' => 0,
                    'recibo_id' => 0,
                    'grupo_key' => $gk,
                    'grupo_label' => (string) ($f['grupo_label'] ?? ''),
                    'cantidad_empleados' => 0,
                ];
                foreach ($columnasMeta as $cm) {
                    if ($cm['numerica']) {
                        $groups[$gk]['c'.$cm['nro']] = 0.0;
                    } else {
                        $groups[$gk]['c'.$cm['nro']] = '';
                    }
                }
            }
            $groups[$gk]['cantidad_empleados']++;
            foreach ($columnasMeta as $cm) {
                if (! $cm['numerica']) {
                    continue;
                }
                $groups[$gk]['c'.$cm['nro']] = round(
                    (float) $groups[$gk]['c'.$cm['nro']] + (float) ($f['c'.$cm['nro']] ?? 0),
                    2
                );
            }
        }

        return array_values($groups);
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $cmp
     * @param  list<array{nro:int,numerica:bool}>  $columnasMeta
     * @return list<array<string, mixed>>
     */
    private function fusionarVariacion(array $base, array $cmp, array $columnasMeta): array
    {
        $idx = [];
        foreach ($cmp as $f) {
            $idx[(int) ($f['legajo'] ?? 0)] = $f;
        }

        $out = [];
        $vistos = [];
        foreach ($base as $f) {
            $leg = (int) ($f['legajo'] ?? 0);
            $vistos[$leg] = true;
            $otro = $idx[$leg] ?? null;
            foreach ($columnasMeta as $cm) {
                if (! $cm['numerica']) {
                    continue;
                }
                $a = (float) ($f['c'.$cm['nro']] ?? 0);
                $b = (float) ($otro['c'.$cm['nro']] ?? 0);
                $f['c'.($cm['nro'] + 1000)] = round($a - $b, 2);
            }
            $out[] = $f;
        }

        // Empleados solo en la liquidación de comparación: base = 0, Δ = -cmp.
        foreach ($cmp as $f) {
            $leg = (int) ($f['legajo'] ?? 0);
            if (isset($vistos[$leg])) {
                continue;
            }
            foreach ($columnasMeta as $cm) {
                if (! $cm['numerica']) {
                    continue;
                }
                $b = (float) ($f['c'.$cm['nro']] ?? 0);
                $f['c'.$cm['nro']] = 0.0;
                $f['c'.($cm['nro'] + 1000)] = round(0 - $b, 2);
            }
            $out[] = $f;
        }

        return $out;
    }

    /**
     * Recupera el centro previo auditado sin agregar una consulta por empleado.
     *
     * @param  list<int>  $empleadoIds
     * @return array<int, string>
     */
    private function centrosCostoAnteriores(ReporteSueldosDefinible $reporte, array $empleadoIds): array
    {
        $usaCampo = $reporte->columnas->contains(
            fn ($columna) => $columna->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO
                && (int) $columna->campo_empleado === 26
        );
        if (! $usaCampo || $empleadoIds === []) {
            return [];
        }

        $idsAnteriores = [];
        $auditorias = DB::table('audits')
            ->where('auditable_type', Empleado_Sueldos::class)
            ->whereIn('auditable_id', $empleadoIds)
            ->where('old_values', 'like', '%"centrocosto_id"%')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['auditable_id', 'old_values']);
        foreach ($auditorias as $auditoria) {
            $empleadoId = (int) $auditoria->auditable_id;
            if (isset($idsAnteriores[$empleadoId])) {
                continue;
            }
            $valores = json_decode((string) $auditoria->old_values, true);
            $centroId = (int) ($valores['centrocosto_id'] ?? 0);
            if ($centroId > 0) {
                $idsAnteriores[$empleadoId] = $centroId;
            }
        }
        if ($idsAnteriores === []) {
            return [];
        }

        $nombres = DB::table('centrocosto')
            ->whereIn('id', array_values($idsAnteriores))
            ->pluck('nombre', 'id');
        $resultado = [];
        foreach ($idsAnteriores as $empleadoId => $centroId) {
            $resultado[$empleadoId] = (string) ($nombres[$centroId] ?? '');
        }

        return $resultado;
    }

    /**
     * Preferir código de negocio Anita; fallback al id ERP solo si no hay código.
     */
    private function coincideAsociado(int $codigoNegocio, int $idErp, int $asociado): bool
    {
        if ($codigoNegocio > 0) {
            return $codigoNegocio === $asociado;
        }

        return $idErp === $asociado;
    }
}
