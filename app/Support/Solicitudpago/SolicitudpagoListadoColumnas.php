<?php

declare(strict_types=1);

namespace App\Support\Solicitudpago;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;

/**
 * Catálogo del workbench de solicitudes de pago (grilla, QBE, orden y gráficos).
 */
final class SolicitudpagoListadoColumnas
{
    public const RECURSO = 'solicitudpago.solicitudpago';

    public const GRUPO_DOCUMENTO = 'documento';

    public const GRUPO_CLASIFICACION = 'clasificacion';

    public const GRUPO_TERCERO = 'tercero';

    public const GRUPO_PLAN = 'plan';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_DOCUMENTO => 'Documento',
        self::GRUPO_CLASIFICACION => 'Clasificación',
        self::GRUPO_TERCERO => 'Proveedor',
        self::GRUPO_PLAN => 'Plan y pago',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'codigo' => [
            'label' => 'Código',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'solicitudpago.codigo',
            'attr' => 'codigo',
            'group' => self::GRUPO_DOCUMENTO,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_empresa.nombre',
            'attr' => 'nombreempresa',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'solicitudpago.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_DOCUMENTO,
        ],
        'vencimiento' => [
            'label' => 'Vencimiento',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'solicitudpago.fecha_vencimiento',
            'attr' => 'fecha_vencimiento',
            'group' => self::GRUPO_DOCUMENTO,
        ],
        'fecha_pago' => [
            'label' => 'Fecha de pago',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'solicitudpago.fecha_pago',
            'attr' => 'fecha_pago',
            'group' => self::GRUPO_DOCUMENTO,
        ],
        'concepto' => [
            'label' => 'Concepto',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_concepto.nombre',
            'attr' => 'nombreconcepto',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'sector' => [
            'label' => 'Sector',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_sector.nombre',
            'attr' => 'nombresector',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'forma_pago' => [
            'label' => 'Forma de pago',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_forma.nombre',
            'attr' => 'nombreforma',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'centrocosto' => [
            'label' => 'Centro de costo',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_cc.nombre',
            'attr' => 'nombrecentrocosto',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'proveedor_codigo' => [
            'label' => 'N° proveedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'sp_proveedor.codigo',
            'attr' => 'codigoproveedor',
            'group' => self::GRUPO_TERCERO,
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_proveedor.nombre',
            'attr' => 'nombreproveedor',
            'group' => self::GRUPO_TERCERO,
        ],
        'beneficiario' => [
            'label' => 'Beneficiario',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitudpago.beneficiario',
            'attr' => 'beneficiario',
            'group' => self::GRUPO_TERCERO,
        ],
        'detalle' => [
            'label' => 'Detalle',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitudpago.detalle',
            'attr' => 'detalle',
            'group' => self::GRUPO_PLAN,
        ],
        'observacion' => [
            'label' => 'Observación',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitudpago.observacion',
            'attr' => 'observacion',
            'group' => self::GRUPO_PLAN,
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sp_moneda.nombre',
            'attr' => 'nombremoneda',
            'group' => self::GRUPO_DOCUMENTO,
        ],
        'monto' => [
            'label' => 'Monto',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'decimal',
            'source' => 'solicitudpago.monto',
            'attr' => 'monto',
            'alinea' => 'derecha',
            'group' => self::GRUPO_DOCUMENTO,
        ],
        'tratamiento' => [
            'label' => 'Tratamiento',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitudpago.tratamiento',
            'attr' => 'tratamiento',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'solicitudpago.estado',
            'attr' => 'estado',
            'group' => self::GRUPO_CLASIFICACION,
        ],
        'pago' => [
            'label' => 'Pago',
            'default' => true,
            'export' => false,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'pago',
            'group' => self::GRUPO_PLAN,
        ],
        'madre' => [
            'label' => 'SP madre',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'sp_madre.codigo',
            'attr' => 'codigomadre',
            'group' => self::GRUPO_PLAN,
        ],
        'cuotas' => [
            'label' => 'Cuotas',
            'default' => true,
            'export' => false,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'cuotas',
            'group' => self::GRUPO_PLAN,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    /**
     * @return list<string>
     */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (! empty($meta['default'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable'])) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
                'group' => $meta['group'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            $column = (string) ($meta['source'] ?? '');
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'] ?? 'texto',
                'column' => $column,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function catalogoPorGrupo(): array
    {
        $porGrupo = [];
        foreach (self::GRUPOS as $gKey => $gLabel) {
            $porGrupo[$gKey] = [];
        }
        foreach (self::catalogoActivo() as $key => $meta) {
            $g = $meta['group'] ?? self::GRUPO_DOCUMENTO;
            $porGrupo[$g][$key] = $meta;
        }

        return array_filter($porGrupo, static fn ($cols) => $cols !== []);
    }

    public static function valorCelda(object $data, string $key): string
    {
        return match ($key) {
            'empresa' => self::textoRelacion($data, 'nombreempresa', 'empresas'),
            'concepto' => self::textoRelacion($data, 'nombreconcepto', 'conceptos'),
            'sector' => self::textoRelacion($data, 'nombresector', 'sectores'),
            'forma_pago' => self::textoRelacion($data, 'nombreforma', 'formapagosol'),
            'centrocosto' => self::textoRelacion($data, 'nombrecentrocosto', 'centrocostos'),
            'moneda' => self::textoRelacion($data, 'nombremoneda', 'monedas'),
            'proveedor' => self::textoRelacion($data, 'nombreproveedor', 'proveedores'),
            'proveedor_codigo' => self::codigoProveedor($data),
            'madre' => self::codigoMadre($data),
            'fecha', 'vencimiento', 'fecha_pago' => self::fecha($data, $key),
            'monto' => number_format((float) ($data->monto ?? 0), 2, ',', '.'),
            'estado' => self::etiquetaEnum(SolicitudpagoEstados::opciones(), (string) ($data->estado ?? '')),
            'tratamiento' => self::etiquetaEnum(SolicitudpagoTratamientos::opciones(), (string) ($data->tratamiento ?? '')),
            'pago' => self::textoPago($data),
            'cuotas' => self::textoCuotas($data),
            'beneficiario' => trim((string) ($data->beneficiario ?? '')),
            default => trim((string) (data_get($data, self::COLUMNAS[$key]['attr'] ?? $key) ?? '')),
        };
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        $meta = self::catalogoActivo()[$key] ?? [];

        return ListadoCortesSupport::sqlAgrupacionDefault($key, $meta);
    }

    private static function textoRelacion(object $data, string $attr, string $relacion): string
    {
        $directo = trim((string) data_get($data, $attr, ''));
        if ($directo !== '') {
            return $directo;
        }
        $rel = $data->{$relacion} ?? null;

        return trim((string) ($rel->nombre ?? ''));
    }

    private static function codigoProveedor(object $data): string
    {
        $directo = data_get($data, 'codigoproveedor');
        if ($directo !== null && $directo !== '') {
            return (string) $directo;
        }
        $rel = $data->proveedores ?? null;

        return $rel && $rel->codigo !== null ? (string) $rel->codigo : '';
    }

    private static function codigoMadre(object $data): string
    {
        $directo = data_get($data, 'codigomadre');
        if ($directo !== null && $directo !== '') {
            return (string) $directo;
        }
        $rel = $data->madre ?? null;

        return $rel && $rel->codigo !== null ? (string) $rel->codigo : '';
    }

    private static function fecha(object $data, string $key): string
    {
        $attr = match ($key) {
            'vencimiento' => 'fecha_vencimiento',
            'fecha_pago' => 'fecha_pago',
            default => 'fecha',
        };
        $valor = $data->{$attr} ?? null;
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', substr($texto, 0, 10));

        return $dt ? $dt->format('d/m/Y') : $texto;
    }

    /**
     * @param  list<array{valor: string, nombre: string}>  $opciones
     */
    private static function etiquetaEnum(array $opciones, string $codigo): string
    {
        foreach ($opciones as $opt) {
            if ((string) $opt['valor'] === $codigo) {
                return (string) $opt['nombre'];
            }
        }

        return $codigo;
    }

    private static function textoPago(object $data): string
    {
        $pagos = $data->cajaMovimientosPago ?? null;
        if ($pagos === null || (is_countable($pagos) && count($pagos) === 0)) {
            return '';
        }
        $partes = [];
        foreach ($pagos as $pago) {
            $abrev = $pago->tipotransaccioncajas->abreviatura ?? 'OP';
            $partes[] = trim($abrev.' '.($pago->numerotransaccion ?? ''));
        }

        return implode(', ', $partes);
    }

    private static function textoCuotas(object $data): string
    {
        $total = (int) ($data->cuotas_total_count ?? 0);
        $pend = (int) ($data->cuotas_pendientes_count ?? 0);
        if ($total <= 0) {
            return (string) $pend;
        }

        return ($total - $pend).'/'.$total;
    }
}
