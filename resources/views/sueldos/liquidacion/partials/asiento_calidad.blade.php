@php
    $cuadre = $preview['cuadre'] ?? [];
    $informe = $preview['informe_conceptos'] ?? [];
@endphp
@if ($cuadre !== [])
    <div class="table-responsive mb-2">
        <table class="table table-sm table-bordered mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Cuadre vs cabecera</th>
                    <th class="text-right">Cabecera / recibos</th>
                    <th class="text-right">Asiento</th>
                    <th class="text-right">Diferencia</th>
                    <th class="text-center" style="width:90px">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cuadre as $fila)
                    <tr>
                        <td>
                            {{ $fila['label'] }}
                            @if (! empty($fila['mensaje']))
                                <div class="small text-muted">{{ $fila['mensaje'] }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format((float) $fila['cabecera'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $fila['asiento'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $fila['diff'], 2, ',', '.') }}</td>
                        <td class="text-center">
                            @if (! empty($fila['ok']))
                                <span class="badge badge-success">OK</span>
                            @elseif (! empty($fila['bloquea']))
                                <span class="badge badge-danger">Bloquea</span>
                            @else
                                <span class="badge badge-warning">Aviso</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($informe !== [])
    <details class="mb-2">
        <summary class="small mb-1" style="cursor:pointer;">
            Informe concepto → cuenta ({{ count($informe) }} conceptos con importe)
        </summary>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Concepto</th>
                        <th>Tipo</th>
                        <th class="text-right">Importe</th>
                        <th>Debe</th>
                        <th>Haber</th>
                        <th>Origen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($informe as $c)
                        <tr>
                            <td>
                                {{ $c['codigo'] }} {{ $c['descripcion'] }}
                                @if (($c['motivo'] ?? '') !== '')
                                    <div class="small text-muted">{{ $c['motivo'] }}</div>
                                @endif
                            </td>
                            <td>{{ $c['tipo_label'] ?? $c['tipo'] }}</td>
                            <td class="text-right">{{ number_format((float) $c['importe'], 2, ',', '.') }}</td>
                            <td>{{ $c['cuenta_debe_codigo'] !== '' ? $c['cuenta_debe_codigo'] : '—' }}</td>
                            <td>{{ $c['cuenta_haber_codigo'] !== '' ? $c['cuenta_haber_codigo'] : '—' }}</td>
                            <td>
                                @if (! empty($c['en_asiento']))
                                    <span class="badge badge-info">{{ $c['origen_label'] }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $c['origen_label'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif
