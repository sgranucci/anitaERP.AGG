@extends("theme.$theme.layout")
@section('titulo')
    Plantillas de contrato
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/maestro/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/maestro/consulta.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Plantillas de contrato / certificados</h3>
                <div class="card-tools">
                    @if (can('crear-contrato-plantilla-sueldos', false))
                        <a href="{{ route('crear_contrato_plantilla_sueldos') }}" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Nueva plantilla
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Cargue modelos Word (.docx) con tags <code>$nombre_empleado$</code>, <code>$categoria$</code>, etc.
                    Luego asigne cada plantilla por tipo de documento, modalidad SIJP, sindicato y/o empresa.
                    Criterios vacíos = comodín. Gana la regla más específica y, a igual score, la de mayor prioridad.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Versión</th>
                                <th>Estado</th>
                                <th>Archivo</th>
                                <th style="width:90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($plantillas as $p)
                                <tr>
                                    <td>{{ $p->codigo }}</td>
                                    <td>{{ $p->nombre }}</td>
                                    <td>{{ $p->tipo_label }}</td>
                                    <td>{{ $p->version }}</td>
                                    <td>
                                        @if ($p->activo)
                                            <span class="badge badge-success">Activa</span>
                                        @else
                                            <span class="badge badge-secondary">Inactiva</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $p->archivo_nombre }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('descargar_contrato_plantilla_sueldos', $p->id) }}" class="btn-accion-tabla tooltipsC" title="Descargar">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        @if (can('editar-contrato-plantilla-sueldos', false))
                                            <a href="{{ route('editar_contrato_plantilla_sueldos', $p->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('borrar-contrato-plantilla-sueldos', false))
                                            <form action="{{ route('eliminar_contrato_plantilla_sueldos', $p->id) }}" method="POST" class="d-inline form-eliminar"
                                                  onsubmit="return confirm('¿Eliminar la plantilla y sus asignaciones?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No hay plantillas cargadas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">Reglas de asignación</h3>
            </div>
            <div class="card-body">
                @php
                    $editando = $asignacionEditar ?? null;
                    $editandoId = $editando?->id;
                @endphp
                @if (can('actualizar-contrato-plantilla-sueldos', false) && $plantillas->isNotEmpty())
                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <strong>
                            @if ($editando)
                                Modificar regla #{{ $editando->id }}
                            @else
                                Nueva regla
                            @endif
                        </strong>
                        @if ($editando)
                            <a href="{{ route('consultar_contrato_plantilla_sueldos') }}" class="btn btn-outline-secondary btn-sm">
                                Cancelar edición
                            </a>
                        @endif
                    </div>
                    <form method="POST"
                          action="{{ $editando ? route('actualizar_asignacion_contrato_plantilla_sueldos', $editando->id) : route('guardar_asignacion_contrato_plantilla_sueldos') }}"
                          class="mb-4"
                          id="form-asignacion-contrato"
                          data-enter-campos="1">
                        @csrf
                        @if ($editando)
                            @method('PUT')
                        @endif
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Plantilla</label>
                                <select name="contrato_plantilla_id" class="form-control form-control-sm" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($plantillas as $p)
                                        <option value="{{ $p->id }}" @selected((int) old('contrato_plantilla_id', $editando->contrato_plantilla_id ?? 0) === (int) $p->id)>
                                            {{ $p->codigo }} — {{ $p->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Tipo documento</label>
                                <select name="tipo" class="form-control form-control-sm" required>
                                    @foreach ($tipos as $k => $lab)
                                        <option value="{{ $k }}" @selected(old('tipo', $editando->tipo ?? '') === $k)>{{ $lab }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Modalidad (vacío = cualquiera)</label>
                                <select name="modalidad" class="form-control form-control-sm">
                                    <option value="">Cualquiera</option>
                                    @foreach ($modalidades as $cod => $lab)
                                        <option value="{{ $cod }}" @selected((string) old('modalidad', $editando->modalidad ?? '') === (string) $cod)>
                                            {{ $cod }} — {{ $lab }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                            'maestro' => 'sindicato',
                            'label' => 'Sindicato (vacío = cualquiera)',
                            'inputName' => 'sindicato_id',
                            'inputId' => 'asig_sindicato_id',
                            'registroId' => old('sindicato_id', $editando->sindicato_id ?? ''),
                            'codigo' => old('asig_sindicato_codigo', $editando->sindicato->codigo ?? ''),
                            'descripcion' => old('asig_sindicato_nombre', $editando->sindicato->descripcion ?? ''),
                            'required' => false,
                            'col_label' => 'col-lg-3 control-label text-right pr-2',
                            'col_input' => 'col-lg-6',
                        ])
                        @include('includes.form-empresa-asignada', [
                            'empresa_query' => $empresa_query,
                            'empresa_id' => old('empresa_id', $editando->empresa_id ?? null),
                            'solo_lectura' => false,
                            'required' => false,
                            'permite_vacio' => true,
                            'opcion_vacia' => 'Cualquiera',
                            'label' => 'Empresa (vacío = cualquiera)',
                            'col_label' => 'col-lg-3 control-label text-right pr-2',
                            'col_input' => 'col-lg-5',
                            'name' => 'empresa_id',
                        ])
                        <div class="form-group row">
                            <label class="col-lg-3 control-label text-right pr-2">Prioridad</label>
                            <div class="col-lg-2">
                                <input type="number" name="prioridad" class="form-control form-control-sm"
                                       value="{{ old('prioridad', $editando->prioridad ?? 100) }}" min="1" max="9999">
                            </div>
                            <div class="col-lg-5">
                                @if ($editando)
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fa fa-save"></i> Actualizar regla
                                    </button>
                                    <a href="{{ route('consultar_contrato_plantilla_sueldos') }}" class="btn btn-outline-secondary btn-sm">
                                        Cancelar
                                    </a>
                                @else
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa fa-plus"></i> Agregar regla
                                    </button>
                                @endif
                            </div>
                        </div>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Tipo</th>
                                <th>Plantilla</th>
                                <th>Modalidad</th>
                                <th>Sindicato</th>
                                <th>Empresa</th>
                                <th>Prioridad</th>
                                <th style="width:80px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($asignaciones as $a)
                                <tr class="{{ (int) $editandoId === (int) $a->id ? 'table-warning' : '' }}">
                                    <td>{{ $a->tipo_label }}</td>
                                    <td>{{ $a->plantilla->codigo ?? '—' }}</td>
                                    <td>{{ $a->modalidad ? ($a->modalidad.' — '.$a->modalidad_label) : 'Cualquiera' }}</td>
                                    <td>{{ $a->sindicato->descripcion ?? 'Cualquiera' }}</td>
                                    <td>{{ $a->empresa->nombre ?? 'Cualquiera' }}</td>
                                    <td>{{ $a->prioridad }}</td>
                                    <td class="text-nowrap">
                                        @if (can('actualizar-contrato-plantilla-sueldos', false))
                                            <a href="{{ route('consultar_contrato_plantilla_sueldos', ['editar_asignacion' => $a->id]) }}"
                                               class="btn-accion-tabla tooltipsC"
                                               title="Modificar esta regla">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <form action="{{ route('eliminar_asignacion_contrato_plantilla_sueldos', $a->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar esta regla?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Sin reglas. Anita resolvía por nombre MOD{modalidad}-{sindicato}.docx; acá se configura en tabla.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Tags disponibles en el Word</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach ($tags as $tag => $desc)
                        <div class="col-md-4 col-lg-3 mb-1 small">
                            <code>${{ $tag }}$</code> — {{ $desc }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsulta_maestro_sueldos')
@if (! empty($asignacionEditar))
<script>
(function () {
    var form = document.getElementById('form-asignacion-contrato');
    if (form) {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
})();
</script>
@endif
@endsection
