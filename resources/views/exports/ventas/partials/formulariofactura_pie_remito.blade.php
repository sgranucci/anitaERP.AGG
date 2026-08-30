@php
    $caiRemito = $caiRemito ?? \App\Support\Ventas\CaiRemitoVigenteSupport::paraVenta($venta);
    $fechaVtoCai = $caiRemito?->fecha_vencimiento
        ? \Illuminate\Support\Carbon::parse($caiRemito->fecha_vencimiento)->format('d/m/Y')
        : '';
    $leyendasRemito = $leyendasRemito ?? ['leyenda1' => '', 'leyenda2' => '', 'leyenda3' => '', 'leyenda' => ''];
    $notasRemito = [];
    foreach (['leyenda1', 'leyenda2', 'leyenda3', 'leyenda'] as $claveLeyenda) {
        $textoNota = trim((string) ($leyendasRemito[$claveLeyenda] ?? ''));
        if ($textoNota !== '') {
            $notasRemito[] = $textoNota;
        }
    }
    $decCantPie = (int) config('facturacion.DECIMAL_CANTIDAD');
@endphp
<div class="factura-pie-bloque factura-pie-remito">
    <table class="table borderless factura-remito-pie-grid">
        <tr>
            <td class="factura-remito-pie-izq">
                @if ($notasRemito !== [])
                    <p class="factura-leyenda">
                        <strong>Observaciones</strong>
                        {{ implode(' ', $notasRemito) }}
                    </p>
                @endif
            </td>
            <td class="factura-remito-pie-der">
                <div class="factura-totales-wrap">
                    <table cellpadding="0" cellspacing="0" class="table table-sm table-bordered table-striped tabla-totales-importes">
                        <tbody>
                            <tr>
                                <td>Total kilos</td>
                                <td></td>
                                <td class="text-right">{{ number_format($totalKilosRemito ?? 0, $decCantPie) }}</td>
                            </tr>
                            <tr>
                                <td>Total bultos</td>
                                <td></td>
                                <td class="text-right">{{ number_format($totalBultosRemito ?? 0, 0) }}</td>
                            </tr>
                            <tr class="fila-total-final">
                                <td style="{{ $facturaPdfCeldaTotales }}"><strong>Total asegurado</strong></td>
                                <td style="{{ $facturaPdfCeldaTotales }}"></td>
                                <td class="text-right" style="{{ $facturaPdfCeldaTotales }}">
                                    <strong>{{ number_format($valorAsegurado ?? 0, 2) }}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>
    @if ($caiRemito && $caiRemito->numero_cai)
        <p class="factura-pie-cai">
            CAI: {{ $caiRemito->numero_cai }}<br>
            Fecha vencimiento CAI: {{ $fechaVtoCai }}
        </p>
    @endif
</div>
