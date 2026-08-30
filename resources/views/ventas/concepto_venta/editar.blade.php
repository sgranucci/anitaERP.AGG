@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de venta
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/concepto_venta/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('concepto_venta', $filtrosQuery ?? []);
    $soloConsulta = $solo_consulta ?? false;
    $puedeActualizar = $puede_actualizar ?? can('actualizar-conceptos-venta', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar concepto de venta</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_concepto_venta', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body {{ $soloConsulta && ! $puedeActualizar ? 'pe-none' : '' }}">
                    @include('ventas.concepto_venta.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if ($puedeActualizar)
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
@include('includes.contable.modalconsultacentrocosto')
@endsection
