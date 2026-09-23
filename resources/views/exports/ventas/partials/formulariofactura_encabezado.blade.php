@php
    $esRemitoHoja = (bool) ($esRemitoHoja ?? false);
    $facturaPdfEsFerli = (bool) ($facturaPdfEsFerli ?? \App\Support\Configuracion\EntornoEmpresaSupport::esFerli());
    $esRinFerli = (bool) ($esRinFerli ?? \App\Support\Ventas\FerliRinNumeracionSupport::esVentaRin($venta ?? null));
    $esCabeceraRemitoStyle = $esRemitoHoja || $esRinFerli;
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
    $empresaPv = $venta->puntoventas->empresas ?? null;
    $empresaPdfId = (int) ($empresaPv->id ?? $venta->puntoventas->empresa_id ?? 0);
    $facturaPdfEsLocal = \App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfSupport::esVentaLocal($venta ?? null);
    $membretePdf = \App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfSupport::membreteParaVenta(
        $venta ?? null,
        $empresaPdfId > 0 ? $empresaPdfId : null
    );
    $ferliImpInternos = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_IMP_INTERNOS] ?? '');
    $ferliSegHigiene = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_SEGURIDAD_HIGIENE] ?? '');
    $ferliHabilitacion = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_HABILITACION] ?? '');
    $ferliWebBase = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_WEB] ?? '');
    $ferliWeb = \App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfSupport::lineaContacto(
        $venta ?? null,
        $ferliWebBase,
        $venta->puntoventas->email ?? null
    );
    $pdfLugar = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_LUGAR] ?? '');
    $pdfLeyendaIva = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_LEYENDA_IVA] ?? 'I.V.A. RESPONSABLE INSCRIPTO');
    $pdfInicioFallback = (string) ($membretePdf[\App\Support\Ventas\FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK] ?? '');
    if ($facturaPdfEsLocal) {
        // Local: no usar fechainicioactividad de la empresa (fábrica); solo el del local / fallback membrete.
        $inicioActFmt = \App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfSupport::inicioActividadesFmt(
            $venta,
            $pdfInicioFallback
        );
    } else {
        $inicioAct = $empresaPv->fechainicioactividad ?? null;
        $inicioActFmt = ($inicioAct && (string) $inicioAct !== '0000-00-00')
            ? date('d/m/Y', strtotime((string) $inicioAct))
            : $pdfInicioFallback;
    }
