@php
    $origenLabel = match ($registro->origen) {
        'import_anita' => 'Anita (histórico)',
        'automatico' => 'Automático',
        'manual' => 'Manual',
        default => $registro->origen,
    };
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0">
        <tbody>
            <tr>
                <th class="text-nowrap" style="width:38%">Empresa</th>
                <td>{{ $registro->empresa->nombre ?? '—' }}</td>
            </tr>
            <tr>
                <th>CUIT</th>
                <td>{{ $registro->cuit }}</td>
            </tr>
            <tr>
                <th>Periodo / quincena</th>
                <td>{{ $registro->periodo }} / Q{{ $registro->orden }}</td>
            </tr>
            <tr>
                <th>CAEA</th>
                <td><code>{{ $registro->nro_caea ?? '—' }}</code></td>
            </tr>
            <tr>
                <th>Estado</th>
                <td>
                    @if ($registro->estado === 'ok')
                        <span class="badge badge-success">OK</span>
                    @elseif ($registro->estado === 'observacion')
                        <span class="badge badge-warning">Observaciones</span>
                    @elseif ($registro->estado === 'error')
                        <span class="badge badge-danger">Error</span>
                    @else
                        <span class="badge badge-secondary">{{ $registro->estado }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Vigencia</th>
                <td>
                    @if ($registro->fecha_vigencia_desde && $registro->fecha_vigencia_hasta)
                        {{ $registro->fecha_vigencia_desde->format('d/m/Y') }}
                        al {{ $registro->fecha_vigencia_hasta->format('d/m/Y') }}
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <th>Tope informe</th>
                <td>{{ $registro->fecha_tope_informe?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            <tr>
                <th>Proceso ARCA</th>
                <td>{{ $registro->fecha_proceso?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            <tr>
                <th>Origen</th>
                <td>{{ $origenLabel }}</td>
            </tr>
            <tr>
                <th>Solicitado por</th>
                <td>{{ $registro->solicitadoPor->nombre ?? '—' }}</td>
            </tr>
            @if ($registro->mensaje_error)
                <tr>
                    <th class="text-danger">Error</th>
                    <td class="text-danger small">{{ $registro->mensaje_error }}</td>
                </tr>
            @endif
            @if ($registro->observaciones)
                <tr>
                    <th>Observaciones ARCA</th>
                    <td class="small">{{ $registro->observaciones['texto'] ?? json_encode($registro->observaciones) }}</td>
                </tr>
            @endif
            <tr>
                <th>Actualizado</th>
                <td>{{ $registro->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
            </tr>
            @if ($registro->estaAutorizado() && is_array($resumenInforme ?? null))
                <tr>
                    <th>Informe quincenal</th>
                    <td>
                        @php $infEstado = $registro->informe_estado ?? 'pendiente'; @endphp
                        @if ($infEstado === 'ok')
                            <span class="badge badge-success">Completo</span>
                        @elseif ($infEstado === 'observacion')
                            <span class="badge badge-warning">Con observaciones</span>
                        @elseif ($infEstado === 'parcial')
                            <span class="badge badge-info">Parcial</span>
                        @elseif ($infEstado === 'error')
                            <span class="badge badge-danger">Con errores</span>
                        @else
                            <span class="badge badge-secondary">Pendiente</span>
                        @endif
                        <div class="mt-1 small">
                            Total comprobantes CAEA: {{ (int) ($resumenInforme['total'] ?? 0) }} —
                            OK: {{ (int) ($resumenInforme['informados_ok'] ?? 0) }},
                            Obs.: {{ (int) ($resumenInforme['informados_obs'] ?? 0) }},
                            Pend.: {{ (int) ($resumenInforme['pendientes'] ?? 0) }},
                            Err.: {{ (int) ($resumenInforme['errores'] ?? 0) }}
                        </div>
                        @if (! empty($resumenInforme['por_tipo_pv']))
                            <table class="table table-sm table-bordered mt-2 mb-0">
                                <thead>
                                    <tr>
                                        <th>PV</th>
                                        <th>Tipo AFIP</th>
                                        <th>Último informado ERP</th>
                                        <th>Último en ARCA</th>
                                        <th>Fecha informe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($resumenInforme['por_tipo_pv'] as $pt)
                                        <tr>
                                            <td>{{ str_pad((string) ($pt['pto_vta'] ?? ''), 5, '0', STR_PAD_LEFT) }}</td>
                                            <td>{{ $pt['tipo_afip'] ?? '—' }} ({{ $pt['letra'] ?? '' }})</td>
                                            <td>
                                                @if (! empty($pt['ultimo_numero']))
                                                    #{{ $pt['ultimo_numero'] }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if (isset($pt['ultimo_arca']) && $pt['ultimo_arca'] !== null)
                                                    #{{ $pt['ultimo_arca'] }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if (! empty($pt['ultimo_informado_at']))
                                                    {{ \Carbon\Carbon::parse($pt['ultimo_informado_at'])->format('d/m/Y H:i') }}
                                                @elseif (! empty($pt['ultimo_arca_consultado_at']))
                                                    <span class="text-muted">Consulta ARCA {{ \Carbon\Carbon::parse($pt['ultimo_arca_consultado_at'])->format('d/m/Y H:i') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @if (! empty($resumenInforme['errores_consulta_arca']))
                            <div class="alert alert-warning small py-2 mt-2 mb-0">
                                <strong>No se pudo consultar último autorizado en ARCA:</strong>
                                <ul class="mb-0 pl-3">
                                    @foreach ($resumenInforme['errores_consulta_arca'] as $errArca)
                                        <li>
                                            PV {{ str_pad((string) ($errArca['pto_vta'] ?? ''), 5, '0', STR_PAD_LEFT) }}
                                            T{{ $errArca['tipo_afip'] ?? '?' }}:
                                            {{ \Illuminate\Support\Str::limit($errArca['mensaje'] ?? '', 120) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($registro->informe_procesado_at)
                            <div class="text-muted small mt-1">
                                Último proceso:
                                {{ $registro->informe_procesado_at->format('d/m/Y H:i') }}
                                @if ($registro->informadoPor)
                                    — {{ $registro->informadoPor->nombre }}
                                @endif
                            </div>
                        @endif
                        @if (! empty($leyendaInforme))
                            <div class="text-muted small mt-1">
                                {{ $leyendaInforme }}
                            </div>
                        @endif
                        @if (! empty($erroresAgrupados))
                            <div class="mt-2">
                                <strong class="small text-danger">Errores ARCA al informar:</strong>
                                <table class="table table-sm table-bordered mt-1 mb-0">
                                    <thead>
                                        <tr>
                                            <th>Cód.</th>
                                            <th>Mensaje</th>
                                            <th>Cant.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($erroresAgrupados as $err)
                                            <tr>
                                                <td>{{ $err['codigo'] ?? '—' }}</td>
                                                <td class="small">{{ $err['mensaje'] ?? '' }}</td>
                                                <td>{{ (int) ($err['cantidad'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if (! empty($erroresInforme))
                            <details class="mt-2">
                                <summary class="small text-danger">Detalle por comprobante ({{ count($erroresInforme) }})</summary>
                                <table class="table table-sm table-bordered mt-1 mb-0">
                                    <thead>
                                        <tr>
                                            <th>PV</th>
                                            <th>Nro</th>
                                            <th>Error</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($erroresInforme as $err)
                                            <tr>
                                                <td>{{ str_pad((string) ($err['pto_vta'] ?? ''), 5, '0', STR_PAD_LEFT) }}</td>
                                                <td>#{{ $err['numero'] ?? '?' }}</td>
                                                <td class="small">
                                                    @if (! empty($err['codigo_error']))
                                                        <code>[{{ $err['codigo_error'] }}]</code>
                                                    @endif
                                                    {{ \Illuminate\Support\Str::limit($err['mensaje'] ?? '', 200) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

@if ($puedeInformar ?? false)
    @if ($procesoActivo ?? false)
        <div class="alert alert-warning py-2 mt-3 mb-2 small">
            <i class="fa fa-spinner fa-spin"></i>
            {{ $leyendaInforme !== '' ? $leyendaInforme : 'Presentación en segundo plano…' }}
            No se puede encolar otra hasta que termine (recibirás un mail).
        </div>
    @endif
    <form method="post"
        action="{{ route('arca_caea_informar', $registro->id) }}"
        class="mt-3 mb-0 d-inline js-arca-caea-informar-form"
        data-overlay-titulo="Encolando presentación CAEA…"
        data-overlay-subtitulo="El proceso corre en segundo plano. Al terminar recibirás un mail con el resultado."
        data-confirm-msg="¿Encolar la presentación CAEA pendiente? Al terminar recibirás un mail con el resultado.">
        @csrf
        @include('ventas.arca_caea.partials.filtros_index_hidden', ['filtrosQuery' => $filtrosQuery ?? []])
        <button type="submit" class="btn btn-primary btn-sm" @disabled(! ($puedePresentar ?? false))>
            @if ($procesoActivo ?? false)
                <i class="fa fa-spinner fa-spin"></i> Procesando en segundo plano…
            @else
                <i class="fa fa-paper-plane"></i> Informar comprobantes pendientes
            @endif
        </button>
    </form>
    @if (($resumenInforme['errores'] ?? 0) > 0)
        <form method="post"
            action="{{ route('arca_caea_informar', $registro->id) }}"
            class="mt-2 mb-0 d-inline js-arca-caea-informar-form"
            data-overlay-titulo="Encolando reintento CAEA…"
            data-overlay-subtitulo="El proceso corre en segundo plano. Al terminar recibirás un mail con el resultado."
            data-confirm-msg="¿Encolar el reintento de errores CAEA? Al terminar recibirás un mail con el resultado.">
            @csrf
            @include('ventas.arca_caea.partials.filtros_index_hidden', ['filtrosQuery' => $filtrosQuery ?? []])
            <input type="hidden" name="solo_errores" value="1">
            <button type="submit" class="btn btn-outline-danger btn-sm" @disabled($procesoActivo ?? false)>
                <i class="fa fa-refresh"></i> Reintentar solo errores ({{ (int) ($resumenInforme['errores'] ?? 0) }})
            </button>
        </form>
    @endif
@endif

@include('ventas.arca_caea.partials.herramienta_manual')

@if ($puedeReintentar ?? false)
    <form method="post" action="{{ route('arca_caea_reintentar', $registro->id) }}" class="mt-3 mb-0">
        @csrf
        @include('ventas.arca_caea.partials.filtros_index_hidden', ['filtrosQuery' => $filtrosQuery ?? []])
        <button type="submit" class="btn btn-warning btn-sm">
            <i class="fa fa-refresh"></i> Reintentar solicitud
        </button>
    </form>
@endif

@if ($puedeGrabarAnita ?? false)
    <form method="post"
        action="{{ route('arca_caea_grabar_anita', $registro->id) }}"
        class="mt-2 mb-0"
        onsubmit="return confirm('¿Grabar este CAEA en Anita (Informix)?');">
        @csrf
        @include('ventas.arca_caea.partials.filtros_index_hidden', ['filtrosQuery' => $filtrosQuery ?? []])
        <button type="submit" class="btn btn-success btn-sm">
            <i class="fa fa-database"></i> Grabar en Anita
        </button>
    </form>
@endif
