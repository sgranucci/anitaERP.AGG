@php
    use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
    use App\Support\Contable\CierreRendicionOrigenConsultaSupport;
    $puedeConsultarRend = CierreRendicionOrigenConsultaSupport::puedeConsultarRendicionEstacionamiento();
    $puedePdfRend = CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionEstacionamiento();
    $puedeConsultarTurno = CierreRendicionOrigenConsultaSupport::puedeConsultarCierreTurnoEstacionamiento();
    $puedePdfTurno = CierreRendicionOrigenConsultaSupport::puedeVerPdfCierreTurnoEstacionamiento();
@endphp
<tr class="grupo-detalle collapse" id="detalle-grupo-{{ $grupoId }}" data-parent="#tabla-paginada">
    <td colspan="13" class="p-0 bg-light">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Ticket</th>
                    <th>Fecha rend.</th>
                    <th>Turno</th>
                    <th class="text-right" title="Venta neta">Venta neta</th>
                    <th class="text-right" title="Notas de cr&eacute;dito">NC</th>
                    <th class="text-right" title="Venta bruta = neta + NC">Venta total</th>
                    <th class="text-right">Invit.</th>
                    <th class="text-right">Cobrado</th>
                    <th>Estado</th>
                    <th class="width160">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rendiciones as $row)
                @php
                    $cerrada = $row->tieneCierreContable();
                    $nc = (float) ($row->totalnotacredito ?? 0);
                    $neta = (float) ($row->totalfactura ?? 0);
                    $bruta = round($neta + $nc, 2);
                    $turnoId = (int) ($row->turno_operativo_estacionamiento_id ?? 0);
                @endphp
                <tr class="{{ $cerrada ? 'table-success' : '' }}">
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                    <td>
                        <small>
                            {{ CierreRendicionEstacionamientoGrupoSupport::etiquetaTurno($row) }}
                            @if ($row->turnoOperativo?->identificador_pc)
                                — {{ $row->turnoOperativo->identificador_pc }}
                            @endif
                        </small>
                    </td>
                    <td class="text-right text-nowrap">{{ number_format($neta, 2, ',', '.') }}</td>
                    <td class="text-right text-nowrap">
                        @if ($nc > 0.009)
                            {{ number_format($nc, 2, ',', '.') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right text-nowrap font-weight-bold">{{ number_format($bruta, 2, ',', '.') }}</td>
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
                                <span class="badge badge-secondary" title="Cerrada sin asiento porque no hubo montos a imputar">
                                    {{ \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::ETIQUETA_ESTADO_LEGACY }}
                                </span>
                            @else
                                <span class="badge badge-success">Cerrada</span>
                            @endif
                        @else
                            <span class="badge badge-warning">Pendiente</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if ($puedeConsultarRend)
                            <a href="{{ route('editar_rendicionestacionamiento', ['id' => $row->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC" title="Consultar rendici&oacute;n" target="_blank" rel="noopener">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if ($puedePdfRend)
                            <a href="{{ route('imprimir_rendicion_estacionamiento', ['id' => $row->id, 'inline' => 1]) }}"
                               class="btn-accion-tabla tooltipsC" title="PDF rendici&oacute;n" target="_blank" rel="noopener">
                                <i class="fa fa-file-pdf-o text-danger"></i>
                            </a>
                        @endif
                        @if ($turnoId > 0 && $puedeConsultarTurno)
                            <a href="{{ route('estacionamiento_cierre_turno_ver', ['id' => $turnoId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC" title="Consultar cierre de turno" target="_blank" rel="noopener">
                                <i class="fas fa-file-invoice text-primary"></i>
                            </a>
                        @endif
                        @if ($turnoId > 0 && $puedePdfTurno)
                            <a href="{{ route('estacionamiento_cierre_turno_comprobante_cierre', ['id' => $turnoId, 'inline' => 1]) }}"
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
