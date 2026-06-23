@extends("theme.$theme.layout")
@section('titulo')
Requisición de sala
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/depmae/consulta.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/deposito.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sala/requisicion_sala/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Requisición de sala #{{ $data->numerorequisicion }}</h3>
                <div class="card-tools">
                    <a href="{{ route('imprimir_pdf_requisicion_sala', ['id' => $data->id]) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener noreferrer" title="PDF emisión">
                        <i class="fa fa-file-pdf-o"></i> PDF
                    </a>
                    @if(empty($visualizar))
                    <a href="{{ route('consultar_requisicion_sala') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_requisicion_sala', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off"
                data-url-npu="{{ route('requisicion_sala_consulta_npu') }}">
                @csrf
                @method('PUT')
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos principales</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">Archivos</button>
                </div>
                <div class="card-body">
                    @include('sala.requisicion_sala.form')
                    <div class="form4" id="requisicion-sala-solapa-archivos" style="display:none;">
                        <p class="text-muted small mb-2">Archivos actuales</p>
                        @include('sala.requisicion_sala.partials.archivos_adjuntos', [
                            'data' => $data,
                            'ocultarInputsConservar' => ! empty($visualizar),
                        ])
                        @include('sala.requisicion_sala.partials.solapa_agregar_archivos', [
                            'data' => $data,
                            'visualizar' => $visualizar ?? null,
                        ])
                    </div>
                </div>
                <div class="card-footer">
                    @if(empty($visualizar))
                        @if (can('actualizar-requisicion-sala', false))
                        <button type="button" id="botonform0" class="btn btn-success"><i class="fa fa-save"></i> Actualizar</button>
                        @endif
                    @endif
                </div>
            </form>
            @if(empty($visualizar) && ($data->estado ?? '') === ($estado_a_compras ?? ''))
                @if (can('enviar-arbol-requisicion-sala', false))
                <form action="{{ route('enviar_arbol_requisicion_sala', ['id' => $data->id]) }}" method="POST" class="d-inline mt-3"
                      onsubmit="return confirm('¿Enviar al árbol de aprobación?');">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">Enviar al árbol de aprobación</button>
                </form>
                @endif
            @endif
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@endsection
