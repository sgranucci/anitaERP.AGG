@extends("theme.$theme.layout")
@section('titulo')
    Salidas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $soloConsulta = (bool) ($soloConsulta ?? (request('origen') === 'modal_consulta' || request('vista') === 'consulta'));
    $puedeActualizar = (bool) ($puedeActualizar ?? can('actualizar-salidas', false));
    $formReadonly = $soloConsulta && ! $puedeActualizar;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">{{ $soloConsulta ? 'Consultar salida' : 'Editar Salida' }}</h3>
                <div class="card-tools">
                    @if ($soloConsulta)
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close();">
                            <i class="fa fa-times"></i> Cerrar solapa
                        </button>
                    @else
                        <a href="{{route('salida')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_salida', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                @if ($soloConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body{{ $formReadonly ? ' pe-none' : '' }}">
                    @include('configuracion.salida.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if ($soloConsulta)
                                <button type="button" class="btn btn-outline-secondary" onclick="window.close();">
                                    <i class="fa fa-times"></i> Cerrar solapa
                                </button>
                                @if ($puedeActualizar)
                                    @include('includes.boton-form-editar')
                                @endif
                            @else
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
