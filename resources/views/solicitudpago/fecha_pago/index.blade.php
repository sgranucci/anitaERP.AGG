@extends("theme.$theme.layout")
@section('titulo')
    Fecha de pago
@endsection

@section('scripts')
<script>
(function () {
    function filasMarcadas() {
        return Array.prototype.slice.call(document.querySelectorAll('.sp-check:checked'));
    }
    function fechaDe(chk) {
        var tr = chk.closest('tr');
        return tr ? tr.querySelector('.sp-fecha') : null;
    }
    function sincronizarGrupo(grupo) {
        var checks = document.querySelectorAll('.sp-check[data-grupo="' + grupo + '"]');
        var maestro = document.querySelector('.sp-grupo[data-grupo="' + grupo + '"]');
        if (!maestro) return;
        var marcados = 0;
        checks.forEach(function (c) { if (c.checked) marcados++; });
        maestro.checked = checks.length > 0 && marcados === checks.length;
        maestro.indeterminate = marcados > 0 && marcados < checks.length;
    }
    function actualizarResumen() {
        var conFecha = 0;
        var sinFecha = 0;
        document.querySelectorAll('.sp-fecha').forEach(function (input) {
            if (input.value) conFecha++;
            else sinFecha++;
        });
        var el = document.getElementById('sp-resumen-fechas');
        if (el) {
            el.textContent = conFecha + ' con fecha de pago · ' + sinFecha + ' sin fecha';
        }
    }
    var todas = document.getElementById('sp-check-todas');
    if (todas) {
        todas.addEventListener('change', function () {
            document.querySelectorAll('.sp-check, .sp-grupo').forEach(function (c) {
                c.checked = todas.checked;
                c.indeterminate = false;
            });
        });
    }
    document.querySelectorAll('.sp-grupo').forEach(function (maestro) {
        maestro.addEventListener('change', function () {
            document.querySelectorAll('.sp-check[data-grupo="' + maestro.dataset.grupo + '"]').forEach(function (c) {
                c.checked = maestro.checked;
            });
            maestro.indeterminate = false;
        });
    });
    document.querySelectorAll('.sp-check').forEach(function (chk) {
        chk.addEventListener('change', function () {
            sincronizarGrupo(chk.dataset.grupo);
        });
    });
    var aplicar = document.getElementById('btn-aplicar-fecha');
    if (aplicar) {
        aplicar.addEventListener('click', function () {
            var fecha = document.getElementById('fecha_pago_masiva').value;
            if (!fecha) {
                window.alert('Indique la fecha de pago a aplicar.');
                return;
            }
            var marcadas = filasMarcadas();
            if (marcadas.length === 0) {
                window.alert('Marque al menos una solicitud.');
                return;
            }
            marcadas.forEach(function (chk) {
                var input = fechaDe(chk);
                if (input) input.value = fecha;
            });
            actualizarResumen();
        });
    }
    var copiar = document.getElementById('btn-copiar-vencimiento');
    if (copiar) {
        copiar.addEventListener('click', function () {
            var marcadas = filasMarcadas();
            if (marcadas.length === 0) {
                window.alert('Marque al menos una solicitud.');
                return;
            }
            marcadas.forEach(function (chk) {
                var input = fechaDe(chk);
                if (input && chk.dataset.vencimiento) input.value = chk.dataset.vencimiento;
            });
            actualizarResumen();
        });
    }
    var limpiar = document.getElementById('btn-limpiar-fecha');
    if (limpiar) {
        limpiar.addEventListener('click', function () {
            var marcadas = filasMarcadas();
            if (marcadas.length === 0) {
                window.alert('Marque al menos una solicitud.');
                return;
            }
            marcadas.forEach(function (chk) {
                var input = fechaDe(chk);
                if (input) input.value = '';
            });
            actualizarResumen();
        });
    }
    document.querySelectorAll('.sp-fecha').forEach(function (input) {
        input.addEventListener('change', actualizarResumen);
    });
    actualizarResumen();
})();
</script>
@endsection

