{{-- Celdas de mercadería: detalle (caja/unidad/cantidad) o solo cantidad --}}
@php
    use App\Support\Ventas\PedidoListadoSupport;
    use App\Support\Ventas\VentasListadoEtiquetasSupport;
    $claseNum = $claseNum ?? 'text-right';
    $leer = static function ($totales, string $clave): float {
        if (is_array($totales)) {
            return (float) ($totales[$clave] ?? 0);
        }
        if (is_object($totales)) {
            return (float) ($totales->{$clave} ?? 0);
        }

        return 0.0;
    };
    $caja = $leer($totales ?? null, 'caja');
    $pieza = $leer($totales ?? null, 'pieza');
    $kilo = $leer($totales ?? null, 'kilo');
    $fmt = $fmt ?? static fn ($v) => PedidoListadoSupport::formatearTotal($v);
@endphp
@if (VentasListadoEtiquetasSupport::muestraCajaUnidad())
    <td class="{{ $claseNum }}">{{ $fmt($caja) }}</td>
    <td class="{{ $claseNum }}">{{ $fmt($pieza) }}</td>
@endif
<td class="{{ $claseNum }}">{{ $fmt($kilo) }}</td>
