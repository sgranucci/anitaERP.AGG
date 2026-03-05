@extends("theme.$theme.layout")
@section('titulo')
    Proveedores
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/crear.js")}}" type="text/javascript"></script>
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
                <h3 class="card-title">Editar Proveedor </h3>&nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->nombre}}
                
                @if ($tipoalta == 'P')
                    &nbsp; PROVEEDOR PROVISORIO
                @endif

                <div class="card-tools">
                    @if ($tipoalta == 'P')
                        <button type="button" id="botontipoalta" class="btn btn-info btn-sm">
                            <i class="fa fa-bell"></i> Cambia a DEFINITIVO
                        </button>
                    @endif
					<button type="button" id="botonestado" class="btn btn-info btn-sm">
                        <i class="fa fa-bell"></i> Estado {{ $data->descripcionestado }}
                    </button>
                    @if ($tipoconsulta == "REMOTA")
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a consulta
                        </a>
                    @else
                        <a href="{{route('proveedor')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_proveedor', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
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
                    @include('compras.proveedor.suspensionmodal')
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
