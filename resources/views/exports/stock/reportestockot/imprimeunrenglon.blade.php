@php
    $conFoto = ($imprimefoto ?? '') === 'CON_FOTO';
    $medidasCols = $medidas_columnas ?? [];

    // Preferir curva unitaria del módulo (como Excel original); si no hay, cantidades absolutas.
    $cantPorMedida = [];
    $usarCurvaModulo = false;
    $sumaModulo = 0.0;
    foreach (($lote['modulo'] ?? []) as $m) {
        $cant = (float) ($m['cantidad'] ?? 0);
        if (abs($cant) > 0.0001) {
            $usarCurvaModulo = true;
            $sumaModulo += $cant;
        }
    }
    $tt = (float) ($lote['total_pares'] ?? 0);
    if ($usarCurvaModulo && $sumaModulo > 0.0001) {
        foreach (($lote['modulo'] ?? []) as $m) {
            $cantPorMedida[(int) ($m['medida'] ?? 0)] = (float) ($m['cantidad'] ?? 0);
        }
        $ps = $sumaModulo;
        $nModulos = (int) max(1, (int) round($tt / $ps));
    } else {
        foreach (($lote['medidas'] ?? []) as $m) {
            $cantPorMedida[(int) ($m['medida'] ?? 0)] = (float) ($m['cantidad'] ?? 0);
        }
        $ps = 0.0;
        foreach ($medidasCols as $medida) {
            $ps += (float) ($cantPorMedida[(int) $medida] ?? 0);
        }
        if ($ps <= 0.0001) {
            $ps = $tt;
        }
        $nModulos = 1;
    }

    $precio = (float) ($lote['precio'] ?? 0);
    $deposito = trim((string) ($lote['deposito_codigo'] ?? ''));
    if ($deposito === '') {
        $deposito = trim((string) ($lote['deposito_nombre'] ?? ''));
    }
@endphp
<tr @if(!empty($lote['en_produccion'])) style="color:#FF0000;" @endif>
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
</tr>
