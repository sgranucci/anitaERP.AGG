@if (! ($facturaPdfSinEnvelope ?? false))
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @include('exports.ventas.partials.formulariofactura_estilos')
</head>
<body>
@endif
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\FacturaPdfPaginacionSupport;

    $facturaPdfEsElBierzo = config('app.empresa') === 'EL BIERZO';
    $facturaPdfCeldaTotales = 'background-color: #e9ecef; border: 1px solid #dee2e6;';
    $facturaPdfPieCentroTieneTexto = ($letra === 'B') || $facturaPdfEsElBierzo;
    if (empty($logoEmpresaDataUri)) {
        $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($venta->puntoventas->empresas->nombre ?? null);
        $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
    }
    if (empty($qrDataUri) && ! empty($output_file)) {
        $qrPath = public_path('storage/'.$output_file);
        if (is_file($qrPath)) {
            $qrDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($qrPath));
        }
    }
    $qrDataUri = $qrDataUri ?? '';
    $facturaEsGastronomia = $venta->gastronomiaEmision !== null;
    $facturaEsEstacionamiento = $venta->estacionamientoEmision !== null;
    $facturaPdfRemitoDebajoCliente = ! $facturaEsGastronomia && ! $facturaEsEstacionamiento;
    if ($facturaEsGastronomia) {
        $lineaClienteFactura = \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreClientePie($venta);
    } else {
        $codigoClienteFactura = trim((string) ($venta->clientes->codigo ?? ''));
        $nombreClienteFactura = trim((string) ($venta->clientes->nombre ?? ''));
        $lineaClienteFactura = $codigoClienteFactura !== '' && $nombreClienteFactura !== ''
            ? $codigoClienteFactura.' - '.$nombreClienteFactura
            : ($nombreClienteFactura !== '' ? $nombreClienteFactura : $codigoClienteFactura);
    }

    $itemsFactura = array_values(is_array($tblItem) ? $tblItem : []);
    $totalesDocumento = [
        'cantidad' => 0.0,
        'kilodescuento' => 0.0,
    ];
    foreach ($itemsFactura as $it) {
        $totalesDocumento['cantidad'] += (float) ($it['cantidad'] ?? 0);
        $totalesDocumento['kilodescuento'] += (float) ($it['kilodescuento'] ?? 0);
    }
    $ivaContenido = 0.0;
    $impuestoInterno = 0.0;
    foreach ($conceptosTotales as $itemTotal) {
        if (strpos((string) ($itemTotal['concepto'] ?? ''), 'Iva') !== false) {
            $ivaContenido += (float) $itemTotal['importe'];
        }
        if (($itemTotal['concepto'] ?? '') === 'Impuesto Interno') {
            $impuestoInterno += (float) $itemTotal['importe'];
        }
    }
    $tipoPaginacion = $facturaPdfRemitoDebajoCliente ? 'admin' : 'pos';
    $paginasFactura = FacturaPdfPaginacionSupport::paginas($itemsFactura, $tipoPaginacion);
    $paginasRemito = FacturaPdfPaginacionSupport::paginas($itemsFactura, 'remito');
    $mostrarHojaFactura = ! ($facturaPdfSoloHojaRemito ?? false);
    $mostrarHojaRemito = ($facturaPdfEsElBierzo && ! ($facturaPdfOmitirHojaRemito ?? false))
        || ($facturaPdfSoloHojaRemito ?? false);
    $valorAsegurado = \App\Support\Ventas\RemitoValorAseguradoSupport::desdeRemitoOItemsFactura(
        $venta->remitos?->remito_articulos,
        $itemsFactura
    );
    $leyendasRemito = \App\Support\Ventas\RemitoFormularioLeyendaSupport::desdeVenta($venta);
    $totalKilosRemito = (float) ($totalesDocumento['cantidad'] ?? 0);
    $totalBultosRemito = (float) ($venta->cantidadbulto ?? 0);
    $totalPiezasRemito = 0.0;
    foreach ($itemsFactura as $itRemitoTot) {
        $totalPiezasRemito += (float) ($itRemitoTot['pieza'] ?? 0);
        if ((float) ($venta->cantidadbulto ?? 0) <= 0.00001) {
            $totalBultosRemito += (float) ($itRemitoTot['caja'] ?? 0);
        }
    }
@endphp
<div id="area-pdf">
    @if ($mostrarHojaFactura)
        @foreach ($paginasFactura as $pagIdx => $itemsPagina)
            @php
                $esPrimera = $pagIdx === 0;
                $esUltima = $pagIdx === array_key_last($paginasFactura);
                $salto = ($facturaPdfSaltoAntes ?? false) || $pagIdx > 0;
            @endphp
            <div class="page factura-pagina {{ $salto ? 'salto-pagina' : '' }}">
                @include('exports.ventas.partials.formulariofactura_encabezado', ['esRemitoHoja' => false])
                @include('exports.ventas.partials.formulariofactura_items', [
                    'itemsPagina' => $itemsPagina,
                    'mostrarPrecios' => true,
                    'mostrarBonificacion' => $facturaPdfEsElBierzo,
                    'mostrarTotalesFila' => $esUltima,
                    'totalesDocumento' => $totalesDocumento,
                ])
                @if ($esUltima)
                    @include('exports.ventas.partials.formulariofactura_pie')
                @endif
            </div>
        @endforeach
    @endif

    @if ($mostrarHojaRemito)
        @foreach ($paginasRemito as $pagIdx => $itemsPagina)
            @php
                $esUltima = $pagIdx === array_key_last($paginasRemito);
                $saltoRemito = $mostrarHojaFactura
                    || ($facturaPdfSaltoAntes ?? false)
                    || $pagIdx > 0;
            @endphp
            <div class="page factura-pagina {{ $saltoRemito ? 'salto-pagina' : '' }}">
                @include('exports.ventas.partials.formulariofactura_encabezado', ['esRemitoHoja' => true])
                @include('exports.ventas.partials.formulariofactura_items', [
                    'itemsPagina' => $itemsPagina,
                    'mostrarPrecios' => false,
                    'mostrarBonificacion' => false,
                    'mostrarTotalesFila' => $esUltima,
                    'totalesDocumento' => $totalesDocumento,
                    'esRemitoHoja' => true,
                    'totalPiezasRemito' => $totalPiezasRemito,
                ])
                @if ($esUltima)
                    @include('exports.ventas.partials.formulariofactura_pie_remito')
                @endif
            </div>
        @endforeach
    @endif
</div>
@if (! ($facturaPdfSinEnvelope ?? false))
</body>
</html>
@endif
