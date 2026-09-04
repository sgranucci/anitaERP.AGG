<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\PedidoArticuloInterforming;
use App\Models\Ventas\PedidoInterforming;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Carbon\Carbon;

/**
 * Datos y totales del PDF de pedido INTERFORMING (estilo Anita a-pedido, UI ERP).
 */
final class PedidoInterformingPdfSupport
{
    /**
     * Número tipo Anita: letra + sucursal + '-' + nro (ej. X1-11669).
     */
    public static function numeroComprobante(PedidoInterforming $pedido): string
    {
        $letra = trim((string) ($pedido->letra_comprobante ?? ''));
        $sucursal = (int) ($pedido->sucursal_comprobante ?? 0);
        $nro = (int) ($pedido->numero_comprobante ?? 0);

        if ($letra !== '' && $nro > 0) {
            return $letra.$sucursal.'-'.$nro;
        }

        $codigo = trim((string) ($pedido->codigo ?? ''));

        return $codigo !== '' ? $codigo : (string) $pedido->id;
    }

    public static function etiquetaStock(?string $enStock): string
    {
        $v = strtoupper(trim((string) $enStock));
        if ($v === 'S' || $v === '1') {
            return '1. Tiene stock';
        }
        if ($v === 'N' || $v === '0') {
            return '0. No tiene stock';
        }

        return $enStock !== null && $enStock !== '' ? (string) $enStock : '—';
    }

    public static function abreviaturaUmd(?object $umd): string
    {
        if ($umd === null) {
            return '';
        }
        $abr = trim((string) ($umd->abreviatura ?? ''));
        if ($abr !== '') {
            return $abr;
        }

        return trim((string) ($umd->nombre ?? ''));
    }

    /**
     * @return list<string>
     */
    public static function lineasLugarEntrega(PedidoInterforming $pedido): array
    {
        $lineas = [];
        $lugar = trim((string) ($pedido->lugarentrega ?? ''));
        if ($lugar !== '') {
            $lineas[] = $lugar;
        }

        $entrega = null;
        if ((int) ($pedido->cliente_entrega_id ?? 0) > 0) {
            $entrega = Cliente_Entrega::query()
                ->with(['localidades', 'provincias'])
                ->find((int) $pedido->cliente_entrega_id);
        }

        if ($entrega) {
            $domicilio = trim((string) ($entrega->domicilio ?? ''));
            if ($domicilio !== '' && strcasecmp($domicilio, $lugar) !== 0) {
                $lineas[] = $domicilio;
            }
            $localidad = trim((string) ($entrega->localidades->nombre ?? ''));
            if ($localidad !== '') {
                $lineas[] = $localidad;
            }
            $provincia = trim((string) ($entrega->provincias->nombre ?? ''));
            if ($provincia !== '') {
                $lineas[] = $provincia;
            }
            $cp = trim((string) ($entrega->codigopostal ?? ''));
            if ($cp !== '') {
                $lineas[] = 'Cód. Postal: '.$cp;
            }
        }

        return array_values(array_unique($lineas));
    }

    /**
     * @return array{
     *   empresaNombre: string,
     *   empresaCuit: string,
     *   logoUri: ?string,
     *   numeroPedido: string,
     *   fecha: string,
     *   fechaEntrega: string,
     *   estado: string,
     *   clienteCodigo: string,
     *   clienteNombre: string,
     *   lugarEntregaLineas: list<string>,
     *   condicionVenta: string,
     *   stock: string,
     *   vendedor: string,
     *   transporte: string,
     *   horarioAtencion: string,
     *   ordenCompra: string,
     *   moneda: string,
     *   cotizacion: string,
     *   leyenda: string,
     *   items: list<array<string, mixed>>,
     *   totalesCantidad: list<array{cantidad: float, umd: string}>,
     *   conceptosTotales: list<array<string, mixed>>,
     *   totalSinDescuento: float
     * }
     */
    public static function armar(PedidoInterforming $pedido, ImpuestoService $impuestoService): array
    {
        $empresaId = (int) (session('empresa_id')
            ?: PuntoventaEmpresaSupport::empresaIdDesdePreferenciaFacturacion()
            ?: 0);
        $empresa = $empresaId > 0
            ? Empresa::query()->find($empresaId)
            : Empresa::query()->orderBy('id')->first();

        $empresaNombre = trim((string) ($empresa->nombre ?? config('app.empresa') ?? 'INTERFORMING'));
        $empresaCuit = trim((string) ($empresa->nroinscripcion ?? ''));
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre !== '' ? $empresaNombre : null);

