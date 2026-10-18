{{--
    Asignación de salas (solo Crown). Misma UX dual que empresas.
    Variables: $sala_query (id=>nombre o colección); opcional $data.
--}}
@php
    use App\Support\Configuracion\EntornoEmpresaSupport;
@endphp
@if (EntornoEmpresaSupport::esCrown())
@php
    $salasRaw = $sala_query ?? [];
    $salasTodas = collect($salasRaw);
    if ($salasTodas->isNotEmpty() && ! is_object($salasTodas->first())) {
        $salasTodas = collect($salasRaw)->map(fn ($nombre, $id) => (object) [
            'id' => (int) $id,
            'nombre' => $nombre,
        ])->values();
    }

    $salasAsignadasIds = collect(old('sala_ids', isset($data) ? $data->usuario_salas->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();

    $salasAsignadas = $salasTodas->whereIn('id', $salasAsignadasIds)->values();
    $salasDisponibles = $salasTodas->whereNotIn('id', $salasAsignadasIds)->values();
@endphp

<div class="card card-outline card-info mb-3" id="usuario-asignacion-salas-crown">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="fas fa-dice mr-1"></i> Salas asignadas (Crown)</strong>
        <small class="text-muted d-block">Vacío = acceso a todas las salas. Con salas = filtra mesas, mozos, POS y descuentos.</small>
    </div>
    <div class="card-body">
        <div class="usuario-dual-grupo mb-0" data-grupo-sala="1">
            <div class="row usuario-dual-etiquetas">
                <div class="col-md-5">
                    <label for="salas_disponibles" class="usuario-dual-etiqueta mb-1">Salas disponibles</label>
                </div>
                <div class="col-md-2 d-none d-md-block"></div>
                <div class="col-md-5">
                    <label for="salas_asignadas_list" class="usuario-dual-etiqueta mb-1">Salas del usuario</label>
                </div>
            </div>
            <div class="row align-items-center">
                <div class="col-md-5">
                    <select id="salas_disponibles" class="form-control usuario-dual-list" multiple size="6">
                        @foreach ($salasDisponibles as $sala)
                            <option value="{{ $sala->id }}">{{ $sala->nombre }}{{ !empty($sala->codigo) ? ' ('.$sala->codigo.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 text-center my-2">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-dual-asignar-sala mb-1" title="Asignar">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                    <br>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-dual-quitar-sala" title="Quitar">
                        <i class="fa fa-chevron-left"></i>
                    </button>
                </div>
                <div class="col-md-5">
                    <select id="salas_asignadas_list" class="form-control usuario-dual-list" multiple size="6">
                        @foreach ($salasAsignadas as $sala)
                            <option value="{{ $sala->id }}">{{ $sala->nombre }}{{ !empty($sala->codigo) ? ' ('.$sala->codigo.')' : '' }}</option>
                        @endforeach
                    </select>
                    <div id="salas_asignadas_hidden">
                        @foreach ($salasAsignadasIds as $sid)
                            <input type="hidden" name="sala_ids[]" value="{{ $sid }}">
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
