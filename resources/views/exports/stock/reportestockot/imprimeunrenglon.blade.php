@php
    $conFoto = ($imprimefoto ?? '') === 'CON_FOTO';
    $medidasCols = $medidas_columnas ?? [];

    // Curva de un módulo del catálogo y cuántos hay en stock (PS x N = TT).
    // Si la numeración no es múltiplo de ningún módulo, se muestran los talles reales y N = 1.
    $presentacion = \App\Support\Stock\CurvaModuloPresentacionSupport::presentarDesdeLista($lote['medidas'] ?? []);
    $cantPorMedida = $presentacion['medidas'];
    $ps = (float) $presentacion['pares_modulo'];
    $nModulos = (int) $presentacion['modulos'];
    $tt = (float) ($lote['total_pares'] ?? $presentacion['total']);
    if ($nModulos < 1) {
        $nModulos = 1;
    }

    $precio = (float) ($lote['precio'] ?? 0);
    $deposito = trim((string) ($lote['deposito_codigo'] ?? ''));
    if ($deposito === '') {
        $deposito = trim((string) ($lote['deposito_nombre'] ?? ''));
    }
    $rojoExcel = \App\Support\Stock\ReporteStockOtSituacionSupport::colorearRojoEnExcel(
        (string) ($lote['situacion'] ?? ''),
        ! empty($lote['en_produccion'])
    );
@endphp
<tr @if($rojoExcel) style="color:#FF0000;" @endif>
    @if ($conFoto)
        <td></td>
    @endif
    <td>{{ $lote['nombrelinea'] ?? '' }}</td>
    <td>{{ $lote['sku_excel'] ?? $lote['sku'] ?? '' }}</td>
    <td>{{ $lote['descripcion_excel'] ?? '' }}</td>
    @foreach ($medidasCols as $medida)
        @php $cant = (float) ($cantPorMedida[(int) $medida] ?? 0); @endphp
        @if (abs($cant) > 0.0001)
            <td align="right">{{ number_format($cant, 0, ',', '.') }}</td>
        @else
            <td></td>
        @endif
    @endforeach
    <td align="right">{{ number_format($ps, 0, ',', '.') }}</td>
    <td>X</td>
    <td align="right">{{ $nModulos }}</td>
    <td align="right">{{ number_format($tt, 0, ',', '.') }}</td>
    <td align="right">
        @if ($precio > 0)
            $ {{ number_format($precio, 0, ',', '.') }}
        @endif
    </td>
    <td>{{ $lote['situacion'] ?? '' }}</td>
    <td>{{ $lote['lote'] ?? '' }}</td>
    <td>{{ $deposito }}</td>
    @if (! empty($es_movimientos))
        <td>{{ $lote['modulo_nombre'] ?? '' }}</td>
        <td>
            @if ((int) ($lote['pedido'] ?? 0) > 0)
                {{ (int) $lote['pedido'] }}
            @endif
        </td>
    @endif
</tr>
