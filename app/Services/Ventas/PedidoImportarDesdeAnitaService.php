<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Pedido_Articulo;
use App\Models\Ventas\Pedido_Articulo_Caja;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Zonavta;
use App\Models\Ventas\Venta;
use App\Support\Ventas\ClienteDespachoSupport;
use App\Support\Ventas\KiloPedidoListadoFiltros;
use App\Support\Ventas\PedidoDespachoAnitaCierreSupport;
use App\Support\Ventas\PedidoEstadoErpSupport;
use App\Support\Ventas\PedidoFacturacionExclusivaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa pedidos Anita (pendmae/pendmov) a ERP filtrados por fecha de entrega y transporte/reparto.
 * Solo EL BIERZO (letra X en Anita; penv_observacion no existe en este esquema).
 */
class PedidoImportarDesdeAnitaService
{
    private const LISTAPRECIO_DEFAULT = 1;

    private const MONEDA_DEFAULT = 1;

    public static function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }

    public function assertElBierzo(): void
    {
        if (! self::esElBierzo()) {
            throw new RuntimeException('La importación de pedidos Anita solo aplica a EL BIERZO.');
        }
    }

    /**
     * @param  array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}  $filtros
     * @return list<array<string, mixed>>
     */
    public function listarPreview(array $filtros): array
    {
        $this->assertElBierzo();

        $cabeceras = $this->listarCabecerasAnita($filtros);
        if ($cabeceras === []) {
            return [];
        }

        $codigos = [];
        foreach ($cabeceras as $cab) {
            $codigos[] = $this->codigoErpDesdeCabecera($cab);
        }

        $existentes = Pedido::query()
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo', 'estado', 'estadopedido'])
            ->keyBy('codigo');

        $idsConFactura = $this->idsConFacturaEmitida(
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
            $esDespacho = ClienteDespachoSupport::esCodigoAnita($codigoCliente);
            $estadoErp = 'nuevo';
            if ($esDespacho) {
                $estadoErp = 'omitido_despacho';
            } elseif ($existe && $this->motivoOmitirReimport($existente, $idsConFactura) !== null) {
                $estadoErp = 'omitido_facturado';
            } elseif ($existe) {
                $estadoErp = 'existe';
            }

            $out[] = [
                'codigo' => $codigo,
                'sucursal' => (int) ($cab->penm_sucursal ?? 0),
                'nro' => (int) ($cab->penm_nro ?? 0),
                'letra' => trim((string) ($cab->penm_letra ?? 'X')),
                'codigo_cliente' => $codigoCliente,
                'nombre_cliente' => $nombreCliente,
                'fecha' => $this->formatearFechaAnita($cab->penm_fecha ?? null),
                'fecha_entrega' => $this->formatearFechaAnita($cab->penm_fecha_ent ?? null),
                'reparto' => (string) (int) ($cab->penm_expreso ?? 0),
                'estado_anita' => trim((string) ($cab->penm_estado ?? '')),
                'leyenda' => trim((string) ($cab->penm_leyenda ?? '')),
                'estado_erp' => $estadoErp,
                'pedido_id' => $existe ? (int) $existente->id : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}  $filtros
     * @return array{
     *   creados: int,
     *   actualizados: int,
     *   omitidos: int,
     *   cerrados: int,
     *   errores: int,
     *   total: int,
     *   detalle: list<array{codigo: string, estado: string, mensaje: string|null}>
     * }
     */
    public function importar(array $filtros, ?int $usuarioId = null): array
    {
        $this->assertElBierzo();

        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '512M');

        $usuarioId = $usuarioId ?: (int) (Auth::id() ?: 0);
        $cabeceras = $this->listarCabecerasAnita($filtros);

        $resumen = [
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'cerrados' => 0,
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
                } elseif ($resultado['estado'] === 'cerrado') {
                    $resumen['cerrados']++;
                } else {
                    $resumen['errores']++;
                }
                $resumen['detalle'][] = [
                    'codigo' => $codigo,
                    'estado' => $resultado['estado'],
                    'mensaje' => $resultado['mensaje'],
                ];
            } catch (\Throwable $e) {
                $resumen['errores']++;
                $resumen['detalle'][] = [
                    'codigo' => $codigo,
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return $resumen;
    }

    /**
     * @return array{estado: string, mensaje: string|null, pedido_id: int|null}
     */
    private function importarUno(object $cab, int $usuarioId): array
    {
        $codigo = $this->codigoErpDesdeCabecera($cab);
        $fechaAnita = (int) ($cab->penm_fecha ?? 0);
        if ($fechaAnita > 0 && $fechaAnita < 20230100) {
            return ['estado' => 'error', 'mensaje' => 'Fecha de pedido anterior a 2023.', 'pedido_id' => null];
        }

        $cliente = $this->resolverCliente(trim((string) ($cab->penm_cliente ?? '')));
        if (! $cliente) {
            return [
                'estado' => 'error',
                'mensaje' => 'Cliente Anita '.trim((string) ($cab->penm_cliente ?? '')).' no existe en ERP.',
                'pedido_id' => null,
            ];
        }

        if (ClienteDespachoSupport::es((int) $cliente->id)) {
            $cierre = PedidoDespachoAnitaCierreSupport::cerrarPorClave(
                PedidoDespachoAnitaCierreSupport::claveDesdeCabeceraAnita($cab)
            );
            if (! ($cierre['ok'] ?? false)) {
                return [
                    'estado' => 'error',
                    'mensaje' => $cierre['mensaje'] !== ''
                        ? $cierre['mensaje']
                        : 'No se pudo cerrar el pedido DESPACHO en Anita.',
                    'pedido_id' => null,
                ];
            }

            return [
                'estado' => 'cerrado',
                'mensaje' => 'Cliente DESPACHO: no se importa (circuito solo ERP). '
                    .(($cierre['cerrado'] ?? false)
                        ? 'Pedido cerrado en Anita.'
                        : (($cierre['existia'] ?? false)
                            ? 'Ya estaba cerrado en Anita.'
                            : 'No había pedido abierto en Anita.')),
                'pedido_id' => null,
            ];
        }

        $pedidoExistente = Pedido::query()->where('codigo', $codigo)->first();
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

        $condicionventaId = (int) ($cab->penm_cond_vta ?? 0);
        if ($condicionventaId === 0) {
            $condicionventaId = 1;
        }
        if ($condicionventaId === 1) {
            $condicionventaId = 3;
        }

        $vendedorId = $this->resolverVendedorId(
            $cab->penm_vendedor ?? null,
            (int) ($cliente->vendedor_id ?? 0)
        );

        $transporte = Transporte::query()
            ->select('id', 'codigo')
            ->where('codigo', (string) (int) ($cab->penm_expreso ?? 0))
            ->first();

        $sucursal = (int) ($cab->penm_sucursal ?? 0);
        $mventaId = $sucursal > 5 ? 1 : max(1, $sucursal);

        $zonavtaId = $this->resolverZonavtaId($cab->penm_zonavta ?? null);

        // Pedidos nuevos (o pendientes sin factura) entran como pendientes para pesada/facturar en ERP.
        // Los que ya tienen FAC / Facturado / Transferido / Anulado no llegan acá.
        $mapeoEstado = PedidoEstadoErpSupport::cabeceraPendiente();

        $campos = [
            'fecha' => $this->fechaAnitaACarbon($cab->penm_fecha ?? null),
            'fechaentrega' => $this->fechaAnitaACarbon($cab->penm_fecha_ent ?? null),
            'cliente_id' => (int) $cliente->id,
            'condicionventa_id' => $condicionventaId,
            'vendedor_id' => $vendedorId,
            'transporte_id' => $transporte?->id,
            'mventa_id' => $mventaId,
            'estado' => $mapeoEstado['estado'],
            'estadopedido' => $mapeoEstado['estadopedido'],
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'leyenda' => trim((string) ($cab->penm_leyenda ?? '')) ?: ' ',
            'descuento' => (float) ($cab->penm_dto ?? 0),
            'descuentointegrado' => (string) ($cab->penm_dto_integrado ?? ' '),
            'lugarentrega' => trim((string) ($cab->penm_entrega ?? '')),
            'codigo' => $codigo,
            'zonavta_id' => $zonavtaId,
            'caja_reales' => self::enteroDesdeAnita($cab->penm_caja_reales ?? null),
        ];

        $lineasAnita = $this->leerPendmov(
            (string) ($cab->penm_tipo ?? 'PED'),
            (string) ($cab->penm_letra ?? 'X'),
            $sucursal,
            (int) ($cab->penm_nro ?? 0)
        );

        return DB::transaction(function () use ($codigo, $campos, $lineasAnita) {
            $pedido = Pedido::query()->where('codigo', $codigo)->first();
            $esNuevo = $pedido === null;

            if ($esNuevo) {
                $pedido = Pedido::query()->create($campos);
            } else {
                unset($campos['codigo']);
                $pedido->fill($campos);
                $pedido->save();
                Pedido_Articulo_Caja::query()->where('pedido_id', $pedido->id)->delete();
                Pedido_Articulo::query()->where('pedido_id', $pedido->id)->delete();
            }

            $this->grabarLineas((int) $pedido->id, $lineasAnita);

            return [
                'estado' => $esNuevo ? 'creado' : 'actualizado',
                'mensaje' => null,
                'pedido_id' => (int) $pedido->id,
            ];
        });
    }

    /**
     * @param  list<object>  $lineasAnita
     */
    private function grabarLineas(int $pedidoId, array $lineasAnita): void
    {
        if ($lineasAnita === []) {
            return;
        }

        $i = 0;
        $n = count($lineasAnita);
        while ($i < $n) {
            $row = $lineasAnita[$i];
            $skuRaw = trim((string) ($row->penv_articulo ?? ''));
            if ($skuRaw === '' || stripos($skuRaw, 'texto') === 0) {
                $i++;
                continue;
            }

            $articulo = $this->resolverArticulo($skuRaw);
            if (! $articulo) {
                $i++;
                continue;
            }

            $numeroitem = (int) ($row->penv_orden ?? ($i + 1));
            $precio = (float) ($row->penv_precio ?? 0);
            $descuento = (float) ($row->penv_dto_art ?? 0);
            $incluyeimpuesto = trim((string) ($row->penv_incl_impuesto ?? 'N')) ?: 'N';
            $monedaId = (int) ($row->penv_cod_mon ?? 0);
            if ($monedaId <= 0) {
                $monedaId = self::MONEDA_DEFAULT;
            }
            $observacion = trim((string) ($row->penv_observacion ?? ''));

            $pieza = 0.0;
            $kilo = 0.0;
            $pesada = 0.0;
            $ordenActual = $numeroitem;

            while ($i < $n && (int) ($lineasAnita[$i]->penv_orden ?? $ordenActual) === $ordenActual) {
                $r = $lineasAnita[$i];
                $pieza += (float) ($r->penv_pieza ?? 0);
                $kilo += (float) ($r->penv_cantidad ?? 0);
                $pesada += (float) ($r->penv_kilos_reales ?? 0);
                $i++;
            }

            $uxenv = (float) ($articulo->unidadesxenvase ?? 0);
            $caja = ($uxenv > 0 && $pieza > 0) ? round($pieza / $uxenv, 6) : 0.0;

            Pedido_Articulo::query()->create([
                'pedido_id' => $pedidoId,
                'articulo_id' => (int) $articulo->id,
                'numeroitem' => $numeroitem,
                'caja' => $caja,
                'pieza' => $pieza,
                'kilo' => $kilo,
                'pesada' => $pesada,
                'precio' => $precio,
                'listaprecio_id' => self::LISTAPRECIO_DEFAULT,
                'incluyeimpuesto' => $incluyeimpuesto,
                'moneda_id' => $monedaId,
                'descuento' => $descuento,
                'observacion' => $observacion !== '' ? $observacion : null,
                'unidadmedida_id' => $articulo->unidadmedida_id ?? null,
                'estado' => 'P',
            ]);
        }
    }

    /**
     * @param  array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}  $filtros
     * @return list<object>
     */
    private function listarCabecerasAnita(array $filtros): array
    {
        $desde = $this->fechaYmdAAnita((string) ($filtros['fecha_entrega_desde'] ?? ''));
        $hasta = $this->fechaYmdAAnita((string) ($filtros['fecha_entrega_hasta'] ?? ''));
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        // El Bierzo graba pedidos Anita con letra X (no A).
        $where = " WHERE penm_tipo='PED' AND penm_letra='X'"
            ." AND penm_fecha_ent BETWEEN {$desde} AND {$hasta}";
        $where .= $this->whereRepartoAnita((string) ($filtros['filtro_reparto'] ?? ''));

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => '
                penm_cliente, penm_tipo, penm_letra, penm_sucursal, penm_nro,
                penm_fecha, penm_fecha_ent, penm_cond_vta, penm_vendedor, penm_zonavta,
                penm_entrega, penm_dto, penm_expreso, penm_estado, penm_leyenda,
                penm_dto_integrado, penm_caja_reales
            ',
            'whereArmado' => $where,
        ];

        $rows = json_decode($api->apiCall($data));

        return is_array($rows) ? $rows : [];
    }

    private function whereRepartoAnita(string $filtroReparto): string
    {
        $filtroReparto = trim($filtroReparto);
        if ($filtroReparto === '') {
            return '';
        }

        [$desde, $hasta] = KiloPedidoListadoFiltros::normalizarRangoRepartos($filtroReparto, '');

        if (KiloPedidoListadoFiltros::esListaRepartos($desde)) {
            $codigos = KiloPedidoListadoFiltros::parseListaRepartos($desde);
            $nums = [];
            foreach ($codigos as $c) {
                $n = KiloPedidoListadoFiltros::codigoRepartoANumero($c);
                if ($n !== null) {
                    $nums[] = $n;
                }
            }
            $nums = array_values(array_unique($nums));
            if ($nums === []) {
                return '';
            }

            return ' AND penm_expreso IN ('.implode(',', $nums).')';
        }

        $desdeNum = KiloPedidoListadoFiltros::codigoRepartoANumero($desde);
        $hastaNum = KiloPedidoListadoFiltros::codigoRepartoANumero($hasta);

        if ($desdeNum === null) {
            return '';
        }

        if ($hasta === '' || $hastaNum === null || KiloPedidoListadoFiltros::esRepartoHastaAbierto($hasta)) {
            return ' AND penm_expreso = '.$desdeNum;
        }

        if ($desdeNum > $hastaNum) {
            [$desdeNum, $hastaNum] = [$hastaNum, $desdeNum];
        }

        return " AND penm_expreso BETWEEN {$desdeNum} AND {$hastaNum}";
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
            'tabla' => 'pendmov',
            'campos' => '
                penv_cliente, penv_tipo, penv_letra, penv_sucursal, penv_nro, penv_orden,
                penv_articulo, penv_cantidad, penv_precio, penv_dto_art, penv_incl_impuesto,
                penv_cod_mon, penv_pieza, penv_kilos_reales, penv_piezas_reales,
                penv_reparto, penv_fl_bonif
            ',
            'whereArmado' => " WHERE penv_tipo='".$this->esc($tipo)."' AND penv_letra='".$this->esc($letra)
                ."' AND penv_sucursal=".$sucursal.' AND penv_nro='.$nro.' ',
        ];
        $rows = json_decode($api->apiCall($data));
        if (! is_array($rows)) {
            return [];
        }

        usort($rows, static function ($a, $b) {
            return ((int) ($a->penv_orden ?? 0)) <=> ((int) ($b->penv_orden ?? 0));
        });

        return $rows;
    }

    /**
     * No reescribir un pedido que el ERP ya procesó (factura, transferido o anulado).
     *
     * @param  array<int, true>|null  $idsConFactura  mapa pedido_id => true; null consulta la venta
     */
    private function motivoOmitirReimport(Pedido $pedido, ?array $idsConFactura = null): ?string
    {
        $estado = PedidoEstadoErpSupport::normalizarEstadoCabecera(
            $pedido->estado ?? null,
            $pedido->estadopedido ?? null
        );
        if ($estado === PedidoEstadoErpSupport::FACTURADO) {
            return 'Ya está facturado en ERP; no se pisa.';
        }
        if ($estado === PedidoEstadoErpSupport::TRANSFERIDO) {
            return 'Ya fue transferido al despacho; no se pisa.';
        }
        if ($estado === PedidoEstadoErpSupport::ANULADO) {
            return 'Está anulado en ERP; no se pisa.';
        }

        $pedidoId = (int) $pedido->id;
        $tieneFactura = $idsConFactura === null
            ? PedidoFacturacionExclusivaSupport::tieneFacturaEmitida($pedidoId)
            : isset($idsConFactura[$pedidoId]);
        if ($tieneFactura) {
            return 'Ya tiene factura en ERP; no se pisa.';
        }

        return null;
    }

    /**
     * @param  list<int>  $pedidoIds
     * @return array<int, true>
     */
    private function idsConFacturaEmitida(array $pedidoIds): array
    {
        if ($pedidoIds === []) {
            return [];
        }

        $ids = Venta::query()
            ->whereIn('pedido_id', $pedidoIds)
            ->where('codigo', 'like', 'FAC%')
            ->distinct()
            ->pluck('pedido_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    private function codigoErpDesdeCabecera(object $cab): string
    {
        $tipo = trim((string) ($cab->penm_tipo ?? 'PED')) ?: 'PED';
        $letra = trim((string) ($cab->penm_letra ?? 'X')) ?: 'X';

        return $tipo.'-'.$letra.'-'
            .str_pad((string) (int) ($cab->penm_sucursal ?? 0), 5, '0', STR_PAD_LEFT).'-'
            .str_pad((string) (int) ($cab->penm_nro ?? 0), 8, '0', STR_PAD_LEFT);
    }

    private function resolverCliente(string $codigoAnita): ?Cliente
    {
        $codigo = ltrim(trim($codigoAnita), '0');
        $codigoAnita = trim($codigoAnita);
        if ($codigo === '' && $codigoAnita === '') {
            return null;
        }

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

    private function resolverArticulo(string $skuRaw): ?Articulo
    {
        $sku = ltrim(trim($skuRaw), '0');
        $skuPad = trim($skuRaw);
        $articulo = Articulo::query()
            ->select('id', 'sku', 'unidadesxenvase', 'unidadmedida_id')
            ->where(function ($q) use ($sku, $skuPad) {
                if ($sku !== '') {
                    $q->where('sku', $sku);
                }
                if ($skuPad !== '' && $skuPad !== $sku) {
                    $q->orWhere('sku', $skuPad);
                }
            })
            ->first();

        if ($articulo) {
            return $articulo;
        }

        $modelo = new Articulo();
        $modelo->traerRegistroDeAnita($skuPad !== '' ? $skuPad : $sku, true);

        return Articulo::query()
            ->select('id', 'sku', 'unidadesxenvase', 'unidadmedida_id')
            ->where(function ($q) use ($sku, $skuPad) {
                if ($sku !== '') {
                    $q->where('sku', $sku);
                }
                if ($skuPad !== '' && $skuPad !== $sku) {
                    $q->orWhere('sku', $skuPad);
                }
            })
            ->first();
    }

    private function resolverVendedorId(mixed $vendedorAnita, int $vendedorClienteId): int
    {
        $codigo = trim((string) $vendedorAnita);
        $codigoNum = (string) (int) $codigo;
        $idAnita = (int) $codigo;

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

            // Fallback: Anita a veces alinea id ERP con el código numérico.
            if ($idAnita > 0 && Vendedor::query()->whereKey($idAnita)->exists()) {
                return $idAnita;
            }
        }

        if ($vendedorClienteId > 0 && Vendedor::query()->whereKey($vendedorClienteId)->exists()) {
            return $vendedorClienteId;
        }

        return 1;
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

    /**
     * Anita Informix puede devolver enteros en notación científica (1.0, 2.2e+01).
     * No hay penv_cajas_reales en pendmov; el dato de bultos finales es penm_caja_reales.
     */
    private static function enteroDesdeAnita(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return max(0, (int) round((float) $valor));
    }
}
