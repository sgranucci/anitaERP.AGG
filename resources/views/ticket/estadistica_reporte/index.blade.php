@extends("theme.$theme.layout")
@section('titulo')
    Informe estadístico de tickets
@endsection

@section('scripts')
<script>
    (function () {
        var overlay = document.getElementById('ticket-estadistica-overlay');
        function mostrar() {
            if (!overlay) return;
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }
        function ocultar() {
            if (!overlay) return;
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }
        var form = document.getElementById('form-informe-estadistico-ticket');
        if (form) {
            form.addEventListener('submit', function () {
                if (form.checkValidity()) {
                    mostrar();
                }
            });
        }
        document.querySelectorAll('a[href*="listar-informe-estadistico-ticket"]').forEach(function (a) {
            a.addEventListener('click', function () { mostrar(); });
        });
        window.addEventListener('pageshow', ocultar);
    })();
</script>
@endsection

@section('contenido')
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ticket\TicketEstadisticaReporteFiltros;
    $tot = $totales ?? [];
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
@endphp
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ticket-estadistica-overlay',
    'tituloId' => 'ticket-estadistica-titulo',
    'subtituloId' => 'ticket-estadistica-subtitulo',
    'titulo' => 'Generando informe…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe estadístico de tickets — Área Tecnología</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Analiza las tareas del área: tiempo insumido, demora en asignar y demora en resolver.
                    Si filtra por <strong>técnico</strong>, los minutos son solo los de ese técnico.
                    Si filtra por <strong>sala</strong> o solo por <strong>fechas</strong>, los minutos son el total del ticket.
                </p>
                <form method="get" action="{{ route('informe_estadistico_ticket') }}" id="form-informe-estadistico-ticket" class="mb-0">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2">
                            <label for="fecha_desde">Fecha desde</label>
                            <input type="date" class="form-control" name="fecha_desde" id="fecha_desde"
                                   value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="fecha_hasta">Fecha hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" id="fecha_hasta"
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="criterio_fecha">Período según</label>
                            <select name="criterio_fecha" id="criterio_fecha" class="form-control">
                                <option value="{{ TicketEstadisticaReporteFiltros::CRITERIO_FECHA_ALTA }}"
                                    @selected(($filtros['criterio_fecha'] ?? '') === TicketEstadisticaReporteFiltros::CRITERIO_FECHA_ALTA)>
                                    Fecha de alta
                                </option>
                                <option value="{{ TicketEstadisticaReporteFiltros::CRITERIO_FECHA_RESOLUCION }}"
                                    @selected(($filtros['criterio_fecha'] ?? '') === TicketEstadisticaReporteFiltros::CRITERIO_FECHA_RESOLUCION)>
                                    Fecha de resoluci&oacute;n
                                </option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="sala_id">Sala</label>
                            <select name="sala_id" id="sala_id" class="form-control">
                                <option value="">Todas</option>
                                @foreach ($sala_query ?? [] as $sala)
                                    <option value="{{ $sala->id }}" @selected((int) ($filtros['sala_id'] ?? 0) === (int) $sala->id)>
                                        {{ $sala->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="tecnico_id">T&eacute;cnico</label>
                            <select name="tecnico_id" id="tecnico_id" class="form-control">
                                <option value="">Todos (total del ticket)</option>
                                @foreach ($tecnico_query ?? [] as $tecnico)
                                    <option value="{{ $tecnico->id }}" @selected((int) ($filtros['tecnico_id'] ?? 0) === (int) $tecnico->id)>
                                        {{ $tecnico->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($estado_enum ?? [] as $estadoItem)
                                    <option value="{{ $estadoItem['nombre'] }}" @selected(($filtros['estado'] ?? '') === $estadoItem['nombre'])>
                                        {{ $estadoItem['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12 mb-0">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <a href="{{ route('informe_estadistico_ticket') }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            @if ($consultado ?? false)
            <div class="card-body p-0 border-top">
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_informe_estadistico_ticket',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small text-md-right">
                        <span class="text-muted">{{ $subtitulo ?? '' }}</span>
                    </div>
                </div>
                <div class="px-3 py-2 small border-bottom">
                    <strong>{{ (int) ($tot['cantidad'] ?? 0) }}</strong> tickets
                    · Insumido <strong>{{ $tot['suma_insumido_fmt'] ?? '0' }}</strong> min
                    (prom. {{ $tot['promedio_insumido_fmt'] ?? '0' }})
                    · Asignar prom. <strong>{{ ($tot['promedio_asignacion_fmt'] ?? '') !== '' ? $tot['promedio_asignacion_fmt'] : '—' }}</strong>
                    ({{ (int) ($tot['cantidad_con_asignacion'] ?? 0) }} con asignaci&oacute;n)
                    · Resolver prom. <strong>{{ ($tot['promedio_resolucion_fmt'] ?? '') !== '' ? $tot['promedio_resolucion_fmt'] : '—' }}</strong>
                    ({{ (int) ($tot['cantidad_con_resolucion'] ?? 0) }} finalizados)
                </div>
                @if (! empty($logos))
                <div class="px-3 pt-2">
                    @foreach ($logos as $logo)
                        <img src="{{ is_array($logo) ? ($logo['uri'] ?? '') : $logo }}" alt="logo" style="height:40px;margin-right:8px;">
                    @endforeach
                </div>
                @endif
                <div class="table-responsive">
                    @include('ticket.estadistica_reporte.partials.tabla_datos', [
                        'filas' => $filas,
                        'es_export' => false,
                        'modo_tiempo' => $modo_tiempo ?? 'ticket',
                        'puede_ver_ticket' => $puede_ver_ticket ?? false,
                    ])
                </div>
            </div>
            @if (method_exists($filas, 'links'))
            <div class="card-footer clearfix">
                <div class="float-left">
                    @if ($filas->total() > 0)
                        Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                    @endif
                </div>
                <div class="float-right">
                    {{ $filas->appends($filtrosQuery ?? [])->links() }}
                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
</div>
@endsection
