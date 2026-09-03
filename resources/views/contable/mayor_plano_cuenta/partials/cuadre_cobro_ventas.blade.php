@php
    $cuadre = $cuadre_cobro_ventas ?? null;
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
@if (! empty($cuadre))
    <div class="px-3 py-2 border-bottom bg-white">
        <p class="small font-weight-bold text-muted mb-2">Cuadre total comprobantes (listado IVA ventas / subdiario)</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.82rem; max-width: 720px;">
                <thead>
                    <tr style="background-color: #85C1E9; color: #17202A;">
                        <th>Concepto</th>
                        <th class="text-right">Neto Debe (D &minus; H)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Deudores por ventas ({{ $cuadre['deudores_codigo'] ?: '113100' }})</td>
                        <td class="text-right">{{ $fmt($cuadre['deudores'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Caja contado ({{ $cuadre['caja_codigo'] ?: '111100' }})</td>
                        <td class="text-right">{{ $fmt($cuadre['caja'] ?? 0) }}</td>
                    </tr>
                    <tr class="font-weight-bold" style="background:#fef9e7;">
                        <td>Total cobro (= columna Total del listado)</td>
                        <td class="text-right">{{ $fmt($cuadre['total_cobro'] ?? 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-2">
            <i class="fa fa-info-circle"></i>
            Las ventas de contado no pasan por Deudores: el d&eacute;bito va a Caja. Comparar el total del listado solo con 113100
            deja diferencia por el importe de Caja.
        </p>
    </div>
@endif
