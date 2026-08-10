@extends(!empty($acceso_visualizacion_por_hash) ? 'layouts.requisicion-visualizar-hash' : "theme.$theme.layout")
@section('titulo')
Requisición de sala
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/depmae/consulta.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/deposito.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/grabando.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sala/requisicion_sala/grabando.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/crear.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sala/requisicion_sala/crear.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $accesoPorHash = ! empty($acceso_visualizacion_por_hash);
    $modoConsulta = request()->input('vista') === 'consulta';
    $soloConsulta = $accesoPorHash || ! empty($soloConsulta) || $modoConsulta || ! empty($visualizar);
    $ocultarVolver = $accesoPorHash || ! empty($ocultarVolver) || $soloConsulta;
    $modoEdicionCompleta = empty($visualizar)
        && ! empty($puedeActualizarRequisicionSala)
        && ! empty($edicion_completa);
    $modoEdicionMenor = empty($visualizar)
        && ! empty($puedeActualizarRequisicionSala)
        && ! empty($edicion_menor)
        && empty($edicion_completa);
    $puedeGrabar = $modoEdicionCompleta || $modoEdicionMenor;
    $soloLectura = ! $puedeGrabar;
    $bloqueoEstructural = $modoEdicionMenor;
    $mostrarReabrir = empty($visualizar)
        && ! empty($puedeReabrirRequisicionSala)
        && ! empty($puede_reabrir);
    $formAction = $modoEdicionMenor
        ? route('actualizar_datos_menores_requisicion_sala', ['id' => $data->id])
        : route('actualizar_requisicion_sala', ['id' => $data->id]);
