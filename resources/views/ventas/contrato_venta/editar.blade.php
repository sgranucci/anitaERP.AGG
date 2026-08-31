@extends("theme.$theme.layout")
@section('titulo')
    Abonos / contratos de venta
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/concepto_venta/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/contrato_venta/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('contrato_venta', $filtrosQuery ?? []);
    $soloConsulta = $solo_consulta ?? false;
    $puedeActualizar = $puede_actualizar ?? can('actualizar-contratos-venta', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar abono / contrato de venta</h3>
                <div class="card-tools">
                    @if ($soloConsulta)
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close();">
                            Cerrar solapa
                        </button>
                    @else
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_contrato_venta', ['id' => $data->id] + ($filtrosQuery ?? []) + ($soloConsulta ? ['origen' => 'modal_consulta', 'vista' => 'consulta'] : [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body {{ $soloConsulta && ! $puedeActualizar ? 'pe-none' : '' }}">
                    @include('ventas.contrato_venta.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if ($puedeActualizar)
                                @include('includes.boton-form-editar')
                            @endif
                            @if ($soloConsulta)
                                <button type="button" class="btn btn-outline-secondary" onclick="window.close();">
                                    Cerrar solapa
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultaconceptoventa')
@endsection
