<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioIvaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalReceptorSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Recupera en ERP una FAC Local ya autorizada en ARCA cuyo rollback dejó hueco de numeración.
 */
final class FacturacionLocalRecuperarComprobanteArcaService
{
    public function __construct(
        private readonly ArcaWsfeFacturaElectronicaService $arcaWsfeService,
        private readonly FacturacionService $facturacionService,
        private readonly FacturacionLocalCobranzaService $cobranzaService,
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    /**
     * @param  list<array{articulo_id:int,cantidad:float,precio:float,descuento?:float,combinacion_id?:int,talle_id?:int,descripcion?:string}>  $lineas
     * @param  list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>  $mediosPago
     * @return array<string, mixed>
     */
    public function recuperar(
        LocalVenta $local,
        int $numeroComprobante,
        array $lineas,
        array $mediosPago = [],
        bool $dryRun = false,
        ?string $identificadorPc = null,
    ): array {
        $pv = $local->puntoventaDefault();
        if (! $pv) {
            throw new InvalidArgumentException('El local no tiene punto de venta.');
        }

        $empresaId = (int) ($local->empresa_id ?: $pv->empresa_id ?: 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Sin empresa en el local/PV.');
        }

        $tipoFacId = $local->tipoFacId();
        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            (int) (\App\Models\Ventas\Tipotransaccion::query()->whereKey($tipoFacId)->value('codigo') ?? 1),
            'B',
        );
        $arca = $this->consultarArca($empresaId, (int) $pv->codigo, $codigoAfip, $numeroComprobante);

        $existente = Venta::query()
            ->where('puntoventa_id', $pv->id)
            ->where('numerocomprobante', $numeroComprobante)
            ->first();
        if ($existente !== null) {
            throw new InvalidArgumentException(
                'Ya existe venta ERP '.$existente->codigo.' (id '.$existente->id.').',
            );
        }

        if ($lineas === []) {
            throw new InvalidArgumentException('Indique al menos una línea (SKU/precio) para armar el comprobante.');
        }

        $receptor = FacturacionLocalReceptorSupport::resolver([
            'cliente_id' => null,
            'receptor' => [],
            'receptor_manual' => [],
        ]);

        $payload = $this->armarPayload($local, $pv, $numeroComprobante, $arca, $lineas, $receptor);

        if ($dryRun) {
            return [
                'ok' => true,
                'dry_run' => true,
                'arca' => $arca,
                'payload_resumen' => [
                    'local_id' => $local->id,
                    'puntoventa_id' => $pv->id,
                    'pv_codigo' => $pv->codigo,
                    'numerocomprobante_forzado' => $numeroComprobante,
                    'fechafactura' => $payload['fechafactura'],
                    'total_esperado_arca' => $arca['imp_total'],
                    'items' => count($payload['articulo_ids']),
                    'medios' => count($mediosPago),
                ],
            ];
        }

        $turno = $this->turnoService->turnoAbierto((int) $local->id, $identificadorPc);

        return DB::transaction(function () use (
            $local,
            $pv,
            $payload,
            $arca,
            $mediosPago,
            $turno,
            $numeroComprobante,
        ): array {
            $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
            if (! is_array($resultado) || ! empty($resultado['error'])) {
                throw new RuntimeException((string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'Error al grabar venta'));
            }

            $ventaId = (int) ($resultado['venta_id'] ?? 0);
            $venta = Venta::query()->find($ventaId);
            if (! $venta) {
                throw new RuntimeException('generaComprobanteGeneral no devolvió venta_id.');
            }

            $diff = abs(round((float) $venta->total, 2) - (float) $arca['imp_total']);
            if ($diff > 0.05) {
                throw new RuntimeException(sprintf(
                    'Total ERP %.2f no coincide con ARCA %.2f (diff %.2f). Abortando.',
                    (float) $venta->total,
                    (float) $arca['imp_total'],
                    $diff,
                ));
            }

            $caePendiente = $resultado['cae_pendiente'] ?? null;
            if (! is_array($caePendiente)) {
                throw new RuntimeException('Se esperaba cae_pendiente (omitir_solicitud_arca_cae).');
            }

            $this->facturacionService->completarSolicitudCaePendiente(
                $caePendiente,
                false,
                [
                    'cae' => (string) $arca['cae'],
                    'fechavencimientocae' => (string) $arca['fechavencimientocae'],
                ],
            );

            $venta->refresh();

            $cobranzaId = null;
            if ($mediosPago !== []) {
                $cob = $this->cobranzaService->registrar($venta, $local, $mediosPago, false);
                $cobranzaId = (int) ($cob['cobranza_id'] ?? 0);
            }

            if ($turno) {
                $this->turnoService->sumarFacturacion($turno, (float) $venta->total);
            }

            $emision = FacturacionLocalEmision::query()->create([
                'local_venta_id' => $local->id,
                'turno_operativo_local_id' => $turno?->id,
                'venta_id' => $venta->id,
                'venta_nc_id' => null,
                'vale_cliente_local_id' => null,
                'es_ticket_regalo' => false,
                'payload_resumen_json' => [
                    'recuperacion_arca' => true,
                    'numero' => $numeroComprobante,
                    'cae' => $arca['cae'],
                    'imp_total_arca' => $arca['imp_total'],
                    'usuario_id' => Auth::id(),
                    'pv' => $pv->codigo,
                ],
            ]);

            return [
                'ok' => true,
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'cae' => (string) $venta->cae,
                'total' => (float) $venta->total,
                'cobranza_id' => $cobranzaId,
                'emision_id' => (int) $emision->id,
                'arca' => $arca,
            ];
        });
    }

