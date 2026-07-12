@php
    use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
@endphp
<tr class="grupo-detalle collapse" id="detalle-grupo-{{ $grupoId }}" data-parent="#tabla-paginada">
    <td colspan="11" class="p-0 bg-light">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Ticket</th>
                    <th>Fecha rend.</th>
                    <th>Turno / PC</th>
                    <th class="text-right">Ventas</th>
                    <th class="text-right">Invit.</th>
                    <th class="text-right">Cobrado</th>
                    <th>Estado</th>
                    <th class="width120">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rendiciones as $row)
                @php
                    $cerrada = $row->tieneCierreContable();
                @endphp
                <tr class="{{ $cerrada ? 'table-success' : '' }}">
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                    <td>
                        <small>
                            #{{ $row->turno_operativo_estacionamiento_id }}
                            @if ($row->turnoOperativo?->identificador_pc)
                                — {{ $row->turnoOperativo->identificador_pc }}
                            @endif
                        </small>
                    </td>
                    <td class="text-right text-nowrap">{{ number_format((float) $row->totalfactura, 2, ',', '.') }}</td>
                    <td class="text-right text-nowrap">
                        @if ((float) $row->totalinvitacion > 0.009)
                            {{ number_format((float) $row->totalinvitacion, 2, ',', '.') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right text-nowrap">{{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
                    <td>
                        @if ($cerrada)
                            @if ($row->esCierreContableLegacy())
                                <span class="badge badge-secondary">Hist&oacute;rico</span>
                            @else
                                <span class="badge badge-success">Cerrada</span>
                            @endif
                        @else
                            <span class="badge badge-warning">Pendiente</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if (can('listar-rendicion-estacionamiento-caja', false))
                            <a href="{{ route('editar_rendicionestacionamiento', ['id' => $row->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC" title="Ver rendici&oacute;n" target="_blank" rel="noopener">
                                <i class="fa fa-edit"></i>
                            </a>
                            <a href="{{ route('imprimir_rendicion_estacionamiento', ['id' => $row->id, 'inline' => 1]) }}"
                               class="btn-accion-tabla tooltipsC" title="PDF rendici&oacute;n" target="_blank" rel="noopener">
                                <i class="fa fa-file-pdf-o text-danger"></i>
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </td>
</tr>
