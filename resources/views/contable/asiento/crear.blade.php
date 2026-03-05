@extends("theme.$theme.layout")
@section('titulo')
    Asientos contables
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script>
$( "#botonform0" ).click(function() {
    let flError = false;

    $("#tbody-cuenta-table .moneda").each(function() {
        if ($(this).val() === '')
        {
            alert("Debe ingresar moneda");
            flError = true;
        }
    });

    // Valida montos
    let totDebe = 0;
    let totHaber = 0;

    $("#tbody-cuenta-table .debe").each(function() {
        let valor = parseFloat($(this).val());

        if (valor >= 0)
            totDebe += valor;
    });

    $("#tbody-cuenta-table .haber").each(function() {
        let valor = parseFloat($(this).val());

        if (valor >= 0)
            totHaber += valor;
    });

    if (totDebe - totHaber > 0.009 || totHaber - totDebe > 0.009)
    {
        diferencia = totDebe - totHaber;
        
        alert("No coincide el debe con el haber, diferencia "+diferencia);
        flError = true;
    }

    if (!flError)
        $( "#form-general" ).submit();
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
                <h3 class="card-title">Crear Asiento</h3>
                <div class="card-tools">
                    <a href="{{route('asiento')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_asiento')}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('contable.asiento.form')
                    @include('contable.asiento.form2')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-4">
							<button type="button" id="botonform0" class="btn btn-success">
						     	<i class="fa fa-save"></i> Guardar
							</button>
                    	</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