        $cliente = $pedido->clientes;
        $codigoCliente = trim((string) ($cliente->codigo ?? ''));
        $nombreCliente = trim((string) ($cliente->nombre ?? ''));

        $cond = $pedido->condicionesdeventa;
        $condCodigo = trim((string) ($cond->codigo ?? ''));
        $condNombre = trim((string) ($cond->nombre ?? ''));
        $condicionVenta = trim($condCodigo.' '.$condNombre);

        $vend = $pedido->vendedores;
        $vendedor = trim(trim((string) ($vend->codigo ?? '')).' '.trim((string) ($vend->nombre ?? '')));

        $trans = $pedido->transportes;
        if ($trans) {
            $transCodigo = trim((string) ($trans->codigo ?? ''));
            $transNombre = trim((string) ($trans->nombre ?? ''));
            if ($transCodigo === '') {
                $transCodigo = '0';
            }
            if ($transNombre === '') {
                $transNombre = '- - -';
            }
            $transporte = $transCodigo.' - '.$transNombre;
        } else {
            $transporte = '0 - - -';
        }

        $itemsVista = [];
        $totalesPorUmd = [];
        $tblImpuesto = [];
        $totalSinDto = 0.0;

        foreach ($pedido->pedido_articulos as $item) {
            /** @var PedidoArticuloInterforming $item */
            $cantidad = (float) ($item->cantidad ?? 0);
            $precio = (float) ($item->precio ?? 0);
            $descuento = (float) ($item->descuento ?? 0);
            $importeLinea = round($cantidad * $precio, 2);
            $totalSinDto += $importeLinea;

            $umd = self::abreviaturaUmd($item->unidadmedida);
            if ($umd === '') {
                $umd = self::abreviaturaUmd($item->articulos->unidadesdemedidas ?? null);
            }
            $umdAlt = self::abreviaturaUmd($item->unidadmedidaAlter);
            $cantAlt = (float) ($item->cantidad_alter ?? 0);

            if ($umd !== '' && abs($cantidad) > 0.0000001) {
                $totalesPorUmd[$umd] = ($totalesPorUmd[$umd] ?? 0.0) + $cantidad;
            }
            if ($umdAlt !== '' && abs($cantAlt) > 0.0000001) {
                $totalesPorUmd[$umdAlt] = ($totalesPorUmd[$umdAlt] ?? 0.0) + $cantAlt;
            }

            $porcFason = (float) ($item->porc_fason ?? 0);
            $precioFason = (float) ($item->precio_fason ?? 0);
            $fasonNota = null;
            if ($porcFason > 0.0001) {
                $cantEnvCli = round($cantidad * ($porcFason / 100.0), 2);
                $cantIf = round($cantidad - $cantEnvCli, 2);
                $fasonNota = [
                    'precio_fason' => $precioFason,
                    'env_cli' => $cantEnvCli,
                    'interforming' => $cantIf,
                ];
            }

            $sku = trim((string) ($item->articulos->sku ?? ''));
            $descripcion = trim((string) ($item->articulos->descripcion ?? $item->descripcion_aux ?? ''));

            $itemsVista[] = [
                'numeroitem' => (int) ($item->numeroitem ?? 0),
                'sku' => $sku,
                'descripcion' => $descripcion,
                'articulo_cliente' => trim((string) ($item->articulo_cliente ?? '')),
                'fechaentrega' => self::formatearFecha($item->fechaentrega ?? null),
                'cantidad' => $cantidad,
                'umd' => $umd,
                'cantidad_alter' => $cantAlt,
                'umd_alter' => $umdAlt,
                'precio' => $precio,
                'total' => $importeLinea,
                'descuento' => $descuento,
                'porc_fason' => $porcFason,
                'precio_fason' => $precioFason,
                'fason' => $fasonNota,
                'estado' => PedidoEstadosInterforming::etiquetaItem($item->estado),
                'observacion' => trim((string) ($item->observacion ?? '')),
            ];

            $tblImpuesto[] = [
                'sku' => $sku !== '' ? $sku : ('ITEM-'.$item->id),
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descuento' => $descuento,
                'descuentointegrado' => $item->descuentointegrado ?? 0,
                'descuentofinal' => (float) ($pedido->descuento ?? 0),
                'descuentointegradofinal' => $pedido->descuentointegrado ?? 0,
                'incluyeimpuesto' => $item->incluyeimpuesto ?? '2',
                'impuesto_id' => $item->articulos->impuesto_id ?? null,
                'id' => $item->id,
            ];
        }

