@extends("theme.$theme.layout")
@section('titulo')
    Usuarios
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/stock/depmae/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/tipotransaccion_stock/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/usuario/empresas_roles.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/usuario/depositos.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/usuario/tipotransacciones_stock.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/usuario/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear usuario</h3>
                <div class="card-tools">
                    <a href="{{route('usuario')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_usuario')}}" id="form-general" data-admin-usuario-depositos="1" enctype="multipart/form-data" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="far fa-user"></i> Datos del usuario
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <i class="fas fa-warehouse"></i> Depósitos autorizados
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <i class="fas fa-exchange-alt"></i> Tipos trans. stock
                    </button>
                </div>
                <div class="card-body">
                    @include('admin.usuario.form')
                    @include('admin.usuario.form2')
                    @include('admin.usuario.form3')
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
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultatipotransaccionstock')
@endsection
