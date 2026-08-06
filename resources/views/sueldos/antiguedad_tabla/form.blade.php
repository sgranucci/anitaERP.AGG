@php
    $tramosOld = old('tramos');
    if ($tramosOld === null && isset($data)) {
        $tramosOld = $data->tramos->map(function ($t) {
            return [
                'nro_linea' => $t->nro_linea,
                'anio' => $t->anio,
                'porcentaje' => $t->porcentaje,
                'cantidad' => $t->cantidad,
            ];
        })->values()->all();
    }
    if (! is_array($tramosOld) || count($tramosOld) === 0) {
        $tramosOld = [['nro_linea' => 1, 'anio' => '', 'porcentaje' => '', 'cantidad' => '']];
    }
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
            <input type="hidden" name="codigo" value="{{ $data->codigo }}"/>
        @else
            <input type="number" name="codigo" id="codigo" class="form-control" min="1" max="99" required
                   value="{{ old('codigo') }}"
                   placeholder="1–15 (Anita ANT)"/>
        @endif
        <small class="form-text text-muted">Usado en f&oacute;rmulas como <code>antiguedad_tabla(n)</code> / Anita <code>ANT(n)</code>.</small>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="80" required
               value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-3 col-form-label">Activo</label>
    <div class="col-lg-6">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}/>
            <label class="custom-control-label" for="activo">Tabla habilitada para liquidaci&oacute;n</label>
        </div>
    </div>
</div>

<hr class="my-3"/>
<h5 class="mb-2"><i class="fa fa-list"></i> Tramos por a&ntilde;os de antigüedad</h5>
<p class="text-muted small mb-3">
    Por cada a&ntilde;o cumplido se acumula el <strong>porcentaje</strong> (como Anita <code>ANT</code>).
    La <strong>cantidad</strong> se usa en conceptos con forma importe fijo.
</p>

<div class="card card-outline card-info mb-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-antiguedad-tramos">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:12%">N&deg;</th>
                        <th style="width:22%">A&ntilde;os</th>
                        <th style="width:28%">Porcentaje %</th>
                        <th style="width:28%">Cantidad ($)</th>
                        <th style="width:10%"></th>
                    </tr>
                </thead>
                <tbody id="tbody-antiguedad-tramos">
                    @foreach ($tramosOld as $idx => $t)
                        @include('sueldos.antiguedad_tabla.partials.fila_tramo', ['tramo' => $t, 'idx' => $idx])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('sueldos.antiguedad_tabla.partials.template_tramo')
<div class="mb-3 text-right">
    <button type="button" id="agrega_renglon_antiguedad_tramo" class="btn btn-outline-primary btn-sm">
        <i class="fa fa-plus"></i> Agregar tramo
    </button>
</div>
