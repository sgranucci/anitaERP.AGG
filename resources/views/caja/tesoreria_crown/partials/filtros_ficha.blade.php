@php
    $f = $filtros ?? [];
    $canje = (string) ($f['canje'] ?? '');
@endphp
<div class="collapse border-bottom" id="panel-filtros-tesoreria-crown-ficha" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2">
        <div class="form-row align-items-end">
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Texto</label>
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $f['valor'] ?? '' }}" autocomplete="off">
            </div>
            <div class="form-group col-md-8 mb-2">
                <label class="small mb-1 d-block">Canje</label>
                <label class="mr-3 mb-0">
                    <input type="radio" name="filtro_canje" value="" @checked($canje === '')> Todas
                </label>
                <label class="mr-3 mb-0">
                    <input type="radio" name="filtro_canje" value="1" @checked($canje === '1')> Solo canje
                </label>
                <label class="mr-3 mb-0">
                    <input type="radio" name="filtro_canje" value="0" @checked($canje === '0')> Sin canje
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn-sm btn-info" data-aplicar-filtros-panel>Aplicar filtros</button>
    </div>
</div>
