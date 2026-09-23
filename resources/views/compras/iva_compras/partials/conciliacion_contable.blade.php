@php
    $conc = $resultado['conciliacion_contable'] ?? ['habilitada' => false];
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
    $resumen = $conc['resumen_empresa'] ?? [];
    $lineas = $resumen['lineas'] ?? [];
    $cuentasDet = $conc['cuentas']['detalle'] ?? [];
    $puedeVerCuenta = $puede_ver_cuenta ?? false;
    $puedeVerAsiento = $puede_ver_asiento ?? false;
    $puedeVerComprobante = $puede_ver_comprobante ?? false;
    $queryConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $auditoria = $conc['auditoria_diaria'] ?? ['habilitada' => false];
    $porCp = $conc['por_comprobante_vinculado'] ?? ['habilitada' => false];
    $cuadraGlobal = ! empty($resumen['cuadra_global']);
    $statsDia = $auditoria['stats'] ?? [];
    $diasConDif = (int) ($statsDia['dias_con_diferencia'] ?? 0);
    $compsConDif = (int) ($porCp['stats']['con_diferencia'] ?? 0);
    $compsSinAsiento = (int) ($porCp['stats']['sin_asiento'] ?? 0);
    $compsARevisar = (int) ($porCp['stats']['a_revisar'] ?? ($compsConDif + $compsSinAsiento));
    $abrirPanel = ! $cuadraGlobal || $diasConDif > 0 || $compsARevisar > 0;
