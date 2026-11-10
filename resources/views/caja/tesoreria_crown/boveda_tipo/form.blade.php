@extends("theme.$theme.layout")

@section('titulo')
    {{ $tipo->exists ? 'Editar tipo de movimiento' : 'Nuevo tipo de movimiento' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $tipo->exists ? 'Editar tipo de movimiento' : 'Nuevo tipo de movimiento' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_boveda_tipo', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
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
                    @php
                        $activo = (string) old('activo', ($tipo->activo ?? true) ? '1' : '0') === '1';
                        $signo = (string) old('signo', $tipo->signo ?? '0');
                        $operacion = (string) old('operacion', $tipo->operacion ?? '0');
                    @endphp
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Sala</label>
                        <div class="col-lg-8">
                            <input type="text" class="form-control" readonly value="{{ $sala->codigo }} — {{ $sala->nombre }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
                        <div class="col-lg-4">
                            @if ($tipo->exists)
                                <input type="text" id="codigo" class="form-control" value="{{ $tipo->codigo }}" readonly>
                                <input type="hidden" name="codigo" value="{{ $tipo->codigo }}">
                            @else
                                <input type="number" name="codigo" id="codigo" class="form-control" min="1" max="9999" step="1" inputmode="numeric" required value="{{ old('codigo') }}">
                            @endif
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="abreviatura" class="col-lg-4 control-label text-right pr-2 requerido">Abreviatura</label>
                        <div class="col-lg-4">
                            <input type="text" name="abreviatura" id="abreviatura" class="form-control" maxlength="4" required value="{{ old('abreviatura', $tipo->abreviatura) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
                        <div class="col-lg-8">
                            <input type="text" name="nombre" id="nombre" class="form-control" maxlength="40" required value="{{ old('nombre', $tipo->nombre) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="signo" class="col-lg-4 control-label text-right pr-2 requerido">Efecto en saldo</label>
                        <div class="col-lg-6">
                            <select name="signo" id="signo" class="form-control" required>
                                <option value="0" @selected($signo === '0')>Entra</option>
                                <option value="1" @selected($signo === '1')>Sale</option>
                                <option value="2" @selected($signo === '2')>No mueve saldo</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="operacion" class="col-lg-4 control-label text-right pr-2 requerido">Operación</label>
                        <div class="col-lg-6">
                            <select name="operacion" id="operacion" class="form-control" required>
                                @foreach ($operaciones as $op)
                                    <option value="{{ $op['codigo'] }}" @selected($operacion === (string) $op['codigo'])>
                                        {{ $op['codigo'] }} — {{ $op['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
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
