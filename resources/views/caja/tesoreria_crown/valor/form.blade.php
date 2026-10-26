@extends("theme.$theme.layout")

@section('titulo')
    {{ $valor->exists ? 'Editar valor' : 'Nuevo valor' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $valor->exists ? 'Editar valor' : 'Nuevo valor' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_valor', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form class="form-horizontal form--label-right" id="form-general" method="POST" action="{{ $accion }}" autocomplete="off">
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
                    @php
                        $tipoSel = (int) old('tipo_valor_id', $valor->tipo_valor_id);
                        $activo = (string) old('activo', $valor->activo ? '1' : '0') === '1';
                    @endphp
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Tipo</label>
                        <div class="col-lg-8">
                            @forelse ($tipos as $tipo)
                                <label class="kt-radio d-block mb-1 font-weight-normal">
                                    <input type="radio" name="tipo_valor_id" value="{{ $tipo->id }}" @checked($tipoSel === (int) $tipo->id) required>
                                    {{ $tipo->codigo }} — {{ $tipo->nombre }}
                                    @unless ($tipo->controla_stock)
                                        <span class="text-muted">sin control de stock</span>
                                    @endunless
                                </label>
                            @empty
                                <p class="text-muted mb-0">No hay tipos de valor activos.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="serie" class="col-lg-4 control-label text-right pr-2 requerido">Serie</label>
                        <div class="col-lg-8">
                            <input type="text" name="serie" id="serie" class="form-control" maxlength="40" required value="{{ old('serie', $valor->serie) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="descripcion" class="col-lg-4 control-label text-right pr-2 requerido">Descripción</label>
                        <div class="col-lg-8">
                            <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="120" required value="{{ old('descripcion', $valor->descripcion) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="denominacion" class="col-lg-4 control-label text-right pr-2 requerido">Denominación</label>
                        <div class="col-lg-8">
                            <input type="number" name="denominacion" id="denominacion" class="form-control text-right" min="0" step="0.01" required value="{{ old('denominacion', $valor->denominacion) }}">
                            <small class="form-text text-muted">Por ejemplo 1000000 para la ficha de un millón, o 100 para el dólar de cien.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="orden" class="col-lg-4 control-label text-right pr-2">Orden</label>
                        <div class="col-lg-4">
                            <input type="number" name="orden" id="orden" class="form-control" min="0" max="9999" value="{{ old('orden', $valor->orden ?? 0) }}">
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
