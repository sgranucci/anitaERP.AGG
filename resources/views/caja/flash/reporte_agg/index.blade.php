@extends("theme.$theme.layout")
@section('titulo')
    Flash Report AGG
@endsection

@section('scripts')
<script>
(function () {
    var periodicidad = document.getElementById('frs-periodicidad');
    var diaMes = document.getElementById('frs-dia-mes-wrap');
    var diaSemana = document.getElementById('frs-dia-semana-wrap');
    var periodo = document.getElementById('frs-periodo');
    var mesFijo = document.getElementById('frs-mes-fijo-wrap');
    function sync() {
        var p = periodicidad ? periodicidad.value : 'diaria';
        if (diaMes) {
            diaMes.classList.toggle('d-none', p !== 'mensual');
        }
        if (diaSemana) {
            diaSemana.classList.toggle('d-none', p !== 'semanal');
        }
        var rel = periodo ? periodo.value : 'mes_actual';
        if (mesFijo) {
            mesFijo.classList.toggle('d-none', rel !== 'fijo');
        }
    }
    if (periodicidad) {
        periodicidad.addEventListener('change', sync);
    }
    if (periodo) {
        periodo.addEventListener('change', sync);
    }
    sync();

    var descPerfiles = {};
    try {
        descPerfiles = JSON.parse(document.getElementById('frs-perfiles-desc-json').textContent || '{}');
    } catch (e) {
        descPerfiles = {};
    }
    function syncPerfilDesc(selectId, helpId) {
        var sel = document.getElementById(selectId);
        var help = document.getElementById(helpId);
        if (!sel || !help) {
            return;
        }
        function pintar() {
            help.textContent = descPerfiles[sel.value] || '';
        }
        sel.addEventListener('change', pintar);
        pintar();
    }
    syncPerfilDesc('perfil_vista_consulta', 'perfil-vista-consulta-help');
    syncPerfilDesc('frs-perfil-vista', 'frs-perfil-vista-help');
})();
</script>
@endsection

@section('contenido')
@php
    $editando = $suscripcion_editar ?? null;
    $perfilesVistaDesc = $perfiles_vista_desc ?? [];
    $perfilConsulta = $perfil_vista_consulta ?? 'completa';