@section('contenido')
@php
    $f = $filtros;
    $grupos = $filas->groupBy(fn ($solicitud) => optional($solicitud->fecha_vencimiento)->toDateString() ?: '');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Fecha de pago de solicitudes</h3>
                <div class="card-tools">
                    @if (can('listar-solicitud-pago', false))
                        <a href="{{ route('consultar_solicitudpago') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver a solicitudes de pago
                        </a>
                    @endif
                </div>
            </div>
            @include('solicitudpago.fecha_pago.partials.filtros_empresa')
            <div class="card-body">
                <p class="text-muted mb-3">
                    Lista las solicitudes <strong>autorizadas</strong> (listas para pagar), agrupadas por vencimiento.
                    La fecha que grabe es el día en que salen en la <strong>posición bancaria diaria</strong>.
                    Sin fecha de pago, la solicitud no entra en la posición.
                </p>
                <form method="get" action="{{ route('programar_fecha_pago_solicitud') }}" class="form-row align-items-end">
                    <input type="hidden" name="consultar" value="1">
                    @if (\App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips())
                        @if (($f['empresa_scope'] ?? 'una') === 'todas')
                            <input type="hidden" name="empresa_todas" value="1">
                        @elseif ((int) $f['empresa_id'] > 0)
                            <input type="hidden" name="empresa_id" value="{{ $f['empresa_id'] }}">
                        @endif
                    @else
                        <div class="form-group col-md-2">
                            <label for="empresa_id">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control">
                                <option value="0">Todas</option>
                                @foreach ($empresa_query as $empresa)
                                    <option value="{{ $empresa->id }}" @selected((int) $f['empresa_id'] === (int) $empresa->id)>{{ $empresa->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group col-md-2">
                        <label for="vencimiento_desde">Vencimiento desde</label>
                        <input type="date" class="form-control" id="vencimiento_desde" name="vencimiento_desde" value="{{ $f['vencimiento_desde'] }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="vencimiento_hasta">Vencimiento hasta</label>
                        <input type="date" class="form-control" id="vencimiento_hasta" name="vencimiento_hasta" value="{{ $f['vencimiento_hasta'] }}" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-control">
                            <option value="autorizadas" @selected($f['estado'] === 'autorizadas')>Autorizadas</option>
                            <option value="pagadas" @selected($f['estado'] === 'pagadas')>Pagadas</option>
                            <option value="ambas" @selected($f['estado'] === 'ambas')>Autorizadas y pagadas</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="asignacion">Fecha de pago</label>
                        <select name="asignacion" id="asignacion" class="form-control">
                            <option value="sin_fecha" @selected($f['asignacion'] === 'sin_fecha')>Sin fecha</option>
                            <option value="con_fecha" @selected($f['asignacion'] === 'con_fecha')>Con fecha</option>
                            <option value="todas" @selected($f['asignacion'] === 'todas')>Todas</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="incluir_vencidas" name="incluir_vencidas" value="1" @checked($f['incluir_vencidas'])>
                            <label class="custom-control-label" for="incluir_vencidas">Incluir vencidas sin fecha</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-search"></i> Buscar
                        </button>
                    </div>
                </form>
                @php
                    $queryReporte = [
                        'consultar' => 1,
                        'vencimiento_desde' => $f['vencimiento_desde'],
                        'vencimiento_hasta' => $f['vencimiento_hasta'],
                        'estado' => $f['estado'],
                        'asignacion' => $f['asignacion'],
                    ];
                    if (! empty($f['incluir_vencidas'])) {
                        $queryReporte['incluir_vencidas'] = 1;
                    }
                    if (($f['empresa_scope'] ?? '') === 'todas') {
                        $queryReporte['empresa_todas'] = 1;
                    } elseif ((int) $f['empresa_id'] > 0) {
                        $queryReporte['empresa_id'] = $f['empresa_id'];
                    }
                @endphp
                <div class="mt-2">
                    <a href="{{ route('reporte_fecha_pago_solicitud', ['formato' => 'EXCEL'] + $queryReporte) }}" class="btn btn-success btn-sm">
                        <i class="fa fa-file-excel"></i> Excel impagas
                    </a>
                    <a href="{{ route('reporte_fecha_pago_solicitud', ['formato' => 'PDF'] + $queryReporte) }}" class="btn btn-danger btn-sm">
                        <i class="fa fa-file-pdf"></i> PDF impagas
                    </a>
                    <span class="text-muted small ml-2">Impagas con fecha de pago, por empresa y ordenadas por esa fecha. Mismo formato que el informe de solicitudes.</span>
                </div>
            </div>
        </div>

        <form method="post" action="{{ route('grabar_fecha_pago_solicitud') }}" id="form-fecha-pago">
            @csrf
            <input type="hidden" name="consultar" value="1">
            <input type="hidden" name="vencimiento_desde" value="{{ $f['vencimiento_desde'] }}">
            <input type="hidden" name="vencimiento_hasta" value="{{ $f['vencimiento_hasta'] }}">
            @if (($f['empresa_scope'] ?? 'una') === 'todas')
                <input type="hidden" name="empresa_todas" value="1">
            @elseif ((int) $f['empresa_id'] > 0)
                <input type="hidden" name="empresa_id" value="{{ $f['empresa_id'] }}">
            @endif
            <input type="hidden" name="estado" value="{{ $f['estado'] }}">
            <input type="hidden" name="asignacion" value="{{ $f['asignacion'] }}">
            @if ($f['incluir_vencidas'])
                <input type="hidden" name="incluir_vencidas" value="1">
            @endif

            <div class="card card-outline card-secondary">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div class="form-inline mr-3 mb-1">
                        <label for="fecha_pago_masiva" class="mr-2 mb-0">Fecha de pago</label>
                        <input type="date" class="form-control" id="fecha_pago_masiva" value="{{ $hoy }}">
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mr-1 mb-1" id="btn-aplicar-fecha">
                        Aplicar a las marcadas
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm mr-1 mb-1" id="btn-copiar-vencimiento">
                        Copiar vencimiento
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm mr-3 mb-1" id="btn-limpiar-fecha">
                        Quitar fecha
                    </button>
                    <button type="submit" class="btn btn-success btn-sm mb-1">
                        <i class="fa fa-save"></i> Grabar fechas
                    </button>
                    <span class="text-muted small ml-auto mb-1" id="sp-resumen-fechas"></span>
                </div>
                <div class="card-body p-0 table-responsive">
                    @if ($truncado)
                        <div class="alert alert-warning m-3">
                            Se muestran las primeras {{ \App\Support\Solicitudpago\SolicitudpagoFechaPagoSupport::LIMITE }} solicitudes. Acote el vencimiento para ver el resto.
                        </div>
                    @endif
                    @if ($filas->isEmpty())
                        <div class="alert alert-secondary m-3 mb-3">No hay solicitudes para ese vencimiento.</div>
                    @else
                        @if ($totales !== [])
                            <div class="px-3 pt-3 small text-muted">
                                {{ $filas->count() }} solicitud(es)
                                @foreach ($totales as $moneda => $importe)
                                    · {{ $moneda }} {{ number_format($importe, 2, ',', '.') }}
                                @endforeach
                            </div>
                        @endif
                        <table class="table table-sm table-hover table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:36px">
                                        <input type="checkbox" id="sp-check-todas" title="Marcar todas">
                                    </th>
                                    <th>Código</th>
                                    <th>Empresa</th>
                                    <th>Vencimiento</th>
                                    <th>Fecha de pago</th>
                                    <th>Concepto</th>
                                    <th>Forma de pago</th>
                                    <th>Proveedor / Beneficiario</th>
                                    <th>Detalle</th>
                                    <th>Moneda</th>
                                    <th class="text-right">Monto</th>
                                    <th>Estado</th>
                                    <th class="width120 text-nowrap"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grupos as $vencimiento => $grupo)
                                    @php
                                        $fechaGrupo = $vencimiento !== '' ? \Carbon\Carbon::parse($vencimiento) : null;
                                        $vencida = $fechaGrupo && $fechaGrupo->toDateString() < $hoy;
                                    @endphp
                                    <tr class="table-light">
                                        <td>
                                            <input type="checkbox" class="sp-grupo" data-grupo="{{ $vencimiento }}" title="Marcar este vencimiento">
                                        </td>
                                        <td colspan="12">
                                            <strong>Vencimiento {{ $fechaGrupo ? $fechaGrupo->format('d/m/Y') : '—' }}</strong>
                                            <span class="text-muted">· {{ $grupo->count() }} solicitud(es)</span>
                                            @if ($vencida)
                                                <span class="badge badge-warning text-dark ml-1">Vencida</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @foreach ($grupo as $solicitud)
                                        @php
                                            $fechaPago = $solicitud->fecha_pago?->toDateString() ?? '';
                                            $nombreProveedor = optional($solicitud->proveedores)->nombre ?: ($solicitud->beneficiario ?: '—');
                                        @endphp
                                        <tr class="{{ $fechaPago === '' && $vencida ? 'table-warning' : '' }}">
                                            <td>
                                                <input type="checkbox"
                                                       class="sp-check"
                                                       value="{{ $solicitud->id }}"
                                                       data-grupo="{{ $vencimiento }}"
                                                       data-vencimiento="{{ $vencimiento }}">
                                            </td>
                                            <td>{{ $solicitud->codigo }}</td>
                                            <td>{{ optional($solicitud->empresas)->nombre ?? '—' }}</td>
                                            <td>{{ $fechaGrupo ? $fechaGrupo->format('d/m/Y') : '—' }}</td>
                                            <td>
                                                <input type="date"
                                                       class="form-control form-control-sm sp-fecha"
                                                       name="fechas[{{ $solicitud->id }}]"
                                                       value="{{ $fechaPago }}">
                                            </td>
                                            <td>{{ optional($solicitud->conceptos)->nombre ?? '—' }}</td>
                                            <td>{{ optional($solicitud->formapagosol)->nombre ?? '—' }}</td>
                                            <td>{{ $nombreProveedor }}</td>
                                            <td>{{ $solicitud->detalle ?: '—' }}</td>
                                            <td>{{ optional($solicitud->monedas)->abreviatura ?? '—' }}</td>
                                            <td class="text-right">{{ number_format((float) $solicitud->monto, 2, ',', '.') }}</td>
                                            <td>
                                                @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $solicitud->estado])
                                            </td>
                                            <td class="text-nowrap">
                                                @if (can('editar-solicitud-pago', false))
                                                    <a href="{{ route('editar_solicitudpago', $solicitud->id) }}?vista=consulta"
                                                       class="btn-accion-tabla tooltipsC"
                                                       target="_blank" rel="noopener"
                                                       title="Editar">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                @if ($filas->isNotEmpty())
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> Grabar fechas
                        </button>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
