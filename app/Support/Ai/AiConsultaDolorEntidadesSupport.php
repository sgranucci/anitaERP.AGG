<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\ConsultaInteligente\EntidadSemanticaCatalogo;
use App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Intents de “dolor” escalables: plantillas CI de cada entidad → preview + link.
 * Complementa intents dedicados (precarga, tickets SLA, TM, etc.): no los reemplaza.
 */
final class AiConsultaDolorEntidadesSupport
{
    private const MAX_COLUMNAS = 5;

    private const MAX_FILAS = 12;

    /**
     * Frases NL → entidad + plantilla (además del título de la plantilla).
     *
     * @return list<array{frases: list<string>, entidad: string, plantilla: string}>
     */
    public static function aliasesNl(): array
    {
        return [
            ['frases' => ['cheques para depositar', 'cheques a depositar', 'cheques depositar'], 'entidad' => 'cheque', 'plantilla' => 'para_depositar'],
            ['frases' => ['cheques en cartera', 'cheques cartera'], 'entidad' => 'cheque', 'plantilla' => 'cartera'],
            ['frases' => ['cheques rechazados'], 'entidad' => 'cheque', 'plantilla' => 'rechazados'],
            ['frases' => ['cheques emitidos'], 'entidad' => 'cheque', 'plantilla' => 'emitidos'],
            ['frases' => ['facturas sin remito', 'ventas sin remito', 'comprobantes sin remito'], 'entidad' => 'factura', 'plantilla' => 'sin_remito'],
            ['frases' => ['facturas de venta este mes', 'facturas este mes'], 'entidad' => 'factura', 'plantilla' => 'este_mes'],
            ['frases' => ['pedidos pendientes', 'pedidos abiertos', 'pedidos pendientes este mes'], 'entidad' => 'pedido', 'plantilla' => 'pendientes_mes'],
            // «oc pendientes» queda para el intent de firma; acá el estado de cabecera.
            ['frases' => ['oc estado pendiente', 'oc en pendiente', 'ordenes de compra en pendiente', 'órdenes de compra en pendiente', 'oc estado = pendiente'], 'entidad' => 'ordencompra', 'plantilla' => 'pendientes'],
            ['frases' => ['oc aprobadas', 'ordenes de compra aprobadas', 'órdenes de compra aprobadas', 'oc estado aprobada'], 'entidad' => 'ordencompra', 'plantilla' => 'aprobadas'],
            ['frases' => ['requisiciones pendientes', 'rq pendientes'], 'entidad' => 'requisicion', 'plantilla' => 'pendientes'],
            ['frases' => ['solicitudes de pago emitidas', 'solicitudes emitidas', 'sp emitidas'], 'entidad' => 'solicitudpago', 'plantilla' => 'emitidas'],
            ['frases' => ['solicitudes de pago controladas', 'solicitudes controladas', 'sp controladas'], 'entidad' => 'solicitudpago', 'plantilla' => 'controladas'],
            ['frases' => ['solicitudes de pago pendientes', 'solicitudes pendientes de pago', 'sp pendientes'], 'entidad' => 'solicitudpago', 'plantilla' => 'emitidas'],
            ['frases' => ['cobranzas este mes', 'cobranzas del mes'], 'entidad' => 'cobranza', 'plantilla' => 'este_mes'],
            ['frases' => ['ingresos y egresos este mes', 'ie este mes', 'ingresos egresos este mes'], 'entidad' => 'ingresoegreso', 'plantilla' => 'este_mes'],
            ['frases' => ['pagos a proveedores este mes', 'ordenes de pago este mes', 'órdenes de pago este mes', 'op este mes'], 'entidad' => 'pagoproveedor', 'plantilla' => 'este_mes'],
            ['frases' => ['remitos pendientes', 'remitos pendientes de entrega'], 'entidad' => 'remito', 'plantilla' => 'pendientes'],
            ['frases' => ['remitos este mes'], 'entidad' => 'remito', 'plantilla' => 'este_mes'],
            ['frases' => ['recepciones en borrador', 'com en borrador', 'recepciones borrador'], 'entidad' => 'recepcion_proveedor', 'plantilla' => 'borradores'],
            ['frases' => ['importaciones abiertas', 'importaciones en curso'], 'entidad' => 'importacion', 'plantilla' => 'abiertas'],
            ['frases' => ['importaciones en transito', 'importaciones en tránsito'], 'entidad' => 'importacion', 'plantilla' => 'en_transito'],
            ['frases' => ['capex activos', 'capex en curso'], 'entidad' => 'capex', 'plantilla' => 'activos'],
            ['frases' => ['tm pendientes', 'transferencias pendientes de recepcion', 'transferencias pendientes de recepción'], 'entidad' => 'transferencia_mercaderia', 'plantilla' => 'pendientes'],
            ['frases' => ['comprobantes proveedor este mes', 'facturas de compra este mes'], 'entidad' => 'comprobante_proveedor', 'plantilla' => 'este_mes'],
            ['frases' => ['asientos este mes'], 'entidad' => 'asiento', 'plantilla' => 'este_mes'],
            ['frases' => ['movimientos de stock este mes'], 'entidad' => 'movimientostock', 'plantilla' => 'este_mes'],
            ['frases' => ['empleados activos'], 'entidad' => 'empleado_sueldos', 'plantilla' => 'activos'],
            ['frases' => ['articulos activos', 'artículos activos'], 'entidad' => 'articulo', 'plantilla' => 'activos'],
            ['frases' => ['precargas pendientes', 'precargas de factura pendientes', 'facturas precargadas pendientes'], 'entidad' => 'precarga_comprobante_proveedor', 'plantilla' => 'pendientes'],
            ['frases' => ['precargas generadas', 'precargas con comprobante'], 'entidad' => 'precarga_comprobante_proveedor', 'plantilla' => 'generadas'],
            ['frases' => ['precargas pendiente de entrega', 'precargas pendientes de entrega'], 'entidad' => 'precarga_comprobante_proveedor', 'plantilla' => 'pendiente_entrega'],
            ['frases' => ['recuentos pendientes', 'inventarios pendientes', 'recuento pendiente'], 'entidad' => 'recuento', 'plantilla' => 'pendientes'],
            ['frases' => ['recuentos cierre parcial', 'recuentos parcialmente cerrados', 'cierres parciales de recuento'], 'entidad' => 'recuento', 'plantilla' => 'cerrado_parcial'],
            ['frases' => ['requisiciones de sala pendientes', 'rq sala pendientes', 'requisiciones sala pendientes'], 'entidad' => 'requisicion_sala', 'plantilla' => 'pendientes'],
            ['frases' => ['requisiciones de sala aprobadas', 'rq sala aprobadas'], 'entidad' => 'requisicion_sala', 'plantilla' => 'aprobadas'],
            ['frases' => ['items pendientes de sala', 'ítems pendientes de sala', 'requisicion sala items pendientes'], 'entidad' => 'requisicion_sala', 'plantilla' => 'pendientes_item'],
            ['frases' => ['propuestas de pago en borrador', 'propuestas pago borrador', 'propuestas borrador'], 'entidad' => 'propuesta_pago', 'plantilla' => 'borrador'],
            ['frases' => ['propuestas de pago exportadas', 'propuestas exportadas'], 'entidad' => 'propuesta_pago', 'plantilla' => 'exportado'],
            ['frases' => ['listado de propuestas de pago', 'listado propuestas de pago'], 'entidad' => 'propuesta_pago', 'plantilla' => 'todas'],
        ];
    }

