@extends("theme.$theme.layout")
@section('titulo')
    Conciliación {{ $conciliacion->periodo }}
@endsection

@section('contenido')
@php
    use App\Support\Compras\SuscripcionSupport;
    $abierta = $conciliacion->abierta();
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="row">
            <div class="col-6 col-md-3">
                <div class="info-box bg-light">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Cobertura del período</span>
                        <span class="info-box-number">{{ number_format($resumen['cobertura_pct'], 1, ',', '.') }}%</span>
                        <span class="progress-description text-muted">gasto con orden detrás</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Conciliados</span>
                        <span class="info-box-number">{{ $resumen['conciliados'] }}</span>
                        <span class="progress-description text-muted">{{ number_format($resumen['monto_conciliado'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Desvíos</span>
                        <span class="info-box-number text-warning">{{ $resumen['desvios'] }}</span>
                        <span class="progress-description text-muted">{{ $resumen['en_reaprobacion'] }} en re-aprobación</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Sin identificar</span>
                        <span class="info-box-number text-danger">{{ $resumen['sin_identificar'] }}</span>
                        <span class="progress-description text-muted">{{ number_format($resumen['monto_sin_identificar'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $conciliacion->etiquetaPeriodo() }} · {{ optional($conciliacion->empresas)->nombre }}
                    <span class="badge badge-{{ $abierta ? 'warning' : 'success' }} ml-2">{{ $abierta ? 'Abierta' : 'Cerrada' }}</span>
                </h3>
                <div class="card-tools">
                    <a href="{{ route('conciliacion_suscripcion') }}" class="btn btn-outline-light btn-sm">← Períodos</a>
                </div>
            </div>

            @if ($abierta)
                <div class="card-body border-bottom">
                    <div class="row">
                        <div class="col-md-7">
                            <form method="post" action="{{ route('importar_conciliacion_suscripcion', $conciliacion->id) }}" enctype="multipart/form-data" class="form-inline">
                                @csrf
                                <input type="file" name="archivo" class="form-control-file mr-2" required
                                       accept=".csv,.txt,.xls,.xlsx,.ods">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> Importar resumen</button>
                            </form>
                            <small class="form-text text-muted">
                                CSV, XLS, XLSX u ODS con columnas <code>fecha</code>, <code>comercio</code>,
                                <code>monto</code> y, si está, <code>tarjeta</code>. El separador del CSV se
                                detecta solo. Reimportar el mismo archivo no duplica cargos.
                            </small>
                        </div>
                        <div class="col-md-5 text-right">
                            <form method="post" action="{{ route('rematchear_conciliacion_suscripcion', $conciliacion->id) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa fa-magic"></i> Volver a cruzar</button>
                            </form>
                            @if (can('imputar-suscripcion', false))
                                <form method="post" action="{{ route('imputar_conciliacion_suscripcion', $conciliacion->id) }}" class="d-inline"
                                      onsubmit="return confirm('¿Generar los movimientos de Ingresos y egresos de los cargos conciliados?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa fa-calculator"></i> Imputar conciliados</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('cerrar_conciliacion_suscripcion', $conciliacion->id) }}" class="d-inline"
                                  onsubmit="return confirm('¿Cerrar el período? No se van a poder tocar más los cargos.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm"><i class="fa fa-lock"></i> Cerrar período</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card-body pb-2 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <div class="btn-group btn-group-sm mb-1">
                        @foreach ($periodos_empresa as $p)
                            <a href="{{ route('ver_conciliacion_suscripcion', ['id' => $p->id, 'estado' => $estado]) }}"
                               class="btn btn-{{ (int) $p->id === (int) $conciliacion->id ? 'primary' : 'outline-secondary' }}">
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $p->periodo)->locale('es')->isoFormat('MMM YYYY') }}
                                @if ($p->estado === 'CERRADA')
                                    <i class="fa fa-lock" title="Cerrada"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    <div class="d-flex align-items-center mb-1">
                        @php $qsExport = ['id' => $conciliacion->id, 'estado' => $estado, 'q' => $busqueda]; @endphp
                        <div class="btn-group btn-group-sm mr-2">
                            <a href="{{ route('exportar_conciliacion_suscripcion', $qsExport + ['formato' => 'PDF']) }}"
                               class="btn btn-outline-secondary" title="PDF"><i class="fa fa-file-pdf-o"></i></a>
                            <a href="{{ route('exportar_conciliacion_suscripcion', $qsExport + ['formato' => 'EXCEL']) }}"
                               class="btn btn-outline-secondary" title="XLS"><i class="fa fa-file-excel-o"></i></a>
                            <a href="{{ route('exportar_conciliacion_suscripcion', $qsExport + ['formato' => 'CSV']) }}"
                               class="btn btn-outline-secondary" title="CSV"><i class="fa fa-file-text-o"></i></a>
                        </div>
                        <form method="get" action="{{ route('ver_conciliacion_suscripcion', $conciliacion->id) }}" class="form-inline">
                            <input type="hidden" name="estado" value="{{ $estado }}">
                            <div class="input-group input-group-sm">
                                <input type="text" name="q" class="form-control" value="{{ $busqueda }}" placeholder="Buscar comercio…">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-outline-secondary"><i class="fa fa-search"></i></button>
                                    @if ($busqueda !== '')
                                        <a href="{{ route('ver_conciliacion_suscripcion', ['id' => $conciliacion->id, 'estado' => $estado]) }}"
                                           class="btn btn-outline-secondary" title="Limpiar">&times;</a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <ul class="nav nav-pills nav-sm">
                    @php
                        $solapas = [
                            '' => 'Todos ('.$resumen['cargos'].')',
                            'CONCILIADO' => 'Conciliados ('.$resumen['conciliados'].')',
                            'DESVIO' => 'Desvíos ('.$resumen['desvios'].')',
                            'PENDIENTE_APROBACION' => 'En re-aprobación ('.$resumen['en_reaprobacion'].')',
                            'SIN_IDENTIFICAR' => 'Sin identificar ('.$resumen['sin_identificar'].')',
                            'REGULARIZAR' => 'A regularizar ('.$resumen['a_regularizar'].')',
                        ];
                    @endphp
                    @foreach ($solapas as $valor => $etiqueta)
                        <li class="nav-item">
                            <a class="nav-link py-1 px-2 {{ $estado === $valor ? 'active' : '' }}"
                               href="{{ route('ver_conciliacion_suscripcion', ['id' => $conciliacion->id, 'estado' => $valor, 'q' => $busqueda]) }}">{{ $etiqueta }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comercio</th>
                                <th>Tarjeta</th>
                                <th class="text-right">Importe</th>
                                <th>Suscripción</th>
                                <th class="text-right">Desvío</th>
                                <th>Estado</th>
                                <th style="width:22%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cargos as $cargo)
                                <tr>
                                    <td class="text-nowrap">{{ optional($cargo->fecha)->format('d/m/Y') }}</td>
                                    <td>
                                        {{ $cargo->comercio }}
                                        @if ($cargo->origen_match)
                                            <small class="text-muted d-block">cruce {{ strtolower($cargo->origen_match) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">••{{ $cargo->tarjeta_ult4 ?: '····' }}</td>
                                    <td class="text-right">{{ number_format((float) $cargo->monto, 2, ',', '.') }}</td>
                                    <td>
                                        @if ($cargo->ordencompras)
                                            <a href="{{ route('ver_suscripcion', $cargo->ordencompra_id) }}">
                                                {{ $cargo->ordencompras->suscripcion_nombre }}
                                            </a>
                                            <small class="text-muted d-block">{{ optional($cargo->ordencompras->proveedores)->nombre }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($cargo->desvio_pct !== null)
                                            <span class="{{ $cargo->desvio_pct > 0 ? 'text-danger' : 'text-muted' }}">
                                                {{ $cargo->desvio_pct > 0 ? '+' : '' }}{{ number_format((float) $cargo->desvio_pct, 2, ',', '.') }}%
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="{{ SuscripcionSupport::clasePillEstadoCargo($cargo->estado) }}">
                                            {{ SuscripcionSupport::etiquetaEstadoCargo($cargo->estado) }}
                                        </span>
                                        @if ($cargo->imputado())
                                            <span class="badge badge-light" title="Imputado en Ingresos y egresos"><i class="fa fa-calculator"></i></span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($abierta)
                                            @if (! $cargo->ordencompra_id)
                                                <button type="button" class="btn btn-xs btn-outline-primary btn-asociar"
                                                        data-cargo="{{ $cargo->id }}" data-comercio="{{ $cargo->comercio }}"
                                                        data-monto="{{ number_format((float) $cargo->monto, 2, ',', '.') }}">
                                                    Asociar a OC
                                                </button>
                                                <form method="post" action="{{ route('marcar_cargo_suscripcion', $cargo->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="estado" value="REGULARIZAR">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Gasto real sin orden">Regularizar</button>
                                                </form>
                                                <form method="post" action="{{ route('marcar_cargo_suscripcion', $cargo->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="estado" value="DESCARTADO">
                                                    <button type="submit" class="btn btn-xs btn-outline-secondary" title="No es una suscripción">Descartar</button>
                                                </form>
                                            @else
                                                @if ($cargo->estado === 'DESVIO')
                                                    <form method="post" action="{{ route('revalidar_cargo_suscripcion', $cargo->id) }}" class="d-inline"
                                                          onsubmit="return confirm('¿Mandar el desvío al gerente del sector?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-xs btn-warning">Enviar a revalidar</button>
                                                    </form>
                                                @endif
                                                @if (! $cargo->imputado())
                                                    <form method="post" action="{{ route('desasociar_cargo_suscripcion', $cargo->id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-xs btn-outline-secondary">Desasociar</button>
                                                    </form>
                                                @endif
                                            @endif
                                        @else
                                            <span class="text-muted small">Período cerrado</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">No hay cargos con ese filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-asociar" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" id="form-asociar">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asociar cargo a una suscripción</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        <strong id="modal-comercio"></strong>
                        <span class="text-muted" id="modal-monto"></span>
                    </p>

                    <div id="modal-sugerencias" class="mb-3"></div>

                    <div class="form-group">
                        <label>Suscripción</label>
                        <select name="ordencompra_id" id="modal-oc" class="form-control" required>
                            <option value="">Seleccionar…</option>
                            @foreach ($suscripciones as $s)
                                <option value="{{ $s->id }}">
                                    {{ $s->suscripcion_nombre }}
                                    @if ($s->numeroordencompra) · OC {{ $s->numeroordencompra }} @endif
                                    · {{ number_format((float) $s->suscripcion_monto_periodo, 2, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="chk-alias" name="aprender_alias" value="1" checked>
                        <label class="custom-control-label" for="chk-alias">
                            Recordar este comercio para los próximos períodos
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Asociar</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const modal = $('#modal-asociar');
    const form = document.getElementById('form-asociar');
    const selOc = document.getElementById('modal-oc');
    const contSug = document.getElementById('modal-sugerencias');
    const plantillaAccion = @json(route('asociar_cargo_suscripcion', ['cargoId' => '__ID__']));
    const plantillaSugerencias = @json(route('sugerencias_cargo_suscripcion', ['cargoId' => '__ID__']));

    document.querySelectorAll('.btn-asociar').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const id = this.dataset.cargo;
            form.action = plantillaAccion.replace('__ID__', id);
            document.getElementById('modal-comercio').textContent = this.dataset.comercio;
            document.getElementById('modal-monto').textContent = ' · ' + this.dataset.monto;
            selOc.value = '';
            contSug.innerHTML = '<span class="text-muted small">Buscando coincidencias…</span>';
            modal.modal('show');

            fetch(plantillaSugerencias.replace('__ID__', id))
                .then((r) => r.json())
                .then(function (data) {
                    if (!data.sugerencias.length) {
                        contSug.innerHTML = '<span class="text-muted small">Sin coincidencias automáticas. Elegí la suscripción a mano.</span>';
                        return;
                    }
                    contSug.innerHTML = '<div class="list-group list-group-flush border rounded">' +
                        data.sugerencias.map(function (s) {
                            return '<button type="button" class="list-group-item list-group-item-action py-1 sug" data-id="' + s.id + '">' +
                                '<strong>' + s.nombre + '</strong> <span class="text-muted">· ' + s.proveedor + '</span>' +
                                '<span class="badge badge-info float-right">' + s.puntaje + '</span>' +
                                '<small class="d-block text-muted">' + s.motivo + '</small>' +
                                '</button>';
                        }).join('') + '</div>';

                    contSug.querySelectorAll('.sug').forEach(function (item) {
                        item.addEventListener('click', function () {
                            selOc.value = this.dataset.id;
                            contSug.querySelectorAll('.sug').forEach((s) => s.classList.remove('active'));
                            this.classList.add('active');
                        });
                    });
                })
                .catch(function () {
                    contSug.innerHTML = '<span class="text-danger small">No se pudieron traer las sugerencias.</span>';
                });
        });
    });
})();
</script>
@endsection
