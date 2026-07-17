@extends("theme.$theme.layout")
@section('titulo')
    Proveedores
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/domicilio.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/arca-padron.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-padron-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/arca-validacion-abm.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/arca-apoc-validacion-abm.js') }}" type="text/javascript"></script>
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
@php
    $volverListadoUrl = route('proveedor', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar Proveedor </h3>&nbsp;ID:&nbsp;{{$data->id }}&nbsp;{{$data->nombre}}&nbsp; &nbsp; &nbsp;Código Anita: {{$data->codigo}}
                
                @if ($tipoalta == 'P')
                    &nbsp; PROVEEDOR PROVISORIO
                @endif

                <div class="card-tools">
                    @if ($tipoalta == 'P')
                        <button type="button" id="botontipoalta" class="btn btn-info btn-sm">
                            <i class="fa fa-bell"></i> Cambia a DEFINITIVO
                        </button>
                    @endif
                    @if (can('listar-cuentacorriente-proveedor', false))
                        <a href="{{route('listar_cuentacorriente_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Cuenta Corriente (se abre en modo consulta)">
                            <i class="fa fa-folder-open">Cuenta Corriente</i>
                        </a>
                    @endif       
                    @if (can('listar-encuesta-proveedor', false))
                        <a href="{{route('listar_encuesta_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Encuestas del Proveedor (se abre en modo consulta)">
                            <i class="fa fa-question">Encuestas</i>
                        </a>
                    @endif  
                    @if (can('listar-requisicion-proveedor', false))
                        <a href="{{route('listar_requisicion_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Requisiciones del Proveedor (se abre en modo consulta)">
                            <i class="fa fa-edit">Requisiciones</i>
                        </a>   
                    @endif                             
                    @if (can('listar-ordencompra-proveedor', false))
                        <a href="{{route('listar_ordencompra_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Ordenes de Compra del Proveedor (se abre en modo consulta)">
                            <i class="fa fa-shopping-cart">Ordenes de compra</i>
                        </a>        
                    @endif           
                    @if ($tipoconsulta == "REMOTA")
                        <a href="javascript:history.back()" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a consulta
                        </a>
                    @else
                        <a href="{{$volverListadoUrl}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_proveedor', ['id' => $data->id] + ($filtrosQuery ?? []))}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
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
                    <button type="button" id="btn-consulta-arca-padron-crear" class="btn btn-outline-secondary btn-sm" title="Ingresá el CUIT y consultá el padrón ARCA">
                        <i class="fa fa-search"></i> Consulta padrón ARCA
                    </button>
                </div>
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('compras.proveedor.form1')
                    @if (can('actualiza-impuestos', false))
                        @include('compras.proveedor.form2')
                    @else
                        @include('compras.proveedor.formronly2')
                        {{-- Endpoint ARCA + modales (formronly2 no incluye tab2 ni vistas ARCA) --}}
                        <div id="tab2" class="d-none" data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}" aria-hidden="true"></div>
                        @include('compras.proveedor.arca-padron-modals')
                    @endif
                    @include('compras.proveedor.form3')
                    @include('compras.proveedor.form4')
                    @include('compras.proveedor.form5')
                    @include('compras.proveedor.suspensionmodal')
                    @include('compras.proveedor.partials.arca_validacion_support', ['proveedorId' => $data->id])
                    @include('compras.proveedor.partials.arca_apoc_validacion_support', ['proveedorId' => $data->id])
                </div>
                <div class="card-footer" style="padding-top: 0">
                	<div class="row">
                   		<div class="col-lg-4">
                        	<button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
                    	</div>
            		</div>
            	</div>
            </form>
            @include('compras.proveedor.arca-cuit-entry-modal')
            @include('includes.compras.arca_impuestos_validacion_modal')
            @include('includes.compras.arca_apoc_validacion_modal')
        </div>
    </div>
</div>
@endsection