@endphp
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @include('sala.requisicion_sala.partials.cumplimientos_sala', ['data' => $data, 'cumplimientos_sala' => $cumplimientos_sala ?? []])
        @if($modoEdicionMenor)
        <div class="alert alert-warning mx-0 mb-0 rounded-0 border-left-0 border-right-0">
            <strong>Edición menor (aprobación vigente).</strong>
            Podés corregir comentario, detalle, zona, prioridad, fecha de entrega, leyenda, UID, Nº parte y artículo.
            Para cambiar cantidad, destino o depósito usá <strong>Reabrir / desaprobar</strong>.
            Líneas con transferencia al laboratorio o cumplimientos activos mantienen el artículo bloqueado.
        </div>
        @elseif(!empty($tiene_transferencia_laboratorio) && $modoEdicionCompleta)
        <div class="alert alert-info mx-0 mb-0 rounded-0 border-left-0 border-right-0">
            <strong>Transferencia al laboratorio asociada.</strong>
            No puede cambiar el artículo ni eliminar líneas reparación/devolución incluidas en la TM.
            Puede completar u actualizar NPU, UID, fuera de servicio y demás datos.
            Si agrega <strong>artículos nuevos</strong>, no entrarán en esa transferencia: deberán moverse con otra TM manual.
        </div>
        @endif
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($soloLectura)
                        Consultar requisición de sala #{{ $data->numerorequisicion }}
                    @elseif ($modoEdicionMenor)
                        Requisición de sala #{{ $data->numerorequisicion }} — datos menores
                    @else
                        Requisición de sala #{{ $data->numerorequisicion }}
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('imprimir_pdf_requisicion_sala', ['id' => $data->id]) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener noreferrer" title="PDF emisi&oacute;n" data-modo-consulta-omitir="1">
                        <i class="fa fa-file-pdf-o"></i> PDF
                    </a>
                    @if (can('cumplir-requisicion-sala', false))
                    <a href="{{ route('cumplir_requisicion_sala', ['requisicion_sala_id' => $data->id]) }}" class="btn btn-outline-success btn-sm" title="Cumplimientos" data-modo-consulta-omitir="1">
                        <i class="fa fa-clipboard-check"></i> Cumplimientos
                    </a>
                    @endif
                    @if($mostrarReabrir)
                    <button type="button" class="btn btn-outline-warning btn-sm" data-toggle="modal" data-target="#modal-reabrir-requisicion-sala" title="Volver a PENDIENTE para cambio de negocio">
                        <i class="fa fa-undo"></i> Reabrir / desaprobar
                    </button>
                    @endif
                    @if(empty($ocultarVolver))
                    <a href="{{ route('consultar_requisicion_sala') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ $formAction }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off"
                data-url-npu="{{ route('requisicion_sala_consulta_npu') }}"
                data-tiene-transferencia-laboratorio="{{ !empty($tiene_transferencia_laboratorio) ? '1' : '0' }}"
                data-edicion-menor="{{ $modoEdicionMenor ? '1' : '0' }}"
                @if($soloLectura) onsubmit="return false;" @endif>
                @csrf
                @method('PUT')
                @if ($modoConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos principales</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">Archivos</button>
                </div>
                <div class="card-body @if($soloLectura) pe-none @endif" @if($soloLectura) style="opacity:.92" @endif>
                    @include('sala.requisicion_sala.form', [
                        'bloqueoEstructural' => $bloqueoEstructural,
                        'modoEdicionMenor' => $modoEdicionMenor,
                    ])
                    <div class="form4" id="requisicion-sala-solapa-archivos" style="display:none;">
                        <p class="text-muted small mb-2">Archivos actuales</p>
                        @include('sala.requisicion_sala.partials.archivos_adjuntos', [
                            'data' => $data,
                            'ocultarInputsConservar' => $soloLectura,
                        ])
                        @include('sala.requisicion_sala.partials.solapa_agregar_archivos', [
                            'data' => $data,
                            'visualizar' => $soloLectura ? true : ($visualizar ?? null),
                        ])
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if ($soloConsulta)
                                @if ($puedeGrabar)
                                    <button type="button" id="botonform0" class="btn btn-success">
                                        <i class="fa fa-save"></i>
                                        @if ($modoEdicionMenor)
                                            Guardar datos menores
                                        @else
                                            Actualizar
                                        @endif
                                    </button>
                                @endif
                                <button type="button" class="btn btn-secondary @if($puedeGrabar) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            @else
                                @if ($puedeGrabar)
                                <button type="button" id="botonform0" class="btn btn-success">
                                    <i class="fa fa-save"></i>
                                    @if ($modoEdicionMenor)
                                        Guardar datos menores
                                    @else
                                        Actualizar
                                    @endif
                                </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </form>
            @if(empty($visualizar) && ($data->estado ?? '') === ($estado_en_laboratorio ?? ''))
                @if (can('enviar-arbol-requisicion-sala', false))
                <form action="{{ route('enviar_arbol_requisicion_sala', ['id' => $data->id]) }}" method="POST" class="d-inline mt-3 ml-3 mb-3"
                      onsubmit="return confirm('¿Enviar al árbol de aprobación?');">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">Enviar al árbol de aprobación</button>
                </form>
                @endif
            @endif
        </div>
    </div>
</div>

@if($mostrarReabrir)
@php
    $reabrirBloqueadoPorCumplimientos = (int) ($cumplimientos_activos ?? 0) > 0;
@endphp
<div class="modal fade" id="modal-reabrir-requisicion-sala" tabindex="-1" role="dialog" aria-labelledby="modal-reabrir-requisicion-sala-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('reabrir_requisicion_sala', ['id' => $data->id]) }}" id="form-reabrir-requisicion-sala">
            @csrf
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modal-reabrir-requisicion-sala-label">Reabrir / desaprobar requisición</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @if($reabrirBloqueadoPorCumplimientos)
                    <div class="alert alert-danger small mb-3">
                        Hay <strong>{{ (int) $cumplimientos_activos }}</strong> cumplimiento(s) activo(s).
                        Primero revertí los cumplimientos y después podrás reabrir.
                    </div>
                    @else
                    <p class="small mb-2">
                        La requisición volverá a <strong>PENDIENTE</strong>. Se limpiará el árbol de aprobación
                        y, si hay transferencia automática al laboratorio confirmada, se revertirá.
                    </p>
                    <p class="small text-danger mb-3">
                        Después deberás corregir los datos y <strong>guardar</strong> para reenviar al árbol.
                    </p>
                    <div class="form-group mb-0">
                        <label for="motivo_reabrir_rs" class="requerido">Motivo</label>
                        <textarea name="motivo" id="motivo_reabrir_rs" class="form-control" rows="3" required minlength="5" maxlength="500"
                            placeholder="Ej.: se cargó mal el artículo / cantidad / depósito…"></textarea>
                    </div>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    @if(!$reabrirBloqueadoPorCumplimientos)
                    <button type="submit" class="btn btn-warning btn-sm"
                        onclick="return confirm('¿Confirmás reabrir la requisición #{{ $data->numerorequisicion }}?');">
                        <i class="fa fa-undo"></i> Reabrir
                    </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@include('sala.requisicion_sala.partials.banner_grabando_styles')
@endsection
