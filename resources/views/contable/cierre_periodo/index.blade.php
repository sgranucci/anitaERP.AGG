@extends("theme.$theme.layout")
@section('titulo')
    Cierre de período contable
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cierre_periodo/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/cierre_periodo/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cierre-periodo-overlay',
    'tituloId' => 'cierre-periodo-overlay-titulo',
    'subtituloId' => 'cierre-periodo-overlay-subtitulo',
    'titulo' => 'Procesando cierres…',
    'subtitulo' => 'Puede demorar unos segundos. No cierre la página.',
])

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre de período contable</h3>
                <div class="card-tools">
                    @include('includes.contable.boton-manual')
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Al cerrar un período, ningún proceso del sistema puede generar información contable
                    con fecha anterior o igual al cierre del módulo correspondiente, salvo usuarios con
                    apertura programada aprobada o permiso de operación en período cerrado.
                    Puede cerrar por <strong>módulo entero</strong> (Caja, Ventas, Compras, Stock, Sueldos, Contable)
                    o por <strong>submódulo</strong> (cobranzas, facturación, indumentaria, etc.).
                    Programe el cierre: <strong>fecha de ejecución</strong>,
                    <strong>hora</strong> (por defecto <strong>24:00</strong> = fin del día) y
                    <strong>fecha de cierre</strong> (tope contable inclusive, editable).
                    El sistema ejecuta automáticamente al llegar esa fecha/hora; también puede aplicar en el momento si la fecha ya llegó.
                    La facturación valida contra la fecha de jornada cuando existe.
                </p>

                <form method="get" action="{{ route('cierre_periodo_contable') }}" class="form-horizontal mb-4" id="form-filtro-cierre-periodo">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-md-2',
                        'col_input' => 'col-md-4',
                    ])
                    <div class="form-group row">
                        <label class="col-md-2 control-label">Mes / Año agenda</label>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-4">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) $mes === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ $anio }}">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa fa-search"></i> Consultar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                @if ($empresa_id <= 0 && $empresa_query->count() > 1)
                    <div class="alert alert-info">
                        Seleccione una empresa y pulse <strong>Consultar</strong> para programar o ejecutar cierres.
                    </div>
                @endif

                @if ($empresa_id > 0)
                    <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center">
                        <div class="mb-1">
                            <strong>Cierre general vigente:</strong>
                            @if ($resumen_vigente)
                                hasta {{ \Carbon\Carbon::parse($resumen_vigente['fecha_hasta'])->format('d/m/Y') }}
                                @if (!empty($resumen_vigente['observacion']))
                                    — {{ $resumen_vigente['observacion'] }}
                                @endif
                            @else
                                sin cierre general registrado para esta empresa.
                            @endif
                        </div>
                        @if ($puede_borrar_ultimo_cierre && $ultimo_cierre)
                            <div class="mb-1">
                                @include('contable.cierre_periodo.partials.boton_borrar_ultimo', [
                                    'empresa_id' => $empresa_id,
                                    'fecha_hasta' => $ultimo_cierre->fecha_hasta,
                                    'alcance' => \App\Support\Contable\PeriodoContableCierreSupport::ALCANCE_GENERAL,
                                    'mes' => $mes,
                                    'anio' => $anio,
                                    'btn_class' => 'btn-sm',
                                ])
                            </div>
                        @endif
                    </div>

                    {{-- Agenda mensual --}}
                    <div class="card card-outline card-primary mb-4">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                Agenda de cierres — {{ str_pad((string) $mes, 2, '0', STR_PAD_LEFT) }}/{{ $anio }}
                            </h3>
                            @if ($puede_ejecutar_cierre)
                                <div class="card-tools mt-1 mt-md-0">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        id="btn-expandir-todos-submodulos"
                                        title="Mostrar todos los submódulos">
                                        <i class="fa fa-expand"></i> Submódulos
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm"
                                        data-toggle="modal" data-target="#modal-programar-todos"
                                        title="Programar todos los módulos y submódulos con las mismas fechas">
                                        <i class="fa fa-calendar-plus-o"></i> Programar todos
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm"
                                        data-toggle="modal" data-target="#modal-cerrar-todos"
                                        title="Cerrar ahora todos los módulos (cierre general)">
                                        <i class="fa fa-lock"></i> Cerrar todos ahora
                                    </button>
                                    <form method="post" action="{{ route('ejecutar_pendientes_cierre_periodo_contable') }}"
                                        class="d-inline form-proceso-cierre"
                                        data-overlay-titulo="Ejecutando cierres pendientes…">
                                        @csrf
                                        <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                        <input type="hidden" name="anio_mes" value="{{ $anio_mes }}">
                                        <input type="hidden" name="mes" value="{{ $mes }}">
                                        <input type="hidden" name="anio" value="{{ $anio }}">
                                        <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('¿Ejecutar ahora todos los pendientes con fecha de ejecución ya vencida o de hoy?');"
                                            title="Aplicar ahora los pendientes del mes">
                                            <i class="fa fa-play"></i> Aplicar pendientes
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-0">
                            @if ($puede_ejecutar_cierre)
                                @foreach ($agenda_grupos as $grupo)
                                    @if (!empty($grupo['fila']) && ($grupo['fila']['estado'] ?? '') !== 'ejecutado')
                                        <form method="post" action="{{ route('programar_cierre_periodo_contable') }}"
                                            id="form-prog-{{ $grupo['fila']['alcance'] }}" class="d-none">
                                            @csrf
                                            <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                            <input type="hidden" name="anio_mes" value="{{ $anio_mes }}">
                                            <input type="hidden" name="alcance" value="{{ $grupo['fila']['alcance'] }}">
                                            <input type="hidden" name="mes" value="{{ $mes }}">
                                            <input type="hidden" name="anio" value="{{ $anio }}">
                                        </form>
                                    @endif
                                    @foreach ($grupo['hijos'] as $hijo)
                                        @if (($hijo['estado'] ?? '') !== 'ejecutado')
                                            <form method="post" action="{{ route('programar_cierre_periodo_contable') }}"
                                                id="form-prog-{{ $hijo['alcance'] }}" class="d-none">
                                                @csrf
                                                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                                <input type="hidden" name="anio_mes" value="{{ $anio_mes }}">
                                                <input type="hidden" name="alcance" value="{{ $hijo['alcance'] }}">
                                                <input type="hidden" name="mes" value="{{ $mes }}">
                                                <input type="hidden" name="anio" value="{{ $anio }}">
                                            </form>
                                        @endif
                                    @endforeach
                                @endforeach
                            @endif
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm mb-0" id="tabla-agenda-cierre">
                                    <thead style="background-color:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Módulo / submódulo</th>
                                            <th style="min-width:130px;">Fecha ejecución</th>
                                            <th style="min-width:90px;" title="24:00 = fin del día">Hora</th>
                                            <th style="min-width:130px;">Fecha cierre</th>
                                            <th>Estado</th>
                                            <th>Observación</th>
                                            @if ($puede_ejecutar_cierre)
                                                <th style="min-width:160px;">Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($agenda_grupos as $grupo)
                                            @if (!empty($grupo['fila']))
                                                @include('contable.cierre_periodo.partials.fila_agenda', [
                                                    'fila' => $grupo['fila'],
                                                    'etiqueta_modulo' => $grupo['etiqueta'],
                                                    'grupo_codigo' => $grupo['codigo'],
                                                    'cantidad_hijos' => count($grupo['hijos'] ?? []),
                                                    'empresa_id' => $empresa_id,
                                                    'mes' => $mes,
                                                    'anio' => $anio,
                                                    'puede_ejecutar_cierre' => $puede_ejecutar_cierre,
                                                ])
                                            @endif
                                            @foreach ($grupo['hijos'] as $hijo)
                                                @include('contable.cierre_periodo.partials.fila_agenda', [
                                                    'fila' => $hijo,
                                                    'grupo_codigo' => $grupo['codigo'],
                                                    'empresa_id' => $empresa_id,
                                                    'mes' => $mes,
                                                    'anio' => $anio,
                                                    'puede_ejecutar_cierre' => $puede_ejecutar_cierre,
                                                ])
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-2 small text-muted">
                                Fecha de cierre por defecto: {{ \Carbon\Carbon::parse($fecha_hasta_default)->format('d/m/Y') }}
                                (último día del mes anterior). Puede cambiarla en cada fila.
                                Hora de ejecución por defecto: <strong>24:00</strong> (fin del día).
                                Los submódulos están colapsados: use el chevron de cada módulo (o <strong>Submódulos</strong>) para cerrar selectivo.
                            </div>
                        </div>
                    </div>

                    @if ($puede_ejecutar_cierre)
                        <div class="card card-outline card-warning mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Cierre inmediato (módulo o submódulo)</h3>
                            </div>
                            <form method="post" action="{{ route('ejecutar_cierre_periodo_contable') }}" class="form-proceso-cierre"
                                data-overlay-titulo="Registrando cierre…">
                                @csrf
                                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                <input type="hidden" name="mes" value="{{ $mes }}">
                                <input type="hidden" name="anio" value="{{ $anio }}">
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label class="col-md-2 control-label requerido">Alcance</label>
                                        <div class="col-md-5">
                                            @include('contable.partials.select_alcance_periodo', [
                                                'jerarquia_alcances' => $jerarquia_alcances,
                                                'selected' => old('alcance', 'general'),
                                                'incluir_general' => true,
                                            ])
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2 control-label requerido">Fecha hasta</label>
                                        <div class="col-md-3">
                                            <input type="date" name="fecha_hasta" class="form-control" required
                                                max="{{ date('Y-m-d') }}"
                                                value="{{ old('fecha_hasta', $fecha_hasta_default) }}">
                                        </div>
                                        <div class="col-md-7">
                                            <small class="text-muted">Última fecha incluida en el cierre (inclusive).</small>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <label class="col-md-2 control-label">Observación</label>
                                        <div class="col-md-6">
                                            <textarea name="observacion" class="form-control" rows="2" maxlength="2000">{{ old('observacion') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-warning"
                                        onclick="return confirm('¿Confirma el cierre contable hasta la fecha indicada?');">
                                        <i class="fa fa-lock"></i> Ejecutar cierre
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endif

                <h5 class="mt-2 mb-2">Histórico de cierres</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm" id="tabla-paginada">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>Empresa</th>
                                <th>Módulo</th>
                                <th>Fecha hasta</th>
                                <th>Observación</th>
                                <th>Usuario</th>
                                <th>Registrado</th>
                                @if ($puede_borrar_ultimo_cierre)
                                    <th>Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cierres as $cierre)
                                @php
                                    $claveUltimo = $cierre->empresa_id.'|'.($cierre->alcance ?? 'general');
                                @endphp
                                <tr>
                                    <td>{{ $cierre->id }}</td>
                                    <td>{{ $cierre->empresa?->nombre }}</td>
                                    <td>{{ $cierre->etiquetaAlcance() }}</td>
                                    <td>{{ optional($cierre->fecha_hasta)->format('d/m/Y') }}</td>
                                    <td>{{ $cierre->observacion }}</td>
                                    <td>{{ $cierre->usuario?->nombre }}</td>
                                    <td>{{ optional($cierre->created_at)->format('d/m/Y H:i') }}</td>
                                    @if ($puede_borrar_ultimo_cierre)
                                        <td class="text-nowrap">
                                            @if (($ultimos_cierre_ids[$claveUltimo] ?? null) === $cierre->id)
                                                @include('contable.cierre_periodo.partials.boton_borrar_ultimo', [
                                                    'empresa_id' => $cierre->empresa_id,
                                                    'fecha_hasta' => $cierre->fecha_hasta,
                                                    'alcance' => $cierre->alcance,
                                                    'mes' => $mes,
                                                    'anio' => $anio,
                                                    'btn_class' => 'btn-sm',
                                                ])
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $puede_borrar_ultimo_cierre ? 8 : 7 }}" class="text-center text-muted">Sin registros de cierre.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($cierres->hasPages())
                    <div class="d-flex justify-content-between align-items-center">
                        <small>
                            {{ $cierres->firstItem() }}–{{ $cierres->lastItem() }} de {{ $cierres->total() }}
                        </small>
                        {{ $cierres->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($empresa_id > 0 && $puede_ejecutar_cierre)
    {{-- Modal programar todos --}}
    <div class="modal fade" id="modal-programar-todos" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="{{ route('programar_todos_cierre_periodo_contable') }}" class="form-proceso-cierre"
                data-overlay-titulo="Guardando programación…">
                @csrf
                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                <input type="hidden" name="anio_mes" value="{{ $anio_mes }}">
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="anio" value="{{ $anio }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa fa-calendar-plus-o"></i> Programar todos los módulos y submódulos</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Aplica las mismas fechas a todos los módulos y submódulos del mes (los ya ejecutados no se modifican).
                        </p>
                        <div class="form-group">
                            <label class="requerido">Fecha de ejecución</label>
                            <input type="date" name="fecha_ejecucion" class="form-control" required
                                value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group">
                            <label>Hora de ejecución</label>
                            <input type="text" name="hora_ejecucion" class="form-control" maxlength="5"
                                value="24:00" placeholder="24:00"
                                pattern="^([01]?\d|2[0-3]):[0-5]\d$|^24:00$"
                                title="HH:MM o 24:00 (fin de día)">
                            <small class="text-muted">24:00 = fin del día. Vacío también se toma como 24:00.</small>
                        </div>
                        <div class="form-group">
                            <label class="requerido">Fecha de cierre (tope contable)</label>
                            <input type="date" name="fecha_hasta" class="form-control" required
                                max="{{ date('Y-m-d') }}"
                                value="{{ $fecha_hasta_default }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>Observación</label>
                            <textarea name="observacion" class="form-control" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fa fa-save"></i> Programar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal cerrar todos ahora --}}
    <div class="modal fade" id="modal-cerrar-todos" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="{{ route('cerrar_todos_cierre_periodo_contable') }}" class="form-proceso-cierre"
                data-overlay-titulo="Registrando cierre general…">
                @csrf
                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="anio" value="{{ $anio }}">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="fa fa-lock"></i> Cerrar todos los módulos ahora</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Registra un cierre <strong>general</strong> que bloquea todos los módulos hasta la fecha indicada
                            e incluye el congelamiento de saldos contables.
                        </p>
                        <div class="form-group">
                            <label class="requerido">Fecha hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" required
                                max="{{ date('Y-m-d') }}"
                                value="{{ $fecha_hasta_default }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>Observación</label>
                            <textarea name="observacion" class="form-control" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning"
                            onclick="return confirm('¿Confirma el cierre general de todos los módulos?');">
                            <i class="fa fa-lock"></i> Cerrar todos
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
