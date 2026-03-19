@extends("theme.$theme.layout")
@section('titulo')
    Kilos Pedidos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/ventas/transporte/consulta.js")}}" type="text/javascript"></script>
<script>
    $(function () {
        activa_eventos_consultatransporte();
    });
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Reporte Kilos Pedidos</h3>
            </div>
            <form action="{{route('crear_rep_kilopedido')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repkilopedido.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_repkilopedido'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
