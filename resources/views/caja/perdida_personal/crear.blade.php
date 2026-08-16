@extends("theme.$theme.layout")
@section('titulo')
    P&eacute;rdidas de personal
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script>
    window.perdidaPersonalConceptosMaquina = @json($conceptos_con_maquina ?? [6, 8]);
    window.perdidaPersonalImputacionDefault = @json((int) ($imputacion_default_codigo ?? 4));
    window.perdidaPersonalCatalogosUrls = {
        consulta: @json(route('consultar_catalogo_perdida_personal')),
        resolver: @json(route('resolver_catalogo_perdida_personal'))
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/perdida_personal/consulta_catalogos.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/perdida_personal/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('perdida_personal', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear p&eacute;rdida de personal</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_perdida_personal', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('caja.perdida_personal.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacentrocosto')
@include('includes.caja.modalconsultacatalogoperdidapersonal')
@endsection
