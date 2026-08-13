@php
    use App\Support\Contable\CierreRendicionOrigenConsultaSupport;
    $puedeConsultarRend = CierreRendicionOrigenConsultaSupport::puedeConsultarRendicionMaquinavending();
    $puedePdfRend = CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionMaquinavending();
    $puedePdfVentas = CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionVentasMaquinavending();
@endphp
<tr class="grupo-detalle collapse" id="detalle-grupo-{{ $grupoId }}" data-parent="#tabla-paginada">
    <td colspan="11" class="p-0 bg-light">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>ID</th>
                    <th>Ticket</th>
                    <th>Fecha rend.</th>
                    <th>M&aacute;quina</th>
                    <th class="text-right">Ventas</th>
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
                    $origenId = (int) ($row->maquinavending_rendicion_id ?? 0);
                @endphp
                <tr class="{{ $cerrada ? 'table-success' : '' }}">
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                    <td>
                        <small>{{ $row->maquinavending?->nombre ?? '—' }}</small>
                        @if ($row->maquinavendingRendicion?->numero_cierre)
                            <br><span class="text-muted">Cierre ventas #{{ $row->maquinavendingRendicion->numero_cierre }}</span>
                        @endif
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
                        @if ($puedeConsultarRend)
                            <a href="{{ route('editar_rendicionmaquinavending', ['id' => $row->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="btn-accion-tabla tooltipsC" title="Consultar rendici&oacute;n caja" target="_blank" rel="noopener">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        @if ($puedePdfRend)
                            <a href="{{ route('imprimir_rendicion_maquinavending', ['id' => $row->id, 'inline' => 1]) }}"
                               class="btn-accion-tabla tooltipsC" title="PDF rendici&oacute;n caja" target="_blank" rel="noopener">
                                <i class="fa fa-file-pdf-o text-danger"></i>
                            </a>
                        @endif
                        @if ($origenId > 0 && $puedePdfVentas)
                            <a href="{{ route('maquinavending_rendicion_comprobante', ['id' => $origenId, 'inline' => 1]) }}"
                               class="btn-accion-tabla tooltipsC" title="PDF rendici&oacute;n ventas (origen)" target="_blank" rel="noopener">
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
