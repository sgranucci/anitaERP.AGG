@extends("theme.$theme.layout")
@section('titulo')
    Conciliaci&oacute;n gastronom&iacute;a Contable
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Conciliaci&oacute;n cierres vs flash / mayor (gastronom&iacute;a)</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierres_turno_gastronomia_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    <strong>C&oacute;mo leer la conciliaci&oacute;n</strong>
                    <ul class="mb-0 pl-3">
                        <li><strong>Facturaci&oacute;n</strong> = &Sigma; cierres de turno + post-cierre Waitry (+ totem / agregados CAEA si hay).</li>
                        <li><strong>Flash</strong> = <code>flash_ayb</code> Informix (caja) de la misma fecha de jornada.</li>
                        <li><strong>Asientos Waitry</strong> = snapshot del proceso de cierre (mismos componentes que la facturaci&oacute;n).</li>
                        <li><strong>Mayor Anita</strong> = neto haber (subdiario+ctamov) del d&iacute;a en las cuentas del control
                            (ventas / kiosco / tabaco / IVA d&eacute;bito y cr&eacute;dito fiscal), solo detalle gastronom&iacute;a;
                            excluye estacionamiento y ajustes ajenos en cuentas compartidas.</li>
                        <li>OK si Facturaci&oacute;n&minus;Flash, Facturaci&oacute;n&minus;Asientos y Facturaci&oacute;n&minus;Mayor &asymp; 0.</li>
                    </ul>
                    Este proceso es solo consulta; no genera ni anula asientos. Expand&iacute; cada jornada para ver el detalle por PC (igual que al abrir el d&iacute;a).
                </div>
                <form method="get" action="{{ route('cierres_turno_gastronomia_contable_conciliacion') }}" class="mb-4">
                    @foreach ($retornoListadoQuery ?? [] as $retornoKey => $retornoVal)
                        <input type="hidden" name="retorno[{{ $retornoKey }}]" value="{{ $retornoVal }}">
                    @endforeach
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="empresa_id">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) ($empresa_id ?? 0) === (int) $emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_desde">Jornada desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                   value="{{ $fecha_desde ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_hasta">Jornada hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                   value="{{ $fecha_hasta ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if (! empty($error_conciliacion))
                    <div class="alert alert-danger">{{ $error_conciliacion }}</div>
                @endif

                @if ($consultar && empty($error_conciliacion) && $resultado !== null)
                    @php
                        $resumen = $resultado['resumen'] ?? [];
                        $tol = (float) ($resultado['tolerancia'] ?? 0.02);
                        $offsetFlash = (int) ($resultado['flash_offset_dias'] ?? 0);
                        $dias = $resultado['dias'] ?? [];
                        $totFact = 0.0;
                        $totFlash = 0.0;
                        $totAsientos = 0.0;
                        $totMayor = 0.0;
                        $totCierres = 0;
                        foreach ($dias as $dSum) {
                            $totFact += (float) ($dSum['total_facturacion'] ?? 0);
                            $totFlash += (float) ($dSum['total_flash_ayb'] ?? 0);
                            $totAsientos += (float) ($dSum['total_asientos_debe'] ?? 0);
                            $totMayor += (float) ($dSum['total_mayor_neto'] ?? 0);
                            $totCierres += (int) ($dSum['cantidad_cierres'] ?? 0);
                        }
                    @endphp
                    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                        <div>
                            <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                            — {{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }}
                            <br>
                            <span class="text-muted">
                                {{ (int) ($resumen['total_dias'] ?? 0) }} jornada(s) con actividad —
                                {{ (int) ($resumen['dias_ok'] ?? 0) }} OK,
                                {{ (int) ($resumen['dias_dif'] ?? 0) }} con diferencia
                                @if ((int) ($resumen['dias_sin_asiento_waitry'] ?? 0) > 0)
                                    — <span class="text-warning font-weight-bold">
                                        {{ (int) $resumen['dias_sin_asiento_waitry'] }} d&iacute;a(s) sin asiento Waitry
                                    </span>
                                @endif
                                @if ($offsetFlash > 0)
                                    — flash con offset {{ $offsetFlash }} d&iacute;a(s)
                                @endif
                                — tolerancia {{ number_format($tol, 2, ',', '.') }}
                            </span>
                        </div>
                        @if (can('exportar-cierres-turno-gastronomia-contable', false))
                            <div class="mr-2 mb-1">
                                @include('includes.exportar-tabla-queryparams', [
                                    'ruta' => 'listar_cierres_turno_gastronomia_contable_conciliacion',
                                    'queryparams' => $filtrosQueryConciliacion ?? [],
                                ])
                            </div>
                        @endif
                    </div>

                    @if (empty($dias))
                        <p class="text-muted text-center py-4">Sin cierres, flash ni mayor en el rango indicado.</p>
                    @else
                        <div class="table-responsive">
                            <table id="tabla-paginada" class="table table-bordered table-hover mb-0" style="font-size: 0.95rem;">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th style="width: 2.5rem;"></th>
                                        <th>Jornada</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Cierres</th>
                                        <th class="text-right">Facturaci&oacute;n</th>
                                        <th class="text-right">Flash</th>
                                        <th class="text-right">Asientos</th>
                                        <th class="text-right">Mayor</th>
                                        <th class="text-right">Fact.&minus;Flash</th>
                                        <th class="text-right">Fact.&minus;Asientos</th>
                                        <th class="text-right">Fact.&minus;Mayor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dias as $idx => $dia)
                                        @php
                                            $estado = (string) ($dia['estado'] ?? '');
                                            $badgeClass = match ($estado) {
                                                'OK' => 'badge-success',
                                                'DIF' => 'badge-danger',
                                                default => 'badge-secondary',
                                            };
                                            $collapseId = 'dia-detalle-'.$idx;
                                            $difFlash = (float) ($dia['diferencia_flash'] ?? 0);
                                            $difAsientos = (float) ($dia['diferencia_asientos'] ?? 0);
                                            $difMayor = (float) ($dia['diferencia_mayor'] ?? 0);
                                            $filaClase = $estado === 'DIF' ? 'table-danger' : '';
                                        @endphp
                                        <tr class="{{ $filaClase }}">
                                            <td class="text-center align-middle p-1">
                                                <button class="btn btn-sm btn-outline-secondary collapsed"
                                                        type="button"
                                                        data-toggle="collapse"
                                                        data-target="#{{ $collapseId }}"
                                                        aria-expanded="false"
                                                        aria-controls="{{ $collapseId }}"
                                                        title="Detalle por PC">
                                                    <i class="fa fa-chevron-down"></i>
                                                </button>
                                            </td>
                                            <td class="align-middle">
                                                <strong>{{ $dia['fecha_jornada_fmt'] ?? '' }}</strong>
                                                @if (($dia['fecha_flash'] ?? '') !== ($dia['fecha_jornada'] ?? ''))
                                                    <br><small class="text-muted">flash {{ $dia['fecha_flash_fmt'] ?? '' }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge {{ $badgeClass }}">{{ $estado !== '' ? $estado : '—' }}</span>
                                            </td>
                                            <td class="text-center align-middle">{{ (int) ($dia['cantidad_cierres'] ?? 0) }}</td>
                                            <td class="text-right align-middle font-weight-bold">
                                                {{ number_format((float) ($dia['total_facturacion'] ?? 0), 2, ',', '.') }}
                                                @if (abs((float) ($dia['asientos_tipos']['post_cierre'] ?? 0)) > $tol
                                                    || abs((float) ($dia['asientos_tipos']['totem_ventas'] ?? 0)) > $tol
                                                    || abs((float) ($dia['asientos_tipos']['agregados_caea'] ?? 0)) > $tol)
                                                    <br>
                                                    <small class="text-muted font-weight-normal">
                                                        cierres {{ number_format((float) ($dia['total_cierres'] ?? 0), 2, ',', '.') }}
                                                        @if (abs((float) ($dia['asientos_tipos']['post_cierre'] ?? 0)) > $tol)
                                                            + post {{ number_format((float) $dia['asientos_tipos']['post_cierre'], 2, ',', '.') }}
                                                        @endif
                                                        @if (abs((float) ($dia['asientos_tipos']['totem_ventas'] ?? 0)) > $tol)
                                                            + totem {{ number_format((float) $dia['asientos_tipos']['totem_ventas'], 2, ',', '.') }}
                                                        @endif
                                                        @if (abs((float) ($dia['asientos_tipos']['agregados_caea'] ?? 0)) > $tol)
                                                            + agreg. {{ number_format((float) $dia['asientos_tipos']['agregados_caea'], 2, ',', '.') }}
                                                        @endif
                                                    </small>
                                                @endif
                                            </td>
                                            <td class="text-right align-middle">{{ number_format((float) ($dia['total_flash_ayb'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right align-middle">{{ number_format((float) ($dia['total_asientos_debe'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right align-middle">{{ number_format((float) ($dia['total_mayor_neto'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-right align-middle {{ abs($difFlash) > $tol ? 'text-danger font-weight-bold' : 'text-success' }}">
                                                {{ number_format($difFlash, 2, ',', '.') }}
                                            </td>
                                            <td class="text-right align-middle {{ abs($difAsientos) > $tol ? 'text-danger font-weight-bold' : 'text-success' }}">
                                                {{ number_format($difAsientos, 2, ',', '.') }}
                                            </td>
                                            <td class="text-right align-middle {{ abs($difMayor) > $tol ? 'text-danger font-weight-bold' : 'text-success' }}">
                                                {{ number_format($difMayor, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                        <tr class="collapse-row">
                                            <td colspan="11" class="p-0 border-0">
                                                <div id="{{ $collapseId }}" class="collapse">
                                                    <div class="bg-light border-left border-right border-bottom px-3 py-3">
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-striped table-bordered mb-0 bg-white">
                                                                <thead style="background:#85C1E9;color:#17202A;">
                                                                    <tr>
                                                                        <th>PC</th>
                                                                        <th>Punto de venta</th>
                                                                        <th class="text-center">Cant.</th>
                                                                        <th class="text-right">Facturaci&oacute;n</th>
                                                                        <th class="text-right">Habilitaci&oacute;n</th>
                                                                        <th>Cierres</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @forelse ($dia['terminales'] ?? [] as $term)
                                                                        <tr>
                                                                            <td><code>{{ $term['identificador_pc'] ?? '' }}</code></td>
                                                                            <td>
                                                                                <strong>{{ $term['pv_codigo'] ?? '' }}</strong>
                                                                                @if (! empty($term['pv_nombre']) && ($term['pv_nombre'] ?? '') !== ($term['pv_codigo'] ?? ''))
                                                                                    — {{ $term['pv_nombre'] }}
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-center">{{ (int) ($term['cantidad'] ?? 0) }}</td>
                                                                            <td class="text-right">{{ number_format((float) ($term['total_facturacion'] ?? 0), 2, ',', '.') }}</td>
                                                                            <td class="text-right">{{ number_format((float) ($term['total_habilitacion'] ?? 0), 2, ',', '.') }}</td>
                                                                            <td>
                                                                                <small>
                                                                                    @foreach ($term['cierres'] ?? [] as $c)
                                                                                        @if (! empty($c['id']) && can('listar-cierres-turno-gastronomia-contable', false))
                                                                                            <a href="{{ route('cierres_turno_gastronomia_contable_comprobante_cierre', ['id' => $c['id'], 'inline' => 1]) }}"
                                                                                               class="text-primary" target="_blank" rel="noopener"
                                                                                               title="{{ $c['turno'] ?? '' }} — {{ $c['usuario'] ?? '' }}">
                                                                                                #{{ $c['id'] }}
                                                                                            </a>
                                                                                            ({{ number_format((float) ($c['total_facturacion'] ?? 0), 2, ',', '.') }})
                                                                                        @else
                                                                                            #{{ $c['id'] ?? '' }}
                                                                                        @endif
                                                                                        @if (! $loop->last)
                                                                                            ,
                                                                                        @endif
                                                                                    @endforeach
                                                                                </small>
                                                                            </td>
                                                                        </tr>
                                                                    @empty
                                                                        <tr>
                                                                            <td colspan="6" class="text-center text-muted py-2">Sin cierres definitivos en esta jornada.</td>
                                                                        </tr>
                                                                    @endforelse
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        @if (! empty($dia['asientos_detalle']))
                                                            <div class="mt-2 small">
                                                                <strong>Asientos Waitry</strong>
                                                                (F. d&iacute;a {{ number_format((float) ($dia['asientos_tipos']['factura_dia'] ?? 0), 2, ',', '.') }}
                                                                · Post-cierre {{ number_format((float) ($dia['asientos_tipos']['post_cierre'] ?? 0), 2, ',', '.') }}
                                                                · Totem {{ number_format((float) ($dia['asientos_tipos']['totem_ventas'] ?? 0), 2, ',', '.') }})
                                                                —
                                                                @foreach ($dia['asientos_detalle'] as $asi)
                                                                    @if (! empty($asi['asiento_id']) && can('listar-asiento', false))
                                                                        <a href="{{ route('editar_asiento', ['id' => $asi['asiento_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                                                           class="text-primary" target="_blank" rel="noopener">
                                                                            {{ $asi['numeroasiento'] ?? ('#'.$asi['asiento_id']) }}
                                                                        </a>
                                                                    @else
                                                                        {{ $asi['numeroasiento'] ?? '' }}
                                                                    @endif
                                                                    ({{ $asi['tipo'] ?? '' }} {{ number_format((float) ($asi['total_debe'] ?? 0), 2, ',', '.') }})
                                                                    @if (! $loop->last)
                                                                        ,
                                                                    @endif
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background:#d6eaf8;color:#17202A;font-weight:bold;">
                                        <td></td>
                                        <td>Totales ({{ count($dias) }} d&iacute;as)</td>
                                        <td></td>
                                        <td class="text-center">{{ $totCierres }}</td>
                                        <td class="text-right">{{ number_format($totFact, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($totFlash, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($totAsientos, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($totMayor, 2, ',', '.') }}</td>
                                        <td class="text-right {{ abs($totFact - $totFlash) > $tol ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($totFact - $totFlash, 2, ',', '.') }}
                                        </td>
                                        <td class="text-right {{ abs($totFact - $totAsientos) > $tol ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($totFact - $totAsientos, 2, ',', '.') }}
                                        </td>
                                        <td class="text-right {{ abs($totFact - $totMayor) > $tol ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($totFact - $totMayor, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
