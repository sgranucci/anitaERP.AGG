@extends("theme.$theme.layout")
@section('titulo')
    Tipos de Transacciones de Ventas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset('assets/pages/scripts/ventas/tipotransaccion/form.js')}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $soloConsulta = (bool) ($solo_consulta ?? (request('origen') === 'modal_consulta'));
    $puedeActualizar = (bool) ($puede_actualizar ?? can('actualizar-tipos-transacciones', false));
    $formReadonly = $soloConsulta && ! $puedeActualizar;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($soloConsulta)
                        Consultar Tipo de transacci&oacute;n
                    @else
                        Editar Tipo de transacci&oacute;n
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($soloConsulta)
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close();">
                            <i class="fa fa-times"></i> Cerrar solapa
                        </button>
                    @else
                        <a href="{{route('tipotransaccion')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_tipotransaccion', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right{{ $formReadonly ? ' pe-none' : '' }}" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
                    @include('ventas.tipotransaccion.form')
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
