@extends("theme.$theme.layout")
@section('titulo')
    Plan de cuentas
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('cuentacontable', $filtrosQuery ?? []);
    $esConsulta = ! empty($soloConsulta);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $esConsulta ? 'Consultar' : 'Editar' }} cuenta contable</h3>
                <div class="card-tools">
                    @if (! $esConsulta)
                        <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al plan
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_cuentacontable', ['id' => $data->id] + ($filtrosQuery ?? [])) }}"
                  id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off"
                  @if($esConsulta) onsubmit="return false;" @endif>
                @csrf @method('put')
                @if ($esConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body @if($esConsulta) pe-none @endif" @if($esConsulta) style="opacity:.92" @endif>
                    @include('contable.cuentacontable.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if (! $esConsulta)
                                @include('includes.boton-form-editar')
                            @else
                                <button type="button" class="btn btn-secondary" onclick="window.close()">Cerrar</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
