@extends("theme.$theme.layout")
@section('titulo')
    Administración de Tickets
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/administracion_ticket/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/tarea_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/tecnico_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/categoria_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ticket/subcategoria_ticket/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Ticket</h3>
                <div class="card-tools">
                    <a href="{{route('consulta_administracion_ticket')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guarda_administracion_ticket')}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Artículos
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Historia
                    </button>                    
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('ticket.administracion_ticket.form')
                    @include('ticket.administracion_ticket.form3')
                    @include('ticket.administracion_ticket.form2')
                    @include('ticket.administracion_ticket.form4')
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
@endsection
