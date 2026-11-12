@extends("theme.$theme.layout")

@section('titulo')
    {{ $subtipo->exists ? 'Editar subtipo de movimiento' : 'Nuevo subtipo de movimiento' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/boveda_tipo_consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/boveda_tipo_consulta.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $subtipo->exists ? 'Editar subtipo de movimiento' : 'Nuevo subtipo de movimiento' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_boveda_subtipo', array_filter([
                        'sala_id' => $sala->id,
                        'tipo_id' => $subtipo->tipo_id ?: null,
                    ])) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form class="form-horizontal form--label-right" id="form-general" method="POST" action="{{ $accion }}" autocomplete="off" data-enter-campos="1">
                @csrf
                @if ($metodo !== 'POST')
                    @method($metodo)
                @endif
                <div class="card-body">
                    @include('includes.form-error')
                    @include('includes.mensaje')
                    <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Sala</label>
                        <div class="col-lg-8">
                            <input type="text" class="form-control" readonly value="{{ $sala->codigo }} — {{ $sala->nombre }}">
                        </div>
                    </div>
                    @include('caja.tesoreria_crown.partials.campo_consulta_boveda_tipo', [
                        'etiqueta' => 'Tipo de movimiento',
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                        'editable' => ! $subtipo->exists,
                        'required' => true,
                        'tipoId' => old('tipo_id', $tipo->id ?? $subtipo->tipo_id ?? ''),
                        'tipoCodigo' => old('tipo_codigo', $tipo->codigo ?? ''),
                        'tipoNombre' => old('tipo_nombre', $tipo ? $tipo->etiqueta() : ''),
                    ])
                    @php
                        $activo = (string) old('activo', ($subtipo->activo ?? true) ? '1' : '0') === '1';
                        $mueveBilletaje = (string) old(
                            'mueve_billetaje',
                            ($subtipo->mueve_billetaje ?? true) ? '1' : '0'
                        ) === '1';
                    @endphp
                    <div class="form-group row">
                        <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
                        <div class="col-lg-6">
                            @if ($subtipo->exists)
                                <input type="text" id="codigo" class="form-control" value="{{ $subtipo->codigo }}" readonly>
                                <input type="hidden" name="codigo" value="{{ $subtipo->codigo }}">
                            @else
                                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="8" required value="{{ old('codigo') }}">
                            @endif
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
                        <div class="col-lg-8">
                            <input type="text" name="nombre" id="nombre" class="form-control" maxlength="60" required value="{{ old('nombre', $subtipo->nombre) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Mueve billetaje</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="mueve_billetaje" value="0">
                            <input type="checkbox" name="mueve_billetaje" value="1" @checked($mueveBilletaje)>
                            <small class="form-text text-muted">
                                Si está desmarcado (p. ej. propina Híbrida), el movimiento es solo informativo para RRHH y no altera el saldo de billetes en bóveda.
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Activo</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" value="1" @checked($activo)>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultabovedatipo')
@endsection
