<?php

namespace App\Services\Caja;

use App\Models\Caja\Cobranza_Descuento;
use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\Venta;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\CobranzaDescuentoConfigSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Ventas\NotaCreditoPercepcionIibbSupport;
use Exception;
use InvalidArgumentException;

final class CobranzaDescuentoNotaCreditoService
{
    public const CLAVE_COMPROBANTES_ANITA = '_cobranza_comprobantes_anita';

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

        if (ParametroSistemaSupport::ncpProntoPagoCuentaIdParaEmpresa($empresaId) <= 0) {
            throw new InvalidArgumentException(
                'Falta la cuenta del neto de la nota de crédito por descuento. Cargala en Configuración → Configuración general.'
            );
        }

        $letra = CobranzaDescuentoConfigSupport::extraerLetraDesdeCodigoVenta($ventaOrigen->codigo);
        $puntoventaId = CobranzaDescuentoConfigSupport::puntoventaIdDesdeFactura(
            (int) ($ventaOrigen->puntoventa_id ?? 0)
        );
        $tipoNcId = CobranzaDescuentoConfigSupport::tipotransaccionNotaCreditoId($empresaId, $letra);
        $articuloId = CobranzaDescuentoConfigSupport::articuloIdParaDescuento($empresaId);

        $emisionReferencia = $ventaOrigen->venta_emisiones
            ->first(fn ($e) => (int) ($e->articulo_id ?? 0) > 0)
            ?? $ventaOrigen->venta_emisiones->first();

        $impuestoId = (int) ($emisionReferencia->impuesto_id ?? config('facturacion.IMPUESTO_ID', 3));
        // El importe del descuento es el precio final (lo que el cliente paga de menos).
        // El motor desglosa IVA y, si la factura origen percibió, IIBB desde ese total.
        $precioConIva = $this->precioConIvaParaTotalFinal($importe, $impuestoId, (int) $ventaOrigen->id);

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
            'actividad_arca_id' => CobranzaDescuentoConfigSupport::actividadArcaIdDesdeFactura(
                $ventaOrigen->actividad_arca_id !== null ? (int) $ventaOrigen->actividad_arca_id : null,
                $puntoventaId,
            ),
            'cliente_id' => (int) $ventaOrigen->cliente_id,
            'moneda_id' => (int) $ventaOrigen->moneda_id,
            'listaprecio_id' => 1,
            'descuentolinea' => 0.,
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'articulo_ids' => [$articuloId],
            'cantidades' => [1.],
            'precios' => [$precioConIva],
            'descripcionarticulos' => ['Descuento por cobranza — '.$referenciaCompro],
            'impuesto_ids' => [$impuestoId],
            'incluyeimpuestos' => ['1'],
            NotaCreditoPercepcionIibbSupport::FLAG_NCP => true,
            // NCP de descuento no devuelve mercadería. En Ferli el tipo tiene operación de stock Entrada.
            'opciones_emision' => [
                'omitir_movimiento_stock' => true,
            ],
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
     * El descuento es el total de la NCP. Si la factura origen tiene percepción IIBB
     * de una jurisdicción que la nota prorratea, el precio con IVA deja lugar a
     * esa percepción. Buenos Aires y CABA no se restituyen en un descuento.
     */
    private function precioConIvaParaTotalFinal(float $final, int $impuestoId, int $ventaOrigenId): float
    {
        $final = round($final, 2);
        $tasa = (float) (Impuesto::query()->whereKey($impuestoId)->value('valor') ?? 0);
        if ($tasa <= 0.00001 || ! CobranzaDescuentoConfigSupport::ncPercibeIibb()) {
            return $final;
        }

        $venta = Venta::query()->with('venta_impuestos')->find($ventaOrigenId);
        if (! $venta) {
            return $final;
        }

        $filas = NotaCreditoPercepcionIibbSupport::extraerFilasIibb($venta->venta_impuestos);
        $percepcion = 0.0;
        foreach ($filas as $fila) {
            if (NotaCreditoPercepcionIibbSupport::esFilaBuenosAires($fila)
                || NotaCreditoPercepcionIibbSupport::esFilaCaba($fila)) {
                continue;
            }
            $percepcion += abs((float) ($fila['importe'] ?? 0));
        }
        $netoOrigen = NotaCreditoPercepcionIibbSupport::netoOrigen($filas);
        if ($percepcion < 0.01 || $netoOrigen < 0.01) {
            return $final;
        }

        $k = $percepcion / $netoOrigen;
        $unoMasTasa = 1 + ($tasa / 100);
        $divisor = 1 + ($k / $unoMasTasa);
        if ($divisor <= 1.0000001) {
            return $final;
        }

        return round($final / $divisor, 2);
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

        $this->descontarNotasCreditoDelAplicado($data, $emitidos);

        return $emitidos;
    }

