@extends("theme.$theme.layout")
@section('titulo')
    Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/localidad-cascada.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/admin/localidad-cascada.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/domicilio.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/admin/domicilio.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/arca-padron.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-padron-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/arca-apoc-validacion-async.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/arca-validacion-abm.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/arca-apoc-validacion-abm.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('proveedor', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear Proveedor @if ($tipoalta == 'P') Provisorio @endif</h3>
                <div class="card-tools">
                    <a href="{{$volverListadoUrl}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_proveedor', $filtrosQuery ?? [])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @include('compras.proveedor.partials.barra_solapas', ['mostrar_solapa_ingresos' => false])
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
                    @include('compras.proveedor.partials.arca_validacion_support', ['proveedorId' => null])
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
							<button type="button" id="botonform0" class="btn btn-success">
						   	<i class="fa fa-save"></i> Guardar
							</button>
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
