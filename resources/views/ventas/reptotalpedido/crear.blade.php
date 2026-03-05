@extends("theme.$theme.layout")
@section('titulo')
    Totales de Pedidos de venta
@endsection

@section("scripts")

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Reporte Totales de Pedidos de Venta</h3>
            </div>
            <form action="{{route('crear_reptotalpedido')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.reptotalpedido.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_reptotalpedido'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