    /**
     * La grilla trae el aplicado bruto (efectivo + descuento). La emisión ya aplicó
     * la NCP a su factura: la cobranza aplica solo la diferencia, que es el efectivo.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $emitidos
     */
    public function descontarNotasCreditoDelAplicado(array &$data, array $emitidos): void
    {
        if ($emitidos === []) {
            return;
        }

        $data[self::CLAVE_COMPROBANTES_ANITA] = $this->comprobantesParaAnita($data, $emitidos);
        $ccIds = array_map('intval', (array) ($data['idcuentacorrientes'] ?? []));

        foreach ($emitidos as $nc) {
            $ccOrigenId = (int) $nc['cliente_cuentacorriente_origen_id'];
            $importe = round(abs((float) $nc['importe_calculado']), 2);
            $indice = array_search($ccOrigenId, $ccIds, true);
            if ($indice === false) {
                throw new InvalidArgumentException(
                    'La factura del descuento '.$nc['codigo_nc'].' no está entre los comprobantes de la cobranza.'
                );
            }

            $aplicado = round((float) ($data['montoaplicadocomprobantes'][$indice] ?? 0), 2);
            if ($aplicado + 0.005 < $importe) {
                throw new InvalidArgumentException(
                    'El descuento '.number_format($importe, 2, ',', '.').' supera el importe aplicado a la factura ('
                    .number_format($aplicado, 2, ',', '.').').'
                );
            }

            $data['montoaplicadocomprobantes'][$indice] = round($aplicado - $importe, 2);
        }
    }

    /**
     * Anita no aplica la NCP a la factura: la cancela el recibo. El COB lleva la
     * factura por el bruto y la NCP en negativo, como lo graba Anita.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $emitidos
     * @return array<string, list<mixed>>
     */
    private function comprobantesParaAnita(array $data, array $emitidos): array
    {
        $claves = [
            'idcuentacorrientes', 'idventas', 'codigocomprobantes', 'monedacomprobante_ids',
            'montoaplicadocomprobantes', 'cotizacioncomprobantes', 'fechacomprobantes',
            'fechavencimientocomprobantes', 'montocomprobantes', 'saldocomprobantes',
        ];
        $anita = [];
        foreach ($claves as $clave) {
            $anita[$clave] = array_values((array) ($data[$clave] ?? []));
        }

        $fecha = $data['fecha'] ?? date('Y-m-d');
        foreach ($emitidos as $nc) {
            $importe = round(abs((float) $nc['importe_calculado']), 2);
            $anita['idcuentacorrientes'][] = (int) $nc['cliente_cuentacorriente_nc_id'];
            $anita['idventas'][] = (int) $nc['venta_nc_id'];
            $anita['codigocomprobantes'][] = (string) $nc['codigo_nc'];
            $anita['monedacomprobante_ids'][] = (int) $nc['moneda_id'];
            $anita['montoaplicadocomprobantes'][] = -$importe;
            $anita['cotizacioncomprobantes'][] = (float) $nc['cotizacion'];
            $anita['fechacomprobantes'][] = $fecha;
            $anita['fechavencimientocomprobantes'][] = $fecha;
            $anita['montocomprobantes'][] = $importe;
            $anita['saldocomprobantes'][] = 0;
        }

        return $anita;
    }

    public static function debeEmitirNotasCredito(string $estado): bool
    {
        return strtoupper(trim($estado)) === 'CONFIRMADA';
    }
}
