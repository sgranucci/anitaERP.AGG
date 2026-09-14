<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\FacturacionServiceFerli;
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
        $totalPagar = max(0., (float) $split['neto']);
        $receptor = is_array($input['receptor'] ?? null) ? $input['receptor'] : [];
        $tieneCliente = ((int) ($input['cliente_id'] ?? 0) > 1)
            || trim((string) ($receptor['nombre'] ?? '')) !== ''
            || trim((string) ($receptor['nrodoc'] ?? $receptor['cuit'] ?? '')) !== '';

        $errores = $this->preflightService->erroresAntesDeEmitir(
            $local,
            $turno,
            $lineas,
            $medios,
            $totalPagar,
            $tieneCliente,
            false,
            $esRegalo,
        );
        if ($errores !== []) {
            return ['ok' => false, 'errores' => $errores, 'error' => $errores[0]];
        }

        try {
            return DB::transaction(function () use ($local, $turno, $input, $split, $medios, $esRegalo, $totalPagar, $receptor) {
                $ventaFac = null;
                $ventaNc = null;
                $valeId = null;

                if ($split['fac'] !== []) {
                    $payloadFac = $this->armarPayload($local, $split['fac'], $input, false, $esRegalo);
                    $resultadoFac = $this->facturacionService->generaComprobanteGeneral($payloadFac);
                    if (! is_array($resultadoFac) || ! empty($resultadoFac['error'])) {
                        $msg = trim((string) ($resultadoFac['mensaje'] ?? $resultadoFac['error'] ?? 'Error al emitir factura'));

                        throw new InvalidArgumentException($msg);
                    }
                    $ventaFac = Venta::query()->find((int) ($resultadoFac['venta_id'] ?? 0));
                    if (! $ventaFac) {
                        throw new InvalidArgumentException('No se obtuvo la venta emitida.');
                    }
                    $this->grabarStockLocalSiCorresponde($local, $ventaFac, $split['fac'], false);
                }

                if ($split['tiene_nc']) {
                    $payloadNc = $this->armarPayload($local, $split['nc'], $input, true, false);
                    if ($ventaFac) {
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

                // Cobranza sobre neto a favor de la casa
                if (! $esRegalo && $totalPagar > 0.009 && $medios !== []) {
                    $this->cobranzaService->registrar($ventaPrincipal, $local, $medios, $ventaFac === null && $ventaNc !== null);
                }

                // Excedente → vale o reintegro (UI decide; reintegro = medio negativo no implementado aquí: solo vale)
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
                            'cliente_id' => $input['cliente_id'] ?? null,
                            'tipo_documento' => $receptor['tipodoc'] ?? null,
                            'nro_documento' => $receptor['nrodoc'] ?? $receptor['cuit'] ?? null,
                            'nombre' => $receptor['nombre'] ?? null,
                        ]
                    );
                    $valeId = (int) $vale->id;
                } elseif ($split['neto'] < -0.05 && $excedenteAccion === 'vale') {
                    $vale = $this->valeService->crearVale(
                        $local,
                        abs($split['neto']),
                        $ventaNc ?? $ventaFac,
                        $turno?->id,
                        [
                            'cliente_id' => $input['cliente_id'] ?? null,
                            'tipo_documento' => $receptor['tipodoc'] ?? null,
                            'nro_documento' => $receptor['nrodoc'] ?? $receptor['cuit'] ?? null,
                            'nombre' => $receptor['nombre'] ?? null,
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
                    ],
                ]);

                return [
                    'ok' => true,
                    'venta_id' => (int) ($ventaFac?->id ?? 0) ?: null,
                    'venta_nc_id' => $ventaNc?->id,
                    'vale_id' => $valeId,
                    'cae' => (string) ($ventaFac?->cae ?? $ventaNc?->cae ?? ''),
                    'emision_id' => (int) $emision->id,
                    'codigo' => (string) ($ventaFac?->codigo ?? $ventaNc?->codigo ?? ''),
                ];
            });
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
        } catch (Throwable $e) {
            Log::error('facturacion_local.emitir.fallo', [
                'msg' => $e->getMessage(),
                'local_id' => $local->id,
            ]);

            return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
        }
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function armarPayload(
        LocalVenta $local,
        array $lineas,
        array $input,
        bool $esNc,
        bool $esRegalo,
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
                $precio = 0.01;
            }
            $cantidades[] = $cant;
            $precios[] = $precio;
            $descuentos[] = (float) ($linea['descuento'] ?? $linea['descuentolinea'] ?? 0);
            $descripciones[] = (string) ($linea['descripcion'] ?? '');
            $combinacionIds[] = (int) ($linea['combinacion_id'] ?? 0);
            $talleIds[] = (int) ($linea['talle_id'] ?? 0);
            $colorIds[] = (int) ($linea['color_id'] ?? 0);
        }

        $clienteId = (int) ($input['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            $clienteId = (int) config('facturacion_local.cliente_contado_id', 1);
        }

        $fecha = Carbon::now()->format('Y-m-d');
        $empresaId = (int) ($local->empresa_id ?: 0);

        $payload = [
            'empresa_id' => $empresaId,
            'puntoventa_id' => (int) $local->puntoventa_id,
            'tipotransaccion_id' => $esNc ? $local->tipoNcId() : $local->tipoFacId(),
            'cliente_id' => $clienteId,
            'fechafactura' => $fecha,
            'fecha' => $fecha,
            'deposito_id' => (int) $local->deposito_id,
            'listaprecio_id' => (int) ($local->listaprecio_id ?: 0),
            'moneda_id' => (int) config('facturacion_local.moneda_id', 1),
            'cotizacion' => 1.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descuentolinea' => $descuentos,
            'descripciones' => $descripciones,
            'combinacion_ids' => $combinacionIds,
            'talle_ids' => $talleIds,
            'color_ids' => $colorIds,
            'descuentopie' => (float) ($input['descuentopie'] ?? 0),
            'descuentoimportepie' => (float) ($input['descuentoimportepie'] ?? 0),
            'vendedor_id' => Auth::id(),
            'opciones_emision' => $this->opcionesEmision(),
        ];

        if (! empty($input['receptor']) && is_array($input['receptor'])) {
            $payload['venta_receptor'] = $input['receptor'];
        }

        return $payload;
    }

    /**
     * @return array<string,bool>
     */
    private function opcionesEmision(): array
    {
        return [
            'omitir_movimiento_stock' => false,
            'permitir_caea' => false,
            'origen_facturacion_local' => true,
            // Stock ERP + Anita Local se refuerza aparte; no enganchar resiliencia CAEA de gastronomía
            'omitir_solicitud_arca_cae' => false,
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
            // Construir estructura mínima de talles para grabaStockLocal si el método lo requiere.
            // Si la firma no matchea, se omite sin romper la emisión ERP.
            if (! method_exists($ferli, 'grabaStockLocal')) {
                return;
            }
            $datatalle = [];
            foreach ($lineas as $linea) {
                $articulo = Articulo::query()->find((int) $linea['articulo_id']);
                if (! $articulo) {
                    continue;
                }
                $cant = (float) $linea['cantidad'];
                if ($esEntrada) {
                    // NC: entrada
                }
                $datatalle[] = (object) [
                    'articulo_id' => $articulo->id,
                    'sku' => $articulo->sku,
                    'talle_id' => (int) ($linea['talle_id'] ?? 0),
                    'combinacion_id' => (int) ($linea['combinacion_id'] ?? 0),
                    'color_id' => (int) ($linea['color_id'] ?? 0),
                    'cantidad' => $cant,
                    'precio' => (float) ($linea['precio'] ?? 0),
                ];
            }
            if ($datatalle === []) {
                return;
            }
            $pvCodigo = (int) ($local->puntoventa?->codigo ?? $local->puntoventa_id);
            $ferli->grabaStockLocal(
                $pvCodigo,
                (string) ($venta->letra ?? 'B'),
                $venta,
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
            Log::warning('facturacion_local.stock_local', ['msg' => $e->getMessage(), 'venta_id' => $venta->id]);
        }
    }
}
