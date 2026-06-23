@extends("theme.$theme.layout")
@section('titulo')
    Cumplir requisici&oacute;n de sala
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($pdfToken))
            <div class="alert alert-success">
                Cumplimiento grabado.
                <a href="{{ route('pdf_cumplir_requisicion_sala', ['token' => $pdfToken]) }}" class="btn btn-sm btn-outline-dark ml-2" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf"></i> Imprimir comprobante PDF
                </a>
            </div>
        @endif
        @if (!empty($errorCarga))
            <div class="alert alert-warning">{{ $errorCarga }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cumplimiento de requisici&oacute;n de sala</h3>
            </div>
            <form action="{{ route('grabar_cumplir_requisicion_sala') }}" method="POST" id="form-cumple-requisicion-sala" autocomplete="off">
                @csrf
                <input type="hidden" name="requisicion_sala_id" id="requisicion_sala_id" value="{{ old('requisicion_sala_id', $requisicion->id ?? '') }}">
                <input type="hidden" id="empresa_id" value="{{ $requisicion->empresa_id ?? '' }}">

                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="tabs-modo-cumple">
                        <li class="nav-item">
                            <a class="nav-link {{ empty($modoNpu) ? 'active' : '' }}" href="{{ route('cumplir_requisicion_sala') }}">Por n&uacute;mero de requisici&oacute;n</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ !empty($modoNpu) ? 'active' : '' }}" href="{{ route('cumplir_requisicion_sala', ['modo' => 'npu']) }}">Carga por NPU</a>
                        </li>
                    </ul>

                    <div id="bloque-modo-numero" class="{{ !empty($modoNpu) ? 'd-none' : '' }}">
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">Requisici&oacute;n</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="requisicion_display" readonly
                                    value="{{ $requisicion ? ('#'.$requisicion->numerorequisicion.' — id '.$requisicion->id) : '' }}"
                                    placeholder="Use la lupa para buscar requisici&oacute;n aprobada">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" id="btn-consulta-requisicion-cumple" title="Buscar requisici&oacute;n">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div id="bloque-modo-npu" class="{{ empty($modoNpu) ? 'd-none' : '' }}">
                        <div class="form-group row">
                            <label for="input-npu-cumple" class="col-lg-2 col-form-label requerido">NPU / QR</label>
                            <div class="col-lg-4">
                                <input type="text" class="form-control" id="input-npu-cumple" maxlength="50" placeholder="Escanee o ingrese NPU y Enter" autocomplete="off">
                            </div>
                            <div class="col-lg-6">
                                <p class="form-text text-muted mb-0">Agrega una l&iacute;nea pendiente por cada NPU. Puede mezclar &iacute;tems de distintas requisiciones aprobadas.</p>
                            </div>
                        </div>
                    </div>

                    <div id="bloque-cabecera-requisicion" class="{{ $requisicion ? '' : 'd-none' }}">
                        <div class="row mb-3" id="fila-resumen-cabecera">
                            <div class="col-md-4">
                                <strong>Estado:</strong> <span id="cabecera-estado">{{ $requisicion->estado ?? '' }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Fecha:</strong> <span id="cabecera-fecha">{{ optional($requisicion->fecha ?? null)->format('d/m/Y') }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong>F. entrega req.:</strong> <span id="cabecera-fecha-entrega">{{ optional($requisicion->fecha_entrega ?? null)->format('d/m/Y') }}</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Empresa:</strong> <span id="cabecera-empresa">{{ $requisicion->empresas->nombre ?? '' }}</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Dep&oacute;sito destino:</strong> <span id="cabecera-deposito">{{ $requisicion->depositos->nombre ?? '' }}</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Centro costo:</strong> <span id="cabecera-centrocosto">{{ $requisicion ? trim(($requisicion->centrocostos->codigo ?? '').' '.($requisicion->centrocostos->nombre ?? '')) : '' }}</span>
                            </div>
                            <div class="col-md-12 mt-2 {{ empty($modoNpu) ? 'd-none' : '' }}" id="cabecera-npu-resumen">
                                <span class="badge badge-info" id="badge-requisiciones-npu">0 l&iacute;neas cargadas</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="tabla-lineas-cumple">
                                <thead style="background-color:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Req.</th>
                                        <th>Art&iacute;culo</th>
                                        <th>Descripci&oacute;n</th>
                                        <th class="text-right">Pend.</th>
                                        <th>Dep. origen</th>
                                        <th>T&eacute;cnico</th>
                                        <th>UID</th>
                                        <th>NPU</th>
                                        <th class="text-right">Cant. cumple</th>
                                        <th>Motivo pend.</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-lineas-cumple">
                                    @if ($requisicion && $lineas->isNotEmpty())
                                        @foreach ($lineas as $idx => $linea)
                                            @php
                                                $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);
                                            @endphp
                                            <tr class="fila-cumple-linea" data-linea-id="{{ $linea->id }}" data-requisicion-id="{{ $requisicion->id }}">
                                                <td>#{{ $requisicion->numerorequisicion }}</td>
                                                <td>{{ $linea->articulos->sku ?? '' }}</td>
                                                <td>{{ $linea->articulos->nombre ?? $linea->detalle }}</td>
                                                <td class="text-right pendiente-cell">{{ number_format($pendiente, 2, '.', '') }}</td>
                                                <td>
                                                    @include('stock.partials.campo_consulta_deposito', [
                                                        'prefix' => 'cumple_'.$linea->id,
                                                        'layout' => 'inline',
                                                        'inputName' => 'lineas['.$idx.'][deposito_origen_id]',
                                                        'depositoId' => $depositoLabId,
                                                        'codigo' => $depositoLab->codigo ?? '',
                                                        'descripcion' => $depositoLab->nombre ?? '',
                                                    ])
                                                </td>
                                                <td>
                                                    <select name="lineas[{{ $idx }}][tecnico_laboratorio_id]" class="form-control form-control-sm select-tecnico" required>
                                                        <option value="">Seleccione&hellip;</option>
                                                        @foreach ($tecnicos as $tec)
                                                            <option value="{{ $tec->id }}">{{ $tec->nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>{{ $linea->uid }}</td>
                                                <td>{{ $linea->numeroparte }}</td>
                                                <td>
                                                    <input type="hidden" name="lineas[{{ $idx }}][requisicion_sala_articulo_id]" value="{{ $linea->id }}">
                                                    <input type="hidden" name="lineas[{{ $idx }}][estadoparcial]" class="input-estadoparcial" value="">
                                                    <input type="hidden" name="lineas[{{ $idx }}][estado_linea]" class="input-estado-linea" value="">
                                                    <input type="hidden" name="lineas[{{ $idx }}][fecha_entrega]" class="input-fecha-entrega" value="">
                                                    <input type="hidden" name="lineas[{{ $idx }}][numeroremito]" class="input-numeroremito" value="">
                                                    <input type="hidden" name="lineas[{{ $idx }}][nombreresponsable]" class="input-nombreresponsable" value="">
                                                    <input type="number" step="0.01" min="0" name="lineas[{{ $idx }}][cantidad_entrega]" class="form-control form-control-sm input-cantidad-entrega text-right" data-pendiente="{{ number_format($pendiente, 4, '.', '') }}">
                                                </td>
                                                <td class="motivo-parcial-label small text-muted"></td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group row mt-3">
                            <label for="leyenda" class="col-lg-2 col-form-label">Leyenda</label>
                            <div class="col-lg-8">
                                <textarea name="leyenda" id="leyenda" class="form-control" rows="3" placeholder="Leyendas ...">{{ old('leyenda') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-grabar-cumple" {{ $requisicion ? '' : 'disabled' }}>
                        <i class="fa fa-save"></i> Grabar cumplimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('sala.cumplir_requisicion_sala.partials.modales', [
    'estado_parcial_enum' => $estado_parcial_enum,
    'estado_linea_enum' => $estado_linea_enum,
])
@include('includes.stock.modalconsultadeposito')
@endsection

@section('scripts')
<script>
    window.cumpleRequisicionSalaConfig = {
        urlConsulta: @json(route('consulta_requisicion_sala_cumple')),
        urlConsultaNpu: @json(route('consulta_npu_cumple_requisicion_sala')),
        urlDatos: @json(url('sala/cumplir-requisicion-sala/datos')),
        urlCumplir: @json(route('cumplir_requisicion_sala')),
        urlPdf: @json(url('sala/cumplir-requisicion-sala/pdf')),
        depositoLabId: {{ (int) $depositoLabId }},
        depositoLabCodigo: @json($depositoLab->codigo ?? ''),
        depositoLabNombre: @json($depositoLab->nombre ?? ''),
        modoNpu: {{ !empty($modoNpu) ? 'true' : 'false' }},
        estadoParcialEnum: @json($estado_parcial_enum),
    };
</script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sala/cumplir_requisicion_sala/form.js') }}"></script>
@endsection
