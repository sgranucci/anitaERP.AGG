<tr class="grupo-detalle collapse" id="detalle-grupo-{{ $grupoId }}" data-parent="#tabla-paginada">
    <td colspan="10" class="p-0 bg-light">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>C&oacute;digo</th>
                    <th>Fecha</th>
                    <th>Turno</th>
                    <th class="text-right">Resultado</th>
                    <th class="text-right">Transferencia</th>
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
                    <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                    <td><small>{{ $row->turno_label ?? $row->turno ?? '—' }}</small></td>
                    <td class="text-right text-nowrap">{{ number_format((float) $row->resultado_turno, 2, ',', '.') }}</td>
                    <td class="text-right text-nowrap">{{ number_format((float) $row->transferencia, 2, ',', '.') }}</td>
                    <td>
                        @if ($cerrada)
                            <span class="badge badge-success">{{ $row->esCierreContableLegacy() ? 'Cerrada (hist.)' : 'Cerrada' }}</span>
                        @else
                            <span class="badge badge-warning">Pendiente</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if (can('listar-rendicion-maquina', false))
                            <a href="{{ route('imprimir_rendicion_maquina', ['id' => $row->id, 'inline' => 1]) }}"
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
