@php
    $leyendasTexto = old('leyendas');
    if (! is_string($leyendasTexto)) {
        if (is_array($leyendasTexto)) {
            $leyendasTexto = implode("\n", $leyendasTexto);
        } elseif (isset($data)) {
            $leyendasTexto = $data->leyendas->pluck('leyenda')->implode("\n");
        } else {
            $leyendasTexto = '';
        }
    }
@endphp

<div class="card card-outline card-info mb-0">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-comment"></i> Leyendas del legajo</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Texto libre (Anita empley). Cada renglón se guarda como una línea (máx. 80 caracteres; si supera, se parte automáticamente).
            También se usan «A cargo de» y «Puesto jefe» en la solapa Laborales.
        </p>
        <textarea name="leyendas" id="leyendas" class="form-control" rows="14"
                  placeholder="Escribí las leyendas…">{{ $leyendasTexto }}</textarea>
    </div>
</div>