    /**
     * @return array{ok: bool, entidad?: string, plantilla?: string, titulo?: string}|null
     */
    public static function resolverDesdeNorm(string $norm): ?array
    {
        $norm = mb_strtolower(trim($norm), 'UTF-8');
        if ($norm === '') {
            return null;
        }

        foreach (self::aliasesNl() as $alias) {
            foreach ($alias['frases'] as $frase) {
                $f = self::normalizarTexto($frase);
                if ($f !== '' && (str_contains($norm, $f) || $norm === $f)) {
                    return [
                        'ok' => true,
                        'entidad' => $alias['entidad'],
                        'plantilla' => $alias['plantilla'],
                        'titulo' => $frase,
                    ];
                }
            }
        }

        // Fallback: «{entidad} {titulo plantilla}» / título plantilla solo si es suficientemente específico
        foreach (EntidadSemanticaCatalogo::disponiblesParaUsuario() as $ent) {
            $nombreEnt = self::normalizarTexto($ent->nombre());
            $claveEnt = self::normalizarTexto($ent->clave());
            foreach ($ent->plantillas() as $p) {
                $clave = (string) ($p['clave'] ?? '');
                if ($clave === '' || in_array($clave, ['todas', 'todos'], true)) {
                    continue;
                }
                $titulo = self::normalizarTexto((string) ($p['titulo'] ?? ''));
                if ($titulo === '') {
                    continue;
                }
                $hitTitulo = str_contains($norm, $titulo);
                $hitEntidad = ($nombreEnt !== '' && str_contains($norm, $nombreEnt))
                    || ($claveEnt !== '' && str_contains($norm, $claveEnt));
                if ($hitTitulo && ($hitEntidad || strlen($titulo) >= 12)) {
                    return [
                        'ok' => true,
                        'entidad' => $ent->clave(),
                        'plantilla' => $clave,
                        'titulo' => (string) $p['titulo'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function ejecutar(array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_ENTIDAD_DOLOR;
        $entidadClave = strtolower(trim((string) ($params['entidad'] ?? $params['codigo'] ?? '')));
        $plantillaClave = strtolower(trim((string) ($params['plantilla'] ?? $params['accion'] ?? '')));

        if ($entidadClave === '' || $plantillaClave === '') {
            $desdeTexto = self::resolverDesdeNorm(self::normalizarTexto(
                (string) ($params['pregunta'] ?? $params['valor'] ?? $params['descripcion'] ?? '')
            ));
            if ($desdeTexto !== null) {
                $entidadClave = $desdeTexto['entidad'];
                $plantillaClave = $desdeTexto['plantilla'];
            }
        }

        if ($entidadClave === '' || ! EntidadSemanticaCatalogo::existe($entidadClave)) {
            return self::fallo($intent, 'Indique la entidad (ej.: cheques para depositar, OC pendientes, facturas sin remito).');
        }

        try {
            $ent = EntidadSemanticaCatalogo::porClave($entidadClave);
        } catch (Throwable $e) {
            return self::fallo($intent, 'Entidad no disponible: '.$e->getMessage());
        }

        if (! can($ent->permisoListar(), false) && empty($params['_mcp_bridge'])) {
            return self::fallo($intent, 'Sin permiso para listar '.$ent->nombre().'.');
        }

        $plantilla = self::buscarPlantilla($ent, $plantillaClave);
        if ($plantilla === null) {
            return self::fallo(
                $intent,
                'Plantilla «'.$plantillaClave.'» no existe en '.$ent->nombre().'.'
            );
        }

        $query = is_array($plantilla['query'] ?? null) ? $plantilla['query'] : ['consultar' => 1];
        $query['consultar'] = 1;

        if ($ent instanceof EntidadSemanticaPuenteReporte) {
            $url = $ent->urlReporte($query);

            return [
                'ok' => true,
                'intent' => $intent,
                'score' => 0.86,
                'parrafos' => [
                    $ent->nombre().' · '.((string) ($plantilla['titulo'] ?? $plantillaClave)),
                    (string) ($plantilla['descripcion'] ?? 'Abrí el reporte con los filtros aplicados.'),
                    method_exists($ent, 'ayudaPuente') ? (string) $ent->ayudaPuente() : '',
                ],
                'links' => [
                    ['etiqueta' => 'Abrir '.$ent->nombre(), 'url' => $url],
                ],
                'tabla' => null,
                'datos' => [
                    'entidad' => $entidadClave,
                    'plantilla' => $plantillaClave,
                    'total' => null,
                ],
            ];
        }

        $url = EntidadSemanticaCatalogo::urlExplorar($entidadClave, $query);
        $request = Request::create('/', 'GET', $query);
        $filtros = $ent->resolverFiltros($request);

        $total = null;
        $filasTabla = [];
        $columnas = [];
        try {
            $resultado = $ent->consultar($filtros, true);
            if ($resultado instanceof LengthAwarePaginator) {
                $total = (int) $resultado->total();
                $items = $resultado->getCollection();
            } elseif ($resultado instanceof Collection) {
                $total = $resultado->count();
                $items = $resultado->take(self::MAX_FILAS);
            } else {
                $items = collect();
            }

            $keys = array_slice($ent->columnasVisibles(), 0, self::MAX_COLUMNAS);
            $etiquetas = $ent->etiquetasColumnas();
            foreach ($keys as $key) {
                $columnas[] = [
                    'key' => $key,
                    'label' => (string) ($etiquetas[$key] ?? $key),
                ];
            }
            $n = 0;
            foreach ($items as $row) {
                if ($n >= self::MAX_FILAS) {
                    break;
                }
                $fila = [];
                foreach ($keys as $key) {
                    $fila[$key] = mb_substr($ent->valorCelda($row, $key), 0, 48);
                }
                $filasTabla[] = $fila;
                $n++;
            }
        } catch (Throwable $e) {
            return [
                'ok' => true,
                'intent' => $intent,
                'score' => 0.8,
                'parrafos' => [
                    $ent->nombre().' · '.((string) ($plantilla['titulo'] ?? $plantillaClave)),
                    'No se pudo armar la vista previa: '.$e->getMessage(),
                    'Podés abrir el explorador con los mismos filtros.',
                ],
                'links' => [
                    ['etiqueta' => 'Abrir en Consulta Inteligente', 'url' => $url],
                    ['etiqueta' => 'Listado nativo', 'url' => route($ent->rutaNativa())],
                ],
                'tabla' => null,
                'datos' => [
                    'entidad' => $entidadClave,
                    'plantilla' => $plantillaClave,
                    'error_preview' => $e->getMessage(),
                ],
            ];
        }

        $parrafos = [
            $ent->nombre().' · '.((string) ($plantilla['titulo'] ?? $plantillaClave)),
            (string) ($plantilla['descripcion'] ?? ''),
        ];
        if ($total !== null) {
            $parrafos[] = 'Cantidad: '.$total;
        }
        if ($filasTabla === []) {
            $parrafos[] = 'Sin filas para este filtro.';
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.9,
            'parrafos' => array_values(array_filter($parrafos, static fn ($p) => trim((string) $p) !== '')),
            'links' => [
                ['etiqueta' => 'Abrir en Consulta Inteligente', 'url' => $url],
                ['etiqueta' => 'Listado nativo', 'url' => route($ent->rutaNativa())],
            ],
            'tabla' => $columnas === [] ? null : [
                'columnas' => $columnas,
                'filas' => $filasTabla,
            ],
            'datos' => [
                'entidad' => $entidadClave,
                'plantilla' => $plantillaClave,
                'total' => $total,
            ],
        ];
    }

    /**
     * @return array{clave: string, titulo: string, descripcion: string, query: array<string, mixed>}|null
     */
    private static function buscarPlantilla(EntidadSemantica $ent, string $clave): ?array
    {
        foreach ($ent->plantillas() as $p) {
            if (strtolower((string) ($p['clave'] ?? '')) === $clave) {
                return $p;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function fallo(string $intent, string $msg): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $msg,
        ];
    }

    private static function normalizarTexto(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /**
     * Helper para plantillas QBE en entidades.
     *
     * @return array<string, mixed>
     */
    public static function queryQbeCampo(string $campo, string $op, string $valor = '', string $valorHasta = ''): array
    {
        return [
            'consultar' => 1,
            'filtro_modo' => 'qbe',
            'qbe' => [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => 0,
                    'criterios' => [[
                        'campo' => $campo,
                        'op' => $op,
                        'valor' => $valor,
                        'valor_hasta' => $valorHasta,
                    ]],
                ]],
            ],
        ];
    }

    /**
     * @param  list<array{campo: string, op: string, valor?: string, valor_hasta?: string}>  $criterios
     * @return array<string, mixed>
     */
    public static function queryQbeCriterios(array $criterios): array
    {
        $lista = [];
        foreach ($criterios as $c) {
            $lista[] = [
                'campo' => $c['campo'],
                'op' => $c['op'],
                'valor' => (string) ($c['valor'] ?? ''),
                'valor_hasta' => (string) ($c['valor_hasta'] ?? ''),
            ];
        }

        return [
            'consultar' => 1,
            'filtro_modo' => 'qbe',
            'qbe' => [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => 0,
                    'criterios' => $lista,
                ]],
            ],
        ];
    }
}
