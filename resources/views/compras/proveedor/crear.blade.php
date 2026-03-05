@extends("theme.$theme.layout")
@section('titulo')
    Proveedores
@endsection

@section("styles")

input:invalid {
  background-color: pink;
}

@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/crear.js")}}" type="text/javascript"></script>
<script>
$( "#botonform0" ).click(function() {
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
                <h3 class="card-title">Crear Proveedor @if ($tipoalta == 'P') Provisorio @endif</h3>
                <div class="card-tools">
                    <a href="{{route('proveedor')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_proveedor')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="col-lg-8" align="right" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                    <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-cash-register"></span> Datos impuestos
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-truck"></span> Formas de pago
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-comment"></span> Leyendas
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('compras.proveedor.form1')
                    @if (can('actualiza-impuestos', false))
                        @include('compras.proveedor.form2')
                    @else
                        @include('compras.proveedor.formronly2')
                    @endif
                    @include('compras.proveedor.form3')
                    @include('compras.proveedor.form4')
                    @include('compras.proveedor.form5')
                </div>
                <div class="card-footer">
                	<div class="row">
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
