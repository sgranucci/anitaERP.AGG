<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\PedidoArticuloInterforming;
use App\Models\Ventas\PedidoInterforming;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Zonavta;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Ventas\PedidoAnitaEsquemaSupport;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Importa pedidos Anita (pendmae/pendmov) a ERP para INTERFORMING.
 * Filtra por penm_fecha (no entrega: IF suele tener penm_fecha_ent=0).
 * Tipos PED/PEX; nunca REB/REX.
 */
class PedidoImportarDesdeAnitaInterformingService
{
    private const LISTAPRECIO_DEFAULT = 1;

    private const MONEDA_DEFAULT = 1;

    public function assertInterforming(): void
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            throw new RuntimeException('La importación de pedidos Anita Interforming solo aplica a INTERFORMING.');
        }
    }

    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string, tipo?: string}  $filtros
     * @return list<array<string, mixed>>
     */
    public function listarPreview(array $filtros): array
    {
        $this->assertInterforming();

        $cabeceras = $this->listarCabecerasAnita($filtros);
        if ($cabeceras === []) {
            return [];
        }

        $codigos = [];
        foreach ($cabeceras as $cab) {
            $codigos[] = $this->codigoErpDesdeCabecera($cab);
        }

        $existentes = PedidoInterforming::query()
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo', 'estado', 'estadopedido'])
            ->keyBy('codigo');

        $idsConFactura = $this->idsConVentaVinculada(
            $existentes->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );

        $clientesCache = [];
        $out = [];

        foreach ($cabeceras as $cab) {
            $codigo = $this->codigoErpDesdeCabecera($cab);
            $codigoCliente = ltrim(trim((string) ($cab->penm_cliente ?? '')), '0');
            $nombreCliente = $this->nombreCliente($codigoCliente, $clientesCache);
            $existente = $existentes->get($codigo);
            $existe = $existente !== null;
            $estadoAnita = trim((string) ($cab->penm_estado ?? ''));
            $estadoErp = 'nuevo';
            if ($existe && $this->motivoOmitirReimport($existente, $idsConFactura) !== null) {
                $estadoErp = 'omitido_facturado';
            } elseif ($existe) {
                $estadoErp = 'existe';
            }

            $out[] = [
                'codigo' => $codigo,
                'tipo' => trim((string) ($cab->penm_tipo ?? 'PED')),
                'letra' => trim((string) ($cab->penm_letra ?? 'X')),
                'sucursal' => (int) ($cab->penm_sucursal ?? 0),
                'nro' => (int) ($cab->penm_nro ?? 0),
                'codigo_cliente' => $codigoCliente,
                'nombre_cliente' => $nombreCliente,
                'fecha' => $this->formatearFechaAnita($cab->penm_fecha ?? null),
                'fecha_entrega' => $this->formatearFechaAnita($cab->penm_fecha_ent ?? null),
                'estado_anita' => $estadoAnita,
                'estado_anita_etiqueta' => PedidoEstadosInterforming::etiquetaCabecera($estadoAnita),
                'cotizacion' => self::floatDesdeAnita($cab->penm_cotizacion ?? 0),
                'estado_erp' => $estadoErp,
                'pedido_id' => $existe ? (int) $existente->id : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string, tipo?: string}  $filtros
     * @return array{
     *   creados: int,
     *   actualizados: int,
     *   omitidos: int,
     *   errores: int,
     *   total: int,
     *   detalle: list<array{codigo: string, estado: string, mensaje: string|null}>
     * }
     */
    public function importar(array $filtros, ?int $usuarioId = null): array
    {
        $this->assertInterforming();

        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '512M');

        $usuarioId = $usuarioId ?: (int) (Auth::id() ?: 0);
        $cabeceras = $this->listarCabecerasAnita($filtros);

        $resumen = [
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => 0,
            'total' => count($cabeceras),
            'detalle' => [],
        ];

        foreach ($cabeceras as $cab) {
            $codigo = $this->codigoErpDesdeCabecera($cab);
            try {
                $resultado = $this->importarUno($cab, $usuarioId);
                if ($resultado['estado'] === 'creado') {
                    $resumen['creados']++;
                } elseif ($resultado['estado'] === 'actualizado') {
                    $resumen['actualizados']++;
                } elseif ($resultado['estado'] === 'omitido') {
                    $resumen['omitidos']++;
                } else {
                    $resumen['errores']++;
                }
                $resumen['detalle'][] = [
                    'codigo' => $codigo,
                    'estado' => $resultado['estado'],
                    'mensaje' => $resultado['mensaje'],
                ];
            } catch (\Throwable $e) {
                Log::error('pedido.importar_anita_interforming.error', [
                    'codigo' => $codigo,
                    'mensaje' => $e->getMessage(),
                ]);
                $resumen['errores']++;
                $resumen['detalle'][] = [
                    'codigo' => $codigo,
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        Log::info('pedido.importar_anita_interforming.resumen', [
            'creados' => $resumen['creados'],
            'actualizados' => $resumen['actualizados'],
            'omitidos' => $resumen['omitidos'],
            'errores' => $resumen['errores'],
            'total' => $resumen['total'],
        ]);

        return $resumen;
    }

    /**
     * @return array{estado: string, mensaje: string|null, pedido_id: int|null}
     */
    private function importarUno(object $cab, int $usuarioId): array
    {
        $codigo = $this->codigoErpDesdeCabecera($cab);
        $tipo = strtoupper(trim((string) ($cab->penm_tipo ?? '')));
        if (! in_array($tipo, PedidoAnitaEsquemaSupport::TIPOS_PEDIDO, true)) {
            return [
                'estado' => 'omitido',
                'mensaje' => 'Tipo '.$tipo.' no importable (solo PED/PEX).',
                'pedido_id' => null,
            ];
        }

        $cliente = $this->resolverCliente(trim((string) ($cab->penm_cliente ?? '')));
        if (! $cliente) {
            return [
                'estado' => 'error',
                'mensaje' => 'Cliente Anita '.trim((string) ($cab->penm_cliente ?? '')).' no existe en ERP.',
                'pedido_id' => null,
            ];
        }

        $pedidoExistente = PedidoInterforming::query()->where('codigo', $codigo)->first();
        if ($pedidoExistente) {
            $motivo = $this->motivoOmitirReimport($pedidoExistente);
            if ($motivo !== null) {
                return [
                    'estado' => 'omitido',
                    'mensaje' => $motivo,
                    'pedido_id' => (int) $pedidoExistente->id,
                ];
            }
        }

        $sucursal = (int) ($cab->penm_sucursal ?? 0);
        $nro = (int) ($cab->penm_nro ?? 0);
        $letra = trim((string) ($cab->penm_letra ?? 'X')) ?: 'X';

        $lineasAnita = $this->leerPendmov($tipo, $letra, $sucursal, $nro);
        if ($lineasAnita === []) {
            return [
                'estado' => 'error',
                'mensaje' => 'Sin líneas en pendmov.',
                'pedido_id' => $pedidoExistente ? (int) $pedidoExistente->id : null,
            ];
        }

        $lineasPreparadas = $this->prepararLineas($lineasAnita);
        if (isset($lineasPreparadas['error'])) {
            return [
                'estado' => 'error',
                'mensaje' => $lineasPreparadas['error'],
                'pedido_id' => $pedidoExistente ? (int) $pedidoExistente->id : null,
            ];
        }

        $estadoAnita = trim((string) ($cab->penm_estado ?? PedidoEstadosInterforming::CAB_PENTREGAR));
        $fechaPedido = $this->fechaAnitaACarbon($cab->penm_fecha ?? null);
        $fechaEntrega = $this->resolverFechaEntrega($cab, $lineasAnita, $fechaPedido);

        $condicionventaId = $this->resolverCondicionventaId($cab->penm_cond_vta ?? null);
        $vendedorId = $this->resolverVendedorId(
            $cab->penm_vendedor ?? null,
            (int) ($cliente->vendedor_id ?? 0)
        );
        $depositoId = $this->resolverDepositoId($cab->penm_deposito ?? null);
        $monedaId = $this->resolverMonedaId($cab->penm_cod_mon ?? null);
        $transporte = Transporte::query()
            ->select('id', 'codigo')
            ->where('codigo', (string) (int) ($cab->penm_expreso ?? 0))
            ->first();
        $zonavtaId = $this->resolverZonavtaId($cab->penm_zonavta ?? null);
        $mventaId = $sucursal > 0 ? max(1, $sucursal) : 1;

        $campos = [
            'fecha' => $fechaPedido,
            'fechaentrega' => $fechaEntrega,
            'cliente_id' => (int) $cliente->id,
            'condicionventa_id' => $condicionventaId,
            'vendedor_id' => $vendedorId,
            'transporte_id' => $transporte?->id,
            'mventa_id' => $mventaId,
            'estado' => $estadoAnita,
            'estadopedido' => $estadoAnita,
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'leyenda' => trim((string) ($cab->penm_leyenda ?? '')) ?: ' ',
            'descuento' => self::floatDesdeAnita($cab->penm_dto ?? 0),
            'descuentointegrado' => (string) ($cab->penm_dto_integrado ?? ' '),
            'lugarentrega' => trim((string) ($cab->penm_entrega ?? '')),
            'codigo' => $codigo,
            'zonavta_id' => $zonavtaId,
            'orden_compra' => trim((string) ($cab->penm_o_compra ?? '')) ?: null,
            'deposito_id' => $depositoId,
            'moneda_id' => $monedaId,
            'cotizacion' => self::floatDesdeAnita($cab->penm_cotizacion ?? 0) ?: 1,
            'razon_suspension' => trim((string) ($cab->penm_razon_susp ?? '')) ?: null,
            'en_stock' => trim((string) ($cab->penm_en_stock ?? '')) ?: null,
            'tipo_comprobante' => $tipo,
            'letra_comprobante' => $letra,
            'sucursal_comprobante' => $sucursal,
            'numero_comprobante' => $nro,
        ];

        return DB::transaction(function () use ($codigo, $campos, $lineasPreparadas) {
            $pedido = PedidoInterforming::query()->where('codigo', $codigo)->first();
            $esNuevo = $pedido === null;

            if ($esNuevo) {
                $pedido = PedidoInterforming::query()->create($campos);
            } else {
                unset($campos['codigo']);
                $pedido->fill($campos);
                $pedido->save();
            }

            $this->grabarLineas((int) $pedido->id, $lineasPreparadas['lineas']);

            Log::info('pedido.importar_anita_interforming.pedido', [
                'codigo' => $codigo,
                'estado' => $esNuevo ? 'creado' : 'actualizado',
                'lineas' => count($lineasPreparadas['lineas']),
            ]);

            return [
                'estado' => $esNuevo ? 'creado' : 'actualizado',
                'mensaje' => null,
                'pedido_id' => (int) $pedido->id,
            ];
        });
    }

    /**
     * @param  list<object>  $lineasAnita
     * @return array{lineas?: list<array<string, mixed>>, error?: string}
     */
    private function prepararLineas(array $lineasAnita): array
    {
        $lineas = [];
        foreach ($lineasAnita as $i => $row) {
            $skuRaw = trim((string) ($row->penv_articulo ?? ''));
            if ($skuRaw === '' || stripos($skuRaw, 'texto') === 0) {
                continue;
            }

            $articulo = $this->resolverArticulo($skuRaw);
            if (! $articulo) {
                return [
                    'error' => 'Artículo Anita '.$skuRaw.' no existe en ERP (línea '.((int) ($row->penv_orden ?? $i + 1)).').',
                ];
            }

            $cantidad = self::floatDesdeAnita($row->penv_cantidad ?? 0);
            $cantEntr = self::floatDesdeAnita($row->penv_cantentr ?? 0);
            $monedaId = $this->resolverMonedaId($row->penv_cod_mon ?? null);
            $depositoId = $this->resolverDepositoId($row->penv_deposito ?? null);
            $fechaEntLinea = $this->fechaAnitaACarbon($row->penv_fecha_ent ?? null);

            $lineas[] = [
                'articulo_id' => (int) $articulo->id,
                'numeroitem' => (int) ($row->penv_orden ?? ($i + 1)),
                'cantidad' => $cantidad,
                'cantidad_a_entregar' => self::floatDesdeAnita($row->penv_cantaentr ?? 0),
                'cantidad_entregada' => $cantEntr,
                'cantidad_facturada' => self::floatDesdeAnita($row->penv_cantfact ?? 0),
                'caja' => 0,
                'pieza' => 0,
                'kilo' => $cantidad,
                'pesada' => $cantEntr,
                'precio' => self::floatDesdeAnita($row->penv_precio ?? 0),
                'descuento' => self::floatDesdeAnita($row->penv_dto_art ?? 0),
                'listaprecio_id' => self::LISTAPRECIO_DEFAULT,
                'incluyeimpuesto' => (trim((string) ($row->penv_incl_impuesto ?? 'N')) ?: 'N'),
                'moneda_id' => $monedaId,
                'deposito_id' => $depositoId,
                'estado' => trim((string) ($row->penv_estado ?? PedidoEstadosInterforming::ITEM_PENDIENTE)) ?: PedidoEstadosInterforming::ITEM_PENDIENTE,
                'estado_cierre' => trim((string) ($row->penv_estado_cierre ?? '')) ?: null,
                'partida' => (int) ($row->penv_partida ?? 0),
                'porc_fason' => self::floatDesdeAnita($row->penv_porc_fason ?? 0),
                'porc_fason_ant' => self::floatDesdeAnita($row->penv_porc_fasonant ?? 0),
                'precio_fason' => self::floatDesdeAnita($row->penv_precio_fason ?? 0),
                'moneda_fason_id' => $this->resolverMonedaId($row->penv_cod_mon_fason ?? null),
                'ubicacion' => trim((string) ($row->penv_ubicacion ?? '')) ?: null,
                'detalle_ubicacion' => trim((string) ($row->penv_detalle ?? '')) ?: null,
                'orden_compra' => trim((string) ($row->penv_o_compra ?? '')) ?: null,
                'fechaentrega' => $fechaEntLinea,
                'descripcion_aux' => trim((string) ($row->penv_desc_aux ?? '')) ?: null,
                'observacion' => trim((string) ($row->penv_desc ?? '')) ?: null,
                'unidadmedida_id' => $articulo->unidadmedida_id ?? null,
                'descuentointegrado' => (string) ($row->penv_dto_integrado ?? ' '),
            ];
        }

        if ($lineas === []) {
            return ['error' => 'Sin líneas de artículo válidas en pendmov.'];
        }

        return ['lineas' => $lineas];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function grabarLineas(int $pedidoId, array $lineas): void
    {
        $existentes = PedidoArticuloInterforming::query()
            ->where('pedido_id', $pedidoId)
            ->get();
        $porClave = [];
        foreach ($existentes as $existente) {
            $porClave[$this->claveLinea((int) $existente->numeroitem, (int) $existente->articulo_id)] = $existente;
        }

        $idsConservar = [];
        foreach ($lineas as $campos) {
            $campos['pedido_id'] = $pedidoId;
            $clave = $this->claveLinea((int) $campos['numeroitem'], (int) $campos['articulo_id']);
            $existente = $porClave[$clave] ?? null;

            if ($existente) {
                $existente->fill($campos);
                $existente->save();
                $idsConservar[] = (int) $existente->id;
            } else {
                $nuevo = PedidoArticuloInterforming::query()->create($campos);
                $idsConservar[] = (int) $nuevo->id;
            }
        }

        $aBorrar = $existentes->filter(
            static fn (PedidoArticuloInterforming $item): bool => ! in_array((int) $item->id, $idsConservar, true)
        );
        if ($aBorrar->isNotEmpty()) {
            EloquentAuditDeleteSupport::each(
                PedidoArticuloInterforming::query()->whereIn('id', $aBorrar->pluck('id')->all())
            );
        }
    }

    private function claveLinea(int $numeroitem, int $articuloId): string
    {
        return $numeroitem.':'.$articuloId;
    }

    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string, tipo?: string}  $filtros
     * @return list<object>
     */
    private function listarCabecerasAnita(array $filtros): array
    {
        $desde = $this->fechaYmdAAnita((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = $this->fechaYmdAAnita((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $tipoFiltro = strtoupper(trim((string) ($filtros['tipo'] ?? 'TODOS')));
        if ($tipoFiltro === 'PED') {
            $whereTipo = "penm_tipo='PED'";
        } elseif ($tipoFiltro === 'PEX') {
            $whereTipo = "penm_tipo='PEX'";
        } else {
            $whereTipo = "penm_tipo IN ('PED','PEX')";
        }

        // Nunca remitos REB/REX; filtro por fecha de pedido (no entrega).
        $where = " WHERE {$whereTipo}"
            ." AND penm_fecha BETWEEN {$desde} AND {$hasta}";

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => PedidoAnitaEsquemaSupport::TABLA_CABECERA,
            'campos' => PedidoAnitaEsquemaSupport::sqlCamposPendmae(),
            'whereArmado' => $where,
        ];

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException('No se pudieron leer cabeceras Anita: '.$parsed['error_lectura']);
        }

        $filas = $parsed['filas'];
        usort($filas, static function ($a, $b) {
            $cmp = ((int) ($a->penm_sucursal ?? 0)) <=> ((int) ($b->penm_sucursal ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a->penm_nro ?? 0)) <=> ((int) ($b->penm_nro ?? 0));
        });

        return $filas;
    }

    /**
     * @return list<object>
     */
    private function leerPendmov(string $tipo, string $letra, int $sucursal, int $nro): array
    {
        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => PedidoAnitaEsquemaSupport::TABLA_MOVIMIENTO,
            'campos' => PedidoAnitaEsquemaSupport::sqlCamposPendmov(),
            'whereArmado' => " WHERE penv_tipo='".$this->esc($tipo)."' AND penv_letra='".$this->esc($letra)
                ."' AND penv_sucursal=".$sucursal.' AND penv_nro='.$nro.' ',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException('No se pudieron leer líneas Anita: '.$parsed['error_lectura']);
        }
        $rows = $parsed['filas'];

        usort($rows, static function ($a, $b) {
            return ((int) ($a->penv_orden ?? 0)) <=> ((int) ($b->penv_orden ?? 0));
        });

        return $rows;
    }

    /**
     * @param  array<int, true>|null  $idsConVenta
     */
    private function motivoOmitirReimport(PedidoInterforming $pedido, ?array $idsConVenta = null): ?string
    {
        $estado = trim((string) ($pedido->estadopedido ?? ''));
        if ($estado === PedidoEstadosInterforming::CAB_FACTURADO) {
            return 'Ya está facturado (estadopedido=3); no se pisa.';
        }
        if ($estado === PedidoEstadosInterforming::CAB_ANULADO) {
            return 'Está anulado (estadopedido=7); no se pisa.';
        }

        $pedidoId = (int) $pedido->id;
        $tieneVenta = $idsConVenta === null
            ? Venta::query()->where('pedido_id', $pedidoId)->exists()
            : isset($idsConVenta[$pedidoId]);
        if ($tieneVenta) {
            return 'Ya tiene venta vinculada; no se pisa.';
        }

        return null;
    }

    /**
     * @param  list<int>  $pedidoIds
     * @return array<int, true>
     */
    private function idsConVentaVinculada(array $pedidoIds): array
    {
        if ($pedidoIds === []) {
            return [];
        }

        $ids = Venta::query()
            ->whereIn('pedido_id', $pedidoIds)
            ->distinct()
            ->pluck('pedido_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    private function codigoErpDesdeCabecera(object $cab): string
    {
        $tipo = PedidoAnitaEsquemaSupport::normalizarTipoPedido(
            trim((string) ($cab->penm_tipo ?? 'PED'))
        );
        $letra = trim((string) ($cab->penm_letra ?? 'X')) ?: 'X';

        return $tipo.'-'.$letra.'-'
            .str_pad((string) (int) ($cab->penm_sucursal ?? 0), 5, '0', STR_PAD_LEFT).'-'
            .str_pad((string) (int) ($cab->penm_nro ?? 0), 8, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<object>  $lineasAnita
     */
    private function resolverFechaEntrega(object $cab, array $lineasAnita, ?Carbon $fechaPedido): ?Carbon
    {
        $cabEnt = $this->fechaAnitaACarbon($cab->penm_fecha_ent ?? null);
        if ($cabEnt !== null) {
            return $cabEnt;
        }

        foreach ($lineasAnita as $row) {
            $lineaEnt = $this->fechaAnitaACarbon($row->penv_fecha_ent ?? null);
            if ($lineaEnt !== null) {
                return $lineaEnt;
            }
        }

        return $fechaPedido;
    }

    private function resolverCliente(string $codigoAnita): ?Cliente
    {
        $codigo = ltrim(trim($codigoAnita), '0');
        $codigoAnita = trim($codigoAnita);
        if ($codigo === '' && $codigoAnita === '') {
            return null;
        }

        $cliente = $this->buscarClientePorCodigoAnita($codigo, $codigoAnita);
        if ($cliente) {
            return $cliente;
        }

        // Igual que artículos en Bierzo: si falta en ERP, traer de climae (Anita = fuente de verdad).
        $codigoSync = $codigoAnita !== '' ? $codigoAnita : $codigo;
        try {
            app(ClienteAnitaSyncService::class)->traerRegistroDeAnita($codigoSync);
        } catch (\Throwable $e) {
            Log::warning('Pedido IF Anita: no se pudo traer cliente '.$codigoSync.': '.$e->getMessage());

            return null;
        }

        return $this->buscarClientePorCodigoAnita($codigo, $codigoAnita);
    }

    private function buscarClientePorCodigoAnita(string $codigo, string $codigoAnita): ?Cliente
    {
        return Cliente::query()
            ->where(function ($q) use ($codigo, $codigoAnita) {
                if ($codigo !== '') {
                    $q->where('codigo', $codigo);
                }
                if ($codigoAnita !== '' && $codigoAnita !== $codigo) {
                    $q->orWhere('codigo', $codigoAnita);
                }
            })
            ->first();
    }

    private function resolverArticulo(string $skuRaw): ?\App\Models\Stock\Articulo
    {
        $skuRaw = trim($skuRaw);
        if ($skuRaw === '') {
            return null;
        }

        $articulo = ArticuloSkuMatchSupport::resolverCanonico($skuRaw);
        if ($articulo) {
            return $articulo;
        }

        $sinCeros = ltrim($skuRaw, '0');
        if ($sinCeros !== '' && $sinCeros !== $skuRaw) {
            $articulo = ArticuloSkuMatchSupport::resolverCanonico($sinCeros);
            if ($articulo) {
                return $articulo;
            }
        }

        return null;
    }

    private function resolverCondicionventaId(mixed $condAnita): int
    {
        $codigo = trim((string) $condAnita);
        if ($codigo === '' || $codigo === '0') {
            return 1;
        }

        $cond = Condicionventa::query()
            ->where('codigo', $codigo)
            ->first();
        if ($cond) {
            return (int) $cond->id;
        }

        if (ctype_digit($codigo)) {
            $porId = Condicionventa::query()->find((int) $codigo);
            if ($porId) {
                return (int) $porId->id;
            }
        }

        return 1;
    }

    private function resolverVendedorId(mixed $vendedorAnita, int $vendedorClienteId): int
    {
        $codigo = trim((string) $vendedorAnita);
        $codigoNum = (string) (int) $codigo;

        if ($codigo !== '' && $codigo !== '0') {
            $vendedor = Vendedor::query()
                ->select('id')
                ->where(function ($q) use ($codigo, $codigoNum) {
                    $q->where('codigo', $codigo);
                    if ($codigoNum !== '0' && $codigoNum !== $codigo) {
                        $q->orWhere('codigo', $codigoNum);
                    }
                })
                ->first();

            if ($vendedor) {
                return (int) $vendedor->id;
            }
        }

        if ($vendedorClienteId > 0 && Vendedor::query()->whereKey($vendedorClienteId)->exists()) {
            return $vendedorClienteId;
        }

        return 1;
    }

    private function resolverDepositoId(mixed $depositoAnita): ?int
    {
        $codigo = trim((string) $depositoAnita);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        $dep = Depmae::query()
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', (string) (int) $codigo);
            })
            ->first();

        return $dep ? (int) $dep->id : null;
    }

    private function resolverMonedaId(mixed $codMonAnita): int
    {
        $id = (int) $codMonAnita;
        if ($id <= 0) {
            return self::MONEDA_DEFAULT;
        }

        return $id;
    }

    private function resolverZonavtaId(mixed $zonavtaAnita): ?int
    {
        $codigo = (string) (int) $zonavtaAnita;
        if ($codigo === '0') {
            return null;
        }

        $zona = Zonavta::query()
            ->where(function ($q) use ($codigo, $zonavtaAnita) {
                $q->where('codigo', $codigo)
                    ->orWhere('id', (int) $zonavtaAnita);
            })
            ->first();

        return $zona ? (int) $zona->id : null;
    }

    /**
     * @param  array<string, string>  $cache
     */
    private function nombreCliente(string $codigoCliente, array &$cache): string
    {
        if ($codigoCliente === '') {
            return '';
        }
        if (array_key_exists($codigoCliente, $cache)) {
            return $cache[$codigoCliente];
        }
        $nombre = (string) (Cliente::query()->where('codigo', $codigoCliente)->value('nombre') ?? '');
        $cache[$codigoCliente] = $nombre;

        return $nombre;
    }

    private function fechaYmdAAnita(string $ymd): int
    {
        $ymd = trim($ymd);
        if ($ymd === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 0;
        }

        return (int) str_replace('-', '', $ymd);
    }

    private function fechaAnitaACarbon(mixed $fechaAnita): ?Carbon
    {
        $n = (int) $fechaAnita;
        if ($n < 19000101) {
            return null;
        }
        $s = str_pad((string) $n, 8, '0', STR_PAD_LEFT);

        return Carbon::createFromFormat('Ymd', $s)->startOfDay();
    }

    private function formatearFechaAnita(mixed $fechaAnita): string
    {
        $c = $this->fechaAnitaACarbon($fechaAnita);

        return $c ? $c->format('Y-m-d') : '';
    }

    private function esc(string $v): string
    {
        return str_replace("'", "''", $v);
    }

    private static function floatDesdeAnita(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) $valor;
    }
}
