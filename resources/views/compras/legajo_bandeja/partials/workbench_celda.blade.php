@php
    $nCargadasFac = (int) ($row['facturas_cargadas_count'] ?? 0);
    $hayFacPendiente = ! empty($row['facturas_legajo']);
    $alineacion = match ($key) {
        'dias' => 'text-center',
        'facturas' => 'bandeja-col-facturas small',
        'paquete' => 'bandeja-col-paquete',
        'numero' => 'bandeja-col-oc',
        default => 'small',
    };
    $cls = trim($alineacion.' lw-col');
    $textoPlano = \App\Support\Compras\OrdencompraLegajoBandejaListadoColumnas::valorCelda($row, $key);
@endphp
@switch ($key)
    @case('id')
        <td class="{{ $cls }}" @if ($textoPlano !== '') title="{{ $textoPlano }}" @endif>
            <span class="lw-celda-texto">{{ $row['id'] }}</span>
        </td>
        @break
    @case('numero')
        <td class="{{ $cls }}" title="{{ $row['numero'] }}">
            <a href="{{ $row['url_oc'] }}" target="_blank" rel="noopener">{{ $row['numero'] }}</a>
            @if (! empty($row['es_anticipada']))
                <span class="badge badge-warning" title="Legajo anticipado (factura antes de recepción)">
                    <i class="fa fa-clock-o"></i> Anticipado
                </span>
            @endif
            @if (! empty($row['es_gastronomia']))
                <span class="badge badge-info">Gastro</span>
            @endif
        </td>
        @break
    @case('fecha')
        <td class="{{ $cls }}" @if ($textoPlano !== '') title="{{ $textoPlano }}" @endif>
            <span class="lw-celda-texto">{{ $row['fecha'] }}</span>
        </td>
        @break
    @case('empresa')
        <td class="{{ $cls }}" @if (($row['empresa'] ?? '') !== '') title="{{ $row['empresa'] }}" @endif>
            <span class="lw-celda-texto">{{ $row['empresa'] }}</span>
        </td>
        @break
    @case('proveedor')
        <td class="{{ $cls }}" @if (($row['proveedor'] ?? '') !== '') title="{{ $row['proveedor'] }}" @endif>
            <span class="lw-celda-texto">{{ $row['proveedor'] }}</span>
        </td>
        @break
    @case('centrocosto')
        <td class="{{ $cls }}" @if (($row['centrocosto'] ?? '') !== '') title="{{ $row['centrocosto'] }}" @endif>
            <span class="lw-celda-texto">{{ $row['centrocosto'] }}</span>
        </td>
        @break
    @case('sector')
        <td class="{{ $cls }}" @if (($row['sector'] ?? '') !== '') title="{{ $row['sector'] }}" @endif>
            <span class="lw-celda-texto">{{ $row['sector'] }}</span>
        </td>
        @break
    @case('dias')
        <td class="{{ $cls }}">
            @if ((int) $row['dias'] >= (int) ($dias_recordatorio ?? 3))
                <span class="badge badge-warning" title="{{ $row['fecha_ubicacion'] }}">{{ $row['dias'] }}</span>
            @else
                <span title="{{ $row['fecha_ubicacion'] }}">{{ $row['dias'] }}</span>
            @endif
        </td>
        @break
    @case('paquete')
        <td class="{{ $cls }}" @if ($textoPlano !== '') title="{{ $textoPlano }}" @endif>
            @if (! empty($row['paquete_ok']))
                <span class="badge badge-success" title="{{ $row['paquete_titulo'] ?? '' }}">{{ $row['paquete_etiqueta'] ?? ((! empty($row['exige_com']) || ! array_key_exists('exige_com', $row)) ? 'FC + COM' : 'FC (contrato sin COM)') }}</span>
            @else
                @if (! empty($row['tiene_factura']))
                    <span class="badge badge-secondary">FC</span>
                @endif
                @if (! empty($row['tiene_com']))
                    <span class="badge badge-secondary">COM</span>
                @endif
                @if (empty($row['tiene_factura']) && empty($row['tiene_com']))
                    <span class="text-muted">—</span>
                @endif
            @endif
            @if (! empty($row['tiene_com_asignada']))
                <span class="badge badge-primary" title="COM asignada a la factura">asignada</span>
            @endif
            @if (! empty($row['tiene_comprobante']))
                <span class="badge badge-info" title="Todas las facturas y NC del legajo están en CxP">cargada</span>
            @elseif (! empty($row['tiene_comprobante_parcial']))
                <span class="badge badge-warning" title="Hay comprobantes en CxP, pero quedan documentos pendientes">parcial</span>
            @endif
            @if (! empty($row['tiene_pendiente_entrega']))
                <span class="badge badge-secondary" title="Hay facturas retenidas hasta que llegue la mercadería">pend. entrega</span>
            @endif
            @if (! empty($row['tiene_pago']))
                <span class="badge badge-success" title="Orden de pago">{{ ! empty($row['etiqueta_pago']) ? $row['etiqueta_pago'] : 'OP' }}</span>
            @endif
        </td>
        @break
    @case('facturas')
        <td class="{{ $cls }}">
            @if ($hayFacPendiente)
                @foreach ($row['facturas_legajo'] as $facLeg)
                    <div class="bandeja-fac-item">
                        @if (! empty($facLeg['url_pdf']))
                            <a href="{{ $facLeg['url_pdf'] }}" class="text-primary" target="_blank" rel="noopener"
                               title="Abrir PDF en pantalla completa">{{ $facLeg['numero'] ?? '' }}</a>
                        @else
                            <span>{{ $facLeg['numero'] ?? '' }}</span>
                        @endif
                        @if (! empty($facLeg['estado']))
                            @php
                                $estadoFac = (string) ($facLeg['estado'] ?? '');
                                if ($estadoFac === 'en_anita') {
                                    $badgeFac = 'badge-success';
                                    $textoFac = 'en Anita';
                                } elseif ($estadoFac === 'pendiente_entrega') {
                                    $badgeFac = 'badge-secondary';
                                    $textoFac = 'pend. entrega';
                                } else {
                                    $badgeFac = 'badge-warning';
                                    $textoFac = 'pendiente';
                                }
                            @endphp
                            <span class="badge {{ $badgeFac }}">{{ $textoFac }}</span>
                        @endif
                        @if (! empty($facLeg['origen']))
                            <span class="bandeja-fac-origen">{{ $facLeg['origen'] }}</span>
                        @endif
                    </div>
                @endforeach
            @endif
            @if ($nCargadasFac > 0)
                <div class="bandeja-fac-item">
                    <button type="button"
                            class="btn btn-link bandeja-fac-consultar js-bandeja-ver-legajo"
                            data-url-paquete="{{ $row['url_paquete'] }}"
                            data-numero="{{ $row['numero'] }}"
                            data-tab="facturas"
                            title="Consultar el legajo (facturas, COM y OC)">
                        {{ $nCargadasFac }} ya en CxP
                    </button>
                </div>
            @elseif (! $hayFacPendiente)
                <span class="text-muted">—</span>
            @endif
        </td>
        @break
    @case('decision')
        <td class="{{ $cls }}" @if ($textoPlano !== '') title="{{ $textoPlano }}" @endif>
            @if (($row['decision'] ?? '') === 'Aprobado')
                <span class="badge badge-success">Aprobado</span>
            @elseif (($row['decision'] ?? '') === 'Rechazado')
                <span class="badge badge-danger">Rechazado</span>
            @endif
            @if (! empty($row['firmante']))
                <div><small>{{ $row['firmante'] }}</small></div>
            @endif
            @if (! empty($row['fecha_decision']))
                <div><small>{{ $row['fecha_decision'] }}</small></div>
            @endif
            @if (! empty($row['comentario_decision']))
                <div><small class="text-muted">{{ $row['comentario_decision'] }}</small></div>
            @endif
        </td>
        @break
    @case('fecha_alta')
        <td class="{{ $cls }}" @if (($row['created_at'] ?? '') !== '') title="{{ $row['created_at'] }}" @endif>
            <span class="lw-celda-texto">{{ $row['created_at'] ?? '' }}</span>
        </td>
        @break
    @default
        <td class="{{ $cls }}" @if ($textoPlano !== '') title="{{ $textoPlano }}" @endif>
            <span class="lw-celda-texto">{{ $textoPlano }}</span>
        </td>
@endswitch
