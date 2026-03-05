@extends("theme.$theme.layout")
@section('titulo')
    Reporte de indicadores EWO - Pivot Fibonacci - VMA - CCI - XTL
@endsection

@section("scripts")
<script>
    $(function () {
        $("#lavel-tiempo").css("display", "none");
        $("#tiempo").css("display", "none");

        $(document).on('change', '#administracionposicion', function(event) {
            if ($("#administracionposicion").val() == 'B')
            {
                $("#lavel-tiempo").css("display", "block");
                $("#tiempo").css("display", "block");
            }
            else
            {
                $("#lavel-tiempo").css("display", "none");
                $("#tiempo").css("display", "none");
            }
        });
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
                <h3 class="card-title">Datos Reporte Indicadores</h3>
            </div>
            <form action="{{route('crear_repindicadores')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('graficos.reporteindicadores.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_repewofib'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