        $totalesCantidad = [];
        foreach ($totalesPorUmd as $umdKey => $cant) {
            $totalesCantidad[] = [
                'cantidad' => (float) $cant,
                'umd' => (string) $umdKey,
            ];
        }

        $conceptosTotales = [];
        if ($cliente && $tblImpuesto !== []) {
            $entrega = null;
            if ((int) ($pedido->cliente_entrega_id ?? 0) > 0) {
                $entrega = Cliente_Entrega::query()->find((int) $pedido->cliente_entrega_id);
            }
            $fechaFactura = self::fechaYmd($pedido->fecha ?? null) ?? date('Y-m-d');
            $datosCliente = [
                'condicioniva_id' => $cliente->condicioniva_id,
                'numerodocumento' => $cliente->numerodocumento,
                'retieneiva' => $cliente->retieneiva,
                'condicioniibb_id' => $cliente->condicioniibb_id,
                'provincia' => ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente, $entrega),
                'id' => $cliente->id,
                'empresa_id' => $empresa?->id ?? PuntoventaEmpresaSupport::empresaIdDesdePreferenciaFacturacion(),
            ];
            try {
                $conceptosTotales = $impuestoService->calculaImpuestoVenta($tblImpuesto, $datosCliente, $fechaFactura);
            } catch (\Throwable) {
                $conceptosTotales = self::conceptosDesdeImpuestosNacionales($impuestoService, $tblImpuesto);
            }
        }

        return [
            'empresaNombre' => $empresaNombre,
            'empresaCuit' => $empresaCuit,
            'logoUri' => $logo['uri'] ?? null,
            'numeroPedido' => self::numeroComprobante($pedido),
            'fecha' => self::formatearFecha($pedido->fecha ?? null),
            'fechaEntrega' => self::formatearFecha($pedido->fechaentrega ?? null),
            'estado' => PedidoEstadosInterforming::etiquetaCabecera($pedido->estadopedido),
            'clienteCodigo' => $codigoCliente,
            'clienteNombre' => $nombreCliente,
            'lugarEntregaLineas' => self::lineasLugarEntrega($pedido),
            'condicionVenta' => $condicionVenta !== '' ? $condicionVenta : '—',
            'stock' => self::etiquetaStock($pedido->en_stock ?? null),
            'vendedor' => $vendedor !== '' ? $vendedor : '—',
            'transporte' => $transporte,
            'horarioAtencion' => trim((string) ($cliente->horarioatencion ?? '')),
            'ordenCompra' => trim((string) ($pedido->orden_compra ?? '')),
            'moneda' => trim((string) ($pedido->moneda->abreviatura ?? '$')),
            'cotizacion' => (string) ($pedido->cotizacion ?? '1'),
            'leyenda' => trim((string) ($pedido->leyenda ?? '')),
            'items' => $itemsVista,
            'totalesCantidad' => $totalesCantidad,
            'conceptosTotales' => $conceptosTotales,
            'totalSinDescuento' => round($totalSinDto, 2),
        ];
    }

    /**
     * Fallback sin percepciones IIBB si el motor completo no puede correr.
     *
     * @param  array<int, array<string, mixed>>  $tblImpuesto
     * @return list<array<string, mixed>>
     */
    private static function conceptosDesdeImpuestosNacionales(ImpuestoService $impuestoService, array $tblImpuesto): array
    {
        $nac = $impuestoService->calculaImpuestosNacionalesItems($tblImpuesto, true);
        $conceptos = [];
        $conceptos[] = [
            'concepto' => 'Total del pedido SIN IVA',
            'importe' => (float) ($nac['neto_sin_iva'] ?? 0),
            'tasa' => 0,
        ];
        if ((float) ($nac['importe_descuento'] ?? 0) > 0.00001) {
            $conceptos[] = [
                'concepto' => 'Total descuento',
                'importe' => (float) $nac['importe_descuento'],
                'tasa' => 0,
            ];
        }
        foreach ($nac['filas_iva'] ?? [] as $filaIva) {
            $conceptos[] = [
                'concepto' => 'Iva '.((float) ($filaIva['tasa'] ?? 0)).'%',
                'importe' => (float) ($filaIva['importe'] ?? 0),
                'tasa' => (float) ($filaIva['tasa'] ?? 0),
            ];
        }
        $conceptos[] = [
            'concepto' => 'Total',
            'importe' => (float) ($nac['total'] ?? 0),
            'tasa' => 0,
        ];

        return $conceptos;
    }

    private static function formatearFecha($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }
        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return substr((string) $fecha, 0, 10);
        }
    }

    private static function fechaYmd($fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
