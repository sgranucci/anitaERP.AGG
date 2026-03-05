@extends("theme.$theme.layout")
@section('titulo')
    Rendición
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/receptivo/guia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/receptivo/movil/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/conceptogasto/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/rendicionreceptivo/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Rendición - Número {{$data->numerotalonario}}</h3>
                <div class="card-tools">
                    @if ($origen == 'movimientocaja')
                        <a href="{{route('consulta_movimiento_caja')}}" class="btn btn-outline-info btn-sm">
                    @else
                        <a href="{{route('rendicionreceptivo')}}" class="btn btn-outline-info btn-sm">
                    @endif
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_rendicionreceptivo', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Gastos anteriores
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Vouchers
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Gastos a compensar
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Comisiones
                    </button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Adelantos
                    </button>                                  
                </div>
                <div class="card-body">
                    @include('caja.rendicionreceptivo.form')
                    @include('caja.rendicionreceptivo.form2')
                    @include('caja.rendicionreceptivo.form3')
                    @include('caja.rendicionreceptivo.form4')
                    @include('caja.rendicionreceptivo.form5')
                    @include('caja.rendicionreceptivo.form6')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-4">
							<button type="button" id="botonform0" class="btn btn-success">
						     	<i class="fa fa-save"></i> Actualizar
							</button>
                    	</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