@endphp
<table class="table borderless factura-cabecera {{ $facturaPdfRemitoDebajoCliente && ! $esRemitoHoja ? 'factura-cabecera-admin' : '' }}">
    <tr>
        <td class="factura-cabecera-logo">
            @if ($logoEmpresaDataUri)
                @php
                    $logoPdfAncho = 160;
                    $logoPdfAlto = 70;
                    if (preg_match('#^data:image/[^;]+;base64,(.+)$#', (string) $logoEmpresaDataUri, $logoM)) {
                        $logoBin = base64_decode($logoM[1], true);
                        if ($logoBin !== false) {
                            $logoInfo = @getimagesizefromstring($logoBin);
                            if (is_array($logoInfo) && ($logoInfo[0] ?? 0) > 0 && ($logoInfo[1] ?? 0) > 0) {
                                $logoPdfAlto = (int) max(1, round($logoPdfAncho * $logoInfo[1] / $logoInfo[0]));
                                // DomPDF: tope de alto para no empujar la cabecera
                                if ($logoPdfAlto > 95) {
                                    $logoPdfAlto = 95;
                                    $logoPdfAncho = (int) max(1, round($logoPdfAlto * $logoInfo[0] / $logoInfo[1]));
                                }
                            }
                        }
                    }
                @endphp
                <img width="{{ $logoPdfAncho }}" height="{{ $logoPdfAlto }}" src="{{ $logoEmpresaDataUri }}" alt="">
            @endif
            <div>
                <strong class="factura-empresa-nombre">{{ $empresaPv->nombre ?? '' }}</strong>
                <p class="factura-empresa-datos">
                    @if ($facturaPdfEsFerli)
                        @php
                            // Local POS: domicilio comercial del local (pdf_lugar), no el fiscal de fábrica (PV Villa Madero).
                            $domicilioIzqLocal = $facturaPdfEsLocal ? trim((string) $pdfLugar) : '';
                            $domPv = trim((string) ($venta->puntoventas->domicilio ?? ''));
                            if ($domPv === '-' || $domPv === '.') {
                                $domPv = '';
                            }
                            $locPv = trim((string) ($venta->puntoventas->localidades->nombre ?? ''));
                            $cpPv = trim((string) ($venta->puntoventas->codigopostal ?? ''));
                            $provPv = trim((string) ($venta->puntoventas->provincias->nombre ?? ''));
                            $telPv = trim((string) ($venta->puntoventas->telefono ?? ''));
                            // En maestros Ferli a veces quedó ciudad/CP en telefono; no rotular como TEL.
                            $telPvPareceTelefono = $telPv !== '' && preg_match('/\d/', $telPv) && ! preg_match('/\bCP\.?\s*\d/i', $telPv);
                        @endphp
                        @if ($domicilioIzqLocal !== '')
                            {{ $domicilioIzqLocal }}
                        @else
                            @if ($domPv !== '')
                                {{ $domPv }}<br>
                            @endif
                            @if ($locPv !== '')
                                {{ $locPv }}
                                @if ($cpPv !== '')
                                    ({{ $cpPv }})
                                @endif
                                <br>
                            @endif
                            @if ($provPv !== '')
                                {{ $provPv }}
                            @endif
                        @endif
                        @if ($telPvPareceTelefono)
                            <br>TEL.: {{ $telPv }}
                        @elseif ($telPv !== '')
                            <br>{{ $telPv }}
                        @endif
                        @if ($ferliWeb !== '')
                            <br>{{ $ferliWeb }}
                        @endif
                        @if ($pdfLeyendaIva !== '')
                            <br>{{ $pdfLeyendaIva }}
                        @endif
                    @else
                        {{ $venta->puntoventas->domicilio }}<br>
                        {{ $venta->puntoventas->localidades->nombre }} ({{ $venta->puntoventas->codigopostal }})<br>
                        {{ $venta->puntoventas->provincias->nombre }}<br>
                        {{ $pdfLeyendaIva !== '' ? $pdfLeyendaIva : 'IVA RESPONSABLE INSCRIPTO' }}
                    @endif
                </p>
            </div>
        </td>
        <td class="factura-cabecera-letra {{ $esCabeceraRemitoStyle ? 'factura-cabecera-letra-remito' : '' }}">
            <div class="factura-letra-caja">{{ $esCabeceraRemitoStyle ? 'R' : $letra }}</div>
            @if ($esRinFerli)
                <div class="factura-codigo-tipo">Uso interno</div>
            @else
                <div class="factura-codigo-tipo">Código {{ $esRemitoHoja ? '091' : ($codigoTipoTransaccionPad ?? $codigoTipoTransaccion) }}</div>
            @endif
            @if ($esCabeceraRemitoStyle)
                <div class="factura-remito-no-valido">DOCUMENTO NO VALIDO<br>COMO FACTURA</div>
            @endif
        </td>
        <td class="factura-cabecera-comprobante">
            <strong>{{ $esRemitoHoja ? 'REMITO' : ($esRinFerli ? 'REMITO INTERNO' : ($nombreTipoComprobanteImpresion ?? $venta->tipotransacciones->nombre ?? '')) }}</strong><br>
            <strong>Nro. {{ $esRemitoHoja ? $nroRemitoFormateado : $venta->codigo }}</strong>
            <p>
                @if ($facturaPdfEsFerli)
                    Lugar y fecha: {{ date('d/m/Y', strtotime($venta->fecha ?? '')) }}@if ($pdfLugar !== '') · {{ $pdfLugar }}@endif<br>
                    C.U.I.T.: {{ $empresaPv->nroinscripcion ?? '' }}<br>
                    Ingresos brutos CONV MULT.: {{ $empresaPv->numeroiibb ?? '' }}<br>
                    @if ($ferliImpInternos !== '')
                        Imp.internos: {{ $ferliImpInternos }}<br>
                    @endif
                    @if ($ferliSegHigiene !== '')
                        Seguridad e Higiene: {{ $ferliSegHigiene }}<br>
                    @endif
                    @if ($ferliHabilitacion !== '')
                        Habilitacion: {{ $ferliHabilitacion }}<br>
                    @endif
                    @if ($inicioActFmt !== '')
                        Inicio de Actividades: {{ $inicioActFmt }}
                    @endif
                @else
                    Fecha emisi&oacute;n: {{ date('d/m/Y', strtotime($venta->fecha ?? '')) }}@if ($pdfLugar !== '') · {{ $pdfLugar }}@endif<br>
                    C.U.I.T.: {{ $venta->puntoventas->empresas->nroinscripcion }}<br>
                    Ingresos Brutos: {{ $venta->puntoventas->empresas->numeroiibb }}<br>
                    Inicio de Actividades: {{ date('d/m/Y', strtotime($venta->puntoventas->empresas->fechainicioactividad)) }}
                @endif
            </p>
            <p>{{ $copiaLeyenda ?? 'ORIGINAL' }}</p>
        </td>
    </tr>
    @if (! $esRemitoHoja && ! $facturaPdfRemitoDebajoCliente)
    <tr>
        <td>Remito: {{ $nroRemitoFormateado }}</td>
        <td>
            @if (isset($venta->transportes->codigo))
                Reparto: {{ $venta->transportes->codigo }}
            @endif
        </td>
        <td class="text-right">Condicion de Venta: {{ $venta->condicionventas->nombre ?? $venta->clientes?->condicionventas?->nombre ?? 'CONTADO' }}</td>
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
                    {{ $venta->clientes?->localidades?->nombre ?? '' }} ({{ $venta->clientes?->codigopostal ?? '' }})<br>
                    {{ $venta->clientes?->provincias?->nombre ?? '' }} {{ $venta->clientes?->paises?->nombre ?? '' }}<br>
                @else
                    @php
                        $locSnap = $venta->localidades->nombre ?? '';
                        $provSnap = $venta->provincias->nombre ?? '';
                        // Fallback si la relación no cargó pero hay provincia_id (p. ej. Tiendanube).
                        if ($provSnap === '' && (int) ($venta->provincia_id ?? 0) > 0) {
                            $provSnap = trim((string) (\App\Models\Configuracion\Provincia::query()
                                ->whereKey((int) $venta->provincia_id)
                                ->value('nombre') ?? ''));
                        }
                    @endphp
                    @if ($locSnap !== '' || $provSnap !== '' || ! empty($venta->codigopostal))
                        @if ($locSnap !== '')
                            {{ $locSnap }}@if (! empty($venta->codigopostal)) ({{ $venta->codigopostal }})@endif
                            @if ($provSnap !== '')
                                <br>{{ $provSnap }}
                            @endif
                        @elseif ($provSnap !== '')
                            {{ $provSnap }}@if (! empty($venta->codigopostal)) ({{ $venta->codigopostal }})@endif
                        @elseif (! empty($venta->codigopostal))
                            CP {{ $venta->codigopostal }}
                        @endif
                        <br>
                    @endif
                @endif
                @if (isset($venta->transportes->nombre))
                    {{ $facturaPdfEsFerli ? 'Expreso' : 'Transporte' }}: {{ $venta->transportes->nombre }}<br>
                @endif
                @if (! empty($venta->lugarentrega))
                    {{ $facturaPdfEsFerli ? 'Entrega en' : 'Lugar de entrega' }}: {{ $venta->lugarentrega }}<br>
                @endif
                @include('exports.ventas.partials.papelito_waitry_factura', ['venta' => $venta])
            </p>
        </td>
        <td class="factura-cliente-der">
            <p>
                @php $codCli = \App\Support\Ventas\GastronomiaVentaDisplaySupport::codigoClienteMaestro($venta); @endphp
                @if ($codCli !== '' && ($esRemitoHoja || $facturaEsGastronomia || $facturaPdfEsFerli))
                    Código: {{ $codCli }}<br>
                @endif
                @if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta) && $venta->clientes)
                    Teléfono: {{ $venta->clientes->telefono }}<br>
                @elseif (! empty($venta->telefono))
                    Teléfono: {{ $venta->telefono }}<br>
                @endif
                @php
                    $nombreIva = $venta->clientes?->condicionivas?->nombre
                        ?? $venta->condicionivas?->nombre
                        ?? '';
                    $docReceptorFactura = \App\Support\Ventas\GastronomiaVentaDisplaySupport::documentoReceptorFactura($venta);
                    if ($docReceptorFactura === '') {
                        $docReceptorFactura = trim((string) ($venta->nroinscripcion ?? ''));
                    }
                    $etiqDocReceptorFactura = \App\Support\Ventas\GastronomiaVentaDisplaySupport::abreviaturaDocumentoReceptorFactura($venta);
                @endphp
                @if ($nombreIva !== '')
                    I.V.A.: {{ $nombreIva }}<br>
                @endif
                @if ($docReceptorFactura !== '')
                    {{ $facturaPdfEsFerli ? 'C.U.I.T.' : $etiqDocReceptorFactura }}: {{ $docReceptorFactura }}<br>
                @endif
                @if (! \App\Support\Ventas\GastronomiaVentaDisplaySupport::usaSnapshotReceptorEnVenta($venta) && ! $facturaPdfEsFerli && $venta->clientes)
                    Ingresos Brutos: {{ $venta->clientes->condicioniibbs?->nombre }} {{ $venta->clientes->nroiibb }}
                @endif
            </p>
        </td>
    </tr>
