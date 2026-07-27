{{-- Subtotal, descuento, IVA por tasa y total (moneda del primer ítem de la OC). Importes con moneda adelante. --}}
@php
    $mon = trim((string) ($monedaPdf ?? ''));
    $impMon = static function (float $v, string $mon, bool $negativo = false): string {
        $sign = $negativo ? '-' : '';
        $n = number_format(abs($v), 2, ',', '.');
        if ($mon !== '') {
            return htmlspecialchars($mon, ENT_QUOTES, 'UTF-8').' '.$sign.$n;
        }

        return $sign.$n;
    };
    $dtoValor = ($data->descuento ?? null) !== null ? (float) $data->descuento : null;
    $dtoTipo = \App\Support\Compras\OrdencompraDescuentoSupport::normalizarTipo($data->descuento_tipo ?? null);
    $dtoEsImporte = $dtoTipo === \App\Support\Compras\OrdencompraDescuentoSupport::TIPO_IMPORTE;
    $pctEfectivo = (float) ($totalesOc['descuento_porcentaje_efectivo'] ?? 0);
    if ($pctEfectivo <= 0 && $dtoValor !== null && $dtoValor > 0 && ! $dtoEsImporte) {
        $pctEfectivo = (float) $dtoValor;
    }
@endphp
<table class="pdf-totales">
    <tbody>
        <tr>
            <td>Subtotal ítems (sin IVA, antes de descuento)</td>
            <td class="num">{{ $impMon((float) ($totalesOc['subtotal_bruto_sin_iva'] ?? 0), $mon) }}</td>
        </tr>
        <tr>
            <td>
                @if ($dtoEsImporte)
                    Descuento cabecera (monto)
                @else
                    Descuento cabecera (%)
                @endif
            </td>
            <td class="num">
                @if ($dtoValor === null || $dtoValor <= 0)
                    —
                @elseif ($dtoEsImporte)
                    {{ $impMon($dtoValor, $mon) }}
                    @if ($pctEfectivo > 0)
                        <span class="text-muted">({{ number_format($pctEfectivo, 2, ',', '.') }} %)</span>
                    @endif
                @else
                    {{ number_format($dtoValor, 2, ',', '.') }} %
                @endif
            </td>
        </tr>
        @if (abs((float) ($totalesOc['importe_descuento'] ?? 0)) > 0.0005 || ($dtoValor !== null && $dtoValor > 0))
            <tr>
                <td>Importe descuento (sobre subtotal ítems)</td>
                <td class="num">{{ $impMon(abs((float) ($totalesOc['importe_descuento'] ?? 0)), $mon, true) }}</td>
            </tr>
        @endif
        <tr>
            <td>Neto gravado (sin IVA)</td>
            <td class="num">{{ $impMon((float) ($totalesOc['neto_sin_iva'] ?? 0), $mon) }}</td>
        </tr>
        @foreach ($totalesOc['filas_iva'] ?? [] as $fi)
            <tr>
                <td>IVA {{ number_format((float) ($fi['tasa'] ?? 0), 2, ',', '.') }}%</td>
                <td class="num">{{ $impMon((float) ($fi['importe'] ?? 0), $mon) }}</td>
            </tr>
        @endforeach
        @if (empty($totalesOc['filas_iva']) && ((float) ($totalesOc['iva_total'] ?? 0)) > 0.0005)
            <tr>
                <td>IVA</td>
                <td class="num">{{ $impMon((float) $totalesOc['iva_total'], $mon) }}</td>
            </tr>
        @endif
        <tr class="pdf-totales-final">
            <td>TOTAL</td>
            <td class="num">{{ $impMon((float) ($totalesOc['total'] ?? 0), $mon) }}</td>
        </tr>
    </tbody>
</table>
