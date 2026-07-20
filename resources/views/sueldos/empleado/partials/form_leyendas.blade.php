@php
    $leyendas = old('leyendas');
    if (! is_array($leyendas)) {
        $leyendas = isset($data) ? $data->leyendas->pluck('leyenda')->all() : [''];
    }
    if ($leyendas === []) {
        $leyendas = [''];
    }
@endphp
<p class="text-muted small">Leyendas del legajo (Anita empley). También se usan «A cargo de» y «Puesto jefe» en la solapa laborales.</p>
<div id="leyendas-empleado">
    @foreach ($leyendas as $i => $texto)
        <div class="form-group row leyenda-fila">
            <label class="col-lg-2 control-label">Línea {{ $i + 1 }}</label>
            <div class="col-lg-8">
                <input type="text" name="leyendas[]" class="form-control" maxlength="80" value="{{ $texto }}">
            </div>
            <div class="col-lg-2">
                <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-leyenda" title="Quitar">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    @endforeach
</div>
<button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-leyenda">
    <i class="fa fa-plus"></i> Agregar línea
</button>
