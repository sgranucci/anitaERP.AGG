@php $f = $filtros ?? []; @endphp
<div class="collapse border-bottom" id="panel-filtros-mv-rendicion" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="filtro_empresa_panel">Empresa</label>
                @include('includes.form-empresa-asignada-control', [
                    'empresa_query' => $empresa_query,
                    'empresa_id' => $f['empresa_id'] ?? '',
                    'id' => 'filtro_empresa_panel',
                    'name' => 'empresa_id',
                    'opcion_vacia' => 'Todas',
                ])
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="maquinavending_id">M&aacute;quina</label>
                <select name="maquinavending_id" id="maquinavending_id" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach ($maquinas_query ?? [] as $maq)
                        <option value="{{ $maq->id }}" @selected((int)($f['maquinavending_id'] ?? 0) === (int)$maq->id)>
                            {{ trim(($maq->puntoventa->codigo ?? '').' — '.$maq->nombre, ' —') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="numero_cierre">N&ordm; cierre (empresa)</label>
                <input type="number" min="1" name="numero_cierre" id="numero_cierre" class="form-control form-control-sm" value="{{ $f['numero_cierre'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="fecha_desde">Desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="fecha_hasta">Hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <div class="custom-control custom-checkbox mt-4">
                    <input type="checkbox" class="custom-control-input" id="pendiente_caja" name="pendiente_caja" value="1"
                           @checked(($f['pendiente_caja'] ?? '') === '1')>
                    <label class="custom-control-label" for="pendiente_caja">Solo pendientes de caja</label>
                </div>
            </div>
            <div class="form-group col-md-2 mb-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Aplicar filtros</button>
            </div>
        </div>
    </div>
</div>
