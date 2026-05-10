@extends("theme.$theme.layout")
@section('titulo')
Nueva lista de precios proveedor
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/listaprecio_proveedor/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Nueva lista de precios</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_listaprecio_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_listaprecio_proveedor') }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos principales</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Archivos asociados</button>
                </div>
                <div class="card-body">
                    @include('compras.listaprecio_proveedor.form')
                    @include('compras.listaprecio_proveedor.form_archivos')
                </div>
                <div class="card-footer">
                    <div class="col-lg-4">
                        @include('includes.boton-form-crear')
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
