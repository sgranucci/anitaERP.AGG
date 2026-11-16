<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Relaciones publicadas entre entidades del semántico (no SQL libre).
 * Sirven para navegar del explorador a un cruce útil.
 */
final class ConsultaInteligenteJoinCatalogo
{
    /**
     * @return list<array{
     *   desde: string,
     *   hacia: string,
     *   titulo: string,
     *   descripcion: string,
     *   query_desde_fila: callable(object): array<string, mixed>
     * }>
     */
    public static function desde(string $claveEntidad): array
    {
        $todas = self::todas();
        $out = [];
        foreach ($todas as $rel) {
            if ($rel['desde'] !== $claveEntidad) {
                continue;
            }
            if (! EntidadSemanticaCatalogo::existe($rel['hacia'])) {
                continue;
            }
            $hacia = EntidadSemanticaCatalogo::porClave($rel['hacia']);
            if (! can($hacia->permisoListar(), false)) {
                continue;
            }
            $out[] = $rel;
        }

        return $out;
    }

    /**
     * Links de cruce a nivel catálogo (sin fila).
     *
     * @return list<array{titulo: string, descripcion: string, url: string, color: string}>
     */
    public static function atajosDesde(string $claveEntidad): array
    {
        $out = [];
        foreach (self::desde($claveEntidad) as $rel) {
            $hacia = EntidadSemanticaCatalogo::porClave($rel['hacia']);
            $out[] = [
                'titulo' => $rel['titulo'],
                'descripcion' => $rel['descripcion'],
                'url' => EntidadSemanticaCatalogo::urlExplorar($rel['hacia'], ['consultar' => 1]),
                'color' => $hacia->color(),
            ];
        }

        return $out;
    }

