@php
    $conFoto = ($imprimefoto ?? '') === 'CON_FOTO';
    $medidasCols = $medidas_columnas ?? [];

    // Cantidades reales del stock (por talle).
    $cantReales = [];
    $tt = (float) ($lote['total_pares'] ?? 0);
    foreach (($lote['medidas'] ?? []) as $m) {
        $cantReales[(int) ($m['medida'] ?? 0)] = (float) ($m['cantidad'] ?? 0);
    }

    // Curva unitaria del módulo (solo si divide limpio el total: PS × N = TT).
    // Si no (EVO 9 pares con módulo 12, BALI 44 con módulo 12), mostrar talles reales.
    $sumaModulo = 0.0;
    $curvaModulo = [];
    foreach (($lote['modulo'] ?? []) as $m) {
        $cant = (float) ($m['cantidad'] ?? 0);
        if (abs($cant) > 0.0001) {
            $curvaModulo[(int) ($m['medida'] ?? 0)] = $cant;
            $sumaModulo += $cant;
        }
    }
    $usarCurvaModulo = false;
    $nModulos = 1;
    $ps = $tt;
    if ($sumaModulo > 0.0001 && abs($tt) > 0.0001) {
        $nCalc = (int) round($tt / $sumaModulo);
        if ($nCalc >= 1 && abs($tt - ($nCalc * $sumaModulo)) < 0.051) {
            $usarCurvaModulo = true;
            $ps = $sumaModulo;
            $nModulos = $nCalc;
        }
    }
    $cantPorMedida = $usarCurvaModulo ? $curvaModulo : $cantReales;
    if (! $usarCurvaModulo) {
        $ps = 0.0;
        foreach ($medidasCols as $medida) {
            $ps += (float) ($cantPorMedida[(int) $medida] ?? 0);
        }
        if (abs($ps) < 0.0001) {
            $ps = $tt;
        }
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
</tr>
