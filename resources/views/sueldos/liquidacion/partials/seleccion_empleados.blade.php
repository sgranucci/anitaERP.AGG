@php
    $filtrosLiq = is_array(old('filtros_json'))
        ? old('filtros_json')
        : (is_array($d->filtros_json ?? null) ? $d->filtros_json : []);
    $idsSel = old('empleado_ids');
    if (! is_array($idsSel)) {
        $idsSel = array_map('intval', $filtrosLiq['empleado_ids'] ?? []);
    }
    $idsSel = array_values(array_filter(array_map('intval', $idsSel)));
    $modoSel = old(
        'empleados_modo',
        array_key_exists('empleado_ids', $filtrosLiq) ? 'seleccion' : ($idsSel !== [] ? 'seleccion' : 'todos')
    );
    $empleadosPrecarga = $idsSel !== []
        ? \App\Models\Sueldos\Empleado_Sueldos::query()
            ->whereIn('id', $idsSel)
            ->orderBy('legajo')
            ->get(['id', 'legajo', 'nombre', 'estado', 'fecha_egreso'])
        : collect();
@endphp

<div class="card card-outline card-warning mt-2" id="bloque-seleccion-empleados"
     data-url-candidatos="{{ route('candidatos_empleados_liquidacion_sueldos') }}">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">Empleados a liquidar</h3>
    </div>
    <div class="card-body">
        <div class="form-group mb-2">
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="empleados_modo_todos" name="empleados_modo" value="todos" class="custom-control-input"
                       {{ $modoSel === 'todos' ? 'checked' : '' }}>
                <label class="custom-control-label" for="empleados_modo_todos">
                    Todos los que correspondan
                    <small class="text-muted d-block">
                        Mensual = activos · Final = egresos del per&iacute;odo (provisoria o definitiva)
                    </small>
                </label>
            </div>
            <div class="custom-control custom-radio custom-control-inline mt-1">
                <input type="radio" id="empleados_modo_seleccion" name="empleados_modo" value="seleccion" class="custom-control-input"
                       {{ $modoSel === 'seleccion' ? 'checked' : '' }}>
                <label class="custom-control-label" for="empleados_modo_seleccion">Solo seleccionados</label>
            </div>
        </div>

        <div id="panel-empleados-seleccion" style="{{ $modoSel === 'seleccion' ? '' : 'display:none' }}">
            <div class="mb-2 d-flex flex-wrap align-items-center" style="gap:6px;">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-cargar-candidatos-liq"
                        title="Trae del servidor los legajos del per&iacute;odo/tipo (no selecciona por s&iacute; solo)">
                    <i class="fa fa-users"></i> Cargar candidatos del per&iacute;odo
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-agregar-empleado-liq"
                        title="Buscar un legajo y sumarlo a seleccionados">
                    <i class="fa fa-plus"></i> Agregar empleado
                </button>
                <span class="text-muted small ml-1" id="hint-tipo-candidatos-liq"></span>
            </div>

            <div class="row">
                {{-- Candidatos (pool) --}}
                <div class="col-md-6 mb-2">
                    <div class="border rounded h-100">
                        <div class="px-2 py-1 bg-light border-bottom d-flex align-items-center justify-content-between">
                            <strong class="small mb-0">Candidatos</strong>
                            <span class="badge badge-secondary" id="contador-candidatos-liq">0</span>
                        </div>
                        <div class="p-2 border-bottom">
                            <input type="search" class="form-control form-control-sm" id="filtro-candidatos-liq"
                                   placeholder="Buscar legajo o nombre…" autocomplete="off">
                        </div>
                        <div class="px-2 py-1 border-bottom d-flex flex-wrap" style="gap:4px;">
                            <button type="button" class="btn btn-xs btn-outline-primary" id="btn-pasar-visibles-liq"
                                    title="Pasa a seleccionados los candidatos visibles (filtro aplicado)">
                                Pasar visibles <i class="fa fa-angle-double-right"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-pasar-todos-cand-liq"
                                    title="Pasa todos los candidatos cargados">
                                Pasar todos <i class="fa fa-angle-double-right"></i>
                            </button>
                        </div>
                        <div class="table-responsive" style="max-height: 300px; overflow:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead style="background:#d6eaf8;color:#17202A;">
                                    <tr>
                                        <th style="width:70px">Legajo</th>
                                        <th>Nombre</th>
                                        <th style="width:85px">Egreso</th>
                                        <th style="width:36px"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-candidatos-liq"></tbody>
                            </table>
                            <p class="text-muted small text-center py-3 mb-0" id="vacio-candidatos-liq">
                                Us&aacute; &laquo;Cargar candidatos&raquo; o agreg&aacute; un legajo puntual.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Seleccionados (submit) --}}
                <div class="col-md-6 mb-2">
                    <div class="border rounded h-100">
                        <div class="px-2 py-1 bg-light border-bottom d-flex align-items-center justify-content-between">
                            <strong class="small mb-0">Seleccionados a liquidar</strong>
                            <span class="badge badge-warning" id="contador-seleccionados-liq">0</span>
                        </div>
                        <div class="p-2 border-bottom">
                            <input type="search" class="form-control form-control-sm" id="filtro-seleccionados-liq"
                                   placeholder="Buscar en seleccionados…" autocomplete="off">
                        </div>
                        <div class="px-2 py-1 border-bottom d-flex flex-wrap" style="gap:4px;">
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-devolver-visibles-liq"
                                    title="Devuelve a candidatos los seleccionados visibles">
                                <i class="fa fa-angle-double-left"></i> Devolver visibles
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger" id="btn-quitar-todos-sel-liq"
                                    title="Vac&iacute;a la lista de seleccionados">
                                Quitar todos
                            </button>
                        </div>
                        <div class="table-responsive" style="max-height: 300px; overflow:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead style="background:#f9e79f;color:#17202A;">
                                    <tr>
                                        <th style="width:70px">Legajo</th>
                                        <th>Nombre</th>
                                        <th style="width:85px">Egreso</th>
                                        <th style="width:36px"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-seleccionados-liq">
                                    @foreach ($empleadosPrecarga as $emp)
                                        <tr data-empleado-id="{{ $emp->id }}"
                                            data-legajo="{{ $emp->legajo }}"
                                            data-nombre="{{ $emp->nombre }}"
                                            data-egreso="{{ optional($emp->fecha_egreso)->format('d/m/Y') ?: '—' }}"
                                            data-search="{{ mb_strtolower(trim($emp->legajo.' '.$emp->nombre)) }}">
                                            <td>
                                                {{ $emp->legajo }}
                                                <input type="hidden" name="empleado_ids[]" value="{{ $emp->id }}">
                                            </td>
                                            <td>{{ $emp->nombre }}</td>
                                            <td>{{ optional($emp->fecha_egreso)->format('d/m/Y') ?: '—' }}</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-devolver-empleado-liq" title="Devolver a candidatos">
                                                    <i class="fa fa-angle-left"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="text-muted small text-center py-3 mb-0" id="vacio-seleccionados-liq"
                               style="{{ $empleadosPrecarga->isEmpty() ? '' : 'display:none' }}">
                                Todav&iacute;a no hay legajos seleccionados.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <small class="form-text text-muted">
                <strong>Cargar candidatos</strong> arma el pool de la izquierda seg&uacute;n el tipo:
                mensual = activos; <strong>final</strong> = legajos con fecha de egreso en la ventana del per&iacute;odo.
                Pasalos a la derecha para incluirlos. Solo los de la derecha se liquidan.
            </small>
        </div>
    </div>
</div>

{{-- Campo oculto para agregar vía modal estándar de empleado --}}
<div class="d-none">
    @include('sueldos.partials.campo_consulta_empleado_sueldos', [
        'prefix' => 'liq_sel',
        'layout' => 'form_row',
        'label' => 'Empleado',
        'idInputName' => 'liq_sel_empleado_id',
        'col_label' => 'col-lg-3',
        'col_input' => 'col-lg-6',
    ])
</div>
