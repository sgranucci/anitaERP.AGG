@if (is_array($cobrosPostCierre) && ! empty($cobrosPostCierre['tiene_anomalias']))
    <div class="alert alert-warning mt-3 mb-0 cobros-post-cierre-panel">
        <h6 class="alert-heading mb-2">
            <i class="fa fa-exclamation-triangle"></i>
            Cobros en tótem posteriores al cierre ({{ $cobrosPostCierre['cantidad_comandas'] ?? 0 }})
        </h6>
        <p class="small mb-2 mb-md-3">
            Comandas dentro de la ventana de jornada cobradas en Waitry después del cierre
            ({{ $cobrosPostCierre['cierre_jornada_en_fmt'] ?? '—' }}).
            El Informe Z histórico no cambia; el total de Tesorería incluye estos importes para la facturación post-cierre.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2 bg-white">
                <thead class="thead-light">
                    <tr>
                        <th>Comanda</th>
                        <th>Medio</th>
                        <th class="text-right">Monto</th>
                        <th>Colocada</th>
                        <th>Cobrada Waitry</th>
                        <th>Factura proceso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cobrosPostCierre['comandas'] ?? [] as $comanda)
                        <tr>
                            <td>
                                {{ $comanda['display_id'] ?? '—' }}
                                @if (! empty($comanda['waitry_order_id']))
                                    <span class="text-muted small">(#{{ (int) $comanda['waitry_order_id'] }})</span>
                                @endif
                            </td>
                            <td>{{ $comanda['medio_etiqueta'] ?? '—' }}</td>
                            <td class="text-right">$ {{ number_format((float) ($comanda['total'] ?? 0), 2, ',', '.') }}</td>
                            <td>{{ $comanda['placed_at_fmt'] ?? '—' }}</td>
                            <td>
                                {{ $comanda['cobro_en_fmt'] ?? '—' }}
                                @if (! empty($comanda['minutos_despues_cierre']))
                                    <span class="text-muted small">(+{{ (int) $comanda['minutos_despues_cierre'] }} min)</span>
                                @endif
                            </td>
                            <td class="small">
                                @if (! empty($comanda['facturada_proceso']))
                                    {{ $comanda['numero_comprobante'] ?? 'Sí' }}
                                    @if (! empty($comanda['cierre_jornada_proceso_lote']))
                                        <span class="text-muted">(lote {{ (int) $comanda['cierre_jornada_proceso_lote'] }})</span>
                                    @endif
                                @else
                                    <span class="text-muted">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="row small font-weight-bold">
            <div class="col-md-4">
                Informe Z al cierre:
                <span class="d-block">$ {{ number_format((float) ($cobrosPostCierre['total_cierre_historico'] ?? 0), 2, ',', '.') }}</span>
            </div>
            <div class="col-md-4">
                + Post-cierre tótem:
                <span class="d-block text-warning">$ {{ number_format((float) ($cobrosPostCierre['total_post_cierre'] ?? 0), 2, ',', '.') }}</span>
            </div>
            <div class="col-md-4">
                = Total Tesorería:
                <span class="d-block text-dark" style="font-size:1.05rem;">$ {{ number_format((float) ($cobrosPostCierre['total_tesoreria'] ?? 0), 2, ',', '.') }}</span>
            </div>
        </div>
    </div>
@endif
