{{--
    Asignación de empresas por tilde para reportes multiempresa.
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
    } else {
        $empresasTodas = $empresasTodas->values();
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

    $colLabel = $col_label ?? 'col-lg-2';
    $colBody = $col_body ?? 'col-lg-10';
    $idPrefix = $id_prefix ?? 'reporte';
    $mostrarConsolidar = (bool) ($mostrar_consolidar ?? true);
    $consolidarActivo = (bool) ($consolidar_empresas ?? true);
    $cantidadSeleccionadas = count($empresasAsignadasIds);
    $cantidadTotal = $empresasTodas->count();
@endphp

<div class="form-group row reporte-empresas-checkboxes-grupo">
    <label class="{{ $colLabel }} col-form-label requerido">Empresas</label>
    <div class="{{ $colBody }}">
        <div class="reporte-empresas-checkboxes" id="{{ $idPrefix }}-empresas-checkboxes"
            data-empresa-unica="{{ $empresaUnicaSistema ? '1' : '0' }}"
            data-id-prefix="{{ $idPrefix }}">
            @if ($empresaUnicaSistema && $empresaUnicaRegistro)
                <input type="hidden" name="empresa_ids[]" id="{{ $idPrefix }}_empresa_id" value="{{ $empresaUnicaRegistro->id }}">
                <input type="text" class="form-control form-control-sm" readonly value="{{ $empresaUnicaRegistro->nombre }}">
                <small class="form-text text-muted">Única empresa asignada al usuario.</small>
            @else
                <div class="reporte-empresas-checkboxes-panel">
                    <div class="reporte-empresas-checkboxes-toolbar">
                        <div class="reporte-empresas-checkboxes-acciones btn-group btn-group-sm" role="group" aria-label="Selección de empresas">
                            <button type="button" class="btn btn-outline-primary btn-reporte-empresas-todas" title="Marcar todas las empresas">
                                <i class="fas fa-check-double mr-1"></i> Todas
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-reporte-empresas-ninguna" title="Desmarcar todas">
                                <i class="fas fa-times mr-1"></i> Ninguna
                            </button>
                        </div>
                        <div class="reporte-empresas-checkboxes-toolbar-meta">
                            <span class="reporte-empresas-checkboxes-contador badge badge-light border" id="{{ $idPrefix }}_empresas_contador" aria-live="polite">
                                <span class="reporte-empresas-checkboxes-contador-n">{{ $cantidadSeleccionadas }}</span>
                                / {{ $cantidadTotal }}
                            </span>
                            <small class="text-muted reporte-empresas-checkboxes-ayuda" title="Marque las empresas autorizadas que desea incluir en el reporte.">
                                Tilde las autorizadas
                            </small>
                        </div>
                    </div>

                    <div class="reporte-empresas-checkboxes-cuerpo">
                        <div id="{{ $idPrefix }}_empresas_asignadas_hidden"
                            class="reporte-empresas-checkboxes-lista"
                            data-error-container="#{{ $idPrefix }}_grupo-empresas-asignadas"
                            role="group"
                            aria-label="Empresas autorizadas">
                            @foreach ($empresasTodas as $emp)
                                @php
                                    $empId = (int) $emp->id;
                                    $checked = in_array($empId, $empresasAsignadasIds, true);
                                    $inputId = $idPrefix . '_empresa_chk_' . $empId;
                                @endphp
                                <label class="reporte-empresa-check-item{{ $checked ? ' is-checked' : '' }}" for="{{ $inputId }}">
                                    <input type="checkbox"
                                        class="reporte-empresa-check-input"
                                        id="{{ $inputId }}"
                                        name="empresa_ids[]"
                                        value="{{ $empId }}"
                                        {{ $checked ? 'checked' : '' }}>
                                    <span class="reporte-empresa-check-box" aria-hidden="true">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <span class="reporte-empresa-check-label">{{ $emp->nombre }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @if ($mostrarConsolidar)
                        <div class="reporte-empresas-checkboxes-consolidar reporte-consolidar-empresas">
                            <input type="hidden" name="consolidar_empresas" id="{{ $idPrefix }}_consolidar_empresas_input" value="{{ $consolidarActivo ? '1' : '0' }}">
                            <button type="button"
                                class="btn btn-sm {{ $consolidarActivo ? 'btn-success' : 'btn-outline-secondary' }} btn-toggle-consolidar-empresas"
                                id="{{ $idPrefix }}_btn_toggle_consolidar"
                                data-input="#{{ $idPrefix }}_consolidar_empresas_input"
                                title="Activo: un solo reporte con todas las empresas. Desactivado: un reporte por empresa.">
                                <i class="fa fa-check mr-1"></i>
                                Consolidar
                            </button>
                            <small class="text-muted reporte-consolidar-ayuda">
                                On = empresas juntas · Off = por empresa
                            </small>
                        </div>
                    @endif

                    <div id="{{ $idPrefix }}_grupo-empresas-asignadas" class="d-none" aria-hidden="true"></div>
                    <input type="hidden" id="{{ $idPrefix }}_empresa_ids_validacion" name="empresa_ids_validacion" value="{{ $cantidadSeleccionadas ? '1' : '' }}">
                </div>
            @endif
        </div>
    </div>
</div>
