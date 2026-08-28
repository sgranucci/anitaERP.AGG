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
})();
</script>
@endsection

@section('contenido')
@php
    $editando = $suscripcion_editar ?? null;
@endphp
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
                        <label class="col-lg-2 control-label text-right requerido">Mes / hasta</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <input type="month" name="mes" class="form-control" required value="{{ $mes }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha_hasta" class="form-control" required value="{{ $fecha_hasta }}">
                                </div>
                            </div>
                            <small class="form-text text-muted">Arranca el día 1 del mes y corta en «hasta» (through day).</small>
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
                        Con <strong>mes en curso</strong>, un envío diario manda el MTD hasta hoy.
                        Con <strong>mes anterior</strong>, el envío del día 5 manda el mes cerrado.
                    </p>

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
                                <label class="small mb-0">Mails destino (separados por coma)</label>
                                <input type="text" name="destinatarios" class="form-control form-control-sm" required
                                       value="{{ old('destinatarios', $editando->destinatarios ?? '') }}"
                                       placeholder="direccion@empresa.com, gerencia@empresa.com">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label class="small mb-0">Mensaje en el cuerpo del mail (opcional)</label>
                                <textarea name="mensaje" class="form-control form-control-sm" rows="2" maxlength="2000">{{ old('mensaje', $editando->mensaje ?? '') }}</textarea>
                            </div>
                            <div class="form-group col-md-4">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="hidden" name="activo" value="0">
                                    <input type="checkbox" class="custom-control-input" id="frs-activo" name="activo" value="1"
                                           {{ (string) old('activo', ($editando->activo ?? true) ? '1' : '0') === '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label small" for="frs-activo">Activo</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-save"></i> {{ $editando ? 'Actualizar envío' : 'Guardar envío' }}
                        </button>
                        @if ($editando)
                            <a href="{{ route('flash_reporte_agg') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                        @endif
                    </form>

                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Envío</th>
                                <th>Cuándo</th>
                                <th>Período</th>
                                <th>Destinatarios</th>
                                <th>Última corrida</th>
                                <th style="width:220px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suscripciones as $s)
                                <tr>
                                    <td>
                                        {{ $s->nombre }}
                                        @if (! $s->activo)
                                            <span class="badge badge-secondary">inactivo</span>
                                        @endif
                                    </td>
                                    <td>{{ $s->periodicidadTexto() }}</td>
                                    <td>{{ $periodos_relativos[$s->periodo_relativo] ?? $s->periodo_relativo }}</td>
                                    <td>{{ $s->destinatarios }}</td>
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
                                    <td>
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
                                    <td colspan="6" class="text-muted">Todavía no hay envíos automáticos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">
                        El cron <code>flash:distribuir-reportes</code> corre cada hora. «Probar» manda el mail ahora
                        con la configuración guardada.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
