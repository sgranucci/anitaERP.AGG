<?php

namespace App\Services\Caja;

use App\Models\Caja\Cobranza_Descuento;
use App\Models\Ventas\Venta;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\CobranzaDescuentoConfigSupport;
use Exception;
use InvalidArgumentException;

final class CobranzaDescuentoNotaCreditoService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly Cliente_CuentacorrienteRepositoryInterface $clienteCuentacorrienteRepository,
    ) {}

    /**
     * @return array{
     *     venta_origen_id:int,
     *     cliente_cuentacorriente_origen_id:int,
     *     venta_nc_id:int,
     *     cliente_cuentacorriente_nc_id:int,
     *     codigo_nc:string,
     *     importe_calculado:float,
     *     moneda_id:int,
     *     cotizacion:float,
     *     tipo:string,
     *     valor:float,
     *     leyenda:?string
     * }
     */
    public function emitirNotaCreditoDescuento(
        int $empresaId,
        int $ventaOrigenId,
        int $clienteCuentacorrienteOrigenId,
        string $tipo,
        float $valor,
        float $importeCalculado,
        string $fechaCobranza,
        ?string $leyendaUsuario = null,
    ): array {
        if (! CobranzaDescuentoConfigSupport::habilitado()) {
            throw new InvalidArgumentException('Los descuentos con nota de crédito en cobranza están deshabilitados.');
        }

        $ventaOrigen = Venta::query()
            ->with(['tipotransacciones', 'venta_emisiones'])
            ->find($ventaOrigenId);

        if (! $ventaOrigen) {
            throw new InvalidArgumentException('No se encontró la factura origen id '.$ventaOrigenId.'.');
        }

        if ((int) $ventaOrigen->empresa_id !== $empresaId && $ventaOrigen->empresa_id !== null) {
            throw new InvalidArgumentException('La factura origen no pertenece a la empresa de la cobranza.');
        }

        $tipoFactura = $ventaOrigen->tipotransacciones;
        if ($tipoFactura && $tipoFactura->signo !== 'S') {
            throw new InvalidArgumentException('Solo puede aplicar descuento sobre facturas de venta.');
        }

        if ((float) $ventaOrigen->total <= 0.) {
            throw new InvalidArgumentException('La factura origen no tiene importe positivo.');
        }

        $ccOrigen = $this->clienteCuentacorrienteRepository->find($clienteCuentacorrienteOrigenId);
        if ((int) $ccOrigen->venta_id !== $ventaOrigenId) {
            throw new InvalidArgumentException('El comprobante de cuenta corriente no corresponde a la factura indicada.');
        }

        $importe = round($importeCalculado, 2);
        if ($importe <= 0.) {
            throw new InvalidArgumentException('El importe del descuento debe ser mayor a cero.');
        }

        $saldoFactura = abs((float) $ccOrigen->total);
        if ($importe - $saldoFactura > 0.01) {
            throw new InvalidArgumentException(
                'El descuento ($ '.number_format($importe, 2, ',', '.')
                .') supera el saldo del comprobante ($ '.number_format($saldoFactura, 2, ',', '.').').'
            );
        }

        $letra = CobranzaDescuentoConfigSupport::extraerLetraDesdeCodigoVenta($ventaOrigen->codigo);
        $puntoventaId = CobranzaDescuentoConfigSupport::puntoventaIdParaEmpresa($empresaId);
        $tipoNcId = CobranzaDescuentoConfigSupport::tipotransaccionNotaCreditoId($empresaId, $letra);
        $articuloId = CobranzaDescuentoConfigSupport::articuloIdParaDescuento($empresaId);

        $emisionReferencia = $ventaOrigen->venta_emisiones
            ->first(fn ($e) => (int) ($e->articulo_id ?? 0) > 0)
            ?? $ventaOrigen->venta_emisiones->first();

        $impuestoId = (int) ($emisionReferencia->impuesto_id ?? config('facturacion.IMPUESTO_ID', 3));
        $incluyeImpuesto = (string) ($emisionReferencia->incluyeimpuesto ?? '1');
        $incluyeImpuesto = in_array($incluyeImpuesto, ['S', '1', 'Y'], true) ? '1' : 'N';

        $referenciaCompro = (string) ($ventaOrigen->codigo ?? $ventaOrigen->id);
        $leyendaManual = trim((string) $leyendaUsuario);
        if ($leyendaManual !== '') {
            $leyendaNc = $leyendaManual.' (NC descuento cobranza — '.$referenciaCompro.')';
        } else {
            $leyendaNc = 'Descuento por cobranza — comprobante '.$referenciaCompro;
        }
        if (mb_strlen($leyendaNc) > 255) {
            $leyendaNc = mb_substr($leyendaNc, 0, 255);
        }

        $payload = [
            'venta_id' => (int) $ventaOrigen->id,
            'tipotransaccion_id' => $tipoNcId,
            'puntoventa_id' => $puntoventaId,
            'fechafactura' => $fechaCobranza,
            'leyendafactura' => $leyendaNc,
            'actividad_arca_id' => (int) ($ventaOrigen->actividad_arca_id ?? 1),
            'cliente_id' => (int) $ventaOrigen->cliente_id,
            'moneda_id' => (int) $ventaOrigen->moneda_id,
            'listaprecio_id' => 1,
            'descuentolinea' => 0.,
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'articulo_ids' => [$articuloId],
            'cantidades' => [1.],
            'precios' => [$importe],
            'descripcionarticulos' => ['Descuento por cobranza — '.$referenciaCompro],
            'impuesto_ids' => [$impuestoId],
            'incluyeimpuestos' => [$incluyeImpuesto],
        ];

        if (trim((string) ($ventaOrigen->nombre ?? '')) !== '' || trim((string) ($ventaOrigen->numerodocumento ?? '')) !== '') {
            $payload['venta_receptor'] = [
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ];
            $payload['arca_receptor'] = array_filter([
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $resultado = $this->facturacionService->generaComprobanteGeneral($payload);

        if (! empty($resultado['error'])) {
            throw new Exception(is_string($resultado['error']) ? $resultado['error'] : 'Error al emitir nota de crédito en ARCA.');
        }

        $ventaNcId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaNcId <= 0) {
            throw new Exception('No se pudo recuperar la nota de crédito generada.');
        }

        $ventaNc = Venta::query()->find($ventaNcId);
        $ccNcRows = $this->clienteCuentacorrienteRepository->findPorVenta($ventaNcId);
        $ccNc = $ccNcRows->first();
        if (! $ccNc) {
            throw new Exception('La nota de crédito no generó movimiento en cuenta corriente.');
        }

        return [
            'venta_origen_id' => $ventaOrigenId,
            'cliente_cuentacorriente_origen_id' => $clienteCuentacorrienteOrigenId,
            'venta_nc_id' => $ventaNcId,
            'cliente_cuentacorriente_nc_id' => (int) $ccNc->id,
            'codigo_nc' => (string) ($ventaNc->codigo ?? $resultado['factura'] ?? ''),
            'importe_calculado' => $importe,
            'moneda_id' => (int) $ventaOrigen->moneda_id,
            'cotizacion' => (float) ($ventaOrigen->cotizacion ?: 1.),
            'tipo' => $tipo,
            'valor' => $valor,
            'leyenda' => $leyendaManual !== '' ? $leyendaManual : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public function parseDescuentosDesdeRequest(array $data): array
    {
        $ventaIds = $data['descuento_venta_origen_ids'] ?? [];
        if (! is_array($ventaIds)) {
            return [];
        }

        $ccIds = $data['descuento_cc_origen_ids'] ?? [];
        $tipos = $data['descuento_tipos'] ?? [];
        $valores = $data['descuento_valores'] ?? [];
        $importes = $data['descuento_importes'] ?? [];
        $leyendas = $data['descuento_leyendas'] ?? [];

        $descuentos = [];
        for ($i = 0; $i < count($ventaIds); $i++) {
            $ventaId = (int) ($ventaIds[$i] ?? 0);
            $importe = round((float) ($importes[$i] ?? 0), 2);
            if ($ventaId <= 0 || $importe <= 0.) {
                continue;
            }

            $descuentos[] = [
                'venta_origen_id' => $ventaId,
                'cliente_cuentacorriente_origen_id' => (int) ($ccIds[$i] ?? 0),
                'tipo' => (string) ($tipos[$i] ?? Cobranza_Descuento::TIPO_IMPORTE),
                'valor' => (float) ($valores[$i] ?? 0),
                'importe_calculado' => $importe,
                'leyenda' => trim((string) ($leyendas[$i] ?? '')) ?: null,
            ];
        }

        return $descuentos;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $descuentos
     */
    public function fusionarNotasCreditoEnComprobantes(array &$data, array $emitidos): void
    {
        foreach ($emitidos as $nc) {
            $importe = (float) $nc['importe_calculado'];
            $data['idcuentacorrientes'][] = (int) $nc['cliente_cuentacorriente_nc_id'];
            $data['idventas'][] = (int) $nc['venta_nc_id'];
            $data['codigocomprobantes'][] = (string) $nc['codigo_nc'];
            $data['monedacomprobante_ids'][] = (int) $nc['moneda_id'];
            $data['montoaplicadocomprobantes'][] = -abs($importe);
            $data['cotizacioncomprobantes'][] = (float) $nc['cotizacion'];
            $data['fechacomprobantes'][] = $data['fecha'] ?? date('Y-m-d');
            $data['fechavencimientocomprobantes'][] = $data['fecha'] ?? date('Y-m-d');
            $data['montocomprobantes'][] = abs($importe);
            $data['saldocomprobantes'][] = 0;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $descuentos
     * @param  list<array<string, mixed>>  $emitidos
     */
    public function persistirDescuentos(int $cobranzaId, array $descuentos, array $emitidos): void
    {
        Cobranza_Descuento::query()->where('cobranza_id', $cobranzaId)->delete();

        $emitidosPorVenta = [];
        foreach ($emitidos as $row) {
            $emitidosPorVenta[(int) $row['venta_origen_id']] = $row;
        }

        foreach ($descuentos as $desc) {
            $ventaId = (int) $desc['venta_origen_id'];
            $emitido = $emitidosPorVenta[$ventaId] ?? null;

            Cobranza_Descuento::query()->create([
                'cobranza_id' => $cobranzaId,
                'venta_origen_id' => $ventaId,
                'cliente_cuentacorriente_origen_id' => (int) $desc['cliente_cuentacorriente_origen_id'],
                'venta_nc_id' => $emitido ? (int) $emitido['venta_nc_id'] : null,
                'cliente_cuentacorriente_nc_id' => $emitido ? (int) $emitido['cliente_cuentacorriente_nc_id'] : null,
                'tipo' => (string) $desc['tipo'],
                'valor' => (float) $desc['valor'],
                'importe_calculado' => (float) $desc['importe_calculado'],
                'leyenda' => $desc['leyenda'] ?? null,
                'estado' => $emitido ? Cobranza_Descuento::ESTADO_EMITIDA : Cobranza_Descuento::ESTADO_PENDIENTE,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $descuentos
     * @return list<array<string, mixed>>
     */
    public function emitirDescuentosPendientes(array &$data, array $descuentos): array
    {
        if ($descuentos === []) {
            return [];
        }

        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $fecha = (string) ($data['fecha'] ?? date('Y-m-d'));
        $emitidos = [];

        foreach ($descuentos as $desc) {
            if ((int) ($desc['cliente_cuentacorriente_origen_id'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Falta el comprobante de cuenta corriente para el descuento.');
            }

            $emitidos[] = $this->emitirNotaCreditoDescuento(
                $empresaId,
                (int) $desc['venta_origen_id'],
                (int) $desc['cliente_cuentacorriente_origen_id'],
                (string) $desc['tipo'],
                (float) $desc['valor'],
                (float) $desc['importe_calculado'],
                $fecha,
                $desc['leyenda'] ?? null,
            );
        }

        $this->fusionarNotasCreditoEnComprobantes($data, $emitidos);

        return $emitidos;
    }

    public static function debeEmitirNotasCredito(string $estado): bool
    {
        return strtoupper(trim($estado)) === 'CONFIRMADA';
    }
}
