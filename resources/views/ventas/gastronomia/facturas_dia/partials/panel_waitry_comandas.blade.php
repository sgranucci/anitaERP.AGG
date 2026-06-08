@if (($waitry_comandas ?? []) !== [])
    @php $soloTabla = ! empty($solo_tabla); @endphp
    @if (! $soloTabla)
        <div class="card card-outline card-info mb-3" id="waitry-comandas-panel">
            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                <span>
                    <i class="fa fa-list-alt"></i>
                    <strong>Comandas Waitry</strong>
                    <span class="badge badge-info ml-1">{{ count($waitry_comandas) }}</span>
                    @if ($es_factura_cierre_jornada ?? false)
                        <span class="badge badge-secondary ml-1">Cierre jornada</span>
                    @endif
                    @if (($cierre_jornada_proceso_lote ?? null) !== null && (int) $cierre_jornada_proceso_lote > 0)
                        <span class="small text-muted ml-1">Lote {{ (int) $cierre_jornada_proceso_lote }}</span>
                    @endif
                </span>
                @if (($waitry_comandas_total ?? 0) > 0.0001)
                    <span class="small text-muted">
                        Total comandas: {{ number_format((float) $waitry_comandas_total, 2, ',', '.') }}
                    </span>
                @endif
            </div>
            <div class="card-body py-2 px-2">
    @else
        <p class="small text-muted mb-2">
            Comandas Waitry incluidas en esta factura
            @if ($es_factura_cierre_jornada ?? false)
                (emisión cierre de jornada
                @if (($cierre_jornada_proceso_lote ?? null) !== null && (int) $cierre_jornada_proceso_lote > 0)
                    — lote {{ (int) $cierre_jornada_proceso_lote }}
                @endif
                ).
            @else
                .
            @endif
        </p>
    @endif
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0 gastro-waitry-comandas-grid">
                        <thead class="thead-light">
                            <tr>
                                <th># Waitry</th>
                                <th>Papelito / ref.</th>
                                <th>Fecha/hora</th>
                                <th>Medio Waitry</th>
                                <th class="text-right gastro-col-monto">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($waitry_comandas as $comanda)
                                <tr>
                                    <td class="text-nowrap">{{ $comanda['waitry_order_id'] }}</td>
                                    <td>
                                        @if (! empty($comanda['display_id']))
                                            <strong>{{ $comanda['display_id'] }}</strong>
                                        @endif
                                        @if (! empty($comanda['referencia_waitry']))
                                            @if (! empty($comanda['display_id']))
                                                <br>
                                            @endif
                                            <small class="text-muted">{{ $comanda['referencia_waitry'] }}</small>
                                        @endif
                                        @if (empty($comanda['display_id']) && empty($comanda['referencia_waitry']))
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap"><small>{{ $comanda['placed_at_fmt'] ?? '—' }}</small></td>
                                    <td><small>{{ $comanda['medio_waitry_label'] ?? '—' }}</small></td>
                                    <td class="text-right gastro-col-monto">
                                        {{ number_format((float) ($comanda['total'] ?? 0), 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if (count($waitry_comandas) > 1 && ($waitry_comandas_total ?? 0) > 0.0001)
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="4" class="text-right">Total comandas</td>
                                    <td class="text-right gastro-col-monto">
                                        {{ number_format((float) $waitry_comandas_total, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
    @if (! $soloTabla)
            </div>
        </div>
    @endif
@endif
