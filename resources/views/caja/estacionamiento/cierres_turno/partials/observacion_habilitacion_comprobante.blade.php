@php
    $textoHab = trim((string) ($d['observacion_habilitacion'] ?? ''));
    $anulaciones = is_array($d['anulaciones_cierre'] ?? null) ? $d['anulaciones_cierre'] : [];
@endphp

@if ($textoHab !== '')
<tr>
    <td class="lbl">Obs. habilitación</td>
    <td colspan="3" class="bloque-obs">{{ $textoHab }}</td>
</tr>
@endif

@if (count($anulaciones) > 0)
<tr>
    <td class="lbl">Anulaciones cierre</td>
    <td colspan="3" class="celda-anulaciones">
        <table class="tabla-anulaciones">
            <thead>
                <tr>
                    <th>Fecha anulación</th>
                    <th>Cierre</th>
                    <th>Usuario / PC</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($anulaciones as $a)
                <tr>
                    <td>{{ $a['fecha_anulacion'] ?: '—' }}</td>
                    <td>
                        @if (!empty($a['cierre_numero']))
                            #{{ $a['cierre_numero'] }}
                            @if (!empty($a['cierre_fecha']))
                                <span class="muted">({{ $a['cierre_fecha'] }})</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (!empty($a['usuario']) || !empty($a['pc']))
                            {{ $a['usuario'] ?: '—' }}
                            @if (!empty($a['pc']))
                                <span class="muted">· PC {{ $a['pc'] }}</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="motivo-anulacion">{{ $a['motivo'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </td>
</tr>
@endif
