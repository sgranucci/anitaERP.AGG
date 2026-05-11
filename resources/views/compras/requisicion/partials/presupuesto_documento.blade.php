@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $nombreEmpresaLogo = optional($req->empresas)->nombre;
    $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresaLogo);
    $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
    $articulos = $detalle['articulos'] ?? [];
    $totalCotizado = 0.0;
    foreach ($articulos as $_ln) {
        $c = (float) ($_ln['cantidad_requisicion'] ?? 0);
        $p = (float) ($_ln['precio_unitario'] ?? 0);
        $totalCotizado += $c * $p;
    }
@endphp
<table class="pdf-cabecera">
    <tr>
        <td style="width:55%;">
            @if ($logoEmpresaDataUri)
                <img class="logo-empresa" src="{{ $logoEmpresaDataUri }}" alt="">
            @endif
            <div style="font-size: 12px; font-weight: bold; margin-top: 4px;">{{ $nombreEmpresaLogo ?? '—' }}</div>
        </td>
        <td style="text-align: right;">
            <h1 style="margin-top: 0;">Presupuesto / cotización proveedor</h1>
            <p class="muted" style="margin: 0;">Requisición Nº {{ $req->numerorequisicion ?? '—' }} — Ref. presupuesto #{{ $detalle['id'] ?? '—' }}</p>
            <p class="muted" style="margin: 0;">Generado el {{ date('d/m/Y H:i') }}</p>
        </td>
    </tr>
</table>

<h2>Identificación</h2>
<table class="cabecera">
    <tr>
        <td class="lbl">Proveedor cotizado</td>
        <td colspan="3">{{ $detalle['proveedor_codigo'] ?? '' }} — {{ $detalle['proveedor_nombre'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Fecha presupuesto</td>
        <td>{{ !empty($detalle['fecha']) ? date('d/m/Y', strtotime($detalle['fecha'])) : '—' }}</td>
        <td class="lbl">Estado</td>
        <td>{{ $detalle['estado'] ?? '—' }}</td>
    </tr>
</table>

<h2>Condiciones comerciales</h2>
<table>
    <tr>
        <td class="lbl" style="width:18%;">Entrega</td>
        <td class="bloque-texto">{{ $detalle['condicionentrega_nombre'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Compra</td>
        <td class="bloque-texto">{{ $detalle['condicioncompra_nombre'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Pago</td>
        <td class="bloque-texto">{{ $detalle['condicionpago_nombre'] ?? '—' }}</td>
    </tr>
</table>

<h2>Ítems cotizados</h2>
<table class="items">
    <thead>
        <tr>
            <th class="cen">#</th>
            <th>SKU</th>
            <th>Descripción</th>
            <th class="num">Cantidad</th>
            <th class="num">P. unit. cotiz.</th>
            <th>Mon.</th>
            <th class="num">Subtotal</th>
            <th>Obs.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($articulos as $i => $ln)
            @php
                $cant = (float) ($ln['cantidad_requisicion'] ?? 0);
                $pu = (float) ($ln['precio_unitario'] ?? 0);
                $sub = $cant * $pu;
            @endphp
            <tr>
                <td class="cen">{{ $i + 1 }}</td>
                <td>{{ $ln['articulo_codigo'] ?? '—' }}</td>
                <td>{{ $ln['articulo_descripcion'] ?? '—' }}</td>
                <td class="num">{{ number_format($cant, 4, ',', '.') }}</td>
                <td class="num">{{ number_format($pu, 4, ',', '.') }}</td>
                <td>{{ $ln['moneda_abreviatura'] ?? '—' }}</td>
                <td class="num">{{ number_format($sub, 2, ',', '.') }}</td>
                <td class="bloque-texto">{{ $ln['observacion'] ?? '' }}</td>
            </tr>
        @endforeach
        <tr class="subtotal">
            <td colspan="6" style="text-align:right;">Total cotización (suma líneas)</td>
            <td class="num">{{ number_format($totalCotizado, 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

@if (!empty($detalle['archivos']) && count($detalle['archivos']))
    <p class="muted" style="margin-top:8px;">Archivos adjuntos registrados: {{ count($detalle['archivos']) }}.</p>
@endif
