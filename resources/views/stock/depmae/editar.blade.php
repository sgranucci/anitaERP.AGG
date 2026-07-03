@extends("theme.$theme.layout")
@section('titulo')
    Dep&oacute;sitos
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/stock/depmae/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if (! empty($soloConsulta) && empty($puedeActualizarDeposito))
                        Consultar
                    @else
                        Editar
                    @endif
                    Dep&oacute;sito
                </h3>
                <div class="card-tools">
                    @if (empty($ocultarVolver))
                    <a href="{{route('depmae')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_depmae', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if(!empty($soloConsulta) && empty($puedeActualizarDeposito)) onsubmit="return false;" @endif>
                @csrf @method("put")
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body @if(!empty($soloConsulta) && empty($puedeActualizarDeposito)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeActualizarDeposito)) style="opacity:.92" @endif>
                    @include('stock.depmae.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6 text-center">
                            @if (empty($soloConsulta))
                                @include('includes.boton-form-editar')
                            @else
                                @if (! empty($puedeActualizarDeposito))
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-secondary @if(!empty($puedeActualizarDeposito)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
