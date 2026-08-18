@php
    $columnasPayload = $data->columnas->map(function ($col) use ($contenidos, $camposEmpleado) {
        return [
            'id' => (int) $col->id,
            'nro_columna' => (int) $col->nro_columna,
            'descripcion' => (string) $col->descripcion,
            'contenido' => (string) $col->contenido,
            'contenido_label' => $contenidos[$col->contenido] ?? $col->contenido,
            'campo_empleado' => $col->campo_empleado,
            'campo_empleado_label' => $camposEmpleado[$col->campo_empleado] ?? null,
            'largo' => $col->largo,
            'formula' => $col->formula,
            'orden' => (int) $col->orden,
            'conceptos' => $col->conceptos->map(fn ($con) => [
                'concepto_codigo' => (int) $con->concepto_codigo,
                'signo' => $con->signo === '-' ? '-' : '+',
                'orden' => (int) $con->orden,
                'descripcion' => (string) ($con->concepto->descripcion ?? ''),
                'concepto_id' => (int) ($con->concepto->id ?? 0),
            ])->values()->all(),
        ];
    })->values()->all();
    $siguienteNro = (int) (($data->columnas->max('nro_columna') ?? 0) + 1);
    $columnaInicial = (int) (old('columna_id', request('columna', 0)));
    $editorConfig = [
        'siguienteNro' => $siguienteNro,
        'columnaInicial' => $columnaInicial,
        'reporteId' => (int) $data->id,
        'contenidosNumericos' => ['importe', 'cantidad', 'valor', 'concepto_ganancias'],
    ];
@endphp

<style>
    .rsd-editor-columnas-conceptos {
        display: grid;
        grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        gap: 12px;
        align-items: stretch;
    }
    .rsd-panel {
        display: flex;
        flex-direction: column;
        min-height: 0;
        height: clamp(520px, 68vh, 720px);
    }
    .rsd-panel .card {
        display: flex;
        flex-direction: column;
        height: 100%;
        margin-bottom: 0;
    }
    .rsd-panel .card-body {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 auto;
    }
    .rsd-panel-form {
        flex: 0 0 auto;
    }
    .rsd-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: .25rem;
    }
    .rsd-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #85C1E9;
        color: #17202A;
    }
    .rsd-columnas-table tr.table-active,
    .rsd-conceptos-table tr.table-active {
        background: #d6eaf8 !important;
    }
    .rsd-estado-seleccion {
        min-height: 1.25rem;
    }
    @media (max-width: 991.98px) {
        .rsd-editor-columnas-conceptos {
            grid-template-columns: 1fr;
        }
        .rsd-panel {
            height: auto;
        }
        .rsd-scroll {
            max-height: 320px;
        }
    }
</style>

<script type="application/json" id="rsd-columnas-payload">@json($columnasPayload)</script>
<script type="application/json" id="rsd-editor-config">@json($editorConfig)</script>