</table>
@if ($esRemitoHoja)
<table class="table borderless factura-remito-caja-admin">
    <tr>
        <td>Condición de venta: {{ $venta->condicionventas?->nombre ?? $venta->clientes?->condicionventas?->nombre ?? 'CONTADO' }}</td>
        <td class="text-center">
            @if (isset($venta->transportes->codigo))
                Reparto: {{ $venta->transportes->codigo }}
            @endif
        </td>
        <td class="text-right">Factura: {{ $venta->codigo }}</td>
    </tr>
</table>
@elseif ($esRinFerli)
<table class="table borderless factura-remito-caja-admin">
    <tr>
        <td>Condicion de Venta: {{ $venta->condicionventas->nombre ?? $venta->clientes?->condicionventas?->nombre ?? 'CONTADO' }}</td>
        <td class="text-center">
            @if (isset($venta->transportes->codigo))
                Reparto: {{ $venta->transportes->codigo }}
            @endif
        </td>
        <td class="text-right">&nbsp;</td>
    </tr>
</table>
@elseif ($facturaPdfRemitoDebajoCliente)
<table class="table borderless factura-remito-caja-admin">
    <tr>
        <td>Remito: {{ $nroRemitoFormateado }}</td>
        <td class="text-center">
            @if (isset($venta->transportes->codigo))
                Reparto: {{ $venta->transportes->codigo }}
            @endif
        </td>
        <td class="text-right">Condicion de Venta: {{ $venta->condicionventas->nombre ?? $venta->clientes?->condicionventas?->nombre ?? 'CONTADO' }}</td>
    </tr>
</table>
@else
<table class="table borderless factura-linea-cliente-admin">
    <tr class="factura-linea-cliente-admin-fila"><td colspan="3">&nbsp;</td></tr>
</table>
@endif
