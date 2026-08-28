@php
    $esRemitoHoja = (bool) ($esRemitoHoja ?? false);
    $codigoPvRemito = trim((string) (
        $venta->puntoventaremito?->codigo
        ?? $venta->remitos?->puntoventas?->codigo
        ?? ''
    ));
    $numeroRemitoPdf = (int) ($venta->numeroremito ?? $venta->remitos?->numero ?? 0);
    $nroRemitoFormateado = \App\Support\Ventas\VentaNumeracionEmpresaSupport::formatearPuntoVentaNumero(
        $codigoPvRemito,
        $numeroRemitoPdf
    );
@endphp
<table class="table borderless factura-cabecera {{ $facturaPdfRemitoDebajoCliente && ! $esRemitoHoja ? 'factura-cabecera-admin' : '' }}">
    <tr>
        <td class="factura-cabecera-logo">
            @if ($logoEmpresaDataUri)
                <img width="160" height="70" src="{{ $logoEmpresaDataUri }}" alt="">
            @endif
            <div>
                <strong class="factura-empresa-nombre">{{ $venta->puntoventas->empresas->nombre }}</strong>
                <p class="factura-empresa-datos">
                    {{ $venta->puntoventas->domicilio }}<br>
                    {{ $venta->puntoventas->localidades->nombre }} ({{ $venta->puntoventas->codigopostal }})<br>
                    {{ $venta->puntoventas->provincias->nombre }}<br>
                    IVA RESPONSABLE INSCRIPTO
                </p>
            </div>
        </td>
        <td class="factura-cabecera-letra">
            <div class="factura-letra-caja">{{ $esRemitoHoja ? 'R' : $letra }}</div>
            <div class="factura-codigo-tipo">Código {{ $esRemitoHoja ? '091' : ($codigoTipoTransaccionPad ?? $codigoTipoTransaccion) }}</div>
        </td>
        <td class="factura-cabecera-comprobante">
            <strong>{{ $esRemitoHoja ? 'REMITO' : ($nombreTipoComprobanteImpresion ?? $venta->tipotransacciones->nombre ?? '') }}</strong><br>
            <strong>Nro. {{ $esRemitoHoja ? $nroRemitoFormateado : $venta->codigo }}</strong>
            <p>
                Fecha emisi&oacute;n: {{ date('d/m/Y', strtotime($venta->fecha ?? '')) }}<br>
                C.U.I.T.: {{ $venta->puntoventas->empresas->nroinscripcion }}<br>
                Ingresos Brutos: {{ $venta->puntoventas->empresas->numeroiibb }}<br>
                Inicio de Actividades: {{ date('d/m/Y', strtotime($venta->puntoventas->empresas->fechainicioactividad)) }}
            </p>
            <p>{{ $copiaLeyenda ?? 'ORIGINAL' }}</p>
        </td>
    </tr>
    @if ($esRemitoHoja)
    <tr>
        <td>Factura: {{ $venta->codigo }}</td>
        <td>
            @if (isset($venta->transportes->codigo))
                Reparto: {{ $venta->transportes->codigo }}
            @endif
        </td>
        <td class="text-right">
            <strong>Valor asegurado: {{ number_format($valorAsegurado ?? 0, 2) }}</strong>
        </td>
    </tr>
    @elseif (! $facturaPdfRemitoDebajoCliente)
    <tr>
        <td>Remito: {{ $nroRemitoFormateado }}</td>
        <td>@if (isset($venta->transportes->codigo)) Reparto: {{ $venta->transportes->codigo }} @endif</td>
        <td class="text-right">Condicion de Venta: {{ $venta->condicionventas->nombre ?? $venta->clientes->condicionventas->nombre ?? 'CONTADO' }}</td>
    </tr>
    @endif
</table>
@if ($facturaPdfRemitoDebajoCliente && ! $esRemitoHoja)
<table class="table borderless factura-cabecera-admin">
    <tr class="factura-cabecera-admin-linea"><td colspan="3">&nbsp;</td></tr>
</table>
@endif
<table class="table borderless factura-bloque-cliente-admin">
    <tr>
        <td class="factura-cliente-izq">
            <strong>Cliente: {{ $lineaClienteFactura }}</strong>
            <p>
                {{ \App\Support\Ventas\GastronomiaVentaDisplaySupport::domicilioReceptorFactura($venta) }}<br>
                @if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
                    {{ $venta->clientes->localidades->nombre ?? '' }} ({{ $venta->clientes->codigopostal ?? '' }})<br>
                    {{ $venta->clientes->provincias->nombre ?? '' }} {{ $venta->clientes->paises->nombre ?? '' }}<br>
                @endif
                @if (isset($venta->transportes->nombre))
                    Transporte: {{ $venta->transportes->nombre }}<br>
                @endif
                @if (! empty($venta->lugarentrega))
                    Lugar de entrega: {{ $venta->lugarentrega }}<br>
                @endif
                @include('exports.ventas.partials.papelito_waitry_factura', ['venta' => $venta])
            </p>
        </td>
        <td class="factura-cliente-der">
            <p>
                @php $codCli = \App\Support\Ventas\GastronomiaVentaDisplaySupport::codigoClienteMaestro($venta); @endphp
                @if ($facturaEsGastronomia && $codCli !== '')
                    Código: {{ $codCli }}<br>
                @endif
                @if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
                    Teléfono: {{ $venta->clientes->telefono }}<br>
                @endif
                I.V.A.: {{ $venta->clientes->condicionivas->nombre }}<br>
                @php
                    $docReceptorFactura = \App\Support\Ventas\GastronomiaVentaDisplaySupport::documentoReceptorFactura($venta);
                    $etiqDocReceptorFactura = \App\Support\Ventas\GastronomiaVentaDisplaySupport::abreviaturaDocumentoReceptorFactura($venta);
                @endphp
                @if ($docReceptorFactura !== '')
                    {{ $etiqDocReceptorFactura }}: {{ $docReceptorFactura }}<br>
                @endif
                @if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta))
                    Ingresos Brutos: {{ $venta->clientes->condicioniibbs->nombre }} {{ $venta->clientes->nroiibb }}
                @endif
            </p>
        </td>
    </tr>
</table>
@if ($facturaPdfRemitoDebajoCliente && ! $esRemitoHoja)
<table class="table borderless factura-remito-caja-admin">
    <tr>
        <td>Remito: {{ $nroRemitoFormateado }}</td>
        <td class="text-center">@if (isset($venta->transportes->codigo)) Reparto: {{ $venta->transportes->codigo }} @endif</td>
        <td class="text-right">Condicion de Venta: {{ $venta->condicionventas->nombre ?? $venta->clientes->condicionventas->nombre ?? 'CONTADO' }}</td>
    </tr>
</table>
@else
<table class="table borderless factura-linea-cliente-admin">
    <tr class="factura-linea-cliente-admin-fila"><td colspan="3">&nbsp;</td></tr>
</table>
@endif
