@php
    $filas = $filas ?? [];
    $totales = $totales ?? null;
    $mostrarTotal = $mostrarTotal ?? true;
    $esExport = ! empty($esExport);
    $sepAsiento = 'border-top: 2px solid #c5d0da;';
@endphp
@unless ($esExport)
<style>
    .tabla-asientos-mes-maq tbody tr.asiento-inicio > td {
        border-top: 2px solid #c5d0da !important;
        background-color: #f4f7f9;
    }
    .tabla-asientos-mes-maq tbody tr.asiento-cabecera > td {
        background-color: #f4f7f9;
    }
    .tabla-asientos-mes-maq tbody tr.asiento-movimiento > td {
        background-color: #ffffff;
    }
</style>
@endunless
<table class="table table-sm table-bordered table-hover mb-0 tabla-asientos-mes-maq">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha asiento</th>
            <th>Jornada</th>
            <th>N&uacute;mero</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Observaci&oacute;n</th>
            <th>Cuenta</th>
            <th>Descripci&oacute;n</th>
            <th>C. costo</th>
            <th class="text-right">Debe</th>
            <th class="text-right">Haber</th>
            @unless ($esExport)
                <th class="width80" data-orderable="false"></th>
            @endunless
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $asiento)
            @php
                $movimientos = $asiento['movimientos'] ?? [];
                $primero = true;
                $esInicioGrupo = ! $loop->first;
                $claseCabecera = $esInicioGrupo ? 'asiento-inicio' : 'asiento-cabecera';
            @endphp
            @if ($movimientos === [])
                <tr class="font-weight-bold {{ $claseCabecera }}"
                    @if ($esExport) style="{{ $esInicioGrupo ? $sepAsiento.' ' : '' }}background:#f4f7f9;" @endif>
                    <td>{{ $asiento['fecha_fmt'] ?? '—' }}</td>
                    <td>{{ $asiento['jornada_fmt'] ?? '—' }}</td>
                    <td>{{ $asiento['numero'] ?? '—' }}</td>
                    <td>{{ $asiento['tipo'] ?? '—' }}</td>
                    <td><small>{{ $asiento['origen'] ?? 'ERP' }}</small></td>
                    <td><small>{{ $asiento['observacion'] ?? '' }}</small></td>
                    <td colspan="3" class="text-muted">Sin movimientos</td>
                    <td class="text-right text-nowrap">{{ number_format((float) ($asiento['total_debe'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right text-nowrap">{{ number_format((float) ($asiento['total_haber'] ?? 0), 2, ',', '.') }}</td>
                    @unless ($esExport)
                        <td class="text-center">
                            @if (! empty($asiento['id']) && can('listar-asiento', false))
                                <a href="{{ route('editar_asiento', ['id' => $asiento['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                   class="btn btn-link btn-sm p-0" target="_blank" rel="noopener" title="Ver asiento">
                                    <i class="fa fa-external-link"></i>
                                </a>
                            @endif
                        </td>
                    @endunless
                </tr>
            @else
                @foreach ($movimientos as $mov)
                    <tr @class([
                            'asiento-inicio' => $primero && $esInicioGrupo,
                            'asiento-cabecera' => $primero && ! $esInicioGrupo,
                            'asiento-movimiento' => ! $primero,
                        ])
                        @if ($esExport && $primero) style="{{ $esInicioGrupo ? $sepAsiento.' ' : '' }}background:#f4f7f9;" @endif>
                        @if ($primero)
                            <td>{{ $asiento['fecha_fmt'] ?? '—' }}</td>
                            <td>{{ $asiento['jornada_fmt'] ?? '—' }}</td>
                            <td class="font-weight-bold">{{ $asiento['numero'] ?? '—' }}</td>
                            <td>{{ $asiento['tipo'] ?? '—' }}</td>
                            <td><small>{{ $asiento['origen'] ?? 'ERP' }}</small></td>
                            <td><small>{{ $asiento['observacion'] ?? '' }}</small></td>
                        @else
                            <td></td><td></td><td></td><td></td><td></td><td></td>
                        @endif
                        <td class="text-nowrap">{{ $mov['cuenta_codigo'] ?? '' }}</td>
                        <td>{{ $mov['cuenta_nombre'] ?? '' }}</td>
                        <td>{{ $mov['centrocosto'] ?? '' }}</td>
                        <td class="text-right text-nowrap">
                            @if ((float) ($mov['debe'] ?? 0) > 0)
                                {{ number_format((float) $mov['debe'], 2, ',', '.') }}
                            @endif
                        </td>
                        <td class="text-right text-nowrap">
                            @if ((float) ($mov['haber'] ?? 0) > 0)
                                {{ number_format((float) $mov['haber'], 2, ',', '.') }}
                            @endif
                        </td>
                        @unless ($esExport)
                            <td class="text-center">
                                @if ($primero && ! empty($asiento['id']) && can('listar-asiento', false))
                                    <a href="{{ route('editar_asiento', ['id' => $asiento['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="btn btn-link btn-sm p-0" target="_blank" rel="noopener" title="Ver asiento">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                @endif
                            </td>
                        @endunless
                    </tr>
                    @php $primero = false; @endphp
                @endforeach
            @endif
        @empty
            <tr>
                <td colspan="{{ $esExport ? 11 : 12 }}" class="text-center text-muted py-3">
                    No hay asientos MAQ en ERP para el per&iacute;odo.
                </td>
            </tr>
        @endforelse
    </tbody>
    @if ($mostrarTotal && $filas !== [] && $totales !== null)
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="9" class="text-right">Totales ({{ (int) ($totales['cantidad'] ?? count($filas)) }} asiento(s))</td>
                <td class="text-right text-nowrap">{{ number_format((float) ($totales['debe'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right text-nowrap">{{ number_format((float) ($totales['haber'] ?? 0), 2, ',', '.') }}</td>
                @unless ($esExport)
                    <td></td>
                @endunless
            </tr>
        </tfoot>
    @endif
</table>
