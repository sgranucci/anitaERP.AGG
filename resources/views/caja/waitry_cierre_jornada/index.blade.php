@extends("theme.$theme.layout")
@section('titulo')
    Cierre jornada Waitry
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/waitry_cierre_jornada/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/waitry_cierre_jornada/index.js')) ?: time() }}" type="text/javascript"></script>
@if ($puede_proceso_cierre ?? false)
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script>
    window.WAITRY_CIERRE_JORNADA_PROCESO = {
        csrf: @json(csrf_token()),
        puedeProceso: true,
        urlAnalizar: @json(route('waitry_cierre_jornada_api_proceso_analizar')),
        urlRecalcular: @json(route('waitry_cierre_jornada_api_proceso_recalcular')),
        urlPreviewFactura: @json(route('waitry_cierre_jornada_api_proceso_preview_factura')),
        urlMovimientosBase: @json($url_movimientos_proceso_base ?? ''),
        urlCuadroDetalleBase: @json(url('caja/waitry-cierre-jornada/api/proceso/cuadro-detalle/__FILA__/__MEDIO__')),
        urlConfigBase: @json(url('caja/waitry-cierre-jornada/api/proceso/config/__EMPRESA_ID__')),
        urlConfigGuardarBase: @json(url('caja/waitry-cierre-jornada/api/proceso/config/__EMPRESA_ID__')),
        configInicial: @json($config_contable ?? []),
    };
