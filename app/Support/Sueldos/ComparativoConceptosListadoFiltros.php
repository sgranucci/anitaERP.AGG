<?php

namespace App\Support\Sueldos;

use Illuminate\Http\Request;

/**
 * Filtros del informe comparativo de conceptos liquidados (Anita l-comparacon.c).
 */
class ComparativoConceptosListadoFiltros
{
    public const ORDEN_LEGAJO = 'legajo';

    public const ORDEN_CCOSTO = 'ccosto';

    public const ORDEN_CATEGORIA = 'categoria';

    public const ORDEN_SINDICATO = 'sindicato';

    public const FORMA_PERIODO = 'periodo';

    public const FORMA_LIQUIDACION = 'liquidacion';

    public const FORMATO_CANTIDAD = 'cantidad';

    public const FORMATO_IMPORTE = 'importe';

    public const FORMATO_AMBOS = 'ambos';

    public const FILTRO_ACTIVOS = 'activos';

    public const FILTRO_BAJAS = 'bajas';

    public const FILTRO_SIN = 'sin';

    /** @var list<string> */
    public const ORDENES = [
        self::ORDEN_LEGAJO,
        self::ORDEN_CCOSTO,
        self::ORDEN_CATEGORIA,
        self::ORDEN_SINDICATO,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        $orden = (string) $request->input('orden', self::ORDEN_LEGAJO);
        if (! in_array($orden, self::ORDENES, true)) {
            $orden = self::ORDEN_LEGAJO;
        }

        $forma = (string) $request->input('forma', self::FORMA_PERIODO);
        if (! in_array($forma, [self::FORMA_PERIODO, self::FORMA_LIQUIDACION], true)) {
            $forma = self::FORMA_PERIODO;
        }

        $formato = (string) $request->input('formato', self::FORMATO_IMPORTE);
        if (! in_array($formato, [self::FORMATO_CANTIDAD, self::FORMATO_IMPORTE, self::FORMATO_AMBOS], true)) {
            $formato = self::FORMATO_IMPORTE;
        }

        $filtroEmpleados = (string) $request->input('filtro_empleados', self::FILTRO_SIN);
        if (! in_array($filtroEmpleados, [self::FILTRO_ACTIVOS, self::FILTRO_BAJAS, self::FILTRO_SIN], true)) {
            $filtroEmpleados = self::FILTRO_SIN;
        }

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $empresaId = $empresaIds[0] ?? (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaDefault !== null && $empresaDefault > 0) {
            $empresaId = $empresaDefault;
        }
        if ($empresaIds === [] && $empresaId > 0) {
            $empresaIds = [$empresaId];
        }

        $periodoDesde = self::normalizarPeriodo((string) $request->input('periodo_desde', ''));
        $periodoHasta = self::normalizarPeriodo((string) $request->input('periodo_hasta', ''));
        if ($periodoDesde === '' && $periodoHasta === '') {
            $periodoHasta = date('Ym');
            $periodoDesde = date('Ym', strtotime('-1 month'));
        } elseif ($periodoDesde === '') {
            $periodoDesde = $periodoHasta;
        } elseif ($periodoHasta === '') {
            $periodoHasta = $periodoDesde;
        }
        if ($periodoDesde !== '' && $periodoHasta !== '' && (int) $periodoDesde > (int) $periodoHasta) {
            [$periodoDesde, $periodoHasta] = [$periodoHasta, $periodoDesde];
        }

        $liqDesde = self::enteroOVacio($request->input('liquidacion_desde'));
        $liqHasta = self::enteroOVacio($request->input('liquidacion_hasta'));
        if ($liqDesde !== null && $liqHasta !== null && $liqDesde > $liqHasta) {
            [$liqDesde, $liqHasta] = [$liqHasta, $liqDesde];
        }

        $legajoDesde = self::enteroOVacio($request->input('legajo_desde'));
        $legajoHasta = self::enteroOVacio($request->input('legajo_hasta'));
        if ($legajoDesde !== null && $legajoHasta !== null && $legajoDesde > $legajoHasta) {
            [$legajoDesde, $legajoHasta] = [$legajoHasta, $legajoDesde];
        }

        $conceptoDesde = self::enteroOVacio($request->input('concepto_desde'));
        $conceptoHasta = self::enteroOVacio($request->input('concepto_hasta'));
        if ($conceptoDesde !== null && $conceptoHasta !== null && $conceptoDesde > $conceptoHasta) {
            [$conceptoDesde, $conceptoHasta] = [$conceptoHasta, $conceptoDesde];
        }

        $fechaFiltro = trim((string) $request->input('fecha_filtro', ''));
        if ($fechaFiltro !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFiltro)) {
            $fechaFiltro = '';
        }
        if ($filtroEmpleados !== self::FILTRO_SIN && $fechaFiltro === '') {
            $fechaFiltro = date('Y-m-d');
        }

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : 0,
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->exists('consolidar_empresas')
                ? $request->boolean('consolidar_empresas')
                : true,
            'orden' => $orden,
            'forma' => $forma,
            'formato' => $formato,
            'filtro_empleados' => $filtroEmpleados,
            'fecha_filtro' => $fechaFiltro,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
            'liquidacion_desde' => $liqDesde,
            'liquidacion_hasta' => $liqHasta,
            'legajo_desde' => $legajoDesde,
            'legajo_hasta' => $legajoHasta,
            'concepto_desde' => $conceptoDesde,
            'concepto_hasta' => $conceptoHasta,
            'centrocosto_id_desde' => self::enteroOVacio($request->input('centrocosto_id_desde')),
            'centrocosto_id_hasta' => self::enteroOVacio($request->input('centrocosto_id_hasta')),
            'categoria_id_desde' => self::enteroOVacio($request->input('categoria_id_desde')),
            'categoria_id_hasta' => self::enteroOVacio($request->input('categoria_id_hasta')),
            'sindicato_id_desde' => self::enteroOVacio($request->input('sindicato_id_desde')),
            'sindicato_id_hasta' => self::enteroOVacio($request->input('sindicato_id_hasta')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros, bool $consultar = false): array
    {
        $params = [];
        $empresaIdsQs = collect($filtros['empresa_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
        if ($empresaIdsQs !== []) {
            $params['empresa_ids'] = $empresaIdsQs;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (array_key_exists('consolidar_empresas', $filtros) && ! $filtros['consolidar_empresas']) {
            $params['consolidar_empresas'] = 0;
        }
        $params['orden'] = (string) ($filtros['orden'] ?? self::ORDEN_LEGAJO);
        $params['forma'] = (string) ($filtros['forma'] ?? self::FORMA_PERIODO);
        $params['formato'] = (string) ($filtros['formato'] ?? self::FORMATO_IMPORTE);
        $params['filtro_empleados'] = (string) ($filtros['filtro_empleados'] ?? self::FILTRO_SIN);

        foreach ([
            'fecha_filtro',
            'periodo_desde',
            'periodo_hasta',
        ] as $k) {
            if (! empty($filtros[$k])) {
                $params[$k] = (string) $filtros[$k];
            }
        }

        foreach ([
            'liquidacion_desde',
            'liquidacion_hasta',
            'legajo_desde',
            'legajo_hasta',
            'concepto_desde',
            'concepto_hasta',
            'centrocosto_id_desde',
            'centrocosto_id_hasta',
            'categoria_id_desde',
            'categoria_id_hasta',
            'sindicato_id_desde',
            'sindicato_id_hasta',
        ] as $k) {
            if ($filtros[$k] !== null && $filtros[$k] !== '') {
                $params[$k] = (int) $filtros[$k];
            }
        }

        if ($consultar) {
            $params['consultar'] = 1;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [];

        $ordenEtq = [
            self::ORDEN_LEGAJO => 'x legajo',
            self::ORDEN_CCOSTO => 'x centro de costo',
            self::ORDEN_CATEGORIA => 'x categoría',
            self::ORDEN_SINDICATO => 'x sindicato',
        ];
        $partes[] = $ordenEtq[$filtros['orden'] ?? self::ORDEN_LEGAJO] ?? 'x legajo';

        if (($filtros['forma'] ?? '') === self::FORMA_LIQUIDACION) {
            $d = $filtros['liquidacion_desde'] ?? null;
            $h = $filtros['liquidacion_hasta'] ?? null;
            if ($d !== null || $h !== null) {
                $partes[] = 'liq. '.($d ?? '…').'–'.($h ?? '…');
            } else {
                $partes[] = 'x liquidación';
            }
        } else {
            $partes[] = 'período '.($filtros['periodo_desde'] ?? '').'–'.($filtros['periodo_hasta'] ?? '');
        }

        $fmt = [
            self::FORMATO_CANTIDAD => 'cantidades',
            self::FORMATO_IMPORTE => 'importes',
            self::FORMATO_AMBOS => 'cantidades e importes',
        ];
        $partes[] = $fmt[$filtros['formato'] ?? self::FORMATO_IMPORTE] ?? 'importes';

        $fe = $filtros['filtro_empleados'] ?? self::FILTRO_SIN;
        if ($fe === self::FILTRO_ACTIVOS) {
            $partes[] = 'activos al '.self::fechaFmt((string) ($filtros['fecha_filtro'] ?? ''));
        } elseif ($fe === self::FILTRO_BAJAS) {
            $partes[] = 'bajas al '.self::fechaFmt((string) ($filtros['fecha_filtro'] ?? ''));
        } else {
            $partes[] = 'sin filtro de estado';
        }

        return implode(' · ', $partes);
    }

    public static function etiquetaOrden(string $orden): string
    {
        return match ($orden) {
            self::ORDEN_CCOSTO => 'x centro de costo',
            self::ORDEN_CATEGORIA => 'x categoría',
            self::ORDEN_SINDICATO => 'x sindicato',
            default => 'x legajo',
        };
    }

    public static function normalizarPeriodo(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{6}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            return $m[1].$m[2];
        }
        if (preg_match('/^(\d{4})\/(\d{2})$/', $raw, $m)) {
            return $m[1].$m[2];
        }

        return '';
    }

    public static function periodoParaInput(string $yyyymm): string
    {
        if (! preg_match('/^(\d{4})(\d{2})$/', $yyyymm, $m)) {
            return '';
        }

        return $m[1].'-'.$m[2];
    }

    private static function enteroOVacio(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (int) $v;
    }

    private static function fechaFmt(string $ymd): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return $ymd;
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }
}
