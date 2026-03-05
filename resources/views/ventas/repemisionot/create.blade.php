@extends("theme.$theme.layout")
@section('titulo')
    Etiquetas de OT
@endsection

@section("scripts")

<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>

<script>

    var url = "{{ route('configurar_salida', ['programa' => ':programa']) }}";

    $(function () {
		$("#ordenestrabajo").focus();
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
                <h3 class="card-title">Datos Emisi&oacute;n de OT</h3>
				@include('includes.configurar-salida')
            </div>
            <form action="{{route('crearemisionot')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repemisionot.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
							<input type="submit" name="extension" id="extension" class="btn-sm btn-info" value="Emite OT"></input>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
