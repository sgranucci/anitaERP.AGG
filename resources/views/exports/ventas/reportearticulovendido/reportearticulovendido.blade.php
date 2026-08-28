<table>
    <tr>
        <td style="width: 120px;">&nbsp;</td>
        <td colspan="9" style="font-size: 14pt; font-weight: bold; font-family: Arial; color: #17202A; vertical-align: middle;">
            {{ $titulo }}
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td colspan="9" style="font-size: 10pt; font-family: Arial;">
            <strong>{{ $empresa }}</strong>
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td colspan="9" style="font-size: 10pt; font-family: Arial;">
            <strong>Desde:</strong> {{ date('d/m/Y', strtotime($desdefecha ?? '')) }}&nbsp;&nbsp;
            <strong>Hasta:</strong> {{ date('d/m/Y', strtotime($hastafecha ?? '')) }}&nbsp;&nbsp;
            <strong>Marca:</strong> {{ $marca }}
            @if (count($articulos) > 0)
                &nbsp;&nbsp;<strong>Total pares vendidos:</strong> {{ number_format($totales['cantidad'], 0) }}&nbsp;&nbsp;
                <strong>Total importe:</strong> ({{ number_format(abs($totales['importe']), 2, '.', ',') }})
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="10">&nbsp;</td>
    </tr>
    <thead>
        <tr>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Fecha</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Tipo</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Nro. Comprobante</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Nro. Despacho</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Cantidad</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Pares</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">&nbsp;</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Importe</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Cod. Cliente</th>
            <th style="background-color: #4472C4; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #2F5496; font-family: Arial;">Cliente</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($articulos as $articulo)
        <tr style="background-color: #E7E6E6; font-family: Arial;">
            <td colspan="10" style="font-weight: bold; border: 1px solid #B4B4B4;">
                <strong>Art.:</strong> {{ $articulo['codigo'] }} {{ $articulo['nombre'] }}
                <strong>Agr.:</strong> {{ $articulo['agrupacion'] }}
            </td>
        </tr>
        @php $acumuladoPares = 0; @endphp
        @foreach ($articulo['renglones'] as $renglon)
            @php $acumuladoPares += $renglon['cantidad_mov']; @endphp
            @include('exports.ventas.reportearticulovendido.imprimeunrenglon', ['renglon' => $renglon, 'acumuladoPares' => $acumuladoPares])
        @endforeach
        @include('exports.ventas.reportearticulovendido.imprimetotalarticulo', ['articulo' => $articulo])
    @endforeach
    @if (count($articulos) > 0)
        @include('exports.ventas.reportearticulovendido.imprimetotalfinal', ['totales' => $totales])
    @endif
    </tbody>
</table>