<div id="rsd-editor-columnas-conceptos" class="rsd-editor-columnas-conceptos">
    <div id="rsd-panel-columnas" class="rsd-panel">
        <div class="card card-outline card-primary h-100">
            <div class="card-header py-2">
                <strong id="rsd-titulo-form-columna">Agregar columna</strong>
            </div>
            <div class="card-body">
                <form method="post"
                      action="{{ route('guardar_columna_reporte_sueldos_definible', ['id' => $data->id]) }}"
                      id="form-columna-rsd"
                      class="form-horizontal rsd-panel-form">
                    @csrf
                    <input type="hidden" name="columna_id" id="rsd_columna_id" value="{{ old('columna_id') }}">
                    <div id="rsd-conceptos-hidden"></div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small" for="rsd_nro_columna">Nro</label>
                            <input type="number" name="nro_columna" id="rsd_nro_columna"
                                   class="form-control form-control-sm" required min="1"
                                   value="{{ old('nro_columna', $siguienteNro) }}">
                        </div>
                        <div class="form-group col-md-9">
                            <label class="small" for="rsd_descripcion">Descripci&oacute;n</label>
                            <input type="text" name="descripcion" id="rsd_descripcion"
                                   class="form-control form-control-sm" required maxlength="80"
                                   value="{{ old('descripcion') }}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label class="small" for="rsd_contenido">Contenido</label>
                            <select name="contenido" id="rsd_contenido" class="form-control form-control-sm">
                                @foreach ($contenidos as $k => $v)
                                    <option value="{{ $k }}" {{ old('contenido', 'importe') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-7 rsd-campo-empleado">
                            <label class="small" for="rsd_campo_empleado">Campo empleado</label>
                            <select name="campo_empleado" id="rsd_campo_empleado" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($camposEmpleado as $k => $v)
                                    <option value="{{ $k }}" {{ (string) old('campo_empleado') === (string) $k ? 'selected' : '' }}>{{ $k }}. {{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3 rsd-largo">
                            <label class="small" for="rsd_largo">Largo</label>
                            <input type="number" name="largo" id="rsd_largo" class="form-control form-control-sm"
                                   min="1" max="80" value="{{ old('largo') }}">
                        </div>
                        <div class="form-group col-md-9 rsd-formula d-none">
                            <label class="small" for="rsd_formula">F&oacute;rmula</label>
                            <input type="text" name="formula" id="rsd_formula" class="form-control form-control-sm"
                                   maxlength="255" value="{{ old('formula') }}"
                                   placeholder="C1+C2, si(C1&gt;0,C2,0), entre(C1,0,100)">
                            <span class="form-text text-muted small">Usa C1…Cn y campos de la fila. Mismo motor que sueldos: si, entre, comparaciones.</span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-save"></i> <span class="rsd-texto-guardar-columna">Grabar columna</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="rsd-nueva-columna">
                            <i class="fa fa-plus"></i> Nueva
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="rsd-cancelar-edicion-columna">
                            Cancelar edici&oacute;n
                        </button>
                    </div>
                </form>

                <p class="small text-muted rsd-estado-seleccion mb-1" id="rsd-estado-seleccion" aria-live="polite"></p>
                <div id="rsd-columnas-scroll" class="rsd-scroll">
                    <table class="table table-sm table-bordered mb-0 rsd-columnas-table" id="rsd-columnas-table">
                        <caption class="sr-only">Columnas del listado</caption>
                        <thead>
                            <tr>
                                <th scope="col">Nro</th>
                                <th scope="col">Descripci&oacute;n</th>
                                <th scope="col">Contenido</th>
                                <th scope="col">Detalle</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="rsd-columnas-tbody">
                            @forelse ($data->columnas as $col)
                                <tr data-columna-id="{{ $col->id }}">
                                    <td>{{ $col->nro_columna }}</td>
                                    <td>{{ $col->descripcion }}</td>
                                    <td>{{ $contenidos[$col->contenido] ?? $col->contenido }}</td>
                                    <td>
                                        @if ($col->contenido === 'campo_empleado')
                                            {{ $camposEmpleado[$col->campo_empleado] ?? $col->campo_empleado }}
                                        @elseif ($col->contenido === 'formula')
                                            <code>{{ $col->formula }}</code>
                                        @else
                                            @forelse ($col->conceptos as $concepto)
                                                <span class="badge badge-secondary mr-1">
                                                    {{ $concepto->signo }}{{ str_pad((string) $concepto->concepto_codigo, 4, '0', STR_PAD_LEFT) }}
                                                </span>
                                            @empty
                                                <span class="text-muted">Sin conceptos</span>
                                            @endforelse
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button"
                                                class="btn-accion-tabla rsd-seleccionar-columna"
                                                aria-pressed="false"
                                                title="Seleccionar columna {{ $col->nro_columna }}">
                                            <i class="fa fa-edit"></i>
                                            <span class="sr-only">Seleccionar columna {{ $col->nro_columna }}</span>
                                        </button>
                                        <form action="{{ route('eliminar_columna_reporte_sueldos_definible', ['id' => $data->id, 'columnaId' => $col->id]) }}"
                                              method="post" class="d-inline"
                                              onsubmit="return confirm('¿Quitar columna {{ $col->nro_columna }} — {{ $col->descripcion }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla" title="Eliminar columna">
                                                <i class="fa fa-times-circle text-danger"></i>
                                                <span class="sr-only">Eliminar columna</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr class="rsd-columnas-vacio"><td colspan="5" class="text-muted text-center">Sin columnas</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="rsd-panel-conceptos" class="rsd-panel">
        <div class="card card-outline card-info h-100">
            <div class="card-header py-2">
                <strong id="rsd-titulo-panel-conceptos">Conceptos de la columna</strong>
            </div>
            <div class="card-body">
                <div id="rsd-conceptos-vacio" class="alert alert-light border py-2 mb-2">
                    Seleccione o cree una columna. Si el contenido es num&eacute;rico, agregue conceptos aqu&iacute; y luego guarde la columna.
                </div>
                <form id="form-concepto-rsd" class="rsd-panel-form mb-2" onsubmit="return false;">
                    <input type="hidden" id="rsd_concepto_indice" value="">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-8 mb-2">
                            @include('sueldos.partials.campo_consulta_concepto_sueldos', [
                                'layout' => 'compact',
                                'label' => 'Concepto',
                                'inputName' => 'concepto_editor_id',
                                'inputId' => 'rsd_concepto_editor_id',
                                'required' => true,
                            ])
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small" for="rsd_concepto_signo">Signo</label>
                            <select id="rsd_concepto_signo" class="form-control form-control-sm">
                                <option value="+">+</option>
                                <option value="-">−</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <button type="submit" class="btn btn-outline-primary btn-sm btn-block" id="rsd-agregar-concepto">
                                Agregar
                            </button>
                        </div>
                    </div>
                    <p class="small text-muted mb-1" id="rsd-conceptos-pendientes"></p>
                </form>
                <div id="rsd-conceptos-scroll" class="rsd-scroll">
                    <table class="table table-sm table-bordered mb-0 rsd-conceptos-table" id="rsd-conceptos-table">
                        <caption class="sr-only">Conceptos de la columna seleccionada</caption>
                        <thead>
                            <tr>
                                <th scope="col">Signo</th>
                                <th scope="col">C&oacute;digo</th>
                                <th scope="col">Descripci&oacute;n</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="rsd-conceptos-tbody"></tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <button type="submit" class="btn btn-primary btn-sm" form="form-columna-rsd">
                        <i class="fa fa-save"></i> <span class="rsd-texto-guardar-columna">Grabar columna</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