@endphp
<script type="application/json" id="frs-perfiles-desc-json">@json($perfilesVistaDesc)</script>
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Flash Report AGG</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_caja') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('flash_reporte_agg') }}" class="mb-0" autocomplete="off">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Completa la plantilla oficial (Resumen + Biyemas / Kandiko / Rebisco) con el flash diario
                        del mes. Los totales, promedios y gráficos los calcula el Excel.
                    </p>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2 requerido">Mes / hasta</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <input type="month" name="mes" class="form-control" required value="{{ $mes }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha_hasta" class="form-control" required value="{{ $fecha_hasta }}">
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                Arranca el día 1 del mes y corta en «hasta» (through day).
                                Por defecto usa la <strong>fecha de producción</strong> (ayer): el envío del 31/08 es al 30/08.
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2" for="perfil_vista_consulta">Perfil Excel</label>
                        <div class="col-lg-5">
                            <select name="perfil_vista" id="perfil_vista_consulta" class="form-control">
                                @foreach ($perfiles_vista as $k => $label)
                                    <option value="{{ $k }}" {{ $perfilConsulta === $k ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <small id="perfil-vista-consulta-help" class="form-text text-muted">
                                {{ $perfilesVistaDesc[$perfilConsulta] ?? '' }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row mb-0 mt-3">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            @if (can('exportar-flash-reporte-agg', false))
                                <button type="submit" class="btn btn-success btn-sm" formaction="{{ route('exportar_flash_reporte_agg') }}">
                                    <i class="fa fa-file-excel-o"></i> Descargar Excel
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            @if (!empty($resumen))
                <div class="card-body border-top pt-3">
                    <table class="table table-sm table-bordered mb-0" style="max-width: 520px;">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Empresa</th>
                                <th class="text-right">Días con flash</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resumen as $fila)
                                <tr>
                                    <td>{{ $fila['nombre'] }}</td>
                                    <td class="text-right">{{ $fila['dias'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if (can('administrar-flash-reporte-agg', false))
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Envíos automáticos</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        El informe sale solo por mail el día y la hora que indiques, igual que en reportes definibles.
                        Con <strong>mes en curso</strong>, un envío diario manda el MTD hasta la fecha de producción (ayer).
                        Con <strong>mes anterior</strong>, el envío del día 5 manda el mes cerrado.
                        La vista <strong>Finanzas</strong> incluye vending separado de gastronomía.
                    </p>

                    @if ($editando)
                        <div class="alert alert-info py-2">
                            Editando envío <strong>{{ $editando->nombre }}</strong> (id {{ $editando->id }}).
                            Cambiá los datos y pulsá <strong>Actualizar envío</strong>.
                        </div>
                    @endif

                    <form method="post"
                          action="{{ $editando ? route('actualizar_suscripcion_flash_reporte_agg', $editando->id) : route('guardar_suscripcion_flash_reporte_agg') }}"
                          class="mb-4">
                        @csrf
                        @if ($editando)
                            @method('PUT')
                        @endif
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="small mb-0">Nombre del envío</label>
                                <input type="text" name="nombre" class="form-control form-control-sm" maxlength="160" required
                                       value="{{ old('nombre', $editando->nombre ?? '') }}"
                                       placeholder="Flash diario a Dirección">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small mb-0">Cada cuánto</label>
                                <select name="periodicidad" id="frs-periodicidad" class="form-control form-control-sm">
                                    @foreach ($periodicidades as $k => $label)
                                        <option value="{{ $k }}" {{ old('periodicidad', $editando->periodicidad ?? 'diaria') === $k ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 {{ old('periodicidad', $editando->periodicidad ?? 'diaria') === 'mensual' ? '' : 'd-none' }}" id="frs-dia-mes-wrap">
                                <label class="small mb-0">Día del mes</label>
                                <input type="number" name="dia_mes" class="form-control form-control-sm" min="1" max="28"
                                       value="{{ old('dia_mes', $editando->dia_mes ?? 5) }}">
                            </div>
                            <div class="form-group col-md-2 {{ old('periodicidad', $editando->periodicidad ?? 'diaria') === 'semanal' ? '' : 'd-none' }}" id="frs-dia-semana-wrap">
                                <label class="small mb-0">Día</label>
                                <select name="dia_semana" class="form-control form-control-sm">
                                    @foreach ($dias_semana as $k => $label)
                                        <option value="{{ $k }}" {{ (int) old('dia_semana', $editando->dia_semana ?? 1) === (int) $k ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small mb-0">Hora</label>
                                <input type="time" name="hora" class="form-control form-control-sm"
                                       value="{{ old('hora', $editando->hora ?? '16:00') }}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="small mb-0">Período de cada envío</label>
                                <select name="periodo_relativo" id="frs-periodo" class="form-control form-control-sm">
                                    @foreach ($periodos_relativos as $k => $label)
                                        <option value="{{ $k }}" {{ old('periodo_relativo', $editando->periodo_relativo ?? 'mes_actual') === $k ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3 {{ old('periodo_relativo', $editando->periodo_relativo ?? '') === 'fijo' ? '' : 'd-none' }}" id="frs-mes-fijo-wrap">
                                <label class="small mb-0">Mes fijo</label>
                                <input type="month" name="mes_fijo" class="form-control form-control-sm"
                                       value="{{ old('mes_fijo', $editando->mes_fijo ?? '') }}">
                            </div>
                            <div class="form-group col-md-5">
                                <label class="small mb-0" for="frs-perfil-vista">Perfil de contenido</label>
                                <select name="perfil_vista" id="frs-perfil-vista" class="form-control form-control-sm">
                                    @foreach ($perfiles_vista as $k => $label)
                                        <option value="{{ $k }}" {{ old('perfil_vista', $editando->perfil_vista ?? 'completa') === $k ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small id="frs-perfil-vista-help" class="form-text text-muted">
                                    {{ $perfilesVistaDesc[old('perfil_vista', $editando->perfil_vista ?? 'completa')] ?? '' }}
                                </small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="small mb-0">Mails destino (separados por coma)</label>
                            <textarea name="destinatarios" class="form-control form-control-sm" rows="3" required
                                      placeholder="direccion@empresa.com, gerencia@empresa.com">{{ old('destinatarios', $editando->destinatarios ?? '') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="small mb-0">Mensaje en el cuerpo del mail (opcional)</label>
                            <textarea name="mensaje" class="form-control form-control-sm" rows="2" maxlength="2000">{{ old('mensaje', $editando->mensaje ?? '') }}</textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" class="custom-control-input" id="frs-activo" name="activo" value="1"
                                       {{ (string) old('activo', ($editando->activo ?? true) ? '1' : '0') === '1' ? 'checked' : '' }}>
                                <label class="custom-control-label small" for="frs-activo">Activo</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-save"></i> {{ $editando ? 'Actualizar envío' : 'Guardar envío' }}
                        </button>
                        @if ($editando)
                            <a href="{{ route('flash_reporte_agg') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                        @endif
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="min-width:110px;">Envío</th>
                                    <th style="min-width:80px;">Perfil</th>
                                    <th style="min-width:120px;">Cuándo</th>
                                    <th style="min-width:140px;">Período</th>
                                    <th style="min-width:260px;">Destinatarios</th>
                                    <th style="min-width:160px;">Última corrida</th>
                                    <th style="width:220px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suscripciones as $s)
                                    @php
                                        $mailsDest = preg_split('/[;,\s]+/', (string) ($s->destinatarios ?? '')) ?: [];
                                        $mailsDest = array_values(array_filter(array_map('trim', $mailsDest)));
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $s->nombre }}
                                            @if (! $s->activo)
                                                <span class="badge badge-secondary">inactivo</span>
                                            @endif
                                        </td>
                                        <td>{{ $perfiles_vista[$s->perfil_vista ?? 'completa'] ?? ($s->perfil_vista ?? 'completa') }}</td>
                                        <td>{{ $s->periodicidadTexto() }}</td>
                                        <td>{{ $periodos_relativos[$s->periodo_relativo] ?? $s->periodo_relativo }}</td>
                                        <td style="white-space:normal;word-break:break-word;">
                                            @foreach ($mailsDest as $mail)
                                                <div class="small">{{ $mail }}</div>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if ($s->ultima_ejecucion)
                                                {{ $s->ultima_ejecucion->format('d/m/Y H:i') }}
                                                <br><small class="text-muted">{{ $s->ultimo_estado }} — {{ $s->ultimo_mensaje }}</small>
                                                @if ($s->esperaReintentoSmtp())
                                                    <br><small class="text-warning">Si Office 365 no respondió, se reintenta solo a los {{ $s->minutosReintentoSmtp() }} min.</small>
                                                @endif
                                            @else
                                                <span class="text-muted">Nunca</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('flash_reporte_agg', ['editar' => $s->id]) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                            <form method="post" action="{{ route('probar_suscripcion_flash_reporte_agg', $s->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-primary btn-sm">Probar</button>
                                            </form>
                                            <form method="post" action="{{ route('eliminar_suscripcion_flash_reporte_agg', $s->id) }}" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar este envío?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Borrar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-muted">Todavía no hay envíos automáticos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0">
                        El cron <code>flash:distribuir-reportes</code> corre cada 15 minutos. «Probar» manda el mail ahora
                        con la configuración guardada.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
