@extends("theme.$theme.layout")
@section('titulo') Imputación contable de conceptos @endsection
@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/concepto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/imputacion_concepto/form.js') }}" type="text/javascript"></script>
@endsection
@section('contenido')
@php
    $modoConsulta = ! empty($modoConsulta);
    $soloConsulta = ! empty($soloConsulta);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $modoConsulta ? 'Consultar' : 'Editar' }} imputación #{{ $data->id }}</h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual')
                    @if (! $modoConsulta)
                        <a href="{{ route('consultar_imputacion_concepto_sueldos') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_imputacion_concepto_sueldos', array_filter(['id' => $data->id, 'origen' => $modoConsulta ? 'modal_consulta' : null, 'vista' => $modoConsulta ? 'consulta' : null])) }}"
                  method="POST"
                  class="form-horizontal form--label-right {{ $soloConsulta ? 'pe-none' : '' }}"
                  id="form-general"
                  autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('sueldos.imputacion_concepto.form', ['data' => $data])
                </div>
                <div class="card-footer">
                    @if ($modoConsulta)
                        @if (! $soloConsulta)
                            @include('includes.boton-form-editar')
                        @endif
                        <button type="button" class="btn btn-outline-secondary" onclick="window.close();">Cerrar solapa</button>
                    @else
                        @include('includes.boton-form-editar')
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaconcepto_sueldos')
@include('includes.contable.modalconsultacuentacontable')
@endsection
