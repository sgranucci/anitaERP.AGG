@extends("theme.$theme.layout")

@section('titulo')
    {{ $tipo->exists ? 'Editar tipo de valor' : 'Nuevo tipo de valor' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">{{ $tipo->exists ? 'Editar tipo de valor' : 'Nuevo tipo de valor' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_tipo_valor', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
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
                        $referencia = (string) old('es_moneda_referencia', $tipo->es_moneda_referencia ? '1' : '0') === '1';
                        $controla = (string) old('controla_stock', $tipo->controla_stock ? '1' : '0') === '1';
                        $fija = (string) old('cotizacion_fija', $tipo->cotizacion_fija ? '1' : '0') === '1';
                        $activo = (string) old('activo', ($tipo->activo ?? true) ? '1' : '0') === '1';
                    @endphp
                    <div class="form-group row">
                        <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
                        <div class="col-lg-6">
                            @if ($tipo->exists)
                                <input type="text" id="codigo" class="form-control" value="{{ $tipo->codigo }}" readonly>
                                <input type="hidden" name="codigo" value="{{ $tipo->codigo }}">
                            @else
                                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="10" required value="{{ old('codigo') }}">
                            @endif
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
                        <div class="col-lg-8">
                            <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80" required value="{{ old('nombre', $tipo->nombre) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Controla stock</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="controla_stock" value="0">
                            <input type="checkbox" name="controla_stock" value="1" @checked($controla)>
                            <small class="form-text text-muted">Las fichas de color no se controlan. Las de valor y las monedas sí.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Moneda de referencia</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="es_moneda_referencia" value="0">
                            <input type="checkbox" name="es_moneda_referencia" value="1" @checked($referencia)>
                            <small class="form-text text-muted">Una sola. Los informes en pesos usan este tipo. Hoy es ARS.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Cotización fija</label>
                        <div class="col-lg-8">
                            <input type="hidden" name="cotizacion_fija" value="0">
                            <input type="checkbox" name="cotizacion_fija" value="1" @checked($fija)>
                            <small class="form-text text-muted">Queda en 1 en todas las jornadas. Fichas y pesos. Dólares y euros no.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="orden" class="col-lg-4 control-label text-right pr-2">Orden</label>
                        <div class="col-lg-4">
                            <input type="number" name="orden" id="orden" class="form-control" min="0" max="9999" value="{{ old('orden', $tipo->orden ?? 0) }}">
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
