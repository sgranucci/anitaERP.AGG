@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/formula_debugger.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/formula_debugger.js')) ?: time() }}"></script>
<script src="{{asset("assets/pages/scripts/sueldos/concepto/elegibilidad.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/concepto/formula_debugger.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/concepto/formula_debugger.js')) ?: time() }}"></script>
<script src="{{asset('assets/pages/scripts/sueldos/concepto/lsd.js')}}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/concepto/lsd.js')) ?: time() }}"></script>
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
                <h3 class="card-title">Editar concepto #{{ $data->codigo }}</h3>
                <div class="card-tools">
                    @if (! $modoConsulta)
                        <a href="{{route('consultar_concepto_sueldos')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_concepto_sueldos', array_filter(['id' => $data->id, 'origen' => $modoConsulta ? 'modal_consulta' : null, 'vista' => $modoConsulta ? 'consulta' : null]))}}"
                  id="form-general"
                  class="form-horizontal form--label-right {{ $soloConsulta ? 'pe-none' : '' }}"
                  method="POST"
                  autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
                    @include('sueldos.concepto.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if ($modoConsulta)
                                @if (! $soloConsulta)
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-outline-secondary" onclick="window.close();">
                                    Cerrar solapa
                                </button>
                            @else
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Fuera del form principal (sin anidar) --}}
        @include('sueldos.concepto.partials.formula_debugger', [
            'concepto' => $data,
            'empresas' => $empresas ?? [],
            'tiposLiquidacion' => $tiposLiquidacion ?? [],
        ])

        <div id="host-elegibilidad-concepto" class="mt-3"
             data-url="{{ route('elegibilidad_concepto_sueldos', ['concepto' => $data->id]) }}">
            <div class="text-muted small py-2"><i class="fa fa-spinner fa-spin"></i> Cargando reglas…</div>
        </div>
    </div>
</div>
@endsection
