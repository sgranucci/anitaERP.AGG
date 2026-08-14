<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Orden de compra {{ $data->numeroordencompra }}</title>
    <style>
        @page { margin: 12mm 14mm 12mm 14mm; }
        html, body {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
        }
        h2 {
            font-size: 12px;
            margin: 6px 0 4px 0;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
            page-break-after: avoid;
        }
        table { width: 100%; max-width: 100%; border-collapse: collapse; margin-bottom: 4px; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 3px 4px; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; font-size: 9px; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; font-size: 10px; }
        .cabecera td:not(.lbl) { font-size: 10px; }
        table.cabecera td { padding: 4px 6px; vertical-align: middle; }
        .num { text-align: right; white-space: nowrap; overflow: hidden; overflow-wrap: normal; word-wrap: normal; }
        .cen { text-align: center; }
        .muted { color: #444; font-size: 8px; }
        .pdf-cabecera { width: 100%; margin-bottom: 6px; page-break-inside: avoid; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa {
            max-width: 140px;
            max-height: 52px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .pdf-cabecera-marca {
            font-size: 12px;
            font-weight: bold;
            margin-top: 2px;
        }
        .pdf-cabecera-cuit {
            font-size: 9px;
            margin-top: 1px;
        }
        .titulo-doc {
            font-size: 15px;
            font-weight: bold;
            margin: 0;
            padding: 0;
        }
        .fecha-doc-oc {
            font-size: 10px;
            margin: 2px 0 0 0;
            padding: 0;
        }
        .bloque-texto { white-space: pre-wrap; word-wrap: break-word; font-size: 9px; line-height: 1.25; }
        .subtotal { font-weight: bold; background: #f0f0f0; }
        table.items { table-layout: fixed; width: 100%; }
        .items th { font-size: 10px; padding: 3px 4px; }
        .items td { font-size: 10px; padding: 3px 4px; }
        .items .items-col-idx { font-size: 9px; padding: 3px 2px !important; text-align: center; }
        .items .items-col-cant { font-size: 9.5px; padding: 3px 3px !important; overflow: hidden; }
        .items .items-col-punit { font-size: 9.5px; padding: 3px 3px !important; overflow: hidden; }
        .items .mcot { font-size: 9.5px; line-height: 1.15; text-align: center; overflow: hidden; }
        .items .items-subt { text-align: right; white-space: nowrap; overflow: hidden; }
        table.pdf-detalle-fila { table-layout: fixed; width: 100%; }
        .pdf-detalle-fila tr td { vertical-align: middle; }
        .pdf-detalle-fila .lbl { width: 10%; font-size: 10px; padding: 5px 8px !important; text-align: center; }
        .pdf-detalle-fila .pdf-detalle-val {
            font-size: 10px !important;
            line-height: 1.28;
            padding: 4px 6px !important;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: normal;
        }
        .item-leyenda { font-size: 9px; color: #222; background: #f7f7f7; padding: 4px 6px !important; border-top: none !important; }
        .item-leyenda .item-leyenda-detalle {
            font-size: 10px;
            line-height: 1.3;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: normal;
            color: #111;
            margin: 0 0 4px 0;
        }
        .item-leyenda .muted { font-size: 8px; color: #555; }
        .pdf-totales { width: 62%; margin: 6px 0 8px auto; border-collapse: collapse; font-size: 10px; page-break-inside: avoid; }
        .pdf-totales td { border: 1px solid #333; padding: 4px 6px; vertical-align: middle; }
        .pdf-totales td:first-child { text-align: left; width: 72%; }
        .pdf-totales .num { white-space: nowrap; }
        .pdf-totales-final td { font-weight: bold; background: #ebebeb; font-size: 10.5px; }
        .historia th, .historia td { font-size: 9px; padding: 2px 4px; }
        .pdf-pie { margin-top: 6px; page-break-inside: avoid; }
        .pdf-pie .lbl { background: #f0f0f0; font-weight: bold; font-size: 9px; }
        .pdf-pie td:not(.lbl) { font-size: 9px; }
        .pdf-pie-nota { font-size: 8px; color: #555; margin-top: 2px; }
        /* Bloque ítems/totales: misma escala legible (antes compactaba a ~6px). */
        .pdf-oc-flujo-compacto { font-size: 10px; }
        .pdf-oc-flujo-compacto h2 { font-size: 12px; margin: 6px 0 4px 0; padding-bottom: 2px; }
        .pdf-oc-flujo-compacto .items th { font-size: 10px !important; padding: 3px 4px !important; }
        .pdf-oc-flujo-compacto .items td { font-size: 10px !important; padding: 3px 4px !important; }
        .pdf-oc-flujo-compacto .items .items-col-idx { font-size: 9px !important; padding: 3px 2px !important; }
        .pdf-oc-flujo-compacto .items .items-col-cant { font-size: 9.5px !important; }
        .pdf-oc-flujo-compacto .items .items-col-punit { font-size: 9.5px !important; }
        .pdf-oc-flujo-compacto .items .mcot { font-size: 9.5px !important; line-height: 1.15; }
        .pdf-oc-flujo-compacto .item-leyenda { font-size: 9px !important; padding: 4px 6px !important; }
        .pdf-oc-flujo-compacto .item-leyenda .item-leyenda-detalle { font-size: 10px !important; line-height: 1.3; }
        .pdf-oc-flujo-compacto .item-leyenda .muted { font-size: 8px !important; }
        .pdf-oc-flujo-compacto .pdf-totales { font-size: 10px !important; }
        .pdf-oc-flujo-compacto .pdf-totales td { padding: 4px 6px !important; }
        .pdf-oc-flujo-compacto .pdf-totales-final td { font-size: 10.5px !important; }
        .pdf-oc-flujo-compacto .historia th,
        .pdf-oc-flujo-compacto .historia td { font-size: 9px !important; padding: 2px 4px !important; }
        .pdf-oc-flujo-compacto .historia .bloque-texto { font-size: 9px !important; line-height: 1.25; }
        .pdf-oc-flujo-compacto .cabecera .lbl,
        .pdf-oc-flujo-compacto .cabecera td:not(.lbl) { font-size: 10px !important; }
        .pdf-oc-flujo-compacto table.cabecera td { padding: 4px 6px !important; }
        .pdf-oc-flujo-compacto .pdf-pie .lbl,
        .pdf-oc-flujo-compacto .pdf-pie td:not(.lbl) { font-size: 9px !important; }
        .pdf-oc-flujo-compacto .pdf-pie-nota { font-size: 8px !important; }
    </style>
</head>
<body>
    @php
        use App\Support\Configuracion\EmpresaLogoArchivo;
        $nombreEmpresaOc = trim((string) ($data->empresas->nombre ?? ''));
        $cuitEmpresaOc = trim((string) ($data->empresas->nroinscripcion ?? ''));
        $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresaOc !== '' ? $nombreEmpresaOc : null);
        $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
        $fechaOc = $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—';
    @endphp
    <table class="pdf-cabecera">
        <colgroup><col style="width:50%;"><col style="width:50%;"></colgroup>
        <tr>
            <td>
                @if ($logoEmpresaDataUri)
                    <img class="logo-empresa" src="{{ $logoEmpresaDataUri }}" alt="">
                @endif
                <div class="pdf-cabecera-marca">{{ $nombreEmpresaOc !== '' ? $nombreEmpresaOc : '—' }}</div>
                @if ($cuitEmpresaOc !== '')
                    <div class="pdf-cabecera-cuit">CUIT {{ $cuitEmpresaOc }}</div>
                @endif
            </td>
            <td style="text-align: right;">
                <p class="titulo-doc">ORDEN DE COMPRA NRO {{ $data->numeroordencompra }}</p>
                <p class="fecha-doc-oc">Fecha orden de compra: {{ $fechaOc }}</p>
                <p class="muted" style="margin:3px 0 0 0;font-size:9px;">Impresión {{ date('d/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <h2>Datos generales y trazabilidad</h2>
    <table class="cabecera">
        <colgroup>
            <col style="width:12%;"><col style="width:38%;">
            <col style="width:12%;"><col style="width:38%;">
        </colgroup>
        <tr>
            <td class="lbl">Alta sistema</td>
            <td>@if ($data->created_at){{ $data->created_at->format('d/m/Y H:i') }}@else — @endif</td>
            <td class="lbl">Empresa</td>
            <td>
                {{ $data->empresas->nombre ?? '—' }}
                @if ($cuitEmpresaOc !== '')
                    · CUIT {{ $cuitEmpresaOc }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Centro costo</td>
            <td>{{ trim((optional($data->centrocostos)->codigo ?? '').' '.(optional($data->centrocostos)->nombre ?? '—')) }}</td>
            <td class="lbl">Entrega doc.</td>
            <td>{{ $data->fechaentrega ? date('d/m/Y', strtotime($data->fechaentrega)) : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Estado</td>
            <td colspan="3">{{ $data->estadoordencompra ?? '—' }}</td>
        </tr>
        @if ($data->requisicion_id && $data->requisiciones)
            <tr>
                <td class="lbl">Requisición</td>
                <td>{{ $data->requisiciones->numerorequisicion ?? $data->requisicion_id }}</td>
                <td class="lbl">Aprobó req.</td>
                <td>{{ optional($reqUsuarioAprobador)->nombre ?? '—' }}</td>
            </tr>
        @else
            <tr>
                <td class="lbl">Requisición</td>
                <td colspan="3">Sin requisición asociada</td>
            </tr>
        @endif
        <tr>
            <td class="lbl">Proveedor</td>
            <td colspan="3">
                @if($data->proveedores)
                    {{ $data->proveedores->codigo ?? '' }} — {{ $data->proveedores->nombre ?? '' }}
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Sector</td>
            <td>{{ optional($data->sector_legajocompras)->nombre ?? '—' }}</td>
            <td class="lbl">Tratamiento</td>
            <td>{{ $data->tratamiento ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cond. compra</td>
            <td>{{ optional($data->condicioncompras)->nombre ?? '—' }}</td>
            <td class="lbl">Cond. entrega</td>
            <td>{{ optional($data->condicionentregas)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cond. pago</td>
            <td colspan="3">{{ optional($data->condicionpagos)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Transporte</td>
            <td colspan="3">{{ optional($data->transportes)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Lugar entrega</td>
            <td>{{ $data->lugarentrega ?? '—' }}</td>
            <td class="lbl">Comentario</td>
            <td>{{ $data->comentario ?? '—' }}</td>
        </tr>
    </table>

    <table class="pdf-detalle-fila">
        <tr>
            <td class="lbl" style="width:10%;">Detalle</td>
            <td class="bloque-texto pdf-detalle-val" style="width:90%;">{{ $data->detalle ?? '—' }}</td>
        </tr>
    </table>

    @if (trim((string) ($data->condiciones_contratacion ?? '')) !== '')
        <h2>Condiciones de contratación</h2>
        <table>
            <tr>
                <td class="bloque-texto">{{ $data->condiciones_contratacion }}</td>
            </tr>
        </table>
    @endif

    <div class="pdf-oc-flujo-compacto">
    @php
        use App\Support\Compras\OrdencompraTotalesCabecera;
        $monOcItems = trim((string) ($monedaPdf ?? ''));
        $monedaRefPdfId = (int) (optional(collect($data->ordencompra_articulos ?? [])->sortBy('id')->first())->moneda_id ?: 1);
    @endphp
    <h2>Ítems</h2>
    @php
        $ocMostrarPesoPdf = \App\Support\Compras\OrdencompraUiConfigSupport::mostrarPesoArticulo();
        $ocPedirPartidaCapexPdf = \App\Support\Compras\OrdencompraUiConfigSupport::pedirPartidaCapex();
        $ocColspanItemsPdf = $ocMostrarPesoPdf ? 10 : 8;
    @endphp
    <table class="items">
        <thead>
            <tr>
                <th class="cen items-col-idx" style="width:3%;">#</th>
                <th style="width:{{ $ocMostrarPesoPdf ? '6%' : '7%' }};">SKU</th>
                <th style="width:{{ $ocMostrarPesoPdf ? '26%' : '32%' }};">Descripción</th>
                <th class="num items-col-cant" style="width:7%;">Cant.</th>
                @if ($ocMostrarPesoPdf)
                    <th class="num" style="width:6%;">Peso u.</th>
                    <th class="num" style="width:6%;">Peso tot.</th>
                @endif
                <th class="num items-col-punit" style="width:9%;">P.unit.</th>
                <th class="cen" style="width:8%;">Mon./cot.</th>
                <th class="cen" style="width:{{ $ocMostrarPesoPdf ? '12%' : '14%' }};">CC dest.</th>
                <th class="num" style="width:{{ $ocMostrarPesoPdf ? '17%' : '20%' }};">Subt.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->ordencompra_articulos as $i => $linea)
                @php
                    $cot = (float) ($linea->cotizacion ?? 1);
                    if ($cot <= 0) { $cot = 1.0; }
                    $sub = OrdencompraTotalesCabecera::importeLineaEnMonedaReferencia(
                        $monedaRefPdfId,
                        (int) ($linea->moneda_id ?: $monedaRefPdfId ?: 1),
                        (float) $linea->cantidad,
                        (float) $linea->precio,
                        $cot,
                    );
                    $ccDest = $linea->centrocostos_destino;
                    $part = $linea->partidagastos;
                    $cpx = $linea->capexs;
                    $art = $linea->articulos;
                    $refPartCpx = trim(implode(' ', array_filter([
                        optional($part)->codigo,
                        optional($cpx)->codigo,
                    ])));
                    if ($refPartCpx === '') {
                        $refPartCpx = '—';
                    }
                    $monAb = optional($linea->monedas)->abreviatura ?? optional($linea->monedas)->nombre ?? '—';
                    $fEnt = $linea->fechaentrega ? date('d/m/y', strtotime($linea->fechaentrega)) : '—';
                    $ccCod = optional($ccDest)->codigo ?? '';
                    $ccNom = optional($ccDest)->nombre ?? '';
                    $ccEtiqueta = trim($ccCod.' '.$ccNom);
                    if ($ccEtiqueta === '') {
                        $ccEtiqueta = '—';
                    }
                    $detLin = trim((string) ($linea->detalle ?? ''));
                    $origTxt = trim((string) ($linea->precio_origen_etiqueta ?? ''));
                    $pesoUnitPdf = (float) ($linea->peso_unitario ?? 0);
                    $pesoTotPdf = (float) ($linea->peso_total ?? 0);
                    if ($ocMostrarPesoPdf && $pesoTotPdf <= 0 && $pesoUnitPdf > 0) {
                        $pesoTotPdf = $pesoUnitPdf * (float) $linea->cantidad;
                    }
                @endphp
                <tr>
                    <td class="cen items-col-idx">{{ $i + 1 }}</td>
                    <td>{{ $art->sku ?? '—' }}</td>
                    <td>
                        {{ $art->descripcion ?? '—' }}
                        @if (optional($linea->color)->nombre)
                            <br><span class="muted">Color: {{ $linea->color->nombre }}</span>
                        @endif
                        @if (optional($linea->talle)->nombre)
                            <br><span class="muted">Talle: {{ $linea->talle->nombre }}</span>
                        @endif
                    </td>
                    <td class="num items-col-cant">{{ number_format((float) $linea->cantidad, 3, ',', '.') }}</td>
                    @if ($ocMostrarPesoPdf)
                        <td class="num">{{ $pesoUnitPdf > 0 ? number_format($pesoUnitPdf, 3, ',', '.') : '—' }}</td>
                        <td class="num">{{ $pesoTotPdf > 0 ? number_format($pesoTotPdf, 3, ',', '.') : '—' }}</td>
                    @endif
                    <td class="num items-col-punit">{{ number_format((float) $linea->precio, 3, ',', '.') }}</td>
                    <td class="mcot cen">{{ $monAb }}<br>{{ number_format($cot, 3, ',', '.') }}</td>
                    <td class="cen">{{ $ccEtiqueta }}</td>
                    <td class="num items-subt">@if ($monOcItems !== ''){{ $monOcItems }} @endif{{ number_format($sub, 2, ',', '.') }}</td>
                </tr>
                <tr class="item-leyenda">
                    <td colspan="{{ $ocColspanItemsPdf }}">
                        @if ($detLin !== '')
                            <div class="item-leyenda-detalle">{{ $linea->detalle }}</div>
                        @endif
                        <span class="muted">Leyenda del ítem:</span>
                        CC dest. = centro de costo destino.
                        Subtotal línea (sin IVA)@if ($monOcItems !== ''), expresado en moneda OC ({{ $monOcItems }})@endif = cantidad × precio unitario × cotización.
                        Entrega: {{ $fEnt }}.
                        Centro de costo destino: {{ $ccEtiqueta }}.
                        @if ($ocPedirPartidaCapexPdf)
                            Partida / CAPEX: {{ $refPartCpx }}.
                        @endif
                        @if ($ocMostrarPesoPdf)
                            Peso tot. = cant. × peso unitario.
                        @endif
                        @if ($origTxt !== '')
                            Origen del precio: {{ $origTxt }}@if (! empty($linea->precio_origen_tipo)) ({{ $linea->precio_origen_tipo }}@if (! empty($linea->precio_origen_ref_id)), ref. {{ $linea->precio_origen_ref_id }}@endif)@endif.
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('compras.ordencompra.pdf-partials-resumen-financiero')

    <h2>Historia estados (OC)</h2>
    <table class="historia">
        <colgroup>
            <col style="width:15%;">
            <col style="width:15%;">
            <col style="width:18%;">
            <col style="width:52%;">
        </colgroup>
        <thead>
            <tr>
                <th>Fecha y hora</th>
                <th>Estado</th>
                <th>Usuario</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data->ordencompra_estados->sortBy('fecha') as $h)
                <tr>
                    <td>{{ $h->fecha ? date('d/m/Y H:i', strtotime($h->fecha)) : '—' }}</td>
                    <td>{{ $h->estado ?? '—' }}</td>
                    <td>{{ optional($h->usuarios)->nombre ?? '—' }}</td>
                    <td class="bloque-texto">{{ $h->observacion ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="cen">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="cabecera pdf-pie">
        <colgroup>
            <col style="width:18%;"><col style="width:32%;">
            <col style="width:18%;"><col style="width:32%;">
        </colgroup>
        <tbody>
            <tr>
                <td class="lbl">Usuario alta (OC)</td>
                <td>{{ optional($data->usuarios)->nombre ?? '—' }}</td>
                <td class="lbl">Emitió la requisición</td>
                <td>
                    @if ($data->requisicion_id && $data->requisiciones)
                        {{ optional($reqUsuarioEmitio)->nombre ?? '—' }}
                    @else
                        —
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
    <p class="pdf-pie-nota muted">Presentación: hoja Legal vertical. Para la versión apaisada use el enlace PDF apaisado o el parámetro de consulta formato=apaisado en la URL de impresión.</p>
    </div>
</body>
</html>
