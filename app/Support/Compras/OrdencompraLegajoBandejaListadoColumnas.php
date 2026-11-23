<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo de columnas del workbench de la bandeja de legajos
 * (pantalla, export, QBE, cortes y etiquetas).
 *
 * Las columnas derivadas (días, paquete, facturas, decisión) se hidratan
 * en PHP y no entran al QBE SQL.
 */
final class OrdencompraLegajoBandejaListadoColumnas
{
    public const RECURSO = 'compras.legajo_bandeja';

    public const GRUPO_LEGAJO = 'legajo';

    public const GRUPO_PARTES = 'partes';

    public const GRUPO_CIRCUITO = 'circuito';

    public const GRUPO_CONTROL = 'control';

    /** @var array<string, string> */
    public const GRUPOS = [
        self::GRUPO_LEGAJO => 'Legajo',
        self::GRUPO_PARTES => 'Empresa / proveedor',
        self::GRUPO_CIRCUITO => 'Circuito',
        self::GRUPO_CONTROL => 'Control',
    ];

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'id' => [
            'label' => 'Id',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'ordencompra.id',
            'attr' => 'id',
            'group' => self::GRUPO_LEGAJO,
        ],
        'numero' => [
            'label' => 'OC',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'ordencompra.numeroordencompra',
            'attr' => 'numero',
            'group' => self::GRUPO_LEGAJO,
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'ordencompra.fecha',
            'attr' => 'fecha',
            'group' => self::GRUPO_LEGAJO,
        ],
        'empresa' => [
            'label' => 'Empresa',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'empresa.nombre',
            'attr' => 'empresa',
            'group' => self::GRUPO_PARTES,
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'proveedor.nombre',
            'attr' => 'proveedor',
            'group' => self::GRUPO_PARTES,
        ],
        'centrocosto' => [
            'label' => 'Centro de costo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'centrocosto.nombre',
            'attr' => 'centrocosto',
            'group' => self::GRUPO_PARTES,
        ],
        'sector' => [
            'label' => 'Sector',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'sector_legajocompra.nombre',
            'attr' => 'sector',
            'group' => self::GRUPO_CIRCUITO,
        ],
        'dias' => [
            'label' => 'Días',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'entero',
            'source' => '',
            'attr' => 'dias',
            'group' => self::GRUPO_CIRCUITO,
            'alinea' => 'centro',
        ],
        'paquete' => [
            'label' => 'Paquete',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'paquete_etiqueta',
            'group' => self::GRUPO_CIRCUITO,
        ],
        'facturas' => [
            'label' => 'Facturas',
            'default' => true,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'facturas',
            'group' => self::GRUPO_CIRCUITO,
        ],
        'decision' => [
            'label' => 'Decisión',
            // Oculta por defecto: deja espacio a Acciones en un solo renglón. Se puede activar en Diseñar vista.
            'default' => false,
            'export' => true,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'decision',
            'group' => self::GRUPO_CIRCUITO,
        ],
        'fecha_alta' => [
            'label' => 'Fecha de alta',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'ordencompra.created_at',
            'attr' => 'created_at',
            'group' => self::GRUPO_CONTROL,
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
     * @param  list<string>|null  $solicitadas
     * @return list<string>
     */
    public static function normalizarVisibles(?array $solicitadas): array
    {
        $permitidas = array_keys(self::catalogoActivo());
        if ($solicitadas === null || $solicitadas === []) {
            return self::defaultsVisibles();
        }
        $keys = [];
        foreach ($solicitadas as $key) {
            $key = (string) $key;
            if (in_array($key, $permitidas, true) && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? self::defaultsVisibles() : $keys;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposFiltrables(): array
    {
        $out = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable']) || ($meta['source'] ?? '') === '') {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $meta['source'],
                'group' => $meta['group'] ?? '',
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
        foreach (self::camposFiltrables() as $key => $meta) {
            $column = (string) $meta['column'];
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = [
                'label' => $meta['label'],
                'type' => $meta['type'],
                'column' => $column,
            ];
        }

        return $out;
    }

    /**
     * @param  object|array<string, mixed>  $data
     */
    public static function valorCelda(object|array $data, string $key): string
    {
        $row = is_array($data) ? (object) $data : $data;

        if ($key === 'facturas') {
            $items = [];
            foreach ((array) ($row->facturas_legajo ?? []) as $fac) {
                $num = trim((string) ($fac['numero'] ?? ''));
                if ($num !== '') {
                    $items[] = $num;
                }
            }
            $nCargadas = (int) ($row->facturas_cargadas_count ?? 0);
            if ($nCargadas > 0) {
                $items[] = $nCargadas.' ya en CxP';
            }

            return implode('; ', $items);
        }

        if ($key === 'paquete') {
            if (! empty($row->paquete_ok)) {
                return trim((string) ($row->paquete_etiqueta ?? 'OK'));
            }
            $partes = [];
            if (! empty($row->tiene_factura)) {
                $partes[] = 'FC';
            }
            if (! empty($row->tiene_com)) {
                $partes[] = 'COM';
            }
            if (! empty($row->tiene_com_asignada)) {
                $partes[] = 'asignada';
            }
            if (! empty($row->tiene_comprobante)) {
                $partes[] = 'cargada';
            } elseif (! empty($row->tiene_comprobante_parcial)) {
                $partes[] = 'parcial';
            }
            if (! empty($row->tiene_pendiente_entrega)) {
                $partes[] = 'pend. entrega';
            }
            if (! empty($row->tiene_pago)) {
                $partes[] = trim((string) ($row->etiqueta_pago ?? 'OP'));
            }

            return $partes === [] ? '' : implode(' · ', $partes);
        }

        if ($key === 'decision') {
            $partes = [];
            $decision = trim((string) ($row->decision ?? ''));
            if ($decision !== '') {
                $partes[] = $decision;
            }
            if (! empty($row->firmante)) {
                $partes[] = (string) $row->firmante;
            }
            if (! empty($row->fecha_decision)) {
                $partes[] = (string) $row->fecha_decision;
            }

            return implode(' · ', $partes);
        }

        $meta = self::catalogoActivo()[$key] ?? null;
        if ($meta === null) {
            return '';
        }
        $attr = (string) ($meta['attr'] ?? $key);
        $valor = $row->{$attr} ?? '';
        $type = (string) ($meta['type'] ?? 'texto');
        if ($type === 'fecha') {
            if ($valor instanceof \DateTimeInterface) {
                $valor = $valor->format('Y-m-d H:i:s');
            }

            return ListadoQbeSupport::formatearFecha($valor);
        }

        return trim((string) $valor);
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        return ListadoCortesSupport::sqlAgrupacionDefault($key, self::catalogoActivo()[$key] ?? []);
    }
}