</script>
<script src="{{ asset('assets/pages/scripts/caja/waitry_cierre_jornada_proceso.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/waitry_cierre_jornada_proceso.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cierre de jornada Waitry</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Concilia las órdenes de Waitry (<code>getordersdetails</code>) con las ventas facturadas en Anita
                    para la <strong>fecha de jornada</strong> (<code>venta.fechajornada</code>).
                    Proceso de tesorería/auditoría bajo <strong>Caja → Rendiciones</strong>; el POS en vivo usa <code>getOrdersPOS</code>.
                </p>

                <form method="get" action="{{ route('waitry_cierre_jornada') }}" class="form-inline mb-4">
                    <label class="mr-2" for="empresa_id">Empresa</label>
                    <select name="empresa_id" id="empresa_id" class="form-control mr-3" required>
                        @foreach ($empresas as $emp)
                            <option value="{{ $emp->id }}" @selected((int) $empresa_id === (int) $emp->id)>
                                {{ $emp->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <label class="mr-2" for="fecha_jornada">Fecha jornada</label>
                    <input type="date" name="fecha_jornada" id="fecha_jornada" class="form-control mr-3"
                           value="{{ $fecha_jornada }}" required>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </form>

                @if ($error)
                    <div class="alert alert-danger">{{ $error }}</div>
                @endif

                @if ($consultado && $payload && ($payload['ok'] ?? false))
                    @php
                        $resumen = $payload['resumen'] ?? [];
                        $metaConciliacion = $payload['meta_conciliacion'] ?? [];
                    @endphp

                    @if (! empty($metaConciliacion))
                        <div class="alert alert-secondary py-2 mb-3">
                            <p class="mb-1 small">
                                <strong>Consulta Waitry:</strong>
                                {{ $metaConciliacion['waitry_rango_etiqueta'] ?? '' }}
                            </p>
                            <p class="mb-1 small">
                                <strong>Cruce Anita:</strong>
                                {{ $metaConciliacion['anita_criterio'] ?? '' }}
                            </p>
                            @if (! empty($metaConciliacion['ventana_jornada_etiqueta']))
                                <p class="mb-0 small">
                                    <strong>Jornada gastronomía (apertura — cierre):</strong>
                                    {{ $metaConciliacion['ventana_jornada_etiqueta'] }}
                                </p>
                            @endif
                        </div>
                    @endif

                    @if (! empty($payload['jornada']))
                        <div class="alert alert-info py-2">
                            Jornada Anita #{{ $payload['jornada']['id'] }}
                            — estado: <strong>{{ $payload['jornada']['estado'] }}</strong>
                            @if (! empty($payload['jornada']['apertura_en']))
                                · apertura {{ $payload['jornada']['apertura_en'] }}
                            @endif
                            @if (! empty($payload['jornada']['cierre_en']))
                                · cierre {{ $payload['jornada']['cierre_en'] }}
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning py-2">
                            No hay registro de jornada gastronomía en Anita para esta empresa y fecha.
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-shopping-cart"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Órdenes Waitry</span>
                                    <span class="info-box-number">{{ $resumen['ordenes_waitry'] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-file-text"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Facturas Anita (jornada)</span>
                                    <span class="info-box-number">{{ $resumen['facturas_anita_jornada'] ?? ($resumen['facturas_anita_waitry'] ?? 0) }}</span>
                                    <span class="info-box-text small text-muted">
                                        con Waitry: {{ $resumen['facturas_anita_waitry'] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-check-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Anita facturado</span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['total_anita_facturado'] ?? 0), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light">
                                <span class="info-box-icon"><i class="fa fa-money"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Waitry</span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['total_waitry'] ?? 0), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box {{ ($resumen['tiene_diferencias'] ?? false) ? 'bg-warning' : 'bg-success' }}">
                                <span class="info-box-icon"><i class="fa fa-balance-scale"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Dif. global</span>
                                    <span class="info-box-number">${{ number_format((float) ($resumen['diferencia_global'] ?? 0), 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <span class="badge badge-success mr-1">Conciliadas: {{ $resumen['conciliadas'] ?? 0 }}</span>
                        <span class="badge badge-danger mr-1">Sin factura: {{ $resumen['sin_factura_anita'] ?? 0 }}</span>
                        <span class="badge badge-warning mr-1">Importadas pendientes: {{ $resumen['importadas_pendientes'] ?? 0 }}</span>
                        <span class="badge badge-info mr-1">Monto distinto: {{ $resumen['monto_distinto'] ?? 0 }}</span>
                        <span class="badge badge-primary mr-1">Medio distinto: {{ $resumen['medio_distinto'] ?? 0 }}</span>
                        <span class="badge badge-secondary mr-1">Solo Anita (día W.): {{ $resumen['solo_anita'] ?? 0 }}</span>
                        <span class="badge badge-danger mr-1">Anita sin Waitry ID: {{ $resumen['anita_sin_waitry'] ?? 0 }}</span>
                        <span class="badge badge-dark">Otra jornada Anita: {{ $resumen['jornada_distinta'] ?? 0 }}</span>
                        @if (($resumen['waitry_canceladas_cantidad'] ?? 0) > 0)
                            <span class="badge badge-light border text-muted" title="Excluidas del cuadro y totales operativos">
                                Waitry canceladas: {{ $resumen['waitry_canceladas_cantidad'] }}
                                (${{ number_format((float) ($resumen['waitry_canceladas_total'] ?? 0), 2, ',', '.') }})
                            </span>
                        @endif
                    </div>

                    @if (! empty($resumen['por_medio_waitry']))
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Medio Waitry</th>
                                        <th>Cuenta caja esperada</th>
                                        <th class="text-right">Cantidad</th>
                                        <th class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($resumen['por_medio_waitry'] as $medio)
                                        <tr>
                                            <td>{{ $medio['etiqueta'] ?? '—' }}</td>
                                            <td>{{ $medio['cuentacaja_label'] ?? '—' }}</td>
                                            <td class="text-right">{{ (int) ($medio['cantidad'] ?? 0) }}</td>
                                            <td class="text-right">${{ number_format((float) ($medio['total'] ?? 0), 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @php
                        $exportQuery = http_build_query([
                            'empresa_id' => $empresa_id,
                            'fecha_jornada' => $fecha_jornada,
                        ]);
                    @endphp
                    <div class="mb-2">
                        @php
                            $puedePdfWaitry = \App\Support\Caja\RendicionGastronomiaPdfPermiso::puedeVerPdfWaitry();
                        @endphp
                        @if ($puedePdfWaitry)
                            <a href="{{ route('listar_waitry_cierre_jornada', ['formato' => 'PDF']) }}?{{ $exportQuery }}" class="btn btn-app bg-danger">
                                <i class="fas fa-file-pdf"></i> Pdf
                            </a>
                        @else
                            <a href="javascript:void(0)" class="btn btn-app bg-danger disabled" aria-disabled="true" title="Sin permiso para exportar PDF (ver-pdf-waitry-gastronomia-caja)">
                                <i class="fas fa-file-pdf"></i> Pdf
                            </a>
                            <small class="text-muted d-block">
                                Sin permiso para exportar PDF de cierre Waitry. Use Excel/CSV o solicite el permiso <code>ver-pdf-waitry-gastronomia-caja</code>.
                            </small>
                        @endif
                        <a href="{{ route('listar_waitry_cierre_jornada', ['formato' => 'EXCEL']) }}?{{ $exportQuery }}" class="btn btn-app bg-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('listar_waitry_cierre_jornada', ['formato' => 'CSV']) }}?{{ $exportQuery }}" class="btn btn-app bg-warning">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover table-sm" id="tabla-waitry-conciliacion">
                            <thead>
                                <tr>
                                    <th>Orden Waitry</th>
                                    <th>Ref.</th>
                                    <th>Fecha/hora Waitry</th>
                                    <th class="text-right">Total Waitry</th>
                                    <th>Pagada W.</th>
                                    <th>Venta Anita</th>
                                    <th class="text-right">Total Anita</th>
                                    <th>Medio Waitry</th>
                                    <th>Cta. caja esp.</th>
                                    <th>Cta. caja Anita</th>
                                    <th class="text-right">Diferencia</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($payload['filas'] ?? [] as $fila)
                                    @php
                                        $estadoClass = match ($fila['estado']) {
                                            'conciliada' => 'success',
                                            'monto_distinto' => 'info',
                                            'medio_distinto' => 'primary',
                                            'importada_pendiente' => 'warning',
                                            'sin_factura_anita' => 'danger',
                                            'jornada_distinta', 'jornada_distinta_monto' => 'dark',
                                            'solo_anita' => 'secondary',
                                            'anita_sin_waitry' => 'danger',
                                            default => 'light',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $fila['waitry_order_id'] ?: '—' }}</td>
                                        <td>
                                            {{ $fila['referencia_waitry'] ?: '—' }}
                                            @if (! empty($fila['waitry_order_id']) && ($fila['referencia_waitry'] ?? '') !== '#'.$fila['waitry_order_id'])
                                                <br><small class="text-muted">#{{ $fila['waitry_order_id'] }}</small>
                                            @endif
                                            @if (empty($fila['waitry_en_listado_dia']))
                                                <br><small class="text-info">Fuera del listado Waitry del día</small>
                                            @endif
                                        </td>
                                        <td data-order="{{ $fila['placed_at'] ?? '' }}">{{ $fila['fecha_hora_waitry'] ?: ($fila['hora_waitry'] ?: '—') }}</td>
                                        <td class="text-right">
                                            @if ($fila['waitry_total'] !== null)
                                                ${{ number_format((float) $fila['waitry_total'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if ($fila['waitry_paid'] === null)
                                                —
                                            @elseif ($fila['waitry_paid'])
                                                <span class="badge badge-success">Sí</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $fila['anita_codigo'] ?? ($fila['anita_venta_id'] ? '#'.$fila['anita_venta_id'] : '—') }}
                                            @if (! empty($fila['anita_fechajornada_fmt']) && ($fila['estado'] ?? '') !== 'conciliada' && str_starts_with((string) ($fila['estado'] ?? ''), 'jornada_distinta'))
                                                <br><small class="text-muted">Jorn. Anita {{ $fila['anita_fechajornada_fmt'] }}</small>
                                            @endif
                                            @if (in_array($fila['estado'] ?? '', ['anita_sin_waitry', 'solo_anita'], true) || empty($fila['waitry_en_listado_dia']))
                                                <br><small class="{{ ($fila['estado'] ?? '') === 'anita_sin_waitry' ? 'text-danger' : 'text-muted' }}">
                                                    @if (($fila['estado'] ?? '') === 'anita_sin_waitry')
                                                        KDS:
                                                    @else
                                                        Comanda:
                                                    @endif
                                                    {{ $fila['waitry_comanda_estado'] ?? 'sin envío' }}
                                                    @if (! empty($fila['waitry_comanda_error']))
                                                        — {{ \Illuminate\Support\Str::limit($fila['waitry_comanda_error'], 80) }}
                                                    @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($fila['anita_total'] !== null)
                                                ${{ number_format((float) $fila['anita_total'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (! empty($fila['waitry_medio_label']) && $fila['waitry_medio_label'] !== '—')
                                                <span class="badge badge-light">{{ $fila['waitry_medio_label'] }}</span>
                                            @elseif ($fila['anita_totem'])
                                                <span class="badge badge-primary">TOTEM</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $fila['cuentacaja_esperada_label'] ?? '—' }}</td>
                                        <td>{{ $fila['anita_cuentacaja_label'] ?? '—' }}</td>
                                        <td class="text-right">
                                            @if ($fila['diferencia'] !== null)
                                                ${{ number_format((float) $fila['diferencia'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td><span class="badge badge-{{ $estadoClass }}">{{ $fila['estado_label'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center text-muted">Sin órdenes Waitry ni ventas Anita para esta jornada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($consultado && ! $error)
                    <div class="alert alert-info">No hay datos para mostrar.</div>
                @endif

                @include('caja.waitry_cierre_jornada.partials.proceso_cierre')
            </div>
        </div>
    </div>
</div>
@endsection
