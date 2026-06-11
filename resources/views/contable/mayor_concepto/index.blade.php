@extends("theme.$theme.layout")
@section('titulo')
    Mayor por concepto
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos contables por concepto</h3>
            </div>
            <form action="{{ route('generar_mayor_concepto') }}" method="POST" id="form-mayor-concepto">
                @csrf
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => old('empresa_id'),
                    ])

                    <div class="form-group row">
                        <label class="col-lg-3 control-label requerido">Período</label>
                        <div class="col-lg-9">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_mes" value="mes"
                                    {{ old('modo_periodo', 'mes') === 'mes' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_mes">Mes completo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_rango" value="rango"
                                    {{ old('modo_periodo') === 'rango' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_rango">Rango de fechas</label>
                            </div>
                        </div>
                    </div>

                    <div id="panel-mes" class="form-group row">
                        <label class="col-lg-3 control-label requerido">Mes / Año</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-4">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" {{ (int) old('mes', $mes_actual) === $m ? 'selected' : '' }}>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ old('anio', $anio_actual) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-rango" class="form-group row" style="display:none;">
                        <label class="col-lg-3 control-label requerido">Desde / Hasta</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-4">
                                    <input type="date" name="fecha_desde" class="form-control" value="{{ old('fecha_desde') }}">
                                </div>
                                <div class="col-md-4">
                                    <input type="date" name="fecha_hasta" class="form-control" value="{{ old('fecha_hasta') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="moneda_id" class="col-lg-3 control-label requerido">Expresar en</label>
                        <div class="col-lg-5">
                            <select name="moneda_id" id="moneda_id" class="form-control" required>
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" {{ (int) old('moneda_id', 1) === (int) $mon->id ? 'selected' : '' }}>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Los importes se convierten según la cotización de cada movimiento (o cotización diaria si falta).
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Filtro moneda</label>
                        <div class="col-lg-9">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    {{ old('solo_moneda_origen') ? 'checked' : '' }}>
                                <label class="form-check-label" for="solo_moneda_origen">
                                    Solo movimientos en moneda origen (equivalente Anita «Origen»)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-generar">
                        <i class="fa fa-play"></i> Generar reporte
                    </button>
                    <a href="{{ route('asiento') }}" class="btn btn-default">Volver</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    function togglePeriodo() {
        var mes = document.getElementById('modo_mes').checked;
        document.getElementById('panel-mes').style.display = mes ? '' : 'none';
        document.getElementById('panel-rango').style.display = mes ? 'none' : '';
    }
    document.querySelectorAll('input[name="modo_periodo"]').forEach(function (el) {
        el.addEventListener('change', togglePeriodo);
    });
    togglePeriodo();

    document.getElementById('form-mayor-concepto').addEventListener('submit', function () {
        var btn = document.getElementById('btn-generar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando… (puede tardar varios minutos)';
    });
})();
</script>
@endsection
