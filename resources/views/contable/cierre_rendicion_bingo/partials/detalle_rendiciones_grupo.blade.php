@php
    use App\Support\Contable\CierreRendicionOrigenConsultaSupport;
    $puedePdfRend = CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionBingo();
    $puedePdfTurno = CierreRendicionOrigenConsultaSupport::puedeVerPdfCierreTurnoBingo();
@endphp
<tr class="grupo-detalle collapse" id="detalle-grupo-{{ $grupoId }}" data-parent="#tabla-paginada">
    <td colspan="10" class="p-0 bg-light">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Ticket</th>
                    <th>Fecha rend.</th>
                    <th>Turno</th>
                    <th class="text-right">Cartones</th>
                    <th class="text-right">Dep&oacute;sito</th>
                    <th>Estado</th>
                    <th class="width160">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rendiciones as $row)
                @php
                    $cerrada = $row->tieneCierreContable();
                    $turnoId = (int) ($row->turno_operativo_bingo_id ?? 0);
                @endphp
                <tr class="{{ $cerrada ? 'table-success' : '' }}">
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                    <td>
                        <small>
                            {{ $row->turnoOperativo?->turno?->nombre ?? '—' }}
                            @if ($row->turnoOperativo?->identificador_pc)
                                — {{ $row->turnoOperativo->identificador_pc }}
                            @endif
                        </small>
                    </td>
                    <td class="text-right text-nowrap">{{ number_format((float) $row->total_cartones, 2, ',', '.') }}</td>
                    <td class="text-right text-nowrap">{{ number_format((float) $row->deposito, 2, ',', '.') }}</td>
                    <td>
                        @if ($cerrada)
                            <span class="badge badge-success">Cerrada</span>
                        @else
                            <span class="badge badge-warning">Pendiente</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if ($puedePdfRend)
                            <a href="{{ route('imprimir_rendicion_bingo', ['id' => $row->id, 'inline' => 1]) }}"
                               class="btn-accion-tabla tooltipsC" title="PDF rendici&oacute;n" target="_blank" rel="noopener">
                                <i class="fa fa-file-pdf-o text-danger"></i>
                            </a>
                        @endif
                        @if ($turnoId > 0 && $puedePdfTurno)
                            <a href="{{ route('bingo_cierre_turno_comprobante_cierre', ['id' => $turnoId]) }}"
                               class="btn-accion-tabla tooltipsC" title="PDF cierre de turno" target="_blank" rel="noopener">
                                <i class="fa fa-file-pdf-o"></i>
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </td>
</tr>
