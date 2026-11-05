@php
    $pesos = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-list-alt"></i> Anexo III — Cajeros ampliado</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-anexo3new.c</code>: cierre X, efectivo, tarjetas, QR, recargo, fichas, cashless, online, apuestas deportivas, resultado y diferencia.
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo3&amp;recalcular=1">Recalcular</a>
        </p>
        @if ($anexo3 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo III. {{ $anexo3Error }}</div>
        @else
            @foreach ($anexo3['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Caja</th>
                            <th>Cajero</th>
                            <th class="text-right">Cierre X</th>
                            <th class="text-right">Egresos</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">T. crédito</th>
                            <th class="text-right">T. débito</th>
                            <th class="text-right">QR</th>
                            <th class="text-right">Recargo</th>
                            <th class="text-right">Cta.cte.</th>
                            <th class="text-right">Ingresos</th>
                            <th class="text-right">Hab. fichas</th>
                            <th class="text-right">Rend. fichas</th>
                            <th class="text-right">Pago cashless</th>
                            <th class="text-right">Vta. cashless</th>
                            <th class="text-right">Pago online</th>
                            <th class="text-right">Vta. online</th>
                            <th class="text-right">Apuestas</th>
                            <th class="text-right">Resultado</th>
                            <th class="text-right">Dif.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($anexo3['filas'] as $fila)
                            <tr>
                                <td>{{ $fila['caja'] }}</td>
                                <td>{{ $fila['cajero'] }}</td>
                                <td class="text-right">{{ $pesos($fila['cierre_x']) }}</td>
                                <td class="text-right">{{ $pesos($fila['egresos']) }}</td>
                                <td class="text-right">{{ $pesos($fila['efectivo']) }}</td>
                                <td class="text-right">{{ $pesos($fila['tarjeta_credito']) }}</td>
                                <td class="text-right">{{ $pesos($fila['tarjeta_debito']) }}</td>
                                <td class="text-right">{{ $pesos($fila['total_qr']) }}</td>
                                <td class="text-right">{{ $pesos($fila['recargo_fin']) }}</td>
                                <td class="text-right">{{ $pesos($fila['ctacte']) }}</td>
                                <td class="text-right">{{ $pesos($fila['ingresos']) }}</td>
                                <td class="text-right">{{ $pesos($fila['hab_fichas']) }}</td>
                                <td class="text-right">{{ $pesos($fila['rend_fichas']) }}</td>
                                <td class="text-right">{{ $pesos($fila['pago_cashless']) }}</td>
                                <td class="text-right">{{ $pesos($fila['vta_cashless']) }}</td>
                                <td class="text-right">{{ $pesos($fila['pago_online']) }}</td>
                                <td class="text-right">{{ $pesos($fila['venta_online']) }}</td>
                                <td class="text-right">{{ $pesos($fila['apuestas']) }}</td>
                                <td class="text-right">{{ $pesos($fila['resultado']) }}</td>
                                <td class="text-right">{{ $pesos($fila['diferencia']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="20" class="text-center text-muted">Sin filas.</td></tr>
                        @endforelse
                        @if ($anexo3['filas'] !== [])
                            @php $t = $anexo3['totales']; @endphp
                            <tr style="font-weight:bold;background:#f5f5f5;">
                                <td colspan="2">Total</td>
                                <td class="text-right">{{ $pesos($t['cierre_x']) }}</td>
                                <td class="text-right">{{ $pesos($t['egresos']) }}</td>
                                <td class="text-right">{{ $pesos($t['efectivo']) }}</td>
                                <td class="text-right">{{ $pesos($t['tarjeta_credito']) }}</td>
                                <td class="text-right">{{ $pesos($t['tarjeta_debito']) }}</td>
                                <td class="text-right">{{ $pesos($t['total_qr']) }}</td>
                                <td class="text-right">{{ $pesos($t['recargo_fin']) }}</td>
                                <td class="text-right">{{ $pesos($t['ctacte']) }}</td>
                                <td class="text-right">{{ $pesos($t['ingresos']) }}</td>
                                <td class="text-right">{{ $pesos($t['hab_fichas']) }}</td>
                                <td class="text-right">{{ $pesos($t['rend_fichas']) }}</td>
                                <td class="text-right">{{ $pesos($t['pago_cashless']) }}</td>
                                <td class="text-right">{{ $pesos($t['vta_cashless']) }}</td>
                                <td class="text-right">{{ $pesos($t['pago_online']) }}</td>
                                <td class="text-right">{{ $pesos($t['venta_online']) }}</td>
                                <td class="text-right">{{ $pesos($t['apuestas']) }}</td>
                                <td class="text-right">{{ $pesos($t['resultado']) }}</td>
                                <td class="text-right">{{ $pesos($t['diferencia']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
