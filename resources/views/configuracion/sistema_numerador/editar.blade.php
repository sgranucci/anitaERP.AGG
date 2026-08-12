@extends("theme.$theme.layout")
@section('titulo')
    Numeradores del sistema
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/editar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('sistema_numerador', $filtrosQuery ?? []);
    $retornoQuery = $filtrosQuery ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar numerador</h3>
                <div class="card-tools">
                    @if (can('sincronizar-sistema-numerador', false) && $data->anita_clave)
                        <form action="{{ route('sincronizar_sistema_numerador', ['id' => $data->id] + $retornoQuery) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Lee num_ult_numero de Anita y actualiza este registro">
                                <i class="fa fa-sync"></i> Sync Anita
                            </button>
                        </form>
                    @endif
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_sistema_numerador', ['id' => $data->id] + $retornoQuery) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    @include('configuracion.sistema_numerador.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
