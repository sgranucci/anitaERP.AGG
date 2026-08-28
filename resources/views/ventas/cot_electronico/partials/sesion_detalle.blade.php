@if (!empty($sesionDetalle))
    <div class="card card-info mt-3" id="sesion-detalle">
        <div class="card-header">
            <h3 class="card-title">Sesi&oacute;n #{{ $sesionDetalle->id }} — {{ $sesionDetalle->fecha_envio?->format('d/m/Y H:i') }}</h3>
            <div class="card-tools">
                <a href="{{ route('cot_electronico', $filtrosHistoricoQuery ?? []) }}" class="btn btn-sm btn-outline-light">
                    <i class="fa fa-times"></i> Cerrar detalle
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Fecha facturas:</strong> {{ $sesionDetalle->fecha_facturas?->format('d/m/Y') }}</div>
                <div class="col-md-3"><strong>Reparto:</strong> {{ $sesionDetalle->etiquetaRepartos() !== '' ? $sesionDetalle->etiquetaRepartos() : '—' }}</div>
                <div class="col-md-3"><strong>Ambiente:</strong> {{ strtoupper($sesionDetalle->ambiente) }}</div>
                <div class="col-md-3"><strong>Usuario:</strong> {{ $sesionDetalle->usuarios->nombre ?? $sesionDetalle->usuarios->usuario ?? '—' }}</div>
                <div class="col-md-3"><strong>Archivo:</strong> {{ $sesionDetalle->nombre_archivo ?? '—' }}</div>
                <div class="col-md-3 mt-2"><strong>Comprob. ARBA:</strong> {{ $sesionDetalle->numero_comprobante_arba ?? '—' }}</div>
                <div class="col-md-3 mt-2"><strong>CUIT empresa:</strong> {{ $sesionDetalle->cuit_empresa ?? '—' }}</div>
                <div class="col-md-3 mt-2"><strong>Remitos OK / error:</strong> {{ $sesionDetalle->cantidad_ok }} / {{ $sesionDetalle->cantidad_error }}</div>
                <div class="col-md-3 mt-2">
                    <strong>Estado:</strong>
                    @if ($sesionDetalle->ok)
                        <span class="badge badge-success">OK</span>
                    @else
                        <span class="badge badge-danger">Con errores</span>
                    @endif
                </div>
                @if ($sesionDetalle->error_general)
                    <div class="col-12 mt-2">
                        <div class="alert alert-danger mb-0 py-2 small">{{ $sesionDetalle->error_general }}</div>
                    </div>
                @endif
            </div>

            <div class="mb-2">
                @if ($sesionDetalle->cantidad_ok > 0)
                    <a href="{{ route('sesion_impresion_cot', ['id' => $sesionDetalle->id, 'auto' => 1]) }}"
                        class="btn btn-app bg-success" title="Enviar constancias COT a la impresora">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    <a href="{{ route('sesion_impresion_cot', ['id' => $sesionDetalle->id, 'pdf' => 1]) }}"
                        class="btn btn-app bg-primary" title="Armar PDF de constancias sin enviar a impresora">
                        <i class="fas fa-file-alt"></i> Constancia
                    </a>
                @endif
                <a href="{{ route('listar_cot_electronico_sesion', ['id' => $sesionDetalle->id, 'formato' => 'PDF']) }}"
                    class="btn btn-app bg-danger" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf"></i> Pdf
                </a>
                <a href="{{ route('listar_cot_electronico_sesion', ['id' => $sesionDetalle->id, 'formato' => 'EXCEL']) }}"
                    class="btn btn-app bg-success">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="{{ route('listar_cot_electronico_sesion', ['id' => $sesionDetalle->id, 'formato' => 'CSV']) }}"
                    class="btn btn-app bg-warning">
                    <i class="fas fa-file-csv"></i> Csv
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Tipo</th>
                            <th>Letra</th>
                            <th>Suc.</th>
                            <th>N&deg; remito</th>
                            <th>Fecha remito</th>
                            <th>Fecha env&iacute;o</th>
                            <th>Cliente</th>
                            <th>N&deg; COT</th>
                            <th>N&deg; &uacute;nico</th>
                            <th>Proc.</th>
                            <th>Observaci&oacute;n ARBA</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($remitosSesion as $remito)
                            <tr class="{{ strtoupper($remito->procesado ?? '') !== 'SI' && empty($remito->cot) ? 'table-warning' : '' }}">
                                <td>{{ $remito->tipo }}</td>
                                <td>{{ $remito->letra }}</td>
                                <td>{{ $remito->sucursal }}</td>
                                <td>{{ $remito->numero_remito }}</td>
                                <td>{{ $remito->fecha_remito?->format('d/m/Y') }}</td>
                                <td>{{ $sesionDetalle->fecha_envio?->format('d/m/Y H:i') }}</td>
                                <td>{{ $remito->cliente_nombre ?: ($remito->clientes->nombre ?? '—') }}</td>
                                <td>{{ $remito->cot ?: '—' }}</td>
                                <td class="small">{{ $remito->nro_unico ?: '—' }}</td>
                                <td>{{ $remito->procesado ?: '—' }}</td>
                                <td class="small text-danger">{{ $remito->error ?: '—' }}</td>
                                <td class="text-nowrap">
                                    @if ($remito->fueEmitido())
                                        <a href="{{ route('sesion_impresion_cot', ['id' => $sesionDetalle->id, 'remito_envio_id' => $remito->id, 'auto' => 1]) }}"
                                            class="btn btn-outline-success btn-sm" title="Imprimir esta constancia">
                                            <i class="fa fa-print"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
