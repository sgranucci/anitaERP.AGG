@extends("theme.$theme.layout")
@section('titulo')
    Clientes UIF
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/domicilionacimiento.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/actividad_uif/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/uif/cliente_uif/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/imprimirHtml.js")}}" type="text/javascript"></script>

<!-- Bootstrap Date-Picker Plugin -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
<script>
    function sub()
	{
		$('#form-general')[0].submit();
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
                <h3 class="card-title">Editar Cliente UIF </h3>&nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->nombre}}
                <div class="card-tools">
					<button type="button" id="botonestado" class="btn btn-info btn-sm">
                        <i class="fa fa-bell"></i> Estado {{ $data->descripcionestado }}
                    </button>
                    <a href="{{route('consulta_cliente_uif')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualiza_cliente_uif', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos UIF
                    </button>                    
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Premios
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Riesgo
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('uif.cliente_uif.form1')
                    @include('uif.cliente_uif.form2')
                    @include('uif.cliente_uif.form3')
                    @include('uif.cliente_uif.form4')
                    @include('uif.cliente_uif.form5')
                </div>
                <div class="card-footer" style="padding-top: 0">
                	<div class="row">
                   		<div class="col-lg-4">
                        	<button type="button" onclick="sub()" class="btn btn-success">Actualizar</button>
                    	</div>
            		</div>
            	</div>
            </form>
        </div>
    </div>
</div>
@endsection
