@extends("theme.$theme.layout")
@section('titulo')
    Cheques
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
@if (($data->origen ?? '') === 'R')
<script src="{{ asset('assets/pages/scripts/caja/banco/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/banco/consulta.js')) ?: time() }}" type="text/javascript"></script>
@endif
@include('includes.contable.asiento_montos_formato_js')
<script src="{{asset("assets/pages/scripts/contable/asiento/asiento_externo.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/asiento_externo.js')) ?: time() }}" type="text/javascript"></script>
<script>
$(function () {
    $('#botonform1').on('click', function (e) {
        e.preventDefault();
        $('.cheque-datos-principales').show();
        $('.formasientoexterno').hide();
        $('#botonform1').addClass('active');
        $('#botonform5').removeClass('active');
    });
    $('#botonform5').on('click', function (e) {
        e.preventDefault();
        $('.cheque-datos-principales').hide();
        $('.formasientoexterno').show();
        $('#botonform5').addClass('active');
        $('#botonform1').removeClass('active');
    });
});
</script>
@endsection

@section('contenido')
@php
    $soloConsulta = ! empty($soloConsulta);
    $puedeActualizarCheque = ! empty($puedeActualizarCheque);
    $soloLectura = $soloConsulta && ! $puedeActualizarCheque;
    $esTercero = ($data->origen ?? '') === 'R';
    $chequeRechazado = \App\Support\Caja\ChequeAvisoRechazoSupport::estaRechazado($data);
    $retornoImpresionNd = \App\Support\Ventas\ComprobanteImpresionSesionUrlSupport::retornoRequestActual();
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
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($soloLectura && $esTercero)
                        Consultar cheque de terceros
                    @elseif ($soloLectura)
                        Consultar cheque
                    @elseif ($esTercero)
                        Editar cheque de terceros
                    @else
                        Editar cheque
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($chequeRechazado)
                        <a href="{{ route('aviso_rechazo_cheque', ['id' => $data->id]) }}"
                           class="btn btn-outline-danger btn-sm"
                           target="_blank" rel="noopener">
                            <i class="fa fa-file-text-o"></i> Aviso de rechazo
                        </a>
                    @endif
                    @if (! empty($data->venta_nd_id))
                        <a href="{{ route('lista_una_factura_pdf', array_filter(['id' => $data->venta_nd_id, 'retorno' => $retornoImpresionNd])) }}"
                           class="btn btn-outline-light btn-sm"
                           target="_blank" rel="noopener">
                            <i class="fa fa-print"></i> Nota de d&eacute;bito
                        </a>
                    @endif
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
                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas px-3 pt-2">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" id="botonform1" role="tab">
                                <i class="fa fa-user"></i> Datos principales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="botonform5" role="tab">
                                <i class="fa fa-copy"></i> Asiento contable
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body cheque-datos-principales{{ $soloLectura ? ' pe-none' : '' }}" style="{{ $soloLectura ? 'opacity:.92' : '' }}">
                    @include('caja.cheque.form')
                </div>
                @include('includes.contable.formasientoexterno')
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            @if (! $soloLectura)
                                @include('includes.boton-form-editar')
                            @endif
                            @if ($soloConsulta)
                                <button type="button" class="btn btn-secondary" onclick="window.close()">Cerrar solapa</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@if (($data->origen ?? '') === 'R')
    @include('includes.caja.modalconsultabanco')
@endif
@endsection
