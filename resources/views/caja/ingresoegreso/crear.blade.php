@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/ingresoegreso/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/conceptogasto/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script>
    var urlConsultaProveedor = "{{ route('editar_proveedor', ':id') }}";
</script>
@endsection

@section('contenido')
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Movimientos de Caja</h3>
                @if (isset($caja_id))
                    <h3 class="card-title">&nbsp&nbsp&nbsp&nbsp&nbsp Caja: {{$caja_id}} - {{$nombreCaja}}</h3>
                @endif
                <div class="card-tools">
                    @if (isset($caja_id))
                        <a href="{{route('consulta_movimiento_caja')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @else
                        <a href="{{route('ingresoegreso')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('guardar_ingresoegreso')}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                @if (isset($caja_id))
                    <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{$caja_id ?? ''}}" >
                @endif
                <input type="hidden" class="origen" id="origen" name="origen" value="{{$origen ?? ''}}" >
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Cheques
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Comprobantes
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Retenciones
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Asiento Contable
                    </button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('caja.ingresoegreso.form')
                    @include('includes.contable.formasientoexterno')
                    @include('caja.ingresoegreso.form6')
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
