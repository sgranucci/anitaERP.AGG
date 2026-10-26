<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Listado\ListadoOrdenamientoSupport;

/**
 * Columnas del workbench de pedidos Interforming.
 */
final class PedidoInterformingListadoColumnas
{
    public const RECURSO = 'ventas.pedido_interforming';

    /** @var array<string, array<string, mixed>> */
    public const COLUMNAS = [
        'id' => [
            'label' => 'ID',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'pedido.id',
            'attr' => 'id',
        ],
        'codigo' => [
            'label' => 'Código',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pedido.codigo',
            'attr' => 'codigo',
        ],
        'tipo' => [
            'label' => 'Tipo',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pedido.tipo_comprobante',
            'attr' => 'tipo_comprobante',
        ],
        'fecha' => [
            'label' => 'Fecha',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'pedido.fecha',
            'attr' => 'fecha',
        ],
        'fechaentrega' => [
            'label' => 'Entrega',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'fecha',
            'source' => 'pedido.fechaentrega',
            'attr' => 'fechaentrega',
        ],
        'cliente_codigo' => [
            'label' => 'Cód. cliente',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.codigo',
            'attr' => 'cliente_codigo',
        ],
        'cliente' => [
            'label' => 'Cliente',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'cliente.nombre',
            'attr' => 'cliente_nombre',
        ],
        'orden_compra' => [
            'label' => 'O. compra',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pedido.orden_compra',
            'attr' => 'orden_compra',
        ],
        'estado' => [
            'label' => 'Estado',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pedido.estadopedido',
            'attr' => 'estadopedido',
        ],
        'aprobacion' => [
            'label' => 'Aprobación',
            'default' => true,
            'export' => false,
            'filterable' => false,
            'type' => 'texto',
            'source' => '',
            'attr' => 'aprobacion',
        ],
        'vendedor' => [
            'label' => 'Vendedor',
            'default' => true,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'vendedor.nombre',
            'attr' => 'vendedor_nombre',
        ],
        'transporte' => [
            'label' => 'Expreso',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'transporte.nombre',
            'attr' => 'transporte_nombre',
        ],
        'moneda' => [
            'label' => 'Moneda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'moneda.nombre',
            'attr' => 'moneda_nombre',
        ],
        'numero' => [
            'label' => 'Número',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'entero',
            'source' => 'pedido.numero_comprobante',
            'attr' => 'numero_comprobante',
        ],
        'lugarentrega' => [
            'label' => 'Lugar entrega',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pedido.lugarentrega',
            'attr' => 'lugarentrega',
        ],
        'leyenda' => [
            'label' => 'Leyenda',
            'default' => false,
            'export' => true,
            'filterable' => true,
            'type' => 'texto',
            'source' => 'pedido.leyenda',
            'attr' => 'leyenda',
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
     * @return array<string, array{label: string, type: string, column: string}>
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

    public static function valorCelda(object $row, string $key): string
    {
        if (! $row instanceof \App\Models\Ventas\PedidoInterforming) {
            if ($key === 'estado') {
                return PedidoEstadosInterforming::etiquetaCabecera(
                    isset($row->estadopedido) ? (string) $row->estadopedido : (isset($row->estado) ? (string) $row->estado : null),
                    null
                );
            }
            if ($key === 'fecha' || $key === 'fechaentrega') {
                return self::fecha($row->{$key} ?? null);
            }

            return trim((string) ($row->{$key} ?? ''));
        }

        return match ($key) {
            'codigo' => (string) ($row->codigo ?? ''),
            'tipo' => strtoupper(trim((string) ($row->tipo_comprobante ?? ''))),
            'fecha' => self::fecha($row->fecha ?? null),
            'fechaentrega' => self::fecha($row->fechaentrega ?? null),
            'cliente_codigo' => (string) ($row->clientes->codigo ?? ''),
            'cliente' => trim((string) (($row->clientes->codigo ?? '').' '.($row->clientes->nombre ?? ''))),
            'orden_compra' => (string) ($row->orden_compra ?? ''),
            'estado' => $row instanceof \App\Models\Ventas\PedidoInterforming
                ? $row->etiquetaEstado()
                : PedidoEstadosInterforming::etiquetaCabecera($row->estadopedido ?? null, $row->estado ?? null),
            'aprobacion' => $row instanceof \App\Models\Ventas\PedidoInterforming
                ? $row->etiquetaAprobacion()
                : '',
            'vendedor' => (string) ($row->vendedores->nombre ?? ''),
            'transporte' => (string) ($row->transportes->nombre ?? ''),
            'moneda' => (string) ($row->moneda->nombre ?? ''),
            'numero' => (string) ($row->numero_comprobante ?? ''),
            'lugarentrega' => (string) ($row->lugarentrega ?? ''),
            'leyenda' => (string) ($row->leyenda ?? ''),
            'id' => (string) ($row->id ?? ''),
            default => (string) ($row->{$key} ?? ''),
        };
    }

    private static function fecha(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }
        $texto = substr(trim((string) $valor), 0, 10);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return $texto;
    }
}