    /**
     * @return array{cae:string,fechavencimientocae:string,imp_total:float,imp_neto:float,fecha_proceso:string,fecha_comprobante:string,doc_tipo:int,doc_nro:string}
     */
    public function consultarArca(int $empresaId, int $ptoVta, int $cbteTipo, int $numero): array
    {
        $result = $this->arcaWsfeService->feCompConsultar($empresaId, $ptoVta, $cbteTipo, $numero);
        $rg = $result->ResultGet ?? null;
        if ($rg === null) {
            throw new InvalidArgumentException('ARCA no devolvió ResultGet para el comprobante '.$numero.'.');
        }

        $res = (string) ($rg->Resultado ?? '');
        if (! in_array($res, ['A', 'P'], true)) {
            throw new InvalidArgumentException('Comprobante '.$numero.' no autorizado en ARCA (Resultado='.$res.').');
        }

        $cae = trim((string) ($rg->CodAutorizacion ?? ''));
        $vto = trim((string) ($rg->FchVto ?? ''));
        if ($cae === '' || $vto === '') {
            throw new InvalidArgumentException('ARCA sin CAE/vencimiento para comprobante '.$numero.'.');
        }

        return [
            'cae' => $cae,
            'fechavencimientocae' => $vto,
            'imp_total' => round((float) ($rg->ImpTotal ?? 0), 2),
            'imp_neto' => round((float) ($rg->ImpNeto ?? 0), 2),
            'fecha_proceso' => $this->formatearFechaArca((string) ($rg->FchProceso ?? '')),
            'fecha_comprobante' => $this->formatearFechaArca((string) ($rg->CbteFch ?? '')),
            'doc_tipo' => (int) ($rg->DocTipo ?? 99),
            'doc_nro' => (string) ($rg->DocNro ?? '0'),
        ];
    }

