@extends("theme.$theme.layout")
@section('titulo')
    Clientes
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/domicilioentrega.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/localidad/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/provincia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cliente/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/imprimirHtml.js")}}" type="text/javascript"></script>
<script>
    $(function () {
        $("#botontipoalta").click(function(){
                var tipoalta = 'D';
                
                $("#tipoalta").val(tipoalta);
                $("#botontipoalta").css('visibility', 'hidden');
        });
    });
    function sub()
	{
        
		$('#form-general').submit();
	}
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Cliente </h3>&nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->nombre}}
                
                @if ($tipoalta == config('cliente.tipoalta')['PROVISORIO'][0])
                    &nbsp; CLIENTE PROVISORIO
                @endif

                <div class="card-tools">
        			<button type="button" id="botonemitenc" class="btn btn-info btn-sm border btn-danger">
                        <i id="iconoemitenc" class="fa fa-times"></i> Emite NC
                    </button>
                    @if ($tipoalta == config('cliente.tipoalta')['PROVISORIO'][0])
                        <button type="button" id="botontipoalta" class="btn btn-info btn-sm">
                            <i class="fa fa-bell"></i> Cambia a DEFINITIVO
                        </button>
                    @endif
					<button type="button" id="botonestado" class="btn btn-info btn-sm">
                        <i class="fa fa-bell"></i> Estado {{ $data->descripcionestado }}
                    </button>
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
            <form action="{{route('actualizar_cliente', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <input type="hidden" id="emitenotadecredito" name="emitenotadecredito" value="{{old('emitenotadecredito', $data->emitenotadecredito ?? '')}}" >
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
                    @include('ventas.cliente.suspensionmodal')
                </div>
                <div class="card-footer" style="padding-top: 0">
                	<div class="row">
                   		<div class="col-lg-4">
                        	<button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
                    	</div>
            		</div>
            	</div>
            </form>
        </div>
    </div>
</div>
@endsection
