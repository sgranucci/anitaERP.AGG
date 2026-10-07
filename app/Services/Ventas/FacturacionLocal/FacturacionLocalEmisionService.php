<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalAsientoMedioSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioIvaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalReceptorSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalSplitFacNcSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Orquesta emisión FAC (+ NC) del POS Local.
 * Reutiliza FacturacionService; sin CAEA. El stock queda en anitaERP (no se graba en el bridge del local).
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
        if ($split['tiene_nc'] && empty($input['es_ticket_regalo'])) {
            $split['nc'] = MotivoDevolucionSupport::anotarLineas($split['nc']);
        }
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

		// Cambio equivalente (FAC y NC se compensan): ARCA exige mínimo $0,01.
		// Una línea con precio 0 no es un cambio: no se le pega el mínimo.
		$forzarMinimoArca = ! $esRegalo
			&& FacturacionLocalSplitFacNcSupport::esCambioEquivalente($split);
		if ($forzarMinimoArca) {
			$split = $this->aplicarImporteMinimoArcaEnFac($split);
		}

		try {
			$receptorResuelto = FacturacionLocalReceptorSupport::resolver($input);
		} catch (InvalidArgumentException $e) {
			return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
		}

		$totalPagar = $esRegalo
			? 0.
			: ($forzarMinimoArca
				? FacturacionLocalAsientoMedioSupport::IMPORTE_MINIMO_ARCA
				: max(0., (float) $split['neto']));

		// Factura A: el total a cobrar incluye la percepción IIBB (padrón del CUIT del receptor).
		if (! $esRegalo && ! $forzarMinimoArca
			&& empty($receptorResuelto['omitir_percepciones'])
			&& $split['fac'] !== []
		) {
			$fiscal = $this->importesConPercepcion($local, $split, $input, $receptorResuelto);
			if (! empty($fiscal['error'])) {
				return ['ok' => false, 'error' => $fiscal['error'], 'errores' => [$fiscal['error']]];
			}
			$totalPagar = (float) $fiscal['total_pagar'];
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

        // Un medio que quedó del ticket anterior (ej. $85.500 sobre un cambio de $0,01)
        // no puede entrar a caja. Vale: se cobra el total y el exceso queda a cuenta.
        // Reintegro: el vuelto ya salió del cajón, la cobranza queda en el neto.
        $excedenteAccion = (string) ($input['excedente_accion'] ?? '');
        if (! $esRegalo && $totalPagar > 0.009) {
            $sumaMedios = $this->sumaMediosEnPesos($medios);
            if ($sumaMedios - $totalPagar > 0.05) {
                if ($excedenteAccion === 'reintegro') {
                    $medios = $this->escalarMediosAlTotal($medios, $totalPagar);
                } elseif ($excedenteAccion !== 'vale') {
                    $msg = 'El cobro ('.number_format($sumaMedios, 2, ',', '.')
                        .') supera el total a pagar ('.number_format($totalPagar, 2, ',', '.')
                        .'). Ajustá el medio, o indicá vale o reintegro si el excedente es real.';

                    return ['ok' => false, 'error' => $msg, 'errores' => [$msg]];
                }
            }
        }

        try {
            $resultado = DB::transaction(function () use (
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
                }

                if ($split['tiene_nc']) {
                    $payloadNc = $this->armarPayload($local, $split['nc'], $input, true, false, $receptorResuelto);
                    $lineasSinStock = array_values(array_filter(
                        $split['nc'],
                        static fn (array $linea): bool => ! empty($linea['omitir_stock'])
                    ));
                    if ($lineasSinStock !== [] && count($lineasSinStock) === count($split['nc'])) {
                        $payloadNc['opciones_emision']['omitir_movimiento_stock'] = true;
                    }
                    if ($ventaFac) {
                        // venta_id = FAC del mismo cobro → asiento invertido + CbteAsoc ARCA.
                        // canje_pos: el −1 entra con artículo, color, talle y precio propios.
                        // No copiar la FAC (eso queda para la nota de crédito total).
                        $payloadNc['venta_id'] = $ventaFac->id;
                        $payloadNc['comprobanteasociado_id'] = $ventaFac->id;
                        $payloadNc['venta_id_asociada'] = $ventaFac->id;
                        $payloadNc['opciones_emision']['canje_pos'] = true;
                    }
                    $resultadoNc = $this->facturacionService->generaComprobanteGeneral($payloadNc);
                    if (! is_array($resultadoNc) || ! empty($resultadoNc['error'])) {
                        $msg = trim((string) ($resultadoNc['mensaje'] ?? $resultadoNc['error'] ?? 'Error al emitir NC'));

                        throw new InvalidArgumentException($msg);
                    }
                    $ventaNc = Venta::query()->find((int) ($resultadoNc['venta_id'] ?? 0));
                    if ($ventaNc) {
                        app(DevolucionHistorialService::class)->registrarPos($local, $ventaNc, $ventaFac, $split['nc']);
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

            return $resultado;
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'errores' => [$e->getMessage()]];
        } catch (Throwable $e) {
            $msg = VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e)
                ? 'Ya existe un comprobante con ese punto de venta, tipo AFIP y número. Reintente el cobro.'
                : $e->getMessage();
            Log::error('facturacion_local.emitir.fallo', [
                'msg' => $e->getMessage(),
                'local_id' => $local->id,
                'exception' => $e::class,
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return ['ok' => false, 'error' => $msg, 'errores' => [$msg]];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $medios
     */
    private function sumaMediosEnPesos(array $medios): float
    {
        $suma = 0.;
        foreach ($medios as $medio) {
            $cot = (float) ($medio['cotizacion'] ?? 1.);
            if ($cot <= 0.) {
                $cot = 1.;
            }
            $suma += (float) ($medio['monto'] ?? 0) * $cot;
        }

        return round($suma, 2);
    }

    /**
     * Baja los medios al neto del ticket (reintegro: el vuelto ya salió del cajón).
     *
     * @param  list<array<string, mixed>>  $medios
     * @return list<array<string, mixed>>
     */
    private function escalarMediosAlTotal(array $medios, float $total): array
    {
        $suma = $this->sumaMediosEnPesos($medios);
        if ($suma <= 0.009 || $total <= 0.009 || $medios === []) {
            return $medios;
        }

        $factor = $total / $suma;
        $lineas = [];
        $acum = 0.;
        $ultimo = count($medios) - 1;
        foreach ($medios as $i => $medio) {
            $cot = (float) ($medio['cotizacion'] ?? 1.);
            if ($cot <= 0.) {
                $cot = 1.;
            }
            if ($i === $ultimo) {
                $monto = round(($total - $acum) / $cot, 2);
            } else {
                $monto = round((float) ($medio['monto'] ?? 0) * $factor, 2);
                $acum += round($monto * $cot, 2);
            }
            if ($monto <= 0.009) {
                continue;
            }
            $medio['monto'] = $monto;
            $medio['cotizacion'] = $cot;
            $lineas[] = $medio;
        }

        return $lineas !== [] ? $lineas : $medios;
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
	 * Totales del POS. Factura B/CF: neto del carrito. Factura A: suma percepción IIBB.
	 * Si el receptor A todavía está incompleto, devuelve el neto del carrito.
	 *
	 * @param  array<string,mixed>  $input
	 * @return array{
	 *   neto:float,neto_fac:float,neto_nc:float,tiene_nc:bool,
	 *   total_pagar:float,cambio_equivalente:bool,percepcion:float,
	 *   percepciones:list<array{concepto:string,tasa:float,importe:float}>
	 * }
	 */
	public function totalesCobro(LocalVenta $local, array $input): array
	{
		$split = FacturacionLocalSplitFacNcSupport::partir($input['lineas'] ?? []);
		$cambioEquivalente = FacturacionLocalSplitFacNcSupport::esCambioEquivalente($split);
		$base = [
			'neto' => (float) $split['neto'],
			'neto_fac' => (float) $split['neto_fac'],
			'neto_nc' => (float) $split['neto_nc'],
			'tiene_nc' => (bool) $split['tiene_nc'],
			'total_pagar' => $cambioEquivalente
				? FacturacionLocalAsientoMedioSupport::IMPORTE_MINIMO_ARCA
				: max(0., (float) $split['neto']),
			'cambio_equivalente' => $cambioEquivalente,
			'percepcion' => 0.,
			'percepciones' => [],
		];

		try {
			$receptor = FacturacionLocalReceptorSupport::resolver($input);
		} catch (InvalidArgumentException) {
			return $base;
		}

		if ($cambioEquivalente || ! empty($receptor['omitir_percepciones']) || $split['fac'] === []) {
			return $base;
		}

		$fiscal = $this->importesConPercepcion($local, $split, $input, $receptor);
		if (! empty($fiscal['error'])) {
			return $base;
		}

		return array_merge($base, [
			'total_pagar' => (float) $fiscal['total_pagar'],
			'percepcion' => (float) $fiscal['percepcion'],
			'percepciones' => $fiscal['percepciones'],
		]);
	}

	/**
	 * @param  array{fac:list<array<string,mixed>>,nc:list<array<string,mixed>>,tiene_nc:bool,neto_fac:float,neto_nc:float,neto:float}  $split
	 * @param  array<string,mixed>  $input
	 * @param  array<string,mixed>  $receptor
	 * @return array{total_pagar:float,percepcion:float,percepciones:list<array{concepto:string,tasa:float,importe:float}>,error?:string}
	 */
	private function importesConPercepcion(LocalVenta $local, array $split, array $input, array $receptor): array
	{
		$fac = $this->totalFiscal($local, $split['fac'], $input, false, $receptor);
		if ($fac === null) {
			return [
				'total_pagar' => 0.,
				'percepcion' => 0.,
				'percepciones' => [],
				'error' => 'No se pudieron calcular las percepciones de la Factura A.',
			];
		}

		$totalNc = 0.;
		$percNc = 0.;
		if ($split['tiene_nc'] && $split['nc'] !== []) {
			$nc = $this->totalFiscal($local, $split['nc'], $input, true, $receptor);
			if ($nc === null) {
				return [
					'total_pagar' => 0.,
					'percepcion' => 0.,
					'percepciones' => [],
					'error' => 'No se pudieron calcular las percepciones de la nota de crédito.',
				];
			}
			$totalNc = $nc['total'];
			$percNc = $nc['percepcion'];
		}

		return [
			'total_pagar' => round(max(0., $fac['total'] - $totalNc), 2),
			'percepcion' => round($fac['percepcion'] - $percNc, 2),
			'percepciones' => $fac['percepciones'],
		];
	}

	/**
	 * @param  list<array<string,mixed>>  $lineas
	 * @param  array<string,mixed>  $input
	 * @param  array<string,mixed>  $receptor
	 * @return array{total:float,percepcion:float,percepciones:list<array{concepto:string,tasa:float,importe:float}>}|null
	 */
	private function totalFiscal(LocalVenta $local, array $lineas, array $input, bool $esNc, array $receptor): ?array
	{
		if ($lineas === []) {
			return ['total' => 0., 'percepcion' => 0., 'percepciones' => []];
		}

		try {
			$payload = $this->armarPayload($local, $lineas, $input, $esNc, false, $receptor, []);
			$payload['_sin_guardar_preferencia'] = true;
			$calc = $this->facturacionService->calculaFacturaGeneral($payload);
		} catch (Throwable $e) {
			Log::warning('facturacion_local.percepcion', ['error' => $e->getMessage()]);

			return null;
		}

		if (! is_array($calc) || ! empty($calc['error'])) {
			Log::warning('facturacion_local.percepcion', [
				'error' => is_array($calc) ? ($calc['error'] ?? 'calculo') : 'calculo',
			]);

			return null;
		}

		$percepciones = [];
		$suma = 0.;
		foreach ($calc['conceptostotales'] ?? [] as $concepto) {
			if (! is_array($concepto)) {
				continue;
			}
			$nombre = (string) ($concepto['concepto'] ?? '');
			if (! str_starts_with($nombre, 'Perc.')) {
				continue;
			}
			$importe = round((float) ($concepto['importe'] ?? 0), 2);
			if (abs($importe) < 0.009) {
				continue;
			}
			$percepciones[] = [
				'concepto' => $nombre,
				'tasa' => (float) ($concepto['tasa'] ?? 0),
				'importe' => $importe,
			];
			$suma += $importe;
		}

		return [
			'total' => round((float) ($calc['totalcomprobante'] ?? 0), 2),
			'percepcion' => round($suma, 2),
			'percepciones' => $percepciones,
		];
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
        $omitirStockPorItem = [];

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
            $omitirStockPorItem[] = ! empty($linea['omitir_stock']);
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
        // El FAC de locales está en «sin operación». El POS igual descuenta el depósito del local.
        $opciones['forzar_operacion_stock'] = $esNc
            ? TipotransaccionOperacionStockSupport::ENTRADA
            : TipotransaccionOperacionStockSupport::SALIDA;
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
            'omitir_stock_por_item' => $omitirStockPorItem,
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

}
