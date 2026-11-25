@extends("theme.$theme.layout")
@section('titulo')
    Facturas por canal
@endsection

@section('contenido')
    @php
        $periodo = $periodo ?? ($reporte['periodo'] ?? []);
        $porCanal = $reporte['por_canal'] ?? [];
        $ia = $reporte['decisiones_ia'] ?? [];
        $erroresRec = $reporte['errores_recepcion'] ?? ['total' => 0, 'por_origen' => []];
        $pct = static function (?float $tasa): string {
            return $tasa === null ? '—' : number_format($tasa * 100, 1).'%';
        };
        $heroClaves = ['agente_api', 'scan', 'ia_anita'];
        $boxClass = [
            'agente_api' => 'bg-success',
            'scan' => 'bg-info',
            'ia_anita' => 'bg-primary',
        ];
    @endphp
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Facturas por canal — reporte predefinido</h3>
                    <div class="card-tools">
                        @if (can('listar-ai-decisiones', false))
                            <a href="{{ route('ai_decision') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fa fa-arrow-left"></i> Gobernanza IA
                            </a>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">
                            <i class="fa fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Resumen listo para operación: cuántas precargas entraron por <strong>agente externo</strong>,
                        <strong>scan</strong> e <strong>IA Anita</strong>, más errores de recepción y de la skill de factura.
                        El período por defecto es <strong>últimos 30 días</strong>.
                    </p>

                    <div class="mb-3 d-flex flex-wrap align-items-center" style="gap:.5rem;">
                        <span class="mr-1 font-weight-bold">Período rápido:</span>
                        @foreach ($presets as $clave => $etiqueta)
                            <a href="{{ route('ai_decision_facturas_canal', ['preset' => $clave]) }}"
                               class="btn btn-sm {{ ($periodo['preset'] ?? '') === $clave ? 'btn-primary' : 'btn-outline-primary' }}">
                                {{ $etiqueta }}
                            </a>
                        @endforeach
                    </div>

                    <form method="get" action="{{ route('ai_decision_facturas_canal') }}" class="mb-4">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 mb-2">
                                <label for="fecha_desde">Desde</label>
                                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                       value="{{ $periodo['fecha_desde'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label for="fecha_hasta">Hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                       value="{{ $periodo['fecha_hasta'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> Consultar
                                </button>
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <div class="border rounded px-3 py-2 bg-light">
                                    <strong>{{ (int) ($reporte['total_precargas'] ?? 0) }}</strong> precargas
                                    · {{ \Carbon\Carbon::parse($periodo['fecha_desde'] ?? now())->format('d/m/Y') }}
                                    → {{ \Carbon\Carbon::parse($periodo['fecha_hasta'] ?? now())->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                    </form>

                    <h5 class="mb-2">Ingresos por canal</h5>
                    <div class="row mb-3">
                        @foreach ($heroClaves as $clave)
                            @php $canal = $porCanal[$clave] ?? null; @endphp
                            @if ($canal)
                                <div class="col-md-4 col-12 mb-2">
                                    <div class="small-box {{ $boxClass[$clave] ?? 'bg-secondary' }} mb-0">
                                        <div class="inner p-3">
                                            <h3 class="mb-0">{{ (int) $canal['total'] }}</h3>
                                            <p class="mb-1">{{ $canal['etiqueta'] }}</p>
                                            <p class="mb-0 small" style="opacity:.9;">
                                                Pend. {{ (int) $canal['pendientes'] }}
                                                · Gen. {{ (int) $canal['generadas'] }}
                                                · Error {{ (int) $canal['con_error'] }}
                                                · Revisar {{ (int) $canal['para_revisar'] }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-muted small mt-1 mb-0">{{ $canal['ayuda'] }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4 col-12 mb-2">
                            <div class="small-box bg-danger mb-0">
                                <div class="inner p-3">
                                    <h3 class="mb-0">{{ (int) ($erroresRec['total'] ?? 0) }}</h3>
                                    <p class="mb-0">Errores de recepción</p>
                                </div>
                            </div>
                            <p class="text-muted small mt-1 mb-0">
                                Fallos al ingresar (API / mail / batch / PDF IA), aunque no haya quedado precarga.
                            </p>
                        </div>
                        <div class="col-md-4 col-12 mb-2">
                            <div class="small-box bg-warning mb-0">
                                <div class="inner p-3">
                                    <h3 class="mb-0">{{ (int) ($ia['errores'] ?? 0) }}</h3>
                                    <p class="mb-0">Errores skill factura IA</p>
                                </div>
                            </div>
                            <p class="text-muted small mt-1 mb-0">
                                Decisiones <code>extraer_factura_proveedor</code> con acción error.
                            </p>
                        </div>
                        <div class="col-md-4 col-12 mb-2">
                            <div class="small-box bg-secondary mb-0">
                                <div class="inner p-3">
                                    <h3 class="mb-0">{{ $pct($ia['tasa_aceptacion'] ?? null) }}</h3>
                                    <p class="mb-0">Aceptación IA factura</p>
                                </div>
                            </div>
                            <p class="text-muted small mt-1 mb-0">
                                (confirmadas + editadas + auto) / resueltas de la skill.
                            </p>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-lg-7 mb-3">
                            <h5 class="mb-2">Detalle por origen</h5>
                            @if (($reporte['por_origen'] ?? []) === [])
                                <div class="alert alert-info mb-0">No hubo precargas en el período.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Origen</th>
                                                <th class="text-right">Total</th>
                                                <th class="text-right">Pendientes</th>
                                                <th class="text-right">Generadas</th>
                                                <th class="text-right">Anita</th>
                                                <th class="text-right">Anuladas</th>
                                                <th class="text-right">Para revisar</th>
                                                <th class="text-right">Con error</th>
                                                <th class="text-center">Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($reporte['por_origen'] as $fila)
                                                @php
                                                    $detalleUrl = route('ai_decision_facturas_canal_detalle', array_filter([
                                                        'origen' => $fila['origen'],
                                                        'fecha_desde' => $periodo['fecha_desde'] ?? null,
                                                        'fecha_hasta' => $periodo['fecha_hasta'] ?? null,
                                                        'preset' => $periodo['preset'] ?? null,
                                                    ], static fn ($v) => $v !== null && $v !== ''));
                                                @endphp
                                                <tr>
                                                    <td>
                                                        {{ $fila['etiqueta'] }}
                                                        <small class="text-muted">({{ $fila['origen'] }})</small>
                                                    </td>
                                                    <td class="text-right">{{ $fila['total'] }}</td>
                                                    <td class="text-right">{{ $fila['pendientes'] }}</td>
                                                    <td class="text-right">{{ $fila['generadas'] }}</td>
                                                    <td class="text-right">{{ $fila['cargadas_anita'] }}</td>
                                                    <td class="text-right">{{ $fila['anuladas'] }}</td>
                                                    <td class="text-right">{{ $fila['para_revisar'] }}</td>
                                                    <td class="text-right">{{ $fila['con_error'] }}</td>
                                                    <td class="text-center">
                                                        @if ((int) $fila['total'] > 0)
                                                            <a href="{{ $detalleUrl }}"
                                                               class="btn btn-xs btn-primary"
                                                               title="Ver las {{ (int) $fila['total'] }} precargas de este origen">
                                                                <i class="fa fa-list"></i> Ver
                                                            </a>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        <div class="col-lg-5 mb-3">
                            <h5 class="mb-2">Skill factura (Gobernanza IA)</h5>
                            <table class="table table-sm table-bordered mb-3">
                                <tbody>
                                    <tr><th>Total decisiones</th><td class="text-right">{{ (int) ($ia['total'] ?? 0) }}</td></tr>
                                    <tr><th>Confirmadas</th><td class="text-right">{{ (int) ($ia['confirmadas'] ?? 0) }}</td></tr>
                                    <tr><th>Editadas</th><td class="text-right">{{ (int) ($ia['editadas'] ?? 0) }}</td></tr>
                                    <tr><th>Auto-aplicadas</th><td class="text-right">{{ (int) ($ia['auto_aplicadas'] ?? 0) }}</td></tr>
                                    <tr><th>Descartadas</th><td class="text-right">{{ (int) ($ia['descartadas'] ?? 0) }}</td></tr>
                                    <tr><th>Pendientes</th><td class="text-right">{{ (int) ($ia['pendientes'] ?? 0) }}</td></tr>
                                    <tr><th>Errores</th><td class="text-right">{{ (int) ($ia['errores'] ?? 0) }}</td></tr>
                                    <tr><th>Aceptación</th><td class="text-right">{{ $pct($ia['tasa_aceptacion'] ?? null) }}</td></tr>
                                </tbody>
                            </table>

                            <h5 class="mb-2">Errores de recepción por origen</h5>
                            @if ((int) ($erroresRec['total'] ?? 0) === 0)
                                <div class="alert alert-success mb-0">Sin errores de recepción en el período.</div>
                            @else
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#F5B7B1;color:#17202A;">
                                        <tr>
                                            <th>Origen</th>
                                            <th class="text-right">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($erroresRec['por_origen'] as $origenErr => $cant)
                                            <tr>
                                                <td>{{ \App\Support\Compras\PrecargaComprobanteOrigenEntrada::etiqueta($origenErr) }}
                                                    <small class="text-muted">({{ $origenErr }})</small>
                                                </td>
                                                <td class="text-right">{{ $cant }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>

                    @php
                        $otrosCanales = collect($porCanal)->except($heroClaves)->filter(fn ($c) => (int) ($c['total'] ?? 0) > 0);
                    @endphp
                    @if ($otrosCanales->isNotEmpty())
                        <h5 class="mb-2">Otros canales</h5>
                        <div class="row mb-2">
                            @foreach ($otrosCanales as $clave => $canal)
                                <div class="col-md-3 col-6 mb-2">
                                    <div class="border rounded p-2 h-100">
                                        <div class="font-weight-bold">{{ $canal['etiqueta'] }}</div>
                                        <div class="h4 mb-0">{{ (int) $canal['total'] }}</div>
                                        <div class="text-muted small">{{ $canal['ayuda'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
