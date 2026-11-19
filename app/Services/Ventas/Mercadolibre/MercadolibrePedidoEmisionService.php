<?php

namespace App\Services\Ventas\Mercadolibre;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\MercadolibrePedido;
use App\Models\Ventas\MercadolibrePedidoLinea;
use App\Models\Ventas\MercadolibrePedidoVenta;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalEmisionVinculoSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreDefaultsSupport;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoEstadoSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Emite factura ERP desde pedidos Mercado Libre.
 * Regla Cati: una factura por pack (todas las órdenes del pack).
 */
final class MercadolibrePedidoEmisionService
{
    private const MONEDA_PESOS_ID = 1;

    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly CobranzaService $cobranzaService,
        private readonly MercadolibreFiscalDocumentService $fiscalDocumentService,
    ) {
    }

    /**
     * @param  array{
     *   puntoventa_id?:int,
     *   deposito_id?:int,
     *   cliente_id?:int|null,
     *   letra?:string,
     *   medios_pago?:list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>
     * }  $input
     * @return array{ok:bool,error?:string,errores?:list<string>,venta_id?:int,cae?:string,pack_id?:int|null,pedidos?:list<int>}
     */
    public function emitir(MercadolibrePedido $pedido, array $input = []): array
    {
        if ($pedido->estaFacturado()) {
            return ['ok' => false, 'error' => 'El pedido ya está facturado (venta #'.$pedido->venta_id.').'];
        }
        if (! $pedido->estaPagado()) {
            return ['ok' => false, 'error' => 'Solo se facturan órdenes pagadas.'];
        }

        $grupo = $this->grupoFacturable($pedido);
        foreach ($grupo as $hermano) {
            if ($hermano->estaFacturado()) {
                return ['ok' => false, 'error' => 'El pack ya tiene la orden #'.$hermano->order_id.' facturada (venta #'.$hermano->venta_id.').'];
            }
            if (! $hermano->estaPagado()) {
                return ['ok' => false, 'error' => 'La orden #'.$hermano->order_id.' del pack no está pagada.'];
            }
        }

        $ancla = $grupo->first() ?? $pedido;
        $defaults = MercadolibreDefaultsSupport::resolver($ancla->shipping_json);
        $pvId = (int) ($input['puntoventa_id'] ?? $ancla->puntoventa_id_sugerido ?? $defaults['puntoventa_id'] ?? 0);
        $depId = (int) ($input['deposito_id'] ?? $ancla->deposito_id_sugerido ?? $defaults['deposito_id'] ?? 0);
        $cuentaId = (int) ($defaults['cuentacaja_id'] ?? 0);
        $totalGrupo = round((float) $grupo->sum(static fn (MercadolibrePedido $p) => (float) $p->total), 2);

        $medios = $input['medios_pago'] ?? [];
        if ($medios === [] && $cuentaId > 0) {
            $medios = [[
                'cuentacaja_id' => $cuentaId,
                'moneda_id' => self::MONEDA_PESOS_ID,
                'monto' => $totalGrupo,
            ]];
        }

        $input['puntoventa_id'] = $pvId;
        $input['deposito_id'] = $depId;
        $input['medios_pago'] = $medios;
        $input['letra'] = strtoupper(trim((string) ($input['letra'] ?? $ancla->letra_sugerida ?? 'B'))) ?: 'B';

        $errores = $this->validarPreflight($grupo, $input, $totalGrupo);
        if ($errores !== []) {
            foreach ($grupo as $p) {
                $p->estado_erp = MercadolibrePedidoEstadoSupport::BLOQUEADO_FISCAL;
                $p->error_mensaje = $errores[0];
                $p->save();
            }

            return ['ok' => false, 'errores' => $errores, 'error' => $errores[0]];
        }

        try {
            $resultadoTx = DB::transaction(function () use ($grupo, $ancla, $input, $totalGrupo) {
                $ids = $grupo->pluck('id')->all();
                $bloqueados = MercadolibrePedido::query()
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->with('lineas')
                    ->orderBy('id')
                    ->get();
                foreach ($bloqueados as $p) {
                    if ($p->estaFacturado()) {
                        throw new InvalidArgumentException('Carrera: orden #'.$p->order_id.' ya facturada.');
                    }
                }

                $payload = $this->armarPayload($bloqueados, $ancla, $input, $totalGrupo);
                $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
                if (! is_array($resultado) || ! empty($resultado['error'])) {
                    throw new InvalidArgumentException(
                        trim((string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'Error al emitir factura'))
                    );
                }

                $ventaId = (int) ($resultado['venta_id'] ?? 0);
                $venta = Venta::query()->find($ventaId);
                if (! $venta) {
                    throw new InvalidArgumentException('No se obtuvo la venta emitida.');
                }

                // Conciliación: leyenda con MP {payment_id} (sin duplicar si ya vino en el payload).
                $leyenda = $this->leyendaConciliacion($bloqueados, $ancla);
                if (trim((string) ($venta->leyenda ?? '')) === '' || ! str_contains((string) $venta->leyenda, 'MP ')) {
                    $venta->leyenda = mb_substr($leyenda, 0, 255);
                    $venta->save();
                }

                FacturacionLocalEmisionVinculoSupport::vincularVenta((int) $venta->id, 'mercadolibre');
                $this->registrarCobranzaSilenciosa($venta, $input['medios_pago']);

                foreach ($bloqueados as $p) {
                    MercadolibrePedidoVenta::query()->create([
                        'mercadolibre_pedido_id' => $p->id,
                        'venta_id' => $venta->id,
                        'total' => (float) $p->total,
                    ]);
                    $p->venta_id = $venta->id;
                    $p->estado_erp = MercadolibrePedidoEstadoSupport::FACTURADO;
                    $p->facturado_at = now();
                    $p->facturado_por_usuario_id = Auth::id();
                    $p->error_mensaje = null;
                    $p->save();
                }

                return [
                    'ok' => true,
                    'venta_id' => (int) $venta->id,
                    'cae' => (string) ($resultado['cae'] ?? $venta->cae ?? ''),
                    'pack_id' => $ancla->pack_id ? (int) $ancla->pack_id : null,
                    'pedidos' => $bloqueados->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'venta' => $venta,
                    'ancla_id' => (int) $ancla->id,
                ];
            });
        } catch (Throwable $e) {
            Log::error('mercadolibre.emision', [
                'order_id' => $pedido->order_id,
                'pack_id' => $pedido->pack_id,
                'error' => $e->getMessage(),
            ]);
            $pedido->refresh();
            $pedido->estado_erp = MercadolibrePedidoEstadoSupport::ERROR;
            $pedido->error_mensaje = mb_substr($e->getMessage(), 0, 1000);
            $pedido->save();

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $venta = $resultadoTx['venta'];
        $anclaId = (int) $resultadoTx['ancla_id'];
        unset($resultadoTx['venta'], $resultadoTx['ancla_id']);

        if (config('mercadolibre.publicar_factura', true)) {
            $anclaFresh = MercadolibrePedido::query()->find($anclaId) ?? $pedido->fresh();
            $pub = $this->fiscalDocumentService->publicar($anclaFresh, $venta);
            if (! ($pub['ok'] ?? false)) {
                Log::warning('mercadolibre.fiscal_pdf', [
                    'order_id' => $anclaFresh->order_id,
                    'error' => $pub['error'] ?? 'falló publicación',
                ]);
                $resultadoTx['pdf_aviso'] = $pub['error'] ?? 'No se pudo subir el PDF a MeLi';
            } else {
                $ids = $resultadoTx['pedidos'] ?? [];
                MercadolibrePedido::query()
                    ->whereIn('id', $ids)
                    ->update(['fiscal_publicado_at' => now()]);
            }
        }

        return $resultadoTx;
    }

    /**
     * Órdenes del mismo pack (o solo esta si no hay pack).
     *
     * @return Collection<int, MercadolibrePedido>
     */
    public function grupoFacturable(MercadolibrePedido $pedido): Collection
    {
        $pedido->loadMissing('lineas');
        if (! $pedido->pack_id) {
            return collect([$pedido]);
        }

        return MercadolibrePedido::query()
            ->where('seller_id', $pedido->seller_id)
            ->where('pack_id', $pedido->pack_id)
            ->with('lineas')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, MercadolibrePedido>  $grupo
     * @param  array<string,mixed>  $input
     * @return list<string>
     */
    private function validarPreflight(Collection $grupo, array $input, float $totalGrupo): array
    {
        $errores = [];
        if ((int) ($input['puntoventa_id'] ?? 0) <= 0) {
            $errores[] = 'Falta punto de venta (MeLi 21 / Full 27).';
        }
        if ((int) ($input['deposito_id'] ?? 0) <= 0) {
            $errores[] = 'Falta depósito.';
        }
        $medios = $input['medios_pago'] ?? [];
        if ($medios === []) {
            $errores[] = 'Falta medio de pago (cuenta 608).';
        }
        $suma = 0.0;
        foreach ($medios as $m) {
            $suma += (float) ($m['monto'] ?? 0);
            if ((int) ($m['cuentacaja_id'] ?? 0) <= 0) {
                $errores[] = 'Cada medio debe tener cuenta de caja.';
            }
        }
        if ($medios !== [] && abs($suma - $totalGrupo) > 0.05) {
            $errores[] = sprintf('Medios (%.2f) ≠ total pack/órdenes (%.2f).', $suma, $totalGrupo);
        }

        $hayLineas = false;
        foreach ($grupo as $p) {
            foreach ($p->lineas as $linea) {
                $hayLineas = true;
                if (! $linea->articulo_id) {
                    $errores[] = 'Orden #'.$p->order_id.' SKU no encontrado: '.($linea->seller_sku ?: $linea->nombre);
                }
            }
        }
        if (! $hayLineas) {
            $errores[] = 'El pack/orden no tiene líneas.';
        }

        $letra = strtoupper((string) ($input['letra'] ?? 'B'));
        $ancla = $grupo->first();
        $doc = preg_replace('/\D+/', '', (string) ($ancla->buyer_doc ?? '')) ?: '';
        if ($letra === 'A' && strlen($doc) < 11) {
            $errores[] = 'Factura A requiere CUIT (11 dígitos) del comprador.';
        }

        return array_values(array_unique($errores));
    }

    /**
     * @param  Collection<int, MercadolibrePedido>  $grupo
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function armarPayload(Collection $grupo, MercadolibrePedido $ancla, array $input, float $totalGrupo): array
    {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $combinacionIds = [];
        $talleIds = [];
        $colorIds = [];

        foreach ($grupo as $pedido) {
            foreach ($pedido->lineas as $linea) {
                if (! $linea instanceof MercadolibrePedidoLinea || ! $linea->articulo_id) {
                    continue;
                }
                $articuloIds[] = (int) $linea->articulo_id;
                $cantidades[] = (float) $linea->quantity;
                $precios[] = (float) $linea->unit_price;
                $descripciones[] = (string) ($linea->nombre ?? '');
                $combinacionIds[] = (int) ($linea->combinacion_id ?? 0);
                $talleIds[] = (int) ($linea->talle_id ?? 0);
                $colorIds[] = (int) ($linea->color_id ?? 0);
            }
        }

        if ($articuloIds === []) {
            throw new InvalidArgumentException('No hay líneas facturables con artículo ERP.');
        }

        $letra = strtoupper((string) ($input['letra'] ?? 'B'));
        $doc = preg_replace('/\D+/', '', (string) ($ancla->buyer_doc ?? '')) ?: '';
        $nombre = (string) ($ancla->buyer_name ?: $ancla->buyer_nickname ?: 'Consumidor Final');
        // Cliente maestro de contado (CF). El doc real va en venta_receptor / arca_receptor.
        $clienteId = (int) config('mercadolibre.cliente_cf_id', 2194);
        if ((int) ($input['cliente_id'] ?? 0) > 0) {
            $clienteId = (int) $input['cliente_id'];
        }

        if ($letra === 'A') {
            $tipodoc = 80; // CUIT
            if ($doc === '' || strlen($doc) < 11) {
                throw new InvalidArgumentException('Factura A requiere CUIT del comprador (11 dígitos).');
            }
        } elseif ($doc !== '' && strlen($doc) === 11) {
            $tipodoc = 80;
        } elseif ($doc !== '' && strlen($doc) >= 7) {
            $tipodoc = 96; // DNI
        } else {
            $tipodoc = (int) config('arca_wsfe.receptor.consumidor_final_tipo_documento', 99);
            $doc = (string) config('arca_wsfe.receptor.consumidor_final_numero_documento', '0');
        }

        $fecha = Carbon::now()->format('Y-m-d');
        $leyenda = $this->leyendaConciliacion($grupo, $ancla);

        $ventaReceptor = [
            'nombre' => $nombre,
            'nrodoc' => $doc,
            'numerodocumento' => $doc,
            'tipodocumento' => $tipodoc === 80 ? 'CUIT' : ($tipodoc === 96 ? 'DNI' : 'CF'),
            'email' => '',
        ];
        $arcaReceptor = [
            'tipodoc' => $tipodoc,
            'numerodocumento' => $doc,
            'nombre' => $nombre,
            'domicilio' => '',
        ];

        return [
            'empresa_id' => (int) config('mercadolibre.empresa_id', 1),
            'puntoventa_id' => (int) $input['puntoventa_id'],
            'tipotransaccion_id' => (int) config('mercadolibre.tipotransaccion_fac_id', 1),
            'cliente_id' => $clienteId,
            'fechafactura' => $fecha,
            'fecha' => $fecha,
            'deposito_id' => (int) $input['deposito_id'],
            'listaprecio_id' => 0,
            'moneda_id' => (int) config('mercadolibre.moneda_id', 1),
            'cotizacion' => 1.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descuentolinea' => 0.,
            'incluyeimpuestos' => array_fill(0, count($precios), '1'),
            'descripcionarticulos' => $descripciones,
            'combinacion_ids' => $combinacionIds,
            'talle_ids' => $talleIds,
            'color_ids' => $colorIds,
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'vendedor_id' => Auth::id(),
            'leyendafactura' => mb_substr($leyenda, 0, 255),
            'observacion' => mb_substr($leyenda, 0, 255),
            'venta_receptor' => $ventaReceptor,
            'arca_receptor' => $arcaReceptor,
            'letra_forzada' => $letra,
            'omitir_percepciones' => $letra === 'B',
            'opciones_emision' => [
                'omitir_movimiento_stock' => false,
                'omitir_cuenta_corriente' => true,
                'forzar_operacion_stock' => TipotransaccionOperacionStockSupport::SALIDA,
                'permitir_caea' => false,
                'origen_mercadolibre' => true,
                'omitir_solicitud_arca_cae' => false,
                'asiento_medios_pago' => $this->mediosParaAsiento($input['medios_pago'] ?? []),
            ],
            '_total_esperado' => $totalGrupo,
        ];
    }

    /**
     * @param  Collection<int, MercadolibrePedido>  $grupo
     */
    private function leyendaConciliacion(Collection $grupo, MercadolibrePedido $ancla, string $base = ''): string
    {
        $orders = $grupo->pluck('order_id')->unique()->values()->all();
        $parts = ['MeLi'];
        if ($ancla->pack_id) {
            $parts[] = 'pack '.$ancla->pack_id;
        }
        $parts[] = 'ord '.implode(',', $orders);
        $paymentIds = $grupo->pluck('payment_id')->filter()->unique()->values()->all();
        foreach ($paymentIds as $pid) {
            $parts[] = 'MP '.$pid;
        }
        $leyenda = trim($base.' '.implode(' ', $parts));

        return mb_substr(preg_replace('/\s+/', ' ', $leyenda) ?: $leyenda, 0, 255);
    }

    /**
     * @param  list<array{cuentacaja_id?:int,monto?:float,cotizacion?:float|null}>  $mediosPago
     * @return list<array{cuentacaja_id:int,monto:float,cotizacion:float}>
     */
    private function mediosParaAsiento(array $mediosPago): array
    {
        $out = [];
        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monto = round((float) ($medio['monto'] ?? 0), 2);
            if ($cuentacajaId <= 0 || $monto < 0.009) {
                continue;
            }
            $out[] = [
                'cuentacaja_id' => $cuentacajaId,
                'monto' => $monto,
                'cotizacion' => 1.,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>  $mediosPago
     */
    private function registrarCobranzaSilenciosa(Venta $venta, array $mediosPago): void
    {
        try {
            $tipoCajaId = (int) config('mercadolibre.tipotransaccion_caja_id', 0);
            if ($tipoCajaId <= 0 || ! Tipotransaccion_Caja::query()->whereKey($tipoCajaId)->exists()) {
                $tipoCajaId = (int) (Tipotransaccion_Caja::query()
                    ->where('abreviatura', 'COB')
                    ->where('signo', 1)
                    ->orderBy('id')
                    ->value('id') ?? 0);
            }
            if ($tipoCajaId <= 0) {
                return;
            }
            $lineas = [];
            $total = 0.;
            foreach ($mediosPago as $medio) {
                $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
                $monto = (float) ($medio['monto'] ?? 0);
                if ($cuentacajaId <= 0 || $monto <= 0) {
                    continue;
                }
                $obs = 'Cobranza Mercado Libre';
                if (str_contains((string) $venta->leyenda, 'MP ')) {
                    $obs = (string) $venta->leyenda;
                }
                $lineas[] = [
                    'cuentacaja_id' => $cuentacajaId,
                    'moneda_id' => self::MONEDA_PESOS_ID,
                    'monto' => $monto,
                    'cotizacion' => 1.,
                    'observacion' => mb_substr($obs, 0, 191),
                ];
                $total += $monto;
            }
            if ($lineas === []) {
                return;
            }
            $this->cobranzaService->guardaCobranzaGastronomia([
                'venta' => $venta,
                'empresa_id' => (int) ($venta->empresa_id ?: config('mercadolibre.empresa_id', 1)),
                'tipotransaccion_caja_id' => $tipoCajaId,
                'totalfinalcobranza' => round($total, 2),
                'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
                'cotizacion_cobranza' => 1.,
                'lineas' => $lineas,
                'genera_contabilidad' => false,
                'detalle' => mb_substr('Cobranza Mercado Libre — '.$venta->codigo.' — '.(string) $venta->leyenda, 0, 255),
            ]);
        } catch (Throwable $e) {
            Log::warning('mercadolibre.cobranza', ['venta_id' => $venta->id, 'error' => $e->getMessage()]);
        }
    }
}
