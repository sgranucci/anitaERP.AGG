@extends("theme.$theme.layout")

@section('titulo')
    {{ $deposito->exists ? 'Editar depósito' : 'Nuevo depósito' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $deposito->exists ? 'Editar depósito' : 'Nuevo depósito' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_deposito', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
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
                    @php
                        $tipoSel = (int) old('tipo_deposito_id', $deposito->tipo_deposito_id);
                        $activo = (string) old('activo', ($deposito->activo ?? true) ? '1' : '0') === '1';
                    @endphp
                    <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                    <input type="hidden" name="empresa_id" value="{{ $sala->empresa_id }}">
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Sala</label>
                        <div class="col-lg-8">
                            <input type="text" class="form-control" readonly value="{{ $sala->codigo }} — {{ $sala->nombre }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Tipo</label>
                        <div class="col-lg-8">
                            @foreach ($tipos as $tipo)
                                <label class="kt-radio d-block mb-1 font-weight-normal">
                                    <input type="radio" name="tipo_deposito_id" value="{{ $tipo->id }}" @checked($tipoSel === (int) $tipo->id) required>
                                    {{ $tipo->etiqueta() }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
                        <div class="col-lg-6">
                            <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20" required value="{{ old('codigo', $deposito->codigo) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
                        <div class="col-lg-8">
                            <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required value="{{ old('nombre', $deposito->nombre) }}">
                            <small class="form-text text-muted">Bóveda, caja, mesa, pre-depósito o caja chica. El pre-depósito es el paquete que sale de bóveda para Caudales aunque siga físicamente adentro.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="orden" class="col-lg-4 control-label text-right pr-2">Orden</label>
                        <div class="col-lg-4">
                            <input type="number" name="orden" id="orden" class="form-control" min="0" max="9999" value="{{ old('orden', $deposito->orden ?? 0) }}">
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
@endsection
