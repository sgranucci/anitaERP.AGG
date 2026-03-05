@extends("theme.$theme.layout")
@section('titulo')
    Reporte Maestro de Clientes
@endsection

@section("scripts")
<script>
    $(function () {
        $("#estado").change(function(){
            var estado = $(this).val();

            switch(estado)
            {
            case 'ACTIVOS':
            case 'TODOS':
                $("#tiposuspensioncliente_id").hide();
                break;
            case 'SUSPENDIDOS':
                $("#tiposuspensioncliente_id").show();
                break;
            }
        });

        $("#tiposuspensioncliente_id").hide();
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
                <h3 class="card-title">Datos Reporte Maesto de Clientes</h3>
            </div>
            <form action="{{route('crear_repcliente')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repcliente.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_repcliente'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