    /**
     * @param  list<array{articulo_id:int,cantidad:float,precio:float,descuento?:float,combinacion_id?:int,talle_id?:int,descripcion?:string}>  $lineas
     * @param  array<string, mixed>  $arca
     * @param  array<string, mixed>  $receptor
     * @return array<string, mixed>
     */
    private function armarPayload(
        LocalVenta $local,
        Puntoventa $pv,
        int $numeroComprobante,
        array $arca,
        array $lineas,
        array $receptor,
    ): array {
        $fecha = $arca['fecha_comprobante'] !== '' ? $arca['fecha_comprobante'] : ($arca['fecha_proceso'] ?: now()->format('Y-m-d'));

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
            $cantidades[] = (float) $linea['cantidad'];
            $precios[] = (float) $linea['precio'];
            $descuentos[] = (float) ($linea['descuento'] ?? 0);
            $descripciones[] = (string) ($linea['descripcion'] ?? '');
            $combinacionIds[] = (int) ($linea['combinacion_id'] ?? 0);
            $talleIds[] = (int) ($linea['talle_id'] ?? 0);
            $colorIds[] = (int) ($linea['color_id'] ?? 0);
        }

        $clienteId = (int) ($receptor['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            $clienteId = FacturacionLocalReceptorSupport::clienteContadoId();
        }

        $letra = strtoupper(trim((string) ($receptor['letra'] ?? 'B')));
        if ($letra === '') {
            $letra = 'B';
        }
        $listaId = (int) ($local->listaprecio_id ?: 0);
        $prepIva = FacturacionLocalPrecioIvaSupport::prepararParaEmision(
            $precios,
            $articuloIds,
            $listaId,
            $letra,
            true, // precios de lista / comando: cruzar incluyeimpuesto
        );

        $payload = [
            'empresa_id' => (int) ($local->empresa_id ?: $pv->empresa_id),
            'puntoventa_id' => (int) $pv->id,
            'tipotransaccion_id' => $local->tipoFacId(),
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
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'vendedor_id' => Auth::id(),
            'numerocomprobante_forzado' => $numeroComprobante,
            'leyendafactura' => 'Recuperación ARCA FAC B-'.str_pad((string) $pv->codigo, 5, '0', STR_PAD_LEFT).'-'.str_pad((string) $numeroComprobante, 8, '0', STR_PAD_LEFT).' (rollback POS Local).',
            'opciones_emision' => [
                'omitir_movimiento_stock' => false,
                'permitir_caea' => false,
                'origen_facturacion_local' => true,
                'omitir_cuenta_corriente' => true,
                'omitir_solicitud_arca_cae' => true,
            ],
            'arca_receptor' => $receptor['arca_receptor'] ?? ['tipodoc' => 99, 'nrodoc' => 0],
            'venta_receptor' => $receptor['venta_receptor'] ?? [],
            'omitir_percepciones' => true,
            '_descuentos_linea_item' => $descuentos,
        ];

        if (FacturacionLocalPosContextoSupport::pvUsaWebservice($pv)
            && strtoupper(trim((string) ($pv->modofacturacion ?? ''))) === 'M'
        ) {
            $payload['forzar_modofacturacion'] = 'C';
        }

        return $payload;
    }

    private function formatearFechaArca(string $ymd): string
    {
        $ymd = preg_replace('/\D/', '', $ymd) ?? '';
        if (strlen($ymd) === 8) {
            return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
        }

        return '';
    }

    /**
     * Helper: arma líneas desde SKU + precio neto (+ dto %).
     *
     * @return list<array{articulo_id:int,cantidad:float,precio:float,descuento:float,combinacion_id:int,talle_id:int,descripcion:string}>
     */
    public static function lineaDesdeSku(
        string $sku,
        float $precio,
        float $cantidad = 1.,
        float $descuentoPct = 0.,
        int $combinacionId = 0,
        int $talleId = 0,
    ): array {
        $articulo = Articulo::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)->orWhere('sku', ltrim($sku, '0'));
            })
            ->first();
        if (! $articulo) {
            throw new InvalidArgumentException('SKU '.$sku.' inexistente en ERP.');
        }

        return [[
            'articulo_id' => (int) $articulo->id,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'descuento' => $descuentoPct,
            'combinacion_id' => $combinacionId,
            'talle_id' => $talleId,
            'descripcion' => (string) $articulo->descripcion,
        ]];
    }
}
