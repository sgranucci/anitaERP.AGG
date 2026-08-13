@extends("theme.$theme.layout")
@section('titulo')
    Administración de Tickets
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/administracion_ticket/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/tarea_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/tecnico_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/categoria_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/subcategoria_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consulta_administracion_ticket', $filtrosQuery ?? []);
@endphp
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear Ticket</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guarda_administracion_ticket', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                @include('includes.tabs-activas-estilos')
                <div class="card-body">
                    <div class="tabs-activas">
                        @include('ticket.administracion_ticket.partials.tabs_nav')
                    </div>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('ticket.administracion_ticket.form')
                        </div>
                        <div class="tab-pane fade" id="tab-articulos" role="tabpanel">
                            @include('ticket.administracion_ticket.form2')
                        </div>
                        <div class="tab-pane fade" id="tab-historia" role="tabpanel">
                            @include('ticket.administracion_ticket.form3')
                        </div>
                        <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                            @include('ticket.administracion_ticket.form4')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@endsection
