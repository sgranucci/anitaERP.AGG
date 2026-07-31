@extends("theme.$theme.layout")
@section('titulo')
    Cuentas de Caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('cuentacaja', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ ! empty($soloConsulta) ? 'Consultar' : 'Editar' }} Cuenta de Caja</h3>
                <div class="card-tools">
                    @if (empty($soloConsulta))
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_cuentacaja', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off" @if(!empty($soloConsulta)) onsubmit="return false;" @endif>
                @csrf @method("put")
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div class="card-body @if(!empty($soloConsulta)) pe-none @endif" @if(!empty($soloConsulta)) style="opacity:.92" @endif>
                    @include('caja.cuentacaja.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @if (empty($soloConsulta))
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
@include('includes.contable.modalconsultacuentacontable')
@endsection