    /**
     * Links de una fila concreta hacia la entidad relacionada.
     *
     * @return list<array{titulo: string, url: string}>
     */
    public static function linksDesdeFila(string $claveEntidad, object $row): array
    {
        $out = [];
        foreach (self::desde($claveEntidad) as $rel) {
            $query = ($rel['query_desde_fila'])($row);
            if ($query === []) {
                continue;
            }
            $query['consultar'] = 1;
            $out[] = [
                'titulo' => $rel['titulo'],
                'url' => EntidadSemanticaCatalogo::urlExplorar($rel['hacia'], $query),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *   desde: string,
     *   hacia: string,
     *   titulo: string,
     *   descripcion: string,
     *   query_desde_fila: callable(object): array<string, mixed>
     * }>
     */
    private static function todas(): array
    {
        return [
            [
                'desde' => 'comprobante_proveedor',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Maestro del proveedor de la factura',
                'query_desde_fila' => static function (object $row): array {
                    $id = (int) ($row->proveedor_id ?? 0);
                    if ($id < 1) {
                        return [];
                    }

                    return [
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'id',
                                    'op' => 'igual',
                                    'valor' => (string) $id,
                                    'valor_hasta' => '',
                                ]],
                            ]],
                        ],
                    ];
                },
            ],
            [
                'desde' => 'comprobante_proveedor',
                'hacia' => 'pagoproveedor',
                'titulo' => 'Pagos del proveedor',
                'descripcion' => 'Órdenes de pago ligadas al mismo proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre_proveedor_listado ?? $row->nombreproveedor ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'pagoproveedor',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Maestro del proveedor de la OP',
                'query_desde_fila' => static function (object $row): array {
                    $id = (int) ($row->proveedor_id ?? 0);
                    if ($id < 1) {
                        return [];
                    }

                    return [
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'id',
                                    'op' => 'igual',
                                    'valor' => (string) $id,
                                    'valor_hasta' => '',
                                ]],
                            ]],
                        ],
                    ];
                },
            ],
            [
                'desde' => 'pagoproveedor',
                'hacia' => 'comprobante_proveedor',
                'titulo' => 'Facturas del proveedor',
                'descripcion' => 'Comprobantes del mismo proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre_proveedor ?? $row->nombreproveedor ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'comprobante_proveedor',
                'titulo' => 'Facturas',
                'descripcion' => 'Comprobantes de este proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'pagoproveedor',
                'titulo' => 'Pagos',
                'descripcion' => 'Órdenes de pago de este proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'cheque',
                'hacia' => 'cliente',
                'titulo' => 'Ver cliente',
                'descripcion' => 'Cliente vinculado al cheque (si hay)',
                'query_desde_fila' => static function (object $row): array {
                    $id = (int) ($row->cliente_id ?? 0);
                    if ($id < 1) {
                        return [];
                    }

                    return [
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'id',
                                    'op' => 'igual',
                                    'valor' => (string) $id,
                                    'valor_hasta' => '',
                                ]],
                            ]],
                        ],
                    ];
                },
            ],
            [
                'desde' => 'ingresoegreso',
                'hacia' => 'pagoproveedor',
                'titulo' => 'OP vinculada',
                'descripcion' => 'Pago a proveedor asociado al movimiento',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->pagoproveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'ingresoegreso',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Proveedor del movimiento de caja',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'ingresoegreso',
                'hacia' => 'cliente',
                'titulo' => 'Ver cliente',
                'descripcion' => 'Cliente del movimiento de caja',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->cliente_id ?? 0));
                },
            ],
            [
                'desde' => 'pagoproveedor',
                'hacia' => 'ingresoegreso',
                'titulo' => 'Movimientos de caja',
                'descripcion' => 'Ingresos/egresos ligados a esta OP',
                'query_desde_fila' => static function (object $row): array {
                    $id = (int) ($row->id ?? 0);
                    if ($id < 1) {
                        return [];
                    }

                    return [
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'pagoproveedor_id',
                                    'op' => 'igual',
                                    'valor' => (string) $id,
                                    'valor_hasta' => '',
                                ]],
                            ]],
                        ],
                    ];
                },
            ],
            [
                'desde' => 'cheque',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Proveedor vinculado al cheque',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'cheque',
                'hacia' => 'pagoproveedor',
                'titulo' => 'OP del cheque',
                'descripcion' => 'Orden de pago asociada',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->pagoproveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'cheque',
                'hacia' => 'comprobante_proveedor',
                'titulo' => 'Factura vinculada',
                'descripcion' => 'Comprobante de proveedor del cheque',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->comprobante_proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'cliente',
                'hacia' => 'cheque',
                'titulo' => 'Cheques del cliente',
                'descripcion' => 'Valores recibidos de este cliente',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeCampo('cliente_id', (int) ($row->id ?? 0));
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'cheque',
                'titulo' => 'Cheques del proveedor',
                'descripcion' => 'Valores ligados a este proveedor',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeCampo('proveedor_id', (int) ($row->id ?? 0));
                },
            ],
            [
                'desde' => 'comprobante_proveedor',
                'hacia' => 'cheque',
                'titulo' => 'Cheques de la factura',
                'descripcion' => 'Valores asociados al comprobante',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeCampo('comprobante_proveedor_id', (int) ($row->id ?? 0));
                },
            ],
            [
                'desde' => 'ordencompra',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Maestro del proveedor de la OC',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'ordencompra',
                'hacia' => 'requisicion',
                'titulo' => 'Requisición origen',
                'descripcion' => 'Req. que originó la OC',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->requisicion_id ?? 0));
                },
            ],
            [
                'desde' => 'requisicion',
                'hacia' => 'ordencompra',
                'titulo' => 'OC generada',
                'descripcion' => 'Órdenes de compra de la requisición',
                'query_desde_fila' => static function (object $row): array {
                    $nro = trim((string) ($row->numerorequisicion ?? ''));
                    if ($nro === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nro];
                },
            ],
            [
                'desde' => 'requisicion',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Proveedor de la requisición',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'cobranza',
                'hacia' => 'cliente',
                'titulo' => 'Ver cliente',
                'descripcion' => 'Cliente del recibo',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->cliente_id ?? 0));
                },
            ],
            [
                'desde' => 'cliente',
                'hacia' => 'cobranza',
                'titulo' => 'Cobranzas',
                'descripcion' => 'Recibos del cliente',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'ordencompra',
                'titulo' => 'Órdenes de compra',
                'descripcion' => 'OC de este proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'importacion',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Proveedor de la importación',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'importacion',
                'hacia' => 'ordencompra',
                'titulo' => 'OC vinculadas',
                'descripcion' => 'Buscar OC por proveedor de la importación',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombreproveedor ?? $row->nombre_proveedor ?? ''));
                    if ($nombre === '') {
                        return [];
                    }

                    return ['filtro_valor' => $nombre];
                },
            ],
            [
                'desde' => 'solicitudpago',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Proveedor de la solicitud (si aplica)',
                'query_desde_fila' => static function (object $row): array {
                    $id = (int) ($row->proveedor_id ?? 0);
                    if ($id > 0) {
                        return self::qbeId($id);
                    }
                    $nombre = trim((string) ($row->nombreproveedor ?? $row->nombre_proveedor ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'importacion',
                'titulo' => 'Importaciones',
                'descripcion' => 'Carpetas de importación del proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'solicitudpago',
                'titulo' => 'Solicitudes de pago',
                'descripcion' => 'Solicitudes ligadas al proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
            [
                'desde' => 'recepcion_proveedor',
                'hacia' => 'proveedor',
                'titulo' => 'Ver proveedor',
                'descripcion' => 'Proveedor de la recepción',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->proveedor_id ?? 0));
                },
            ],
            [
                'desde' => 'recepcion_proveedor',
                'hacia' => 'ordencompra',
                'titulo' => 'Ver OC',
                'descripcion' => 'Orden de compra de la recepción',
                'query_desde_fila' => static function (object $row): array {
                    $nro = (int) ($row->numeroordencompra ?? 0);
                    if ($nro < 1) {
                        return [];
                    }

                    return ['filtro_valor' => (string) $nro];
                },
            ],
            [
                'desde' => 'ordencompra',
                'hacia' => 'recepcion_proveedor',
                'titulo' => 'Recepciones',
                'descripcion' => 'COM de esta OC',
                'query_desde_fila' => static function (object $row): array {
                    $nro = (int) ($row->numeroordencompra ?? $row->numero ?? 0);
                    if ($nro < 1) {
                        return [];
                    }

                    return ['filtro_valor' => (string) $nro];
                },
            ],
            [
                'desde' => 'proveedor',
                'hacia' => 'recepcion_proveedor',
                'titulo' => 'Recepciones',
                'descripcion' => 'COM del proveedor',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
            [
                'desde' => 'capex',
                'hacia' => 'ordencompra',
                'titulo' => 'OC relacionadas',
                'descripcion' => 'Buscar OC por código/nombre Capex',
                'query_desde_fila' => static function (object $row): array {
                    $txt = trim((string) ($row->codigoproyecto ?? $row->nombre ?? $row->codigo ?? ''));

                    return $txt !== '' ? ['filtro_valor' => $txt] : [];
                },
            ],
            [
                'desde' => 'movimientostock',
                'hacia' => 'articulo',
                'titulo' => 'Ver artículos',
                'descripcion' => 'Catálogo de artículos / saldos',
                'query_desde_fila' => static function (object $row): array {
                    return ['consultar' => 1];
                },
            ],
            [
                'desde' => 'transferencia_mercaderia',
                'hacia' => 'movimientostock',
                'titulo' => 'Movimientos de stock',
                'descripcion' => 'Kardex / movimientos del mismo período',
                'query_desde_fila' => static function (object $row): array {
                    $fecha = trim((string) ($row->fecha ?? ''));
                    if ($fecha === '') {
                        return ['consultar' => 1];
                    }
                    try {
                        $ymd = \Carbon\Carbon::parse($fecha)->toDateString();
                    } catch (\Throwable) {
                        return ['consultar' => 1];
                    }

                    return [
                        'consultar' => 1,
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'fecha',
                                    'op' => 'igual',
                                    'valor' => $ymd,
                                ]],
                            ]],
                        ],
                    ];
                },
            ],
            [
                'desde' => 'movimientostock',
                'hacia' => 'transferencia_mercaderia',
                'titulo' => 'Transferencias TM',
                'descripcion' => 'TM del mismo día',
                'query_desde_fila' => static function (object $row): array {
                    $fecha = trim((string) ($row->fecha ?? ''));
                    if ($fecha === '') {
                        return ['consultar' => 1];
                    }
                    try {
                        $ymd = \Carbon\Carbon::parse($fecha)->toDateString();
                    } catch (\Throwable) {
                        return ['consultar' => 1];
                    }

                    return [
                        'consultar' => 1,
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'fecha',
                                    'op' => 'igual',
                                    'valor' => $ymd,
                                ]],
                            ]],
                        ],
                    ];
                },
            ],
            [
                'desde' => 'pedido',
                'hacia' => 'cliente',
                'titulo' => 'Ver cliente',
                'descripcion' => 'Cliente del pedido',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->cliente_id ?? 0));
                },
            ],
            [
                'desde' => 'cliente',
                'hacia' => 'pedido',
                'titulo' => 'Pedidos',
                'descripcion' => 'Pedidos del cliente',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
            [
                'desde' => 'pedido',
                'hacia' => 'factura',
                'titulo' => 'Facturas del cliente',
                'descripcion' => 'Comprobantes de venta del mismo cliente',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombrecliente ?? ''));

                    return $nombre !== ''
                        ? ['filtro_valor' => $nombre, 'empresa_todas' => 1]
                        : [];
                },
            ],
            [
                'desde' => 'remito',
                'hacia' => 'cliente',
                'titulo' => 'Ver cliente',
                'descripcion' => 'Cliente del remito',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->cliente_id ?? 0));
                },
            ],
            [
                'desde' => 'remito',
                'hacia' => 'factura',
                'titulo' => 'Factura vinculada',
                'descripcion' => 'Comprobante de venta del remito',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->venta_id ?? 0));
                },
            ],
            [
                'desde' => 'factura',
                'hacia' => 'cliente',
                'titulo' => 'Ver cliente',
                'descripcion' => 'Cliente de la factura',
                'query_desde_fila' => static function (object $row): array {
                    return self::qbeId((int) ($row->cliente_id ?? 0));
                },
            ],
            [
                'desde' => 'factura',
                'hacia' => 'remito',
                'titulo' => 'Remito vinculado',
                'descripcion' => 'Remito de la factura',
                'query_desde_fila' => static function (object $row): array {
                    $id = (int) ($row->remito_id ?? 0);
                    if ($id > 0) {
                        return self::qbeId($id);
                    }
                    $ventaId = (int) ($row->id ?? 0);

                    return $ventaId > 0 ? self::qbeCampo('venta_id', $ventaId) : [];
                },
            ],
            [
                'desde' => 'cliente',
                'hacia' => 'factura',
                'titulo' => 'Facturas',
                'descripcion' => 'Comprobantes de venta del cliente',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));

                    return $nombre !== ''
                        ? ['filtro_valor' => $nombre, 'empresa_todas' => 1]
                        : [];
                },
            ],
            [
                'desde' => 'cliente',
                'hacia' => 'remito',
                'titulo' => 'Remitos',
                'descripcion' => 'Remitos del cliente',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) ($row->nombre ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
            [
                'desde' => 'factura',
                'hacia' => 'cobranza',
                'titulo' => 'Cobranzas',
                'descripcion' => 'Recibos del mismo cliente',
                'query_desde_fila' => static function (object $row): array {
                    $nombre = trim((string) (data_get($row, 'clientes.nombre') ?? $row->nombrecliente ?? ''));

                    return $nombre !== '' ? ['filtro_valor' => $nombre] : [];
                },
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function qbeId(int $id): array
    {
        return self::qbeCampo('id', $id);
    }

    /**
     * @return array<string, mixed>
     */
    private static function qbeCampo(string $campo, int $id): array
    {
        if ($id < 1) {
            return [];
        }

        return [
            'filtro_modo' => 'qbe',
            'qbe' => [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => 0,
                    'criterios' => [[
                        'campo' => $campo,
                        'op' => 'igual',
                        'valor' => (string) $id,
                        'valor_hasta' => '',
                    ]],
                ]],
            ],
        ];
    }
}
