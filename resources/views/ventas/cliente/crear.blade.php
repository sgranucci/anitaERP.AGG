@extends("theme.$theme.layout")
@section('titulo')
    Clientes
@endsection

@section("styles")

input:invalid {
  background-color: pink;
}

@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilioentrega.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/localidad/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/provincia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/crear.js")}}" type="text/javascript"></script>
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
                <h3 class="card-title">Crear Cliente @if ($tipoalta == 'P') Provisorio @endif</h3>
                <div class="card-tools">
                    @if (isset($urlOrigen))
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a consulta
                        </a>
                    @else
                        <a href="{{route('cliente')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            @if ($tipoalta == 'P')
                <form action="{{route('guardar_cliente_provisorio')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
            @else
                <form action="{{route('guardar_cliente')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
            @endif
                @csrf
                <input type="hidden" id="emitenotadecredito" name="emitenotadecredito" value="{{old('emitenotadecredito', $data->emitenotadecredito ?? '')}}" >
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Datos facturaci&oacute;n
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Lugares de entrega
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Leyendas
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Seguimiento
                    </button>                    
                    <button type="button" id="botonform7" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Articulos suspendidos
                    </button>
                    <button type="button" id="botonform8" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> CM05
                    </button>                       
                </div>                
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('ventas.cliente.form1')
                    @include('ventas.cliente.form2')
                    @include('ventas.cliente.form3')
                    @include('ventas.cliente.form4')
                    @include('ventas.cliente.form5')
                    @include('ventas.cliente.form6')
                    @include('ventas.cliente.form7')
                    @include('ventas.cliente.form8')
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
