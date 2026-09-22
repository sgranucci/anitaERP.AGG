@extends("theme.$theme.layout")
@section('titulo')
    Cheques
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
@include('includes.contable.asiento_montos_formato_js')
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/asiento_externo.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $soloConsulta = ! empty($soloConsulta);
    $puedeActualizarCheque = ! empty($puedeActualizarCheque);
    $soloLectura = $soloConsulta && ! $puedeActualizarCheque;
    $paramsActualizar = ['id' => $data->id];
    if ($soloConsulta) {
        $paramsActualizar['origen'] = 'modal_consulta';
        $paramsActualizar['vista'] = 'consulta';
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($soloLectura)
                        Consultar cheque
                    @else
                        Editar Cheques
                    @endif
                </h3>
                <div class="card-tools">
                    @if (empty($ocultarVolver))
                    <a href="{{route('cheque')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_cheque', $paramsActualizar) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if($soloLectura) onsubmit="return false;" @endif>
                @csrf @method("put")
                <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{$data->caja_id ?? ''}}" >
                @if ($soloConsulta)
                    {{-- vista=consulta activa PreservarModoConsulta; no enviar origen=modal_consulta (pisa cheque.origen E/R) --}}
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <input type="hidden" class="origen" id="origen" name="origen" value="{{ old('origen', $data->origen ?? '') }}">
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Asiento Contable
                    </button>
                </div>
                <div class="card-body @if($soloLectura) pe-none @endif" @if($soloLectura) style="opacity:.92" @endif>
                    @include('caja.cheque.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if (! $soloLectura)
                                @include('includes.boton-form-editar')
                            @endif
                            @if ($soloConsulta)
                                <button type="button" class="btn btn-secondary" onclick="window.close()">Cerrar solapa</button>
                            @endif
                            @include('includes.contable.formasientoexterno')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
