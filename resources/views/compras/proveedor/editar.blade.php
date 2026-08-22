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
    $urlAplicarCc = route('aplicacion_cuentacorriente_proveedor', [
        'proveedor_id' => $data->id,
        'origen' => 'modal_consulta',
        'vista' => 'consulta',
        'volver_proveedor_id' => $data->id,
    ]);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Editar Proveedor
                    <small class="font-weight-normal">ID {{ $data->id }} · {{ $data->nombre }} · Código Anita {{ $data->codigo }}</small>
                    @if ($tipoalta == 'P')
                        <span class="badge badge-warning ml-2">PROVISORIO</span>
                    @endif
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if ($tipoalta == 'P')
                        <button type="button" id="botontipoalta" class="btn btn-info btn-sm mr-1">
                            <i class="fa fa-bell"></i> Cambia a DEFINITIVO
                        </button>
                    @endif
                    @if (can('listar-cuentacorriente-proveedor', false))
                        <a href="{{route('listar_cuentacorriente_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm mr-1" title="Cuenta Corriente (se abre en modo consulta)">
                            <i class="fa fa-folder-open"></i> Cuenta Corriente
                        </a>
                    @endif
                    @if (can('aplicar-cuentacorriente-proveedor', false))
                        <a href="{{ $urlAplicarCc }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm mr-1" title="Aplicar NC y pagos a cuenta (nueva solapa)">
                            <i class="fa fa-compress-alt"></i> Aplicar CC
                        </a>
                    @endif
                    @if (!empty($url_nuevo_ticket_ingreso))
                        <a href="{{ $url_nuevo_ticket_ingreso }}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm mr-1" title="Solicitar ticket de ingreso a planta">
                            <i class="fa fa-id-badge"></i> Ticket de ingreso
                        </a>
                    @endif
                    @if (can('listar-encuesta-proveedor', false))
                        <a href="{{route('listar_encuesta_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm mr-1" title="Encuestas del Proveedor">
                            <i class="fa fa-question"></i> Encuestas
                        </a>
                    @endif
                    @if (can('listar-requisicion-proveedor', false))
                        <a href="{{route('listar_requisicion_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm mr-1" title="Requisiciones del Proveedor">
                            <i class="fa fa-edit"></i> Requisiciones
                        </a>
                    @endif
                    @if (can('listar-ordencompra-proveedor', false))
                        <a href="{{route('listar_ordencompra_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm mr-1" title="Ordenes de Compra del Proveedor">
                            <i class="fa fa-shopping-cart"></i> Ordenes de compra
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
                @include('compras.proveedor.partials.barra_solapas')
                <div class="card-body" style="padding-bottom: 0; padding-top: 5px;">
                    @include('compras.proveedor.form1')
                    @if (can('actualiza-impuestos', false))
                        @include('compras.proveedor.form2')
                    @else
                        @include('compras.proveedor.formronly2')
                        <div id="tab2" class="d-none" data-arca-constancia-url="{{ route('arca_constancia_inscripcion') }}" aria-hidden="true"></div>
                        @include('compras.proveedor.arca-padron-modals')
                    @endif
                    @include('compras.proveedor.form3')
                    @include('compras.proveedor.form4')
                    @include('compras.proveedor.form5')
                    @include('compras.proveedor.form6')
                    @include('compras.proveedor.form7')
                    @if (!empty($mostrar_solapa_ingresos))
                        @include('compras.proveedor.form8')
                    @endif
                    @include('compras.proveedor.suspensionmodal')
                    @include('compras.proveedor.partials.arca_validacion_support', ['proveedorId' => $data->id])
                    @include('compras.proveedor.partials.arca_apoc_validacion_support', ['proveedorId' => $data->id])
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
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
