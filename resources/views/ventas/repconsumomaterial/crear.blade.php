@extends("theme.$theme.layout")
@section('titulo')
    Reporte de Consumo de Materiales por Pedido
@endsection

@section("scripts")
<script>
    $(function () {
        $("#tipolistado").change(function(){
            var tipolistado = $(this).val();

            switch(tipolistado)
            {
            case 'CAPELLADA':
                $("#desde-capellada").show();
                $("#hasta-capellada").show();
                $("#tipocapellada").show();
                $("#desde-avio").hide();
                $("#hasta-avio").hide();
                $("#tipoavio").hide();
                break;
            case 'AVIO':
                $("#desde-capellada").hide();
                $("#hasta-capellada").hide();
                $("#tipocapellada").hide();
                $("#desde-avio").show();
                $("#hasta-avio").show();
                $("#tipoavio").show();
                break;
            default:
                $("#desde-capellada").hide();
                $("#hasta-capellada").hide();
                $("#tipocapellada").hide();
                $("#desde-avio").hide();
                $("#hasta-avio").hide();
                $("#tipoavio").hide();
            }
        });

        $("#desde-capellada").hide();
        $("#hasta-capellada").hide();
        $("#tipocapellada").hide();
        $("#desde-avio").hide();
        $("#hasta-avio").hide();
        $("#tipoavio").hide();
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
                <h3 class="card-title">Datos Reporte Consumo de Materiales</h3>
            </div>
            <form action="{{route('crear_repconsumomaterial')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repconsumomaterial.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_repconsumomaterial'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
