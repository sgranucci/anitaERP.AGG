@php $f = $filtros ?? []; @endphp
<div class="card-body bg-light border-bottom py-2">
    <div class="form-row align-items-end">
        @include('includes.listado.filtro_empresa_asignada', ['f' => $f])
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="fecha_desde">Desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                   value="{{ $f['fecha_desde'] ?? '' }}" required>
        </div>
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="fecha_hasta">Hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                   value="{{ $f['fecha_hasta'] ?? '' }}" required>
        </div>
        <div class="form-group col-md-3 col-sm-6 mb-2">
            <label class="small mb-1" for="ubicacion_ids">Salas / ubicaciones</label>
            <select name="ubicacion_ids[]" id="ubicacion_ids" class="form-control form-control-sm" multiple size="3"
                    title="Sin selección = todas las salas">
                @foreach ($ubicacion_query ?? [] as $ub)
                    <option value="{{ $ub->id }}"
                        @selected(in_array((int) $ub->id, $f['ubicacion_ids'] ?? [], true))>
                        {{ $ub->nombre }}
                    </option>
                @endforeach
            </select>
            <span class="small text-muted">Ctrl+clic para varias. Vacío = todas.</span>
        </div>
        <div class="form-group col-md-auto mb-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa fa-search"></i> Aplicar filtros
            </button>
        </div>
    </div>
</div>
