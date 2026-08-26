<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Remito_Articulo;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Zonavta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\ClienteDespachoSupport;
use App\Support\Ventas\KiloPedidoListadoFiltros;
use App\Support\Ventas\RemitoEstadosSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa remitos Anita REM R sucursal 1 (pendmae/pendmov) a ERP
 * filtrados por fecha del comprobante y transporte/reparto.
 * Solo EL BIERZO.
 */
class RemitoImportarDesdeAnitaService
{
    private const LISTAPRECIO_DEFAULT = 1;

    private const MONEDA_DEFAULT = 1;

    private const TIPO_ANITA = 'REM';

    private const LETRA_ANITA = 'R';

    private const SUCURSAL_ANITA = 1;

    public static function esElBierzo(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    public function assertElBierzo(): void
    {
        if (! self::esElBierzo()) {
            throw new RuntimeException('La importación de remitos Anita solo aplica a EL BIERZO.');
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

        $puntoventa = $this->resolverPuntoventaSucursalUno();
        $existentes = $this->indexarRemitosExistentes($cabeceras, $puntoventa);

        $clientesCache = [];
        $out = [];

        foreach ($cabeceras as $cab) {
            $codigo = $this->codigoErpDesdeCabecera($cab, $puntoventa);
            $codigoCliente = ltrim(trim((string) ($cab->penm_cliente ?? '')), '0');
            $nombreCliente = $this->nombreCliente($codigoCliente, $clientesCache);
            $existente = $existentes[$this->claveCabecera($cab)] ?? null;
            $esDespacho = ClienteDespachoSupport::esCodigoAnita($codigoCliente);
            $facturado = $existente !== null && $this->remitoYaFacturado($existente);

            $estadoErp = 'nuevo';
            if ($esDespacho) {
                $estadoErp = 'omitido_despacho';
            } elseif ($facturado) {
                $estadoErp = 'omitido_facturado';
            } elseif ($existente !== null) {
                $estadoErp = 'existe';
            }

            $out[] = [
                'codigo' => $codigo,
                'sucursal' => (int) ($cab->penm_sucursal ?? self::SUCURSAL_ANITA),
                'nro' => (int) ($cab->penm_nro ?? 0),
                'letra' => trim((string) ($cab->penm_letra ?? self::LETRA_ANITA)),
                'codigo_cliente' => $codigoCliente,
                'nombre_cliente' => $nombreCliente,
                'fecha' => $this->formatearFechaAnita($cab->penm_fecha ?? null),
                'fecha_entrega' => $this->formatearFechaAnita($cab->penm_fecha_ent ?? null),
                'reparto' => (string) (int) ($cab->penm_expreso ?? 0),
                'estado_anita' => trim((string) ($cab->penm_estado ?? '')),
                'leyenda' => trim((string) ($cab->penm_leyenda ?? '')),
                'estado_erp' => $estadoErp,
                'remito_id' => $existente ? (int) $existente->id : null,
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
        $puntoventa = $this->resolverPuntoventaSucursalUno();

        $resumen = [
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => 0,
            'total' => count($cabeceras),
            'detalle' => [],
        ];

        foreach ($cabeceras as $cab) {
            $codigo = $this->codigoErpDesdeCabecera($cab, $puntoventa);
            try {
                $resultado = $this->importarUno($cab, $usuarioId, $puntoventa);
                if ($resultado['estado'] === 'creado') {
                    $resumen['creados']++;
                } elseif ($resultado['estado'] === 'actualizado') {
                    $resumen['actualizados']++;
                } elseif (in_array($resultado['estado'], ['omitido', 'cerrado'], true)) {
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
     * @return array{estado: string, mensaje: string|null, remito_id: int|null}
     */
    private function importarUno(object $cab, int $usuarioId, ?Puntoventa $puntoventa): array
    {
        if (! $puntoventa) {
            return [
                'estado' => 'error',
                'mensaje' => 'No hay punto de venta ERP con código 1 (sucursal Anita de REM R 1).',
                'remito_id' => null,
            ];
        }

        $codigo = $this->codigoErpDesdeCabecera($cab, $puntoventa);
        $fechaAnita = (int) ($cab->penm_fecha ?? 0);
        if ($fechaAnita > 0 && $fechaAnita < 20230100) {
            return ['estado' => 'error', 'mensaje' => 'Fecha de remito anterior a 2023.', 'remito_id' => null];
        }

        $cliente = $this->resolverCliente(trim((string) ($cab->penm_cliente ?? '')));
        if (! $cliente) {
            return [
                'estado' => 'error',
                'mensaje' => 'Cliente Anita '.trim((string) ($cab->penm_cliente ?? '')).' no existe en ERP.',
                'remito_id' => null,
            ];
        }

        if (ClienteDespachoSupport::es((int) $cliente->id)) {
            return [
                'estado' => 'omitido',
                'mensaje' => 'Cliente DESPACHO: no se importa (circuito solo ERP).',
                'remito_id' => null,
            ];
        }

        $existente = $this->buscarRemitoExistente($cab, $puntoventa);
        if ($existente && $this->remitoYaFacturado($existente)) {
            return [
                'estado' => 'omitido',
                'mensaje' => 'Ya existe en ERP y está facturado; no se pisa.',
                'remito_id' => (int) $existente->id,
            ];
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

        $sucursal = (int) ($cab->penm_sucursal ?? self::SUCURSAL_ANITA);
        $mventaId = $sucursal > 5 ? 1 : max(1, $sucursal);
        $zonavtaId = $this->resolverZonavtaId($cab->penm_zonavta ?? null);
        $pedidoId = $this->resolverPedidoId($cab);
        $numero = (int) ($cab->penm_nro ?? 0);

        $campos = [
            'fecha' => $this->fechaAnitaACarbon($cab->penm_fecha ?? null),
            'fechaentrega' => $this->fechaAnitaACarbon($cab->penm_fecha_ent ?? null)
                ?? $this->fechaAnitaACarbon($cab->penm_fecha ?? null),
            'cliente_id' => (int) $cliente->id,
            'condicionventa_id' => $condicionventaId,
            'vendedor_id' => $vendedorId,
            'transporte_id' => $transporte?->id,
            'mventa_id' => $mventaId,
            'zonavta_id' => $zonavtaId,
            'estado' => 'P',
            'estadoremito' => RemitoEstadosSupport::ESTADOREMITO_PENDIENTE,
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'leyenda' => trim((string) ($cab->penm_leyenda ?? '')) ?: ' ',
            'descuento' => (float) ($cab->penm_dto ?? 0),
            'descuentointegrado' => (string) ($cab->penm_dto_integrado ?? ' '),
            'lugarentrega' => trim((string) ($cab->penm_entrega ?? '')),
            'codigo' => $codigo,
            'tipocomprobante' => self::TIPO_ANITA,
            'letra' => self::LETRA_ANITA,
            'puntoventa_id' => (int) $puntoventa->id,
            'numero' => $numero,
            'pedido_id' => $pedidoId,
            'origen' => 'anita',
        ];

        $lineasAnita = $this->leerPendmov(
            (string) ($cab->penm_tipo ?? self::TIPO_ANITA),
            (string) ($cab->penm_letra ?? self::LETRA_ANITA),
            $sucursal,
            $numero
        );

        return DB::transaction(function () use ($campos, $lineasAnita, $existente) {
            $esNuevo = $existente === null;
            $remito = $existente;

            if ($esNuevo) {
                $remito = Remito::query()->create($campos);
            } else {
                unset($campos['codigo'], $campos['tipocomprobante'], $campos['letra'], $campos['numero'], $campos['puntoventa_id']);
                if (! empty($existente->pedido_id)) {
                    unset($campos['pedido_id']);
                }
                $remito->fill($campos);
                $remito->save();
                EloquentAuditDeleteSupport::each(
                    Remito_Articulo::query()->where('remito_id', $remito->id)
                );
            }

            $this->grabarLineas((int) $remito->id, $lineasAnita);

            return [
                'estado' => $esNuevo ? 'creado' : 'actualizado',
                'mensaje' => null,
                'remito_id' => (int) $remito->id,
            ];
        });
    }

    /**
     * @param  list<object>  $lineasAnita
     */
    private function grabarLineas(int $remitoId, array $lineasAnita): void
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
            $ordenActual = $numeroitem;

            while ($i < $n && (int) ($lineasAnita[$i]->penv_orden ?? $ordenActual) === $ordenActual) {
                $r = $lineasAnita[$i];
                $pieza += (float) ($r->penv_pieza ?? 0);
                $kilosReales = (float) ($r->penv_kilos_reales ?? 0);
                $cantidad = (float) ($r->penv_cantidad ?? 0);
                $kilo += $kilosReales > 0 ? $kilosReales : $cantidad;
                $i++;
            }

            $uxenv = (float) ($articulo->unidadesxenvase ?? 0);
            $caja = ($uxenv > 0 && $pieza > 0) ? round($pieza / $uxenv, 6) : 0.0;

            Remito_Articulo::query()->create([
                'remito_id' => $remitoId,
                'articulo_id' => (int) $articulo->id,
                'numeroitem' => $numeroitem,
                'caja' => $caja,
                'pieza' => $pieza,
                'kilo' => $kilo,
                'precio' => $precio,
                'listaprecio_id' => self::LISTAPRECIO_DEFAULT,
                'incluyeimpuesto' => $incluyeimpuesto,
                'moneda_id' => $monedaId,
                'descuento' => $descuento,
                'observacion' => $observacion !== '' ? $observacion : null,
                'unidadmedida_id' => $articulo->unidadmedida_id ?? null,
                'estado' => RemitoEstadosSupport::LINEA_PENDIENTE,
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

        $where = " WHERE penm_tipo='".self::TIPO_ANITA."' AND penm_letra='".self::LETRA_ANITA."'"
            .' AND penm_sucursal='.self::SUCURSAL_ANITA
            ." AND penm_fecha BETWEEN {$desde} AND {$hasta}"
            ." AND penm_ref_tipo <> 'Z  '";
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
                penm_dto_integrado, penm_ref_tipo, penm_ref_letra, penm_ref_sucursal, penm_ref_nro
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
     * @param  list<object>  $cabeceras
     * @return array<string, Remito>
     */
    private function indexarRemitosExistentes(array $cabeceras, ?Puntoventa $puntoventa): array
    {
        $numeros = [];
        $codigos = [];
        foreach ($cabeceras as $cab) {
            $nro = (int) ($cab->penm_nro ?? 0);
            if ($nro > 0) {
                $numeros[] = $nro;
            }
            $codigos[] = $this->codigoErpDesdeCabecera($cab, $puntoventa);
        }
        $numeros = array_values(array_unique($numeros));
        $codigos = array_values(array_unique(array_filter($codigos)));

        if ($numeros === [] && $codigos === []) {
            return [];
        }

        $query = Remito::query()->select([
            'id', 'codigo', 'numero', 'puntoventa_id', 'venta_id', 'estadoremito', 'estado',
        ]);
        $query->where(function ($q) use ($codigos, $numeros, $puntoventa) {
            if ($codigos !== []) {
                $q->whereIn('codigo', $codigos);
            }
            if ($numeros !== [] && $puntoventa) {
                $q->orWhere(function ($q2) use ($numeros, $puntoventa) {
                    $q2->where('tipocomprobante', self::TIPO_ANITA)
                        ->where('letra', self::LETRA_ANITA)
                        ->where('puntoventa_id', $puntoventa->id)
                        ->whereIn('numero', $numeros);
                });
            }
        });

        $index = [];
        foreach ($query->get() as $remito) {
            $index['n:'.(int) $remito->numero] = $remito;
            $index['c:'.trim((string) $remito->codigo)] = $remito;
        }

        $out = [];
        foreach ($cabeceras as $cab) {
            $clave = $this->claveCabecera($cab);
            $nro = (int) ($cab->penm_nro ?? 0);
            $codigo = $this->codigoErpDesdeCabecera($cab, $puntoventa);
            $out[$clave] = $index['n:'.$nro] ?? $index['c:'.$codigo] ?? null;
        }

        return $out;
    }

    private function buscarRemitoExistente(object $cab, Puntoventa $puntoventa): ?Remito
    {
        $numero = (int) ($cab->penm_nro ?? 0);
        $codigo = $this->codigoErpDesdeCabecera($cab, $puntoventa);

        return Remito::query()
            ->where(function ($q) use ($codigo, $numero, $puntoventa) {
                $q->where('codigo', $codigo);
                if ($numero > 0) {
                    $q->orWhere(function ($q2) use ($numero, $puntoventa) {
                        $q2->where('tipocomprobante', self::TIPO_ANITA)
                            ->where('letra', self::LETRA_ANITA)
                            ->where('puntoventa_id', $puntoventa->id)
                            ->where('numero', $numero);
                    });
                }
            })
            ->first();
    }

    private function remitoYaFacturado(Remito $remito): bool
    {
        if (! empty($remito->venta_id)) {
            return true;
        }

        $estado = (string) ($remito->estadoremito ?? '');

        return in_array($estado, [
            RemitoEstadosSupport::ESTADOREMITO_FACTURADO,
            RemitoEstadosSupport::ESTADOREMITO_ANULADO,
        ], true);
    }

    private function resolverPuntoventaSucursalUno(): ?Puntoventa
    {
        $codigoNorm = Puntoventa::normalizarCodigoArca((string) self::SUCURSAL_ANITA);
        $variantes = array_values(array_unique(array_filter([
            (string) self::SUCURSAL_ANITA,
            $codigoNorm,
        ])));

        return Puntoventa::query()
            ->whereIn('codigo', $variantes)
            ->orderBy('id')
            ->first();
    }

    private function codigoErpDesdeCabecera(object $cab, ?Puntoventa $puntoventa): string
    {
        $pvCodigo = $puntoventa
            ? (string) $puntoventa->codigo
            : (string) (int) ($cab->penm_sucursal ?? self::SUCURSAL_ANITA);

        return self::TIPO_ANITA.' '.self::LETRA_ANITA.' '.$pvCodigo.'-'
            .(int) ($cab->penm_nro ?? 0);
    }

    private function claveCabecera(object $cab): string
    {
        return (int) ($cab->penm_sucursal ?? self::SUCURSAL_ANITA).'-'.(int) ($cab->penm_nro ?? 0);
    }

    private function resolverPedidoId(object $cab): ?int
    {
        $refTipo = strtoupper(substr(trim((string) ($cab->penm_ref_tipo ?? '')), 0, 3));
        if ($refTipo !== 'PED') {
            return null;
        }

        $letra = trim((string) ($cab->penm_ref_letra ?? 'X')) ?: 'X';
        $sucursal = (int) ($cab->penm_ref_sucursal ?? 1);
        $nro = (int) ($cab->penm_ref_nro ?? 0);
        if ($nro <= 0) {
            return null;
        }

        $codigo = 'PED-'.$letra.'-'
            .str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $nro, 8, '0', STR_PAD_LEFT);

        $id = (int) (Pedido::query()->where('codigo', $codigo)->value('id') ?? 0);

        return $id > 0 ? $id : null;
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
}
