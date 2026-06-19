{{--
    Asignación dual de empresas para reportes multiempresa (patrón ABM usuario).
    Variables: $empresa_query, $empresa_ids_seleccionados (array).
    Opcionales: $reporte_clave, $col_label, $col_body, $id_prefix,
                $mostrar_consolidar (bool), $consolidar_empresas (bool).
--}}
@php
    $empresasRaw = $empresa_query ?? [];
    $empresasTodas = collect($empresasRaw);
    if ($empresasTodas->isNotEmpty() && ! is_object($empresasTodas->first())) {
        $empresasTodas = collect($empresasRaw)->map(fn ($nombre, $id) => (object) [
            'id' => (int) $id,
            'nombre' => $nombre,
        ])->values();
    }

    $empresasAsignadasIds = collect(old('empresa_ids', $empresa_ids_seleccionados ?? []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();

    $empresaUnicaSistema = $empresasTodas->count() === 1;
    $empresaUnicaRegistro = $empresaUnicaSistema ? $empresasTodas->first() : null;

    if ($empresaUnicaSistema && $empresaUnicaRegistro && empty($empresasAsignadasIds)) {
        $empresasAsignadasIds = [(int) $empresaUnicaRegistro->id];
    }

    $empresasAsignadas = $empresasTodas->whereIn('id', $empresasAsignadasIds)->values();
    $empresasDisponibles = $empresasTodas->whereNotIn('id', $empresasAsignadasIds)->values();

    $colLabel = $col_label ?? 'col-lg-2';
    $colBody = $col_body ?? 'col-lg-10';
    $idPrefix = $id_prefix ?? 'reporte';
    $mostrarConsolidar = (bool) ($mostrar_consolidar ?? true);
    $consolidarActivo = (bool) ($consolidar_empresas ?? true);
    $listSize = (int) ($list_size ?? 5);
@endphp

<div class="form-group row reporte-empresas-dual-grupo">
    <label class="{{ $colLabel }} col-form-label requerido">Empresas</label>
    <div class="{{ $colBody }}">
        <div class="reporte-empresas-dual" id="{{ $idPrefix }}-empresas-dual"
            data-empresa-unica="{{ $empresaUnicaSistema ? '1' : '0' }}"
            data-id-prefix="{{ $idPrefix }}">
            @if ($empresaUnicaSistema && $empresaUnicaRegistro)
                <input type="hidden" name="empresa_ids[]" id="{{ $idPrefix }}_empresa_id" value="{{ $empresaUnicaRegistro->id }}">
                <input type="text" class="form-control form-control-sm" readonly value="{{ $empresaUnicaRegistro->nombre }}">
                <small class="form-text text-muted">Única empresa asignada al usuario.</small>
            @else
                <div class="reporte-dual-encabezados d-none d-md-flex mb-1 reporte-dual-listas-compact">
                    <div class="reporte-dual-col-lista text-muted small font-weight-bold">
                        <i class="fas fa-list mr-1"></i> Disponibles
                    </div>
                    <div class="reporte-dual-col-acciones"></div>
                    <div class="reporte-dual-col-lista text-muted small font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Seleccionadas
                    </div>
                    @if ($mostrarConsolidar)
                        <div class="reporte-dual-col-consolidar text-muted small font-weight-bold">
                            <i class="fas fa-layer-group mr-1"></i> Salida
                        </div>
                    @endif
                </div>

                <div class="reporte-dual-listas reporte-dual-listas-compact align-items-center">
                    <div class="reporte-dual-col-lista">
                        <select id="{{ $idPrefix }}_empresas_disponibles" class="form-control reporte-dual-list" multiple size="{{ $listSize }}">
                            @foreach ($empresasDisponibles as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="reporte-dual-col-acciones reporte-dual-acciones">
                        <div class="btn-group-vertical mx-auto">
                            <button type="button" class="btn btn-outline-primary btn-sm mb-1 btn-reporte-dual-asignar" data-grupo="empresa" title="Asignar empresas seleccionadas">
                                <i class="fas fa-angle-double-right"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-reporte-dual-quitar" data-grupo="empresa" title="Quitar empresas seleccionadas">
                                <i class="fas fa-angle-double-left"></i>
                            </button>
                        </div>
                    </div>
                    <div class="reporte-dual-col-lista" id="{{ $idPrefix }}_grupo-empresas-asignadas">
                        <select id="{{ $idPrefix }}_empresas_asignadas_list" class="form-control reporte-dual-list" multiple size="{{ $listSize }}">
                            @foreach ($empresasAsignadas as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                            @endforeach
                        </select>
                        <div id="{{ $idPrefix }}_empresas_asignadas_hidden" class="d-none" data-error-container="#{{ $idPrefix }}_grupo-empresas-asignadas">
                            @foreach ($empresasAsignadas as $emp)
                                <input type="hidden" name="empresa_ids[]" value="{{ $emp->id }}">
                            @endforeach
                        </div>
                        <input type="hidden" id="{{ $idPrefix }}_empresa_ids_validacion" name="empresa_ids_validacion" value="{{ count($empresasAsignadasIds) ? '1' : '' }}">
                    </div>
                    @if ($mostrarConsolidar)
                        <div class="reporte-dual-col-consolidar reporte-consolidar-empresas">
                            <input type="hidden" name="consolidar_empresas" id="{{ $idPrefix }}_consolidar_empresas_input" value="{{ $consolidarActivo ? '1' : '0' }}">
                            <button type="button"
                                class="btn btn-sm btn-block text-left {{ $consolidarActivo ? 'btn-success' : 'btn-outline-secondary' }} btn-toggle-consolidar-empresas"
                                id="{{ $idPrefix }}_btn_toggle_consolidar"
                                data-input="#{{ $idPrefix }}_consolidar_empresas_input"
                                title="Activo: un solo reporte con todas las empresas. Desactivado: un reporte por empresa.">
                                <i class="fa fa-check mr-1"></i>
                                Consolidar
                            </button>
                            <small class="text-muted d-block mt-1 reporte-consolidar-ayuda">
                                Off = por empresa
                            </small>
                        </div>
                    @endif
                </div>

                <small class="form-text text-muted mt-1 mb-0">
                    Lista izquierda
                    <i class="fas fa-angle-double-right"></i>
                    asignar; doble clic asigna o quita.
                </small>
            @endif
        </div>
    </div>
</div>
