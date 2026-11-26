@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $totalFilas = $filas->count();
    $colspan = 14;
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $origenTxt = static fn ($o) => \App\Services\Compras\AhorroReporteService::origenTexto($o);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        @page { size: legal landscape; margin: 14mm 8mm 16mm 8mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; margin: 0; padding: 0; }
        table.data { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 2px 3px; vertical-align: top; word-wrap: break-word; }
        table.data thead { display: table-header-group; }
        table.data tbody { display: table-row-group; }
        table.data tbody tr:nth-child(even):not(.total-general) { background-color: #f5f5f5; }
        table.data thead tr.columnas th { background-color: #85C1E9; font-size: 6.5px; font-weight: bold; color: #17202A; }
        table.data tr.total-general td { background-color: #AED6F1; font-weight: bold; }
        table.data td.text-right, table.data th.text-right { text-align: right; }
        .neg { color: #b30000; }
        .resumen { font-size: 7.5px; color: #17202A; margin: 0 0 4px 0; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo ?? '',
            'colspan' => $colspan,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ])
        <tr class="pdf-cabecera-repetida" style="background-color:#ffffff;">
            <th colspan="{{ $colspan }}" style="background-color:#ffffff;border:none;padding:0 2px 4px 2px;font-weight:normal;text-align:left;color:#17202A;font-size:7.5px;">
                Ahorro total: <strong>$ {{ $fmt($resumen['ahorro_pesos']) }}</strong>
                @if ($resumen['porcentaje'] !== null) &middot; <strong>{{ $fmt($resumen['porcentaje']) }} %</strong> @endif
                (ahorro / gasto + ahorro)
            </th>
        </tr>
        <tr class="columnas">
            <th style="width:5%">OC</th>
            <th style="width:5%">Fecha</th>
            <th style="width:4%">Req.</th>
            <th style="width:12%">Proveedor</th>
            <th style="width:15%">Art&iacute;culo</th>
            <th style="width:6%">C. costo</th>
            <th style="width:8%">Comprador</th>
            <th style="width:5%" class="text-right">Cant.</th>
            <th style="width:7%" class="text-right">Precio ant.</th>
            <th style="width:7%" class="text-right">Precio nuevo</th>
            <th style="width:3%">Mon.</th>
            <th style="width:8%" class="text-right">Ahorro $</th>
            <th style="width:5%" class="text-right">% l&iacute;nea</th>
            <th style="width:10%">Tipo de ahorro</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            @php $neg = (float) ($f->ahorro_total_pesos ?? 0) < 0; @endphp
            <tr>
                <td>{{ $f->numeroordencompra ?: $f->ordencompra_id }}</td>
                <td>{{ $f->fecha ? date('d/m/Y', strtotime((string) $f->fecha)) : '' }}</td>
                <td>{{ $f->numerorequisicion }}</td>
                <td>{{ trim(($f->proveedor_codigo ?? '').' '.($f->proveedor_nombre ?? '')) }}</td>
                <td>{{ $f->sku }} {{ $f->articulo_descripcion }}</td>
                <td>{{ $f->centrocosto_codigo }}</td>
                <td>{{ $f->comprador_nombre }}</td>
                <td class="text-right">{{ $fmt($f->cantidad) }}</td>
                <td class="text-right">{{ $fmt($f->preciooriginal) }}</td>
                <td class="text-right">{{ $fmt($f->precio) }}</td>
                <td>{{ $f->moneda_abrev }}</td>
                <td class="text-right {{ $neg ? 'neg' : '' }}">{{ $fmt($f->ahorro_total_pesos) }}</td>
                <td class="text-right {{ $neg ? 'neg' : '' }}">{{ $f->porc_ahorro !== null ? $fmt($f->porc_ahorro).' %' : '' }}</td>
                <td>{{ $f->tipoahorro_nombre }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $colspan }}" style="text-align:center;">No hay l&iacute;neas con ahorro en el per&iacute;odo.</td></tr>
        @endforelse
        <tr class="total-general">
            <td colspan="11">Total general ({{ $totalFilas }} l&iacute;nea(s))</td>
            <td class="text-right">{{ $fmt($resumen['ahorro_pesos']) }}</td>
            <td class="text-right">{{ $resumen['porcentaje'] !== null ? $fmt($resumen['porcentaje']).' %' : '' }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>
