{{--
  Detalle de líneas de OC (pantalla, PDF y Excel).
  Variables: $filas, $pantalla (bool), $puede_ver_ordencompra (bool), $para_pdf (bool),
             $mostrar_cabecera (bool, solo PDF), $titulo, $subtitulo, $logosCabecera, $totalFilas
--}}
@php
    $enPantalla = (bool) ($pantalla ?? false);
    $enPdf = (bool) ($para_pdf ?? false);
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, ',', '.');
    $fechaCorta = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '';
    // El PDF va más angosto (es lo que más pesa en DomPDF): sin código de proveedor ni ¿COM?
    // (la 1ª COM ya lo dice), ni cabecera de la OC ni ¿Factura? y sin Empresa cuando hay una sola. Pantalla, Excel y CSV conservan las 19.
    $verEmpresa = ! ($enPdf && ($ocultar_empresa ?? false));
    $verExtras = ! $enPdf;
    $nColumnas = 15 + ($verEmpresa ? 1 : 0) + ($verExtras ? 4 : 0);
@endphp
<thead>
    @if ($enPdf && ($mostrar_cabecera ?? true))
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo ?? 'Compras',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => $nColumnas,
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? 0,
        ])
    @endif
    <tr class="columnas" style="{{ $enPantalla ? 'background:#85C1E9;color:#17202A;' : '' }}">
        <th>Fecha</th>
        <th>Nro OC</th>
        @if ($verEmpresa)
            <th>Empresa</th>
        @endif
        <th>Usuario</th>
        <th>Proveedor</th>
        @if ($verExtras)
            <th>Cód. prov.</th>
        @endif
        <th>SKU</th>
        <th>Artículo</th>
        <th class="text-right">Cantidad</th>
        <th>Mon.</th>
        <th class="text-right">Precio</th>
        <th class="text-right">Monto $</th>
        <th>Estado</th>
        <th>Vencimiento</th>
        <th>F. entrega</th>
        <th>1ª COM</th>
        @if ($verExtras)
            <th>¿COM?</th>
            <th>Cabecera OC</th>
            <th>¿Factura?</th>
        @endif
    </tr>
</thead>
<tbody>
    @forelse ($filas as $f)
        <tr class="{{ $enPdf && $loop->even ? 'par' : '' }}">
            <td class="text-nowrap">{{ $fechaCorta($f['fecha']) }}</td>
            <td class="text-nowrap">
                @if ($enPantalla && ($puede_ver_ordencompra ?? false))
                    <a class="text-primary" target="_blank" rel="noopener"
                       href="{{ route('editar_ordencompra', ['id' => $f['oc_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $f['numero'] }}</a>
                @else
                    {{ $f['numero'] }}
                @endif
            </td>
            @if ($verEmpresa)
                <td>{{ $f['empresa'] }}</td>
            @endif
            <td>{{ $f['usuario'] }}</td>
            <td>{{ $f['proveedor'] }}</td>
            @if ($verExtras)
                <td>{{ $f['proveedor_codigo'] }}</td>
            @endif
            <td>{{ $f['sku'] }}</td>
            <td>{{ $f['articulo'] }}</td>
            <td class="text-right">{{ $enPdf || $enPantalla ? $fmt($f['cantidad'], 2) : $f['cantidad'] }}</td>
            <td>{{ $f['moneda'] }}</td>
            <td class="text-right">{{ $enPdf || $enPantalla ? $fmt($f['precio']) : $f['precio'] }}</td>
            <td class="text-right">{{ $enPdf || $enPantalla ? $fmt($f['monto_pesos']) : round($f['monto_pesos'], 2) }}</td>
            <td>{{ $f['estado'] }}</td>
            <td>
                @if ($f['vencimiento'] === 'Vencida')
                    <span class="{{ $enPantalla ? 'badge badge-danger' : '' }}">Vencida</span>
                @elseif ($f['vencimiento'] === 'A tiempo')
                    <span class="{{ $enPantalla ? 'badge badge-success' : '' }}">A tiempo</span>
                @endif
            </td>
            <td class="text-nowrap">{{ $fechaCorta($f['fechaentrega']) }}</td>
            <td class="text-nowrap">{{ $fechaCorta($f['primera_com']) }}</td>
            @if ($verExtras)
                <td>{{ $f['tiene_com'] }}</td>
                <td>{{ $f['estado_oc'] }}</td>
                <td>{{ $f['tiene_factura'] }}</td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="{{ $nColumnas }}" class="text-center text-muted">Sin líneas de OC para los filtros elegidos.</td>
        </tr>
    @endforelse
</tbody>
