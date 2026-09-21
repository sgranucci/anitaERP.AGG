<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\FacturacionServiceFerli;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalAsientoMedioSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioIvaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalReceptorSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalSplitFacNcSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Orquesta emisión FAC (+ NC) del POS Local.
 * Reutiliza FacturacionService; sin CAEA; stock Local vía FacturacionServiceFerli cuando aplica.
 */
final class FacturacionLocalEmisionService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly FacturacionLocalPreflightService $preflightService,
        private readonly FacturacionLocalCobranzaService $cobranzaService,
        private readonly FacturacionLocalTurnoService $turnoService,
        private readonly FacturacionLocalValeService $valeService,
    ) {
    }

    /**
     * @param  array{
     *   lineas:list<array<string,mixed>>,
     *   medios_pago?:list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>,
     *   cliente_id?:int|null,
     *   receptor?:array<string,mixed>,
     *   descuentopie?:float,
     *   descuentoimportepie?:float,
     *   excedente_accion?:string|null,
     *   vale_aplicar_id?:int|null,
     *   vale_aplicar_importe?:float|null,
     *   es_ticket_regalo?:bool,
     *   identificador_pc?:string|null
     * }  $input
     * @return array{ok:bool,error?:string,errores?:list<string>,venta_id?:int,venta_nc_id?:int,vale_id?:int,cae?:string,emision_id?:int}
     */
    public function emitir(LocalVenta $local, array $input): array
    {
        $turno = $this->turnoService->turnoAbierto(
            (int) $local->id,
            $input['identificador_pc'] ?? null
        );

        $lineas = $input['lineas'] ?? [];
        $split = FacturacionLocalSplitFacNcSupport::partir($lineas);
        $medios = $input['medios_pago'] ?? [];
        $esRegalo = ! empty($input['es_ticket_regalo']);

        // Saldo negativo (NC > FAC): obligatorio NC completa afuera + factura nueva.
        if ((float) $split['neto'] < -0.009) {
            $msg = 'Saldo negativo no permitido. Emita una nota de crédito completa de la factura original '
                .'y luego realice la factura nuevamente.';

            return ['ok' => false, 'error' => $msg, 'errores' => [$msg]];
        }

        // Solo devoluciones en el carrito: no hay FAC que anclar.
        if ($split['fac'] === [] && $split['tiene_nc']) {
            $msg = 'No se puede emitir solo devoluciones desde el POS. '
                .'Genere una nota de crédito completa desde Facturas Local sobre la factura original.';

            return ['ok' => false, 'error' => $msg, 'errores' => [$msg]];
        }

        // Neto 0 (cambio equivalente): ARCA exige mínimo $0,01.
        $forzarMinimoArca = ! $esRegalo
            && abs((float) $split['neto']) < 0.009
            && $split['fac'] !== [];
        if ($forzarMinimoArca) {
            $split = $this->aplicarImporteMinimoArcaEnFac($split);
        }

        $totalPagar = $esRegalo
            ? 0.
            : ($forzarMinimoArca
                ? FacturacionLocalAsientoMedioSupport::IMPORTE_MINIMO_ARCA
                : max(0., (float) $split['neto']));

        try {
            $receptorResuelto = FacturacionLocalReceptorSupport::resolver($input);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
        }

        $errores = $this->preflightService->erroresAntesDeEmitir(
            $local,
            $turno,
            $lineas,
            $medios,
            $totalPagar,
            (bool) $receptorResuelto['tiene_datos_cliente'],
            false,
            $esRegalo,
        );
        if ($errores !== []) {
            return ['ok' => false, 'errores' => $errores, 'error' => $errores[0]];
        }

        try {
            return DB::transaction(function () use (
                $local,
                $turno,
                $input,
                $split,
                $medios,
                $esRegalo,
                $totalPagar,
                $receptorResuelto,
            ) {
                $ventaFac = null;
                $ventaNc = null;
                $valeId = null;
                $caePendientes = [];

                if ($split['fac'] !== []) {
                    $payloadFac = $this->armarPayload(
                        $local,
                        $split['fac'],
                        $input,
                        false,
                        $esRegalo,
                        $receptorResuelto,
                        $medios,
                    );
                    $resultadoFac = $this->facturacionService->generaComprobanteGeneral($payloadFac);
                    if (! is_array($resultadoFac) || ! empty($resultadoFac['error'])) {
                        $msg = trim((string) ($resultadoFac['mensaje'] ?? $resultadoFac['error'] ?? 'Error al emitir factura'));

                        throw new InvalidArgumentException($msg);
                    }
                    $ventaFac = Venta::query()->find((int) ($resultadoFac['venta_id'] ?? 0));
                    if (! $ventaFac) {
                        throw new InvalidArgumentException('No se obtuvo la venta emitida.');
                    }
                    if (is_array($resultadoFac['cae_pendiente'] ?? null)) {
                        $caePendientes[] = $resultadoFac['cae_pendiente'];
                    }
                    $this->grabarStockLocalSiCorresponde($local, $ventaFac, $split['fac'], false);
                }

                if ($split['tiene_nc']) {
                    $payloadNc = $this->armarPayload($local, $split['nc'], $input, true, false, $receptorResuelto);
                    if ($ventaFac) {
                        // venta_id = FAC origen → asiento invertido + CbteAsoc ARCA.
                        $payloadNc['venta_id'] = $ventaFac->id;
                        $payloadNc['comprobanteasociado_id'] = $ventaFac->id;
                        $payloadNc['venta_id_asociada'] = $ventaFac->id;
                    }
                    $resultadoNc = $this->facturacionService->generaComprobanteGeneral($payloadNc);
                    if (! is_array($resultadoNc) || ! empty($resultadoNc['error'])) {
                        $msg = trim((string) ($resultadoNc['mensaje'] ?? $resultadoNc['error'] ?? 'Error al emitir NC'));

                        throw new InvalidArgumentException($msg);
                    }
                    $ventaNc = Venta::query()->find((int) ($resultadoNc['venta_id'] ?? 0));
                    if ($ventaNc) {
                        $this->grabarStockLocalSiCorresponde($local, $ventaNc, $split['nc'], true);
                    }
                    if (is_array($resultadoNc['cae_pendiente'] ?? null)) {
                        $caePendientes[] = $resultadoNc['cae_pendiente'];
                    }
                }

                $ventaPrincipal = $ventaFac ?? $ventaNc;
                if (! $ventaPrincipal) {
                    throw new InvalidArgumentException('No hay comprobante para grabar.');
                }

                // Aplicar vale existente
                $valeAplicarId = (int) ($input['vale_aplicar_id'] ?? 0);
                $valeAplicarImporte = (float) ($input['vale_aplicar_importe'] ?? 0);
                if ($valeAplicarId > 0 && $valeAplicarImporte > 0 && $ventaFac) {
                    $vale = \App\Models\Ventas\ValeClienteLocal::query()->findOrFail($valeAplicarId);
                    $this->valeService->aplicar($vale, $valeAplicarImporte, $ventaFac);
                }

                // Cobranza sobre neto a favor de la casa (ANTES de pedir CAE a ARCA).
                // Incluye el mínimo ARCA $0,01 cuando el carrito netea en cero.
                if (! $esRegalo && $totalPagar > 0.009 && $medios !== []) {
                    $this->cobranzaService->registrar($ventaPrincipal, $local, $medios, false);
                }

                // Excedente de medios → vale (solo a favor del cliente; nunca por NC > FAC).
                $excedenteAccion = (string) ($input['excedente_accion'] ?? '');
                $sumaMedios = 0.;
                foreach ($medios as $m) {
                    $sumaMedios += (float) ($m['monto'] ?? 0);
                }
                $excedente = round($sumaMedios - $totalPagar, 2);
                if ($excedente > 0.05 && $excedenteAccion === 'vale' && $ventaFac) {
                    $vale = $this->valeService->crearVale(
                        $local,
                        $excedente,
                        $ventaFac,
                        $turno?->id,
                        [
                            'cliente_id' => $receptorResuelto['cliente_id'] ?? null,
                            'tipo_documento' => $receptorResuelto['arca_receptor']['tipodoc'] ?? null,
                            'nro_documento' => $receptorResuelto['venta_receptor']['numerodocumento'] ?? null,
                            'nombre' => $receptorResuelto['venta_receptor']['nombre'] ?? null,
                        ]
                    );
                    $valeId = (int) $vale->id;
                }

                if ($turno) {
                    $this->turnoService->sumarFacturacion($turno, max(0., $totalPagar));
                }

                $emision = FacturacionLocalEmision::query()->create([
                    'local_venta_id' => $local->id,
                    'turno_operativo_local_id' => $turno?->id,
                    'venta_id' => $ventaPrincipal->id,
                    'venta_nc_id' => $ventaNc?->id,
                    'vale_cliente_local_id' => $valeId,
                    'es_ticket_regalo' => $esRegalo,
                    'payload_resumen_json' => [
                        'neto_fac' => $split['neto_fac'],
                        'neto_nc' => $split['neto_nc'],
                        'neto' => $split['neto'],
                        'usuario_id' => Auth::id(),
                        'receptor_modo' => $receptorResuelto['modo'] ?? null,
                        'letra' => $receptorResuelto['letra'] ?? null,
                    ],
                ]);

                // ARCA al final: si falla cobranza/vale no queda CAE huérfano; si falla CAE se revierte todo.
                foreach ($caePendientes as $caePendiente) {
                    $this->facturacionService->completarSolicitudCaePendiente($caePendiente);
                }

                $ventaFac?->refresh();
                $ventaNc?->refresh();

                return [
                    'ok' => true,
                    'venta_id' => (int) ($ventaFac?->id ?? 0) ?: null,
                    'venta_nc_id' => $ventaNc?->id,
                    'vale_id' => $valeId,
                    'cae' => (string) ($ventaFac?->cae ?? $ventaNc?->cae ?? ''),
                    'emision_id' => (int) $emision->id,
                    'codigo' => (string) ($ventaFac?->codigo ?? $ventaNc?->codigo ?? ''),
                    'letra' => (string) ($receptorResuelto['letra'] ?? ''),
                ];
            });
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
        } catch (Throwable $e) {
            Log::error('facturacion_local.emitir.fallo', [
                'msg' => $e->getMessage(),
                'local_id' => $local->id,
                'exception' => $e::class,
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
        }
    }

    /**
     * Cuando FAC ≈ NC (neto 0), sube el precio de la primera línea FAC para dejar $0,01 a cobrar (ARCA).
     *
     * @param  array{fac:list<array<string,mixed>>,nc:list<array<string,mixed>>,tiene_nc:bool,neto_fac:float,neto_nc:float,neto:float}  $split
     * @return array{fac:list<array<string,mixed>>,nc:list<array<string,mixed>>,tiene_nc:bool,neto_fac:float,neto_nc:float,neto:float}
     */
    private function aplicarImporteMinimoArcaEnFac(array $split): array
    {
        if ($split['fac'] === []) {
            return $split;
        }

        $minimo = FacturacionLocalAsientoMedioSupport::IMPORTE_MINIMO_ARCA;
        $linea = $split['fac'][0];
        $cant = (float) ($linea['cantidad'] ?? 0);
        if ($cant < 0.000001) {
            $cant = 1.;
            $linea['cantidad'] = $cant;
        }
        $precio = (float) ($linea['precio'] ?? 0);
        $dto = (float) ($linea['descuento'] ?? $linea['descuentolinea'] ?? 0);
        $factorDto = max(0.000001, 1. - $dto / 100.);
        $linea['precio'] = round($precio + ($minimo / ($cant * $factorDto)), 4);
        $split['fac'][0] = $linea;
        $split['neto_fac'] = round((float) $split['neto_fac'] + $minimo, 2);
        $split['neto'] = round((float) $split['neto_fac'] - (float) $split['neto_nc'], 2);

        return $split;
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $receptorResuelto
     * @param  list<array{cuentacaja_id?:int,monto?:float,cotizacion?:float|null}>  $medios
     * @return array<string,mixed>
     */
    private function armarPayload(
        LocalVenta $local,
        array $lineas,
        array $input,
        bool $esNc,
        bool $esRegalo,
        array $receptorResuelto,
        array $medios = [],
    ): array {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descuentos = [];
        $descripciones = [];
        $combinacionIds = [];
        $talleIds = [];
        $colorIds = [];

        foreach ($lineas as $linea) {
            $articuloIds[] = (int) $linea['articulo_id'];
            $cant = (float) $linea['cantidad'];
            $precio = (float) ($linea['precio'] ?? 0);
            if ($esRegalo) {
                $precio = FacturacionLocalAsientoMedioSupport::IMPORTE_MINIMO_ARCA;
            }
            $cantidades[] = $cant;
            $precios[] = $precio;
            $descuentos[] = (float) ($linea['descuento'] ?? $linea['descuentolinea'] ?? 0);
            $descripciones[] = (string) ($linea['descripcion'] ?? '');
            $combinacionIds[] = (int) ($linea['combinacion_id'] ?? 0);
            $talleIds[] = (int) ($linea['talle_id'] ?? 0);
            $colorIds[] = (int) ($linea['color_id'] ?? 0);
        }

        $clienteId = (int) ($receptorResuelto['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            $clienteId = FacturacionLocalReceptorSupport::clienteContadoId();
        }

        $letra = strtoupper(trim((string) ($receptorResuelto['letra'] ?? 'B')));
        if ($letra === '') {
            $letra = 'B';
        }
        $listaId = (int) ($local->listaprecio_id ?: 0);
        $prepIva = FacturacionLocalPrecioIvaSupport::prepararParaEmision(
            $precios,
            $articuloIds,
            $listaId,
            $letra,
            false, // carrito POS: apiPrecio ya devolvió IVA incluido
        );

        $fecha = Carbon::now()->format('Y-m-d');
        $empresaId = (int) ($local->empresa_id ?: 0);
        $puntoventaId = (int) ($local->puntoventaDefaultId() ?? $local->puntoventa_id ?? 0);

        $opciones = $this->opcionesEmision();
        // FAC contado: asiento VTA imputa medios de pago (no deudores).
        // NC asociada usa venta_id → asiento invertido (incluye las piernas de medios).
        if (! $esNc && ! $esRegalo && $medios !== []) {
            $opciones['asiento_medios_pago'] = $medios;
        }

        $payload = [
            'empresa_id' => $empresaId,
            'puntoventa_id' => $puntoventaId,
            'tipotransaccion_id' => $esNc ? $local->tipoNcId() : $local->tipoFacId(),
            'cliente_id' => $clienteId,
            'fechafactura' => $fecha,
            'fecha' => $fecha,
            'deposito_id' => (int) $local->deposito_id,
            'listaprecio_id' => $listaId,
            'moneda_id' => (int) config('facturacion_local.moneda_id', 1),
            'cotizacion' => 1.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $prepIva['precios'],
            'incluyeimpuestos' => $prepIva['incluyeimpuestos'],
            'descuentolinea' => 0,
            'descripciones' => $descripciones,
            'combinacion_ids' => $combinacionIds,
            'talle_ids' => $talleIds,
            'color_ids' => $colorIds,
            'descuentopie' => (float) ($input['descuentopie'] ?? 0),
            'descuentoimportepie' => (float) ($input['descuentoimportepie'] ?? 0),
            'vendedor_id' => Auth::id(),
            'opciones_emision' => $opciones,
            'arca_receptor' => $receptorResuelto['arca_receptor'],
            'venta_receptor' => $receptorResuelto['venta_receptor'],
            '_descuentos_linea_item' => $descuentos,
        ];

        if (! empty($receptorResuelto['omitir_percepciones'])) {
            $payload['omitir_percepciones'] = true;
        }
        if (! empty($receptorResuelto['provincia_id'])) {
            $payload['provincia_id'] = (int) $receptorResuelto['provincia_id'];
        }

        // Si el PV tiene webservice, numeración + CAE van por ARCA (nunca ERP/manual).
        $pv = $puntoventaId > 0 ? Puntoventa::query()->find($puntoventaId) : null;
        if (FacturacionLocalPosContextoSupport::pvUsaWebservice($pv)
            && strtoupper(trim((string) ($pv->modofacturacion ?? ''))) === 'M'
        ) {
            $payload['forzar_modofacturacion'] = 'C';
        }

        return $payload;
    }

    /**
     * @return array<string,bool|list<array{cuentacontable_id:int,monto:float}>>
     */
    private function opcionesEmision(): array
    {
        return [
            'omitir_movimiento_stock' => false,
            'permitir_caea' => false,
            'origen_facturacion_local' => true,
            // El POS cobra en el momento: la cobranza de caja no deja deuda en cuenta corriente.
            'omitir_cuenta_corriente' => true,
            // Diferir CAE: primero venta+cobranza en ERP; ARCA al final (evita CAE huérfano).
            'omitir_solicitud_arca_cae' => true,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     */
    private function grabarStockLocalSiCorresponde(
        LocalVenta $local,
        Venta $venta,
        array $lineas,
        bool $esEntrada,
    ): void {
        try {
            if (! class_exists(FacturacionServiceFerli::class)) {
                return;
            }
            /** @var FacturacionServiceFerli $ferli */
            $ferli = app(FacturacionServiceFerli::class);
            if (! method_exists($ferli, 'grabaStockLocal')) {
                return;
            }
            $datatalle = [];
            foreach ($lineas as $linea) {
                $articulo = Articulo::query()->with(['categorias'])->find((int) $linea['articulo_id']);
                if (! $articulo) {
                    continue;
                }
                $cant = (float) $linea['cantidad'];
                if ($esEntrada) {
                    // NC: entrada (cantidad positiva hacia Anita Local)
                }
                $talleId = (int) ($linea['talle_id'] ?? 0);
                $talleNombre = '0';
                if ($talleId > 0) {
                    $talleNombre = (string) (\App\Models\Stock\Talle::query()->whereKey($talleId)->value('nombre') ?? $talleId);
                }
                $combId = (int) ($linea['combinacion_id'] ?? 0);
                $codigoComb = '';
                if ($combId > 0) {
                    $codigoComb = (string) (\App\Models\Stock\Combinacion::query()->whereKey($combId)->value('codigo') ?? '');
                }
                $categoriaCodigo = (string) ($articulo->categorias?->codigo ?? '');
                $datatalle[] = [
                    'sku' => (string) $articulo->sku,
                    'descripcion' => (string) $articulo->descripcion,
                    'categoria' => $categoriaCodigo,
                    'impuesto_id' => (int) ($articulo->impuesto_id ?: 3),
                    'incluyeimpuesto' => '1',
                    'codigocombinacion' => $codigoComb,
                    'medidas' => [[
                        'medida' => is_numeric($talleNombre) ? (int) $talleNombre : 0,
                        'cantidad' => $cant,
                        'precio' => (float) ($linea['precio'] ?? 0),
                        'pedido' => '0',
                    ]],
                ];
            }
            if ($datatalle === []) {
                return;
            }
            $pvCodigo = (int) ltrim((string) ($local->puntoventa?->codigo ?? $local->puntoventa_id), '0');
            if ($pvCodigo <= 0) {
                $pvCodigo = (int) ($local->puntoventa_id ?: 0);
            }
            $letra = strtoupper(substr(trim((string) ($venta->codigo ?? 'B')), -1) ?: 'B');
            if (! in_array($letra, ['A', 'B', 'C', 'E', 'M'], true)) {
                $letra = 'B';
            }
            $fecha = $venta->fecha;
            $fechaStr = $fecha instanceof \DateTimeInterface
                ? $fecha->format('Y-m-d')
                : (string) ($fecha ?: now()->format('Y-m-d'));
            $ventaArr = [
                'fecha' => $fechaStr,
                'codigo' => (string) ($venta->codigo ?? 'FAC'),
                'numerocomprobante' => (int) ($venta->numerocomprobante ?? 0),
                'moneda_id' => (int) ($venta->moneda_id ?: 1),
            ];
            $ferli->grabaStockLocal(
                $pvCodigo,
                $letra,
                $ventaArr,
                $datatalle,
                '',
                1,
                0,
                902,
                0,
                $local->anitaServidor(),
                $local->anitaIfxServer()
            );
        } catch (Throwable $e) {
            Log::warning('facturacion_local.stock_local', [
                'msg' => $e->getMessage(),
                'venta_id' => $venta->id,
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }
    }
}
