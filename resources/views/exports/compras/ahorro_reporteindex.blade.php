@php
    $nCols = 20;
    $num = static fn ($v) => number_format((float) $v, 2, '.', '');
    $origenTxt = static fn ($o) => \App\Services\Compras\AhorroReporteService::origenTexto($o);
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $nCols }}" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="{{ $nCols }}"><strong style="font-size: 16px;">{{ $titulo }}</strong></td></tr>
    <tr><td colspan="{{ $nCols }}">Generado {{ date('d/m/Y H:i') }}</td></tr>
    @if (trim($subtitulo ?? '') !== '')
        <tr><td colspan="{{ $nCols }}">{{ $subtitulo }}</td></tr>
    @endif
    <tr>
        <td colspan="{{ $nCols }}">Ahorro total: $ {{ number_format((float) $resumen['ahorro_pesos'], 2, ',', '.') }}
            @if ($resumen['porcentaje'] !== null) · {{ number_format((float) $resumen['porcentaje'], 2, ',', '.') }} % @endif
            (ahorro / gasto + ahorro)</td>
    </tr>
    @if ($filas->count() > 0)
        <tr><td colspan="{{ $nCols }}">Líneas: {{ $filas->count() }}</td></tr>
    @endif
    <thead>
        <tr>
            <th>OC</th>
            <th>Fecha</th>
            <th>Req.</th>
            <th>Proveedor</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th>C. costo</th>
            <th>Comprador</th>
            <th>Cantidad</th>
            <th>Precio anterior</th>
            <th>Precio nuevo</th>
            <th>Mon.</th>
            <th>Ahorro unitario</th>
            <th>Ahorro total (mon. línea)</th>
            <th>Ahorro $</th>
            <th>% línea</th>
            <th>Tipo de ahorro</th>
            <th>Origen precio anterior</th>
            <th>Referencia</th>
            <th>Motivo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f->numeroordencompra ?: $f->ordencompra_id }}</td>
                <td>{{ $f->fecha ? date('d/m/Y', strtotime((string) $f->fecha)) : '' }}</td>
                <td>{{ $f->numerorequisicion }}</td>
                <td>{{ trim(($f->proveedor_codigo ?? '').' '.($f->proveedor_nombre ?? '')) }}</td>
                <td>{{ $f->sku }}</td>
                <td>{{ $f->articulo_descripcion }}</td>
                <td>{{ trim(($f->centrocosto_codigo ?? '').' '.($f->centrocosto_nombre ?? '')) }}</td>
                <td>{{ $f->comprador_nombre }}</td>
                <td>{{ $num($f->cantidad) }}</td>
                <td>{{ $num($f->preciooriginal) }}</td>
                <td>{{ $num($f->precio) }}</td>
                <td>{{ $f->moneda_abrev }}</td>
                <td>{{ $num($f->ahorro_unitario) }}</td>
                <td>{{ $num($f->ahorro_total) }}</td>
                <td>{{ $num($f->ahorro_total_pesos) }}</td>
                <td>{{ $num($f->porc_ahorro) }}</td>
                <td>{{ $f->tipoahorro_nombre }}</td>
                <td>{{ $origenTxt($f->origen_anterior) }}</td>
                <td>{{ $f->referencia_anterior }}</td>
                <td>{{ $f->motivoahorro }}</td>
            </tr>
        @endforeach
        <tr>
            <td>Total general</td>
            @for ($i = 2; $i <= 14; $i++)
                <td></td>
            @endfor
            <td>{{ $num($resumen['ahorro_pesos']) }}</td>
            <td>{{ $resumen['porcentaje'] !== null ? $num($resumen['porcentaje']) : '' }}</td>
            <td></td><td></td><td></td><td></td>
        </tr>
    </tbody>
</table>
