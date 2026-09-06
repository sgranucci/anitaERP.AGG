@extends("theme.$theme.layout")
@section('titulo')
    Reportes de suscripciones
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }
        if (typeof activa_eventos_consulta_cuentacontable === 'function') {
            activa_eventos_consulta_cuentacontable();
        }
    });
})();
</script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\SuscripcionSupport;
    $qs = array_filter($filtros, fn ($v) => $v !== null && $v !== '');
    $estadoActivo = (string) ($filtros['estado'] ?? '');
    $urlEstado = function (string $estado) use ($qs) {
        $q = $qs;
        unset($q['estado']);
        if ($estado !== '') {
            $q['estado'] = $estado;
        }

        return route('reporte_suscripcion', $q);
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="row">
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Vigentes</span>
                        <span class="info-box-number">{{ $indicadores['vigentes'] }}</span>
                        <span class="progress-description text-muted">{{ $indicadores['total'] }} en la base</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Mensualizado</span>
                        <span class="info-box-number">{{ number_format($indicadores['mensualizado'], 2, ',', '.') }}</span>
                        <span class="progress-description text-muted">{{ number_format($indicadores['anualizado'], 0, ',', '.') }} al año</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Cobertura</span>
                        <span class="info-box-number">{{ number_format((float) $indicadores['cobertura_pct'], 1, ',', '.') }}%</span>
                        <span class="progress-description text-muted">período {{ $indicadores['cobertura_periodo'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-box bg-light mb-3">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Sin dueño</span>
                        <span class="info-box-number {{ $indicadores['sin_dueno'] > 0 ? 'text-danger' : '' }}">{{ $indicadores['sin_dueno'] }}</span>
                        <span class="progress-description text-muted">{{ $indicadores['desvios'] }} en desvío</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Base de suscripciones</h3>
                <div class="card-tools">
                    @include('includes.compras.boton-manual-suscripciones')
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm ml-1">← Suscripciones</a>
                </div>
            </div>

            @include('compras.suscripcion.partials.filtros_externos', [
                'rutaNombre' => 'reporte_suscripcion',
                'empresa_query' => $empresa_query,
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'filtrosQuery' => $qs,
            ])

            <div class="card-body py-2 border-bottom">
                <div class="btn-group btn-group-sm flex-wrap mb-2" role="group" aria-label="Estado">
                    <a href="{{ $urlEstado('') }}" class="btn {{ $estadoActivo === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">Todos</a>
                    @foreach ($estados as $val => $label)
                        <a href="{{ $urlEstado($val) }}"
                           class="btn {{ $estadoActivo === $val ? 'btn-info' : 'btn-outline-info' }}">{{ $label }}</a>
                    @endforeach
                </div>

                <form method="get" action="{{ route('reporte_suscripcion') }}" class="mb-0" id="form-filtros-reporte-suscripcion" autocomplete="off">
                    @if (! empty($filtros['empresa_id']))
                        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $filtros['empresa_id'] }}">
                    @endif
                    @if (! empty($filtros['estado']))
                        <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                    @endif
                    @php
                        $ccFiltro = $centrocosto_filtro ?? null;
                        $ctaFiltro = $cuenta_filtro ?? null;
                        $ccIdFiltro = (int) ($filtros['centrocosto_id'] ?? 0);
                        $ctaIdFiltro = (int) ($filtros['cuentacontable_id'] ?? 0);
                    @endphp
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 mb-0">
                            <label class="small mb-1">Centro de costo</label>
                            <div class="tm-centrocosto-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                <input type="hidden" name="centrocosto_id" id="centrocosto_id" class="centrocosto_id"
                                       value="{{ $ccIdFiltro ?: '' }}">
                                <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" name="centrocosto_codigo" id="centrocosto_codigo"
                                       class="form-control form-control-sm codigocentrocosto"
                                       value="{{ old('centrocosto_codigo', optional($ccFiltro)->codigo) }}"
                                       placeholder="Cód." autocomplete="off" style="width:5rem;flex-shrink:0;">
                                <input type="text" name="centrocosto_nombre" id="centrocosto_descripcion"
                                       class="form-control form-control-sm descripcioncentrocosto"
                                       value="{{ old('centrocosto_nombre', optional($ccFiltro)->nombre) }}"
                                       placeholder="Todos" readonly style="min-width:0;flex:1 1 auto;">
                            </div>
                        </div>
                        <div class="form-group col-md-5 mb-0">
                            <label class="small mb-1">Cuenta contable</label>
                            <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                <input type="hidden" name="cuentacontable_id" id="contrato_cuentacontable_id"
                                       class="cuentacontable_id" value="{{ $ctaIdFiltro ?: '' }}">
                                <button type="button" title="Consulta cuenta contable (F1)" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" name="cuentacontable_codigo" id="contrato_cuentacontable_codigo"
                                       class="codigocuentacontable form-control form-control-sm"
                                       value="{{ old('cuentacontable_codigo', optional($ctaFiltro)->codigo) }}"
                                       placeholder="Cód." autocomplete="off" style="width:6rem;flex-shrink:0;">
                                <input type="text" name="cuentacontable_nombre" id="contrato_cuentacontable_nombre"
                                       class="nombrecuentacontable form-control form-control-sm"
                                       value="{{ old('cuentacontable_nombre', optional($ctaFiltro)->nombre) }}"
                                       placeholder="Todas" readonly style="min-width:0;flex:1 1 auto;">
                            </div>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">Filtrar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'exportar_reporte_suscripcion',
                    'queryparams' => $qs,
                ])
                <table class="table table-sm table-striped table-bordered table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Suscripción</th>
                            <th>Proveedor</th>
                            <th>Área</th>
                            <th>CC</th>
                            <th>Cuenta</th>
                            <th>Dueño</th>
                            <th class="text-right">Mensualizado</th>
                            <th>Estado</th>
                            <th>Vence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $oc)
                            @php $est = SuscripcionSupport::estadoNegocio($oc); @endphp
                            <tr>
                                <td>{{ $oc->suscripcion_nombre ?: $oc->detalle }}</td>
                                <td>{{ optional($oc->proveedores)->nombre }}</td>
                                <td>{{ $oc->suscripcion_area }}</td>
                                <td>{{ optional($oc->centrocostos)->codigo }}</td>
                                <td class="small">{{ optional($oc->contrato_cuentacontables)->codigo }}</td>
                                <td>{{ optional($oc->suscripcion_owners)->nombre ?: '—' }}</td>
                                <td class="text-right">
                                    {{ number_format(SuscripcionSupport::montoMensualizado((float) $oc->suscripcion_monto_periodo, $oc->suscripcion_periodicidad), 2, ',', '.') }}
                                </td>
                                <td><span class="{{ SuscripcionSupport::clasePillEstado($est) }}">{{ SuscripcionSupport::etiquetaEstado($est) }}</span></td>
                                <td>{{ $oc->contrato_vigencia_hasta ? $oc->contrato_vigencia_hasta->format('d/m/Y') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Sin resultados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Compromiso recurrente contra presupuesto {{ date('Y') }}</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Centro de costo</th>
                            <th>Cuenta contable</th>
                            <th class="text-center">Suscr.</th>
                            <th class="text-right">Mensual</th>
                            <th class="text-right">Anualizado</th>
                            <th class="text-right">Presupuesto anual</th>
                            <th class="text-right">% tomado</th>
                            <th class="text-right">Disponible / mes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($compromiso as $c)
                            <tr>
                                <td>{{ $c['centrocosto'] }}</td>
                                <td class="small">{{ $c['cuenta'] }}</td>
                                <td class="text-center">{{ $c['cantidad'] }}</td>
                                <td class="text-right">{{ number_format($c['mensualizado'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($c['anualizado'], 2, ',', '.') }}</td>
                                <td class="text-right">
                                    @if ($c['presupuesto_anual'] !== null)
                                        {{ number_format($c['presupuesto_anual'], 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">sin partida</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($c['pct'] !== null)
                                        <span class="{{ \App\Support\Compras\SuscripcionPresupuestoSupport::clasePct($c['pct']) }}">
                                            {{ number_format($c['pct'], 1, ',', '.') }}%
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($c['disponible_mensual'] !== null)
                                        <span class="{{ $c['disponible_mensual'] < 0 ? 'text-danger' : '' }}">
                                            {{ number_format($c['disponible_mensual'], 2, ',', '.') }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-3">Sin suscripciones vigentes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2 small text-muted">
                Qué proporción del presupuesto anual de cada cuenta ya está tomada por gasto recurrente.
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card card-info">
                    <div class="card-header"><h3 class="card-title">Gasto por área</h3></div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead style="background:#85C1E9;color:#17202A;"><tr><th>Área</th><th class="text-center">Cant.</th><th class="text-right">Mensual</th></tr></thead>
                            <tbody>
                                @forelse ($por_area as $a)
                                    <tr>
                                        <td>{{ $a['area'] }}</td>
                                        <td class="text-center">{{ $a['cantidad'] }}</td>
                                        <td class="text-right">{{ number_format($a['mensualizado'], 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center py-3">Sin datos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-info">
                    <div class="card-header"><h3 class="card-title">Vencen en 60 días</h3></div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead style="background:#85C1E9;color:#17202A;"><tr><th>Suscripción</th><th>Dueño</th><th>Vence</th></tr></thead>
                            <tbody>
                                @forelse ($proximas as $oc)
                                    <tr>
                                        <td><a href="{{ route('ver_suscripcion', $oc->id) }}">{{ $oc->suscripcion_nombre }}</a></td>
                                        <td class="small">{{ optional($oc->suscripcion_owners)->nombre ?: '—' }}</td>
                                        <td class="text-nowrap">{{ $oc->contrato_vigencia_hasta->format('d/m/Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center py-3">Nada por vencer.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-info">
                    <div class="card-header"><h3 class="card-title">Gasto sin orden</h3></div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead style="background:#85C1E9;color:#17202A;"><tr><th>Comercio</th><th class="text-center">Meses</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                                @forelse ($sin_orden as $s)
                                    <tr>
                                        <td class="small">{{ $s->comercio_normalizado }}</td>
                                        <td class="text-center">{{ $s->apariciones }}</td>
                                        <td class="text-right">{{ number_format((float) $s->total, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center py-3">Todo el gasto tiene orden.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer py-2 small text-muted">
                        Comercios recurrentes sin suscripción detrás.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.contable.modalconsultacentrocosto')
@include('includes.contable.modalconsultacuentacontable')
@endsection
