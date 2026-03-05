@extends("theme.$theme.layout")
@section('titulo')
    Cat&aacute;logo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/stock/linea/rangolinea.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Cat&aacute;logo de Productos</h3>
            </div>
            <form action="{{route('crear_catalogo')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('stock.combinacion.catalogo.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-pdf')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