@endphp
@if (! empty($conc['habilitada']))
    <div class="px-3 py-2 border-bottom bg-white" id="iva-compras-conciliacion">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-0">
            <button type="button"
                class="btn btn-sm btn-outline-secondary {{ $abrirPanel ? '' : 'collapsed' }}"
                data-toggle="collapse"
                data-target="#panel-conciliacion-iva-compras"
                aria-expanded="{{ $abrirPanel ? 'true' : 'false' }}"
                aria-controls="panel-conciliacion-iva-compras"
                id="btn-toggle-conciliacion-iva-compras">
                <i class="fa {{ $abrirPanel ? 'fa-chevron-down' : 'fa-chevron-right' }} js-conc-chevron"></i>
                Conciliación contable (mayor on-line)
            </button>
            <div class="d-flex flex-wrap align-items-center mt-1 mt-md-0">
                @if ($cuadraGlobal)
                    <span class="badge badge-success mr-1">Cuadre general OK</span>
                @else
                    <span class="badge badge-warning mr-1">Diferencias en cuadre general</span>
                @endif
                @if ($diasConDif > 0)
                    <span class="badge badge-warning mr-1">{{ $diasConDif }} día(s) con dif.</span>
                @endif
                @if ($compsConDif > 0)
                    <span class="badge badge-warning mr-1">{{ $compsConDif }} factura(s) con dif.</span>
                @endif
                @if ($compsSinAsiento > 0)
                    <span class="badge badge-secondary">{{ $compsSinAsiento }} sin asiento</span>
                @endif
            </div>
        </div>

        <div id="panel-conciliacion-iva-compras" class="collapse {{ $abrirPanel ? 'show' : '' }} mt-2">
            @if (count($cuentasDet) > 0)
                <p class="small text-muted mb-2">
                    Cuentas controladas
                    <span class="text-muted">(config/iva_compras.php)</span>:
                    @foreach ($cuentasDet as $c)
                        @if ($puedeVerCuenta && (int) ($c['id'] ?? 0) > 0)
                            <a href="{{ route('editar_cuentacontable', array_merge(['id' => $c['id']], $queryConsulta)) }}"
                               target="_blank" rel="noopener" class="badge badge-light border mr-1 text-primary"
                               title="Fuente: {{ $c['fuente'] ?? '' }}">
                                {{ $c['codigo'] ?? '' }} {{ $c['nombre'] ?? '' }}
                            </a>
                        @else
                            <span class="badge badge-light border mr-1" title="Fuente: {{ $c['fuente'] ?? '' }}">
                                {{ $c['codigo'] ?? '' }} {{ $c['nombre'] ?? '' }}
                            </span>
                        @endif
                    @endforeach
                </p>
            @endif

            <p class="small text-muted mb-2">
                Cuadra IVA crédito y percepciones del libro contra el mayor de los asientos vinculados a esos comprobantes.
                El neto/exento es solo informativo del libro.
            </p>

            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0" style="font-size: 0.78rem;">
                    <thead>
                        <tr style="background-color: #85C1E9; color: #17202A;">
                            <th>Concepto</th>
                            <th class="text-right">IVA compras (ERP)</th>
                            <th class="text-right">Mayor contable</th>
                            <th class="text-right">Diferencia</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lineas as $linea)
                            @php
                                $soloLibro = ! empty($linea['solo_libro']);
                                $cuadra = ! empty($linea['cuadra']);
                                $tieneMov = array_key_exists('tiene_movimiento', $linea)
                                    ? ! empty($linea['tiene_movimiento'])
                                    : true;
                            @endphp
                            <tr @if (! $soloLibro && ! $cuadra && $tieneMov) class="table-warning" @elseif (! $tieneMov || $soloLibro) class="text-muted" @endif>
                                <td>
                                    {{ $linea['concepto'] ?? '' }}
                                    @if ($soloLibro)
                                        <span class="small text-muted">— no se confronta con mayor</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $formatear($linea['erp'] ?? 0) }}</td>
                                <td class="text-right">
                                    @if ($soloLibro || $linea['contable'] === null)
                                        —
                                    @else
                                        {{ $formatear($linea['contable'] ?? 0) }}
                                    @endif
                                </td>
                                <td class="text-right font-weight-bold">
                                    @if ($soloLibro || $linea['diferencia'] === null)
                                        —
                                    @else
                                        {{ $formatear($linea['diferencia'] ?? 0) }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($soloLibro)
                                        <span class="text-muted" title="Solo libro">ℹ</span>
                                    @elseif (! $tieneMov)
                                        <span class="text-muted">—</span>
                                    @elseif ($cuadra)
                                        <i class="fa fa-check text-success" title="Cuadra"></i>
                                    @else
                                        <i class="fa fa-exclamation-triangle text-warning" title="Diferencia"></i>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! empty($conc['notas']))
                <ul class="small text-muted mb-3">
                    @foreach ($conc['notas'] as $nota)
                        <li>{{ $nota }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="accordion" id="accordion-iva-compras-detalle">
                @if (! empty($auditoria['habilitada']))
                    <div class="card card-outline card-secondary mb-2">
                        <div class="card-header py-1 px-2">
                            <button class="btn btn-link btn-sm text-left w-100 d-flex justify-content-between align-items-center {{ $diasConDif > 0 ? '' : 'collapsed' }}"
                                type="button" data-toggle="collapse" data-target="#collapse-aud-diaria-iva-compras"
                                aria-expanded="{{ $diasConDif > 0 ? 'true' : 'false' }}"
                                aria-controls="collapse-aud-diaria-iva-compras">
                                <span>
                                    Auditoría diaria
                                    <span class="small text-muted">
                                        (tol. {{ $formatear($auditoria['tolerancia'] ?? 1) }})
                                    </span>
                                </span>
                                @if ($diasConDif === 0)
                                    <span class="badge badge-success">OK</span>
                                @else
                                    <span class="badge badge-warning">{{ $diasConDif }} día(s) con dif.</span>
                                @endif
                            </button>
                        </div>
                        <div id="collapse-aud-diaria-iva-compras"
                             class="collapse {{ $diasConDif > 0 ? 'show' : '' }}"
                             data-parent="#accordion-iva-compras-detalle">
                            <div class="card-body p-2">
                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                                        <thead>
                                            <tr style="background-color: #85C1E9; color: #17202A;">
                                                <th>Fecha</th>
                                                <th class="text-right">Comp.</th>
                                                <th class="text-right">IVA ERP</th>
                                                <th class="text-right">IVA mayor</th>
                                                <th class="text-right">Dif. IVA</th>
                                                <th class="text-center">OK</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($auditoria['dias'] ?? [] as $dia)
                                                @if (empty($dia['tiene_movimiento']))
                                                    @continue
                                                @endif
                                                <tr @if (empty($dia['cuadra'])) class="table-warning" @endif>
                                                    <td>{{ $dia['fecha_fmt'] ?? $dia['fecha'] ?? '' }}</td>
                                                    <td class="text-right">{{ (int) ($dia['erp']['comprobantes'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['erp']['iva'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['contable']['iva'] ?? 0) }}</td>
                                                    <td class="text-right">{{ $formatear($dia['diferencias']['iva'] ?? 0) }}</td>
                                                    <td class="text-center">
                                                        @if (! empty($dia['cuadra']))
                                                            <i class="fa fa-check text-success"></i>
                                                        @else
                                                            <i class="fa fa-exclamation-triangle text-warning"></i>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if (! empty($porCp['habilitada']))
                    @php $stCp = $porCp['stats'] ?? []; @endphp
                    <div class="card card-outline card-secondary mb-0">
                        <div class="card-header py-1 px-2">
                            <button class="btn btn-link btn-sm text-left w-100 d-flex justify-content-between align-items-center {{ $compsARevisar > 0 ? '' : 'collapsed' }}"
                                type="button" data-toggle="collapse" data-target="#collapse-cp-dif-iva-compras"
                                aria-expanded="{{ $compsARevisar > 0 ? 'true' : 'false' }}"
                                aria-controls="collapse-cp-dif-iva-compras">
                                <span>
                                    Comprobantes a revisar
                                    <span class="small text-muted">
                                        ({{ (int) ($stCp['cuadran'] ?? 0) }}/{{ (int) ($stCp['vinculados'] ?? 0) }} vinculados cuadran)
                                    </span>
                                </span>
                                @if ($compsARevisar === 0)
                                    <span class="badge badge-success">OK</span>
                                @else
                                    <span>
                                        @if ($compsConDif > 0)
                                            <span class="badge badge-warning">{{ $compsConDif }} con dif.</span>
                                        @endif
                                        @if ($compsSinAsiento > 0)
                                            <span class="badge badge-secondary">{{ $compsSinAsiento }} sin asiento</span>
                                        @endif
                                    </span>
                                @endif
                            </button>
                        </div>
                        <div id="collapse-cp-dif-iva-compras"
                             class="collapse {{ $compsARevisar > 0 ? 'show' : '' }}"
                             data-parent="#accordion-iva-compras-detalle">
                            <div class="card-body p-2">
                                @if ($compsARevisar === 0)
                                    <p class="small text-success mb-0">
                                        <i class="fa fa-check-circle"></i>
                                        Todos los comprobantes del libro tienen asiento vinculado y cuadran en IVA y percepciones.
                                    </p>
                                @else
                                    <p class="small text-muted mb-2">
                                        {{ $compsARevisar }} comprobante(s) a revisar:
                                        @if ($compsConDif > 0)
                                            {{ $compsConDif }} con diferencia vs asiento
                                        @endif
                                        @if ($compsConDif > 0 && $compsSinAsiento > 0)
                                            ·
                                        @endif
                                        @if ($compsSinAsiento > 0)
                                            {{ $compsSinAsiento }} sin asiento (explican desvíos del resumen / auditoría diaria)
                                        @endif
                                        . Orden: primero diferencia vs asiento, luego sin asiento.
                                    </p>
                                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                                            <thead>
                                                <tr style="background-color: #85C1E9; color: #17202A;">
                                                    <th>Motivo</th>
                                                    <th>Fecha</th>
                                                    <th>Proveedor</th>
                                                    <th>Comprobante</th>
                                                    <th class="text-right">IVA ERP</th>
                                                    <th class="text-right">IVA asiento</th>
                                                    <th class="text-right">Dif. IVA</th>
                                                    <th class="text-right">Dif. Perc.IVA</th>
                                                    <th class="text-right">Dif. IIBB</th>
                                                    <th>Asiento</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($porCp['comprobantes'] as $row)
                                                    @php $esSinAsiento = ($row['motivo'] ?? '') === 'sin_asiento'; @endphp
                                                    <tr @if ($esSinAsiento) class="table-secondary" @endif>
                                                        <td class="text-nowrap">
                                                            @if ($esSinAsiento)
                                                                <span class="badge badge-secondary">Sin asiento</span>
                                                            @else
                                                                <span class="badge badge-warning">Dif. asiento</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $row['fecha_mov'] ?? '' }}</td>
                                                        <td>{{ $row['proveedor_nombre'] ?? '' }}</td>
                                                        <td>
                                                            @if ($puedeVerComprobante && (int) ($row['comprobante_proveedor_id'] ?? 0) > 0)
                                                                <a href="{{ route('editar_comprobante_proveedor', array_merge(['id' => $row['comprobante_proveedor_id']], $queryConsulta)) }}"
                                                                   target="_blank" rel="noopener" class="text-primary">
                                                                    {{ $row['comprobante'] ?? '' }}
                                                                </a>
                                                            @else
                                                                {{ $row['comprobante'] ?? '' }}
                                                            @endif
                                                        </td>
                                                        <td class="text-right">{{ $formatear($row['erp_iva'] ?? 0) }}</td>
                                                        <td class="text-right">
                                                            @if ($esSinAsiento || $row['contable_iva'] === null)
                                                                —
                                                            @else
                                                                {{ $formatear($row['contable_iva'] ?? 0) }}
                                                            @endif
                                                        </td>
                                                        <td class="text-right font-weight-bold">{{ $formatear($row['diferencia_iva'] ?? 0) }}</td>
                                                        <td class="text-right">{{ $formatear($row['diferencia_perc_iva'] ?? 0) }}</td>
                                                        <td class="text-right">{{ $formatear($row['diferencia_perc_iibb'] ?? 0) }}</td>
                                                        <td>
                                                            @if (! $esSinAsiento && $puedeVerAsiento && (int) ($row['asiento_id'] ?? 0) > 0)
                                                                <a href="{{ route('editar_asiento', array_merge(['id' => $row['asiento_id']], $queryConsulta)) }}"
                                                                   target="_blank" rel="noopener" class="text-primary">
                                                                    #{{ $row['asiento_id'] }}
                                                                </a>
                                                            @elseif ($esSinAsiento)
                                                                <span class="text-muted">—</span>
                                                            @else
                                                                #{{ $row['asiento_id'] ?? '' }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <script>
    (function () {
        var panel = document.getElementById('panel-conciliacion-iva-compras');
        var btn = document.getElementById('btn-toggle-conciliacion-iva-compras');
        if (!panel || !btn || typeof jQuery === 'undefined') {
            return;
        }
        jQuery(panel).on('show.bs.collapse hide.bs.collapse', function (e) {
            if (e.target !== panel) {
                return;
            }
            var icon = btn.querySelector('.js-conc-chevron');
            if (!icon) {
                return;
            }
            if (e.type === 'show') {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
            } else {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            }
        });
    })();
    </script>
@endif
