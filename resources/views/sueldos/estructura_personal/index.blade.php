@extends("theme.$theme.layout")

@section('titulo')
    Estructura de personal
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/sueldos/estructura-personal.css') }}?v={{ @filemtime(public_path('assets/css/sueldos/estructura-personal.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/estructura_personal/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/estructura_personal/index.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $k = $reporte['kpis'];
    $siglas = $reporte['siglas'];
    $qEmpresa = $reporte['empresa_id'] > 0 ? ['empresa_id' => $reporte['empresa_id']] : [];
    $urlExcel = route('excel_estructura_personal_sueldos', array_merge(['corte' => $reporte['corte']], $qEmpresa));
    $maxSector = 1;
    foreach ($reporte['sectores'] as $sector) {
        $maxSector = max($maxSector, (int) $sector['dotacion']);
    }
    $maxMes = 1;
    foreach ($reporte['bajas_por_mes'] as $bm) {
        $maxMes = max($maxMes, (int) $bm['cantidad']);
    }
    $genero = $reporte['composicion']['genero'];
    $corteGeneroM = (float) $genero['m_pct'];
    $corteGeneroF = $corteGeneroM + (float) $genero['f_pct'];
    $totalGrupo = 0;
    foreach ($reporte['navegacion'] as $navEmpresa) {
        $totalGrupo += (int) $navEmpresa['dotacion'];
    }
@endphp

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ep-overlay',
    'tituloId' => 'ep-overlay-titulo',
    'subtituloId' => 'ep-overlay-subtitulo',
    'titulo' => 'Armando el Excel…',
    'subtitulo' => 'Misma dotación que la pantalla. Pulse Esc para cerrar este aviso.',
])

<div class="ep-app" id="ep-app">
    <header class="ep-hdr">
        <div>
            <h1>Estructura de personal</h1>
            <p>Corte {{ $reporte['corte_label'] }} · {{ $reporte['mes_label'] }} · dotación vigente al día</p>
        </div>
        <div class="ep-hdr-actions">
            <button type="button" class="ep-theme" id="ep-theme">Tema</button>
            <a class="ep-excel" id="ep-excel" href="{{ $urlExcel }}">Descargar Excel</a>
        </div>
    </header>

    <div class="ep-months">
        @foreach ($meses as $mes)
            @if ($mes['futuro'])
                <span class="ep-month off">{{ $mes['label'] }}</span>
            @else
                <a class="ep-month {{ $mes['activo'] ? 'on' : '' }}"
                   href="{{ route('estructura_personal_sueldos', array_merge(['corte' => $mes['fecha']], $qEmpresa)) }}">{{ $mes['label'] }}</a>
            @endif
        @endforeach
        <form method="get" action="{{ route('estructura_personal_sueldos') }}" class="ep-corte">
            @if ($reporte['empresa_id'] > 0)
                <input type="hidden" name="empresa_id" value="{{ $reporte['empresa_id'] }}">
            @endif
            <input type="date" name="corte" value="{{ $reporte['corte'] }}" max="{{ date('Y-m-d') }}">
            <button type="submit">Ver</button>
        </form>
    </div>

    <nav class="ep-nav">
        <button type="button" class="ep-tab on" data-ep-tab="ep-resumen">Resumen</button>
        <button type="button" class="ep-tab" data-ep-tab="ep-consolidado">Consolidado</button>
        <button type="button" class="ep-tab" data-ep-tab="ep-bajas">Bajas · año <span class="ep-count" id="ep-bajas-count">{{ count($reporte['bajas']) }}</span></button>
        <button type="button" class="ep-tab" data-ep-tab="ep-rotacion">Rotación</button>
        <button type="button" class="ep-tab" data-ep-tab="ep-composicion">Composición</button>
        <button type="button" class="ep-tab" data-ep-tab="ep-base">Base <span class="ep-count" id="ep-base-count">{{ count($reporte['base']) }}</span></button>
    </nav>

    <div class="ep-pg on" id="ep-resumen">
        <div class="ep-emps" style="margin-bottom:12px">
            <a class="ep-emp ep-all {{ $reporte['empresa_id'] === 0 ? 'on' : '' }}"
               href="{{ route('estructura_personal_sueldos', ['corte' => $reporte['corte']]) }}">
                <div class="ep-emp-n">Todas</div>
                <div class="ep-emp-v">{{ $totalGrupo }}</div>
                <div class="ep-emp-p">Grupo</div>
            </a>
            @foreach ($reporte['navegacion'] as $emp)
                @php
                    $claseSigla = in_array($emp['sigla'], ['BSA', 'KSA', 'RSA'], true) ? 'ep-sig-'.$emp['sigla'] : 'ep-sig-otra';
                @endphp
                <a class="ep-emp {{ $claseSigla }} {{ (int) $reporte['empresa_id'] === (int) $emp['id'] ? 'on' : '' }}"
                   href="{{ route('estructura_personal_sueldos', ['corte' => $reporte['corte'], 'empresa_id' => $emp['id']]) }}">
                    <div class="ep-emp-n">{{ $emp['nombre'] }} · {{ $emp['sigla'] }}</div>
                    <div class="ep-emp-v">{{ $emp['dotacion'] }}</div>
                    <div class="ep-emp-p">{{ number_format($emp['pct'], 1, ',', '.') }}% del corte</div>
                </a>
            @endforeach
        </div>

        <div class="ep-kpis">
            <div class="ep-kpi" style="background:var(--ep-red2)">
                <div class="ep-kpi-lbl">Dotación activa</div>
                <div class="ep-kpi-val">{{ $k['dotacion'] }}</div>
                <div class="ep-kpi-sub">Corte {{ $reporte['corte_label'] }}</div>
            </div>
            <div class="ep-kpi" style="background:var(--ep-org)">
                <div class="ep-kpi-lbl">Desvío vs ppto</div>
                <div class="ep-kpi-val">
                    @if ($k['desvio'] === null)
                        —
                    @else
                        {{ $k['desvio'] > 0 ? '+' : '' }}{{ $k['desvio'] }}
                    @endif
                </div>
                <div class="ep-kpi-sub">
                    @if ($k['presupuesto'] === null)
                        Sin presupuesto {{ $reporte['anio'] }}
                    @else
                        Presupuesto: {{ $k['presupuesto'] }}
                    @endif
                </div>
            </div>
            <div class="ep-kpi" style="background:var(--ep-red)">
                <div class="ep-kpi-lbl">Bajas del año</div>
                <div class="ep-kpi-val">{{ $k['bajas_anio'] }}</div>
                <div class="ep-kpi-sub">{{ $reporte['periodo_bajas'] }}</div>
            </div>
            <div class="ep-kpi" style="background:var(--ep-gold);color:#1C1A18">
                <div class="ep-kpi-lbl">Antigüedad prom.</div>
                <div class="ep-kpi-val">{{ $k['antiguedad'] === null ? '—' : number_format($k['antiguedad'], 1, ',', '.') }}</div>
                <div class="ep-kpi-sub">años promedio</div>
            </div>
            <div class="ep-kpi" style="background:var(--ep-terra)">
                <div class="ep-kpi-lbl">Rotación {{ $reporte['mes_label'] }}</div>
                <div class="ep-kpi-val">{{ number_format($k['rotacion_mes'], 1, ',', '.') }}%</div>
                <div class="ep-kpi-sub">{{ $k['bajas_mes'] }} bajas en el mes</div>
            </div>
        </div>

        <div class="ep-grid2">
            <div class="ep-card">
                <div class="ep-ct">Dotación por centro de costos</div>
                @forelse ($reporte['sectores'] as $sector)
                    <div class="ep-bar">
                        <div class="ep-bar-l" title="{{ $sector['nombre'] }}">{{ $sector['nombre'] }}</div>
                        <div class="ep-bar-t"><div class="ep-bar-f" style="width:{{ round($sector['dotacion'] * 100 / $maxSector) }}%"></div></div>
                        <div class="ep-bar-v">{{ $sector['dotacion'] }}</div>
                        <div class="ep-muted" style="width:42px;text-align:right">{{ number_format($sector['pct'], 1, ',', '.') }}%</div>
                    </div>
                @empty
                    <p class="ep-note">No hay personal vigente en este corte.</p>
                @endforelse
            </div>
            <div class="ep-card">
                <div class="ep-ct">Indicadores del corte</div>
                <div class="ep-inds">
                    <div class="ep-ind"><b>{{ $k['altas_mes'] }}</b><span>Altas del mes</span></div>
                    <div class="ep-ind"><b>{{ $k['bajas_mes'] }}</b><span>Bajas del mes</span></div>
                    <div class="ep-ind"><b>{{ number_format($k['rotacion_anio'], 1, ',', '.') }}%</b><span>Rotación del año</span></div>
                    <div class="ep-ind"><b>{{ $k['edad'] === null ? '—' : number_format($k['edad'], 1, ',', '.') }}</b><span>Edad promedio</span></div>
                    <div class="ep-ind"><b>{{ number_format($k['mujeres_pct'], 1, ',', '.') }}%</b><span>Mujeres</span></div>
                    <div class="ep-ind"><b>{{ number_format($k['fuera_pct'], 1, ',', '.') }}%</b><span>Fuera de convenio</span></div>
                </div>
                <p class="ep-note" style="margin-top:12px">La rotación del mes es las bajas del mes sobre el promedio entre el cierre del mes anterior y este corte. La del año usa el 1 de enero y este corte. Si una persona tiene legajo activo en más de una empresa, cuenta una sola vez, en el alta más antigua.</p>
            </div>
        </div>
    </div>

    <div class="ep-pg" id="ep-consolidado">
        <div class="ep-card">
            <div class="ep-ct">Consolidado por centro de costos</div>
            <p class="ep-note">Cada persona cuenta una vez, en el legajo del alta más antigua si tiene más de una empresa. Directores y managers siguen en su centro de costo; el cuadro de conducción los muestra aparte. Los servicios tercerizados de la planilla (policía, eventuales, Redguard) no están en el maestro de empleados.</p>
            <div class="ep-twrap">
                <table>
                    <thead>
                        <tr class="ep-grp">
                            <th colspan="2"></th>
                            <th class="ep-real" colspan="{{ count($siglas) + 1 }}">Real</th>
                            @if ($reporte['tiene_presupuesto'])
                                <th class="ep-ppto" colspan="{{ count($siglas) + 2 }}">Presupuesto</th>
                            @endif
                        </tr>
                        <tr>
                            <th>Centro de costos</th>
                            <th>Dirección</th>
                            @foreach ($siglas as $sigla)
                                <th class="ep-n">{{ $sigla }}</th>
                            @endforeach
                            <th class="ep-n">Cons.</th>
                            @if ($reporte['tiene_presupuesto'])
                                @foreach ($siglas as $sigla)
                                    <th class="ep-n">{{ $sigla }} ppto</th>
                                @endforeach
                                <th class="ep-n">Ppto</th>
                                <th class="ep-n">Var.</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totSigla = array_fill_keys($siglas, 0);
                            $tot = 0;
                            $totPptoSigla = array_fill_keys($siglas, 0);
                            $totPpto = 0;
                            $hayPptoGrupo = false;
                        @endphp
                        @foreach ($reporte['consolidado'] as $grupo)
                            <tr class="ep-sec"><td colspan="{{ 3 + count($siglas) + ($reporte['tiene_presupuesto'] ? count($siglas) + 2 : 0) }}">{{ $grupo['nombre'] }}</td></tr>
                            @foreach ($grupo['filas'] as $fila)
                                <tr>
                                    <td>{{ $fila['nombre'] }}</td>
                                    <td><span class="ep-badge ep-badge-{{ $grupo['badge'] }}">{{ $grupo['id'] === 'admin' ? 'Adm. y Fin.' : ($grupo['id'] === 'operaciones' ? 'Operaciones' : 'Otros') }}</span></td>
                                    @foreach ($siglas as $sigla)
                                        <td class="ep-n">{{ (int) ($fila['por_sigla'][$sigla] ?? 0) }}</td>
                                    @endforeach
                                    <td class="ep-n"><strong>{{ $fila['total'] }}</strong></td>
                                    @if ($reporte['tiene_presupuesto'])
                                        @foreach ($siglas as $sigla)
                                            <td class="ep-n ep-ppto-cell">{{ $fila['ppto'][$sigla] === null ? '—' : $fila['ppto'][$sigla] }}</td>
                                        @endforeach
                                        <td class="ep-n ep-ppto-cell">{{ $fila['ppto_total'] === null ? '—' : $fila['ppto_total'] }}</td>
                                        <td class="ep-n {{ $fila['variacion'] === null ? 'ep-muted' : ($fila['variacion'] < 0 ? 'ep-var-neg' : ($fila['variacion'] > 0 ? 'ep-var-pos' : 'ep-muted')) }}">
                                            @if ($fila['variacion'] === null)
                                                —
                                            @else
                                                {{ $fila['variacion'] > 0 ? '+' : '' }}{{ $fila['variacion'] }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                                @php
                                    foreach ($siglas as $sigla) {
                                        $totSigla[$sigla] += (int) ($fila['por_sigla'][$sigla] ?? 0);
                                    }
                                    $tot += (int) $fila['total'];
                                    if ($fila['ppto_total'] !== null) {
                                        $hayPptoGrupo = true;
                                        $totPpto += (int) $fila['ppto_total'];
                                        foreach ($siglas as $sigla) {
                                            $totPptoSigla[$sigla] += (int) ($fila['ppto'][$sigla] ?? 0);
                                        }
                                    }
                                @endphp
                            @endforeach
                            <tr class="ep-tot">
                                <td colspan="2">Total {{ $grupo['nombre'] }}</td>
                                @foreach ($siglas as $sigla)
                                    <td class="ep-n">{{ $grupo['por_sigla'][$sigla] ?? 0 }}</td>
                                @endforeach
                                <td class="ep-n">{{ $grupo['total'] }}</td>
                                @if ($reporte['tiene_presupuesto'])
                                    @foreach ($siglas as $sigla)
                                        <td class="ep-n">{{ $grupo['ppto_alguno'] ? ($grupo['ppto'][$sigla] ?? 0) : '—' }}</td>
                                    @endforeach
                                    <td class="ep-n">{{ $grupo['ppto_total'] === null ? '—' : $grupo['ppto_total'] }}</td>
                                    <td class="ep-n">
                                        @if ($grupo['variacion'] === null)
                                            —
                                        @else
                                            {{ $grupo['variacion'] > 0 ? '+' : '' }}{{ $grupo['variacion'] }}
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        <tr class="ep-gtot">
                            <td colspan="2">Total consolidado</td>
                            @foreach ($siglas as $sigla)
                                <td class="ep-n">{{ $totSigla[$sigla] }}</td>
                            @endforeach
                            <td class="ep-n">{{ $tot }}</td>
                            @if ($reporte['tiene_presupuesto'])
                                @foreach ($siglas as $sigla)
                                    <td class="ep-n">{{ $hayPptoGrupo ? $totPptoSigla[$sigla] : '—' }}</td>
                                @endforeach
                                <td class="ep-n">{{ $hayPptoGrupo ? $totPpto : '—' }}</td>
                                <td class="ep-n">{{ $hayPptoGrupo ? (($tot - $totPpto) > 0 ? '+' : '').($tot - $totPpto) : '—' }}</td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ep-pg" id="ep-bajas">
        <div class="ep-card">
            <div class="ep-ct">Bajas {{ $reporte['periodo_bajas'] }}</div>
            <div class="ep-chips">
                <button type="button" class="ep-chip ep-mot" data-motivo="">Todas</button>
                @foreach ($reporte['motivos'] as $motivo)
                    <button type="button" class="ep-chip ep-mot" data-motivo="{{ $motivo['nombre'] }}">{{ $motivo['nombre'] }} · {{ $motivo['cantidad'] }}</button>
                @endforeach
            </div>
            <div class="ep-twrap">
                <table id="ep-bajas">
                    <thead>
                        <tr>
                            <th>Legajo</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Centro de costos</th>
                            <th>Motivo</th>
                            <th>Fecha</th>
                            <th class="ep-n">Años</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reporte['bajas'] as $baja)
                            <tr data-motivo="{{ $baja['motivo'] }}">
                                <td>{{ $baja['legajo'] }}</td>
                                <td>
                                    @if ($reporte['url_empleado'])
                                        <a class="ep-link" target="_blank" rel="noopener" href="{{ str_replace('__ID__', (string) $baja['id'], $reporte['url_empleado']) }}">{{ $baja['nombre'] }}</a>
                                    @else
                                        {{ $baja['nombre'] }}
                                    @endif
                                </td>
                                <td>{{ $baja['sigla'] }}</td>
                                <td>{{ $baja['cc'] }}</td>
                                <td>{{ $baja['motivo'] }}</td>
                                <td>{{ $baja['fecha_label'] }}</td>
                                <td class="ep-n">{{ $baja['anios'] === null ? '—' : number_format($baja['anios'], 1, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ep-muted">No hay bajas en el año hasta este corte.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ep-pg" id="ep-rotacion">
        <div class="ep-grid2">
            <div class="ep-card">
                <div class="ep-ct">Bajas por mes — {{ $reporte['anio'] }}</div>
                <div class="ep-mc">
                    @foreach ($reporte['bajas_por_mes'] as $bm)
                        <div class="ep-mc-c">
                            <div class="ep-mc-b" style="height:{{ max(4, round($bm['cantidad'] * 70 / $maxMes)) }}px"></div>
                            <div class="ep-mc-l">{{ $bm['label'] }}</div>
                            <strong>{{ $bm['cantidad'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="ep-card">
                <div class="ep-ct">Altas de {{ $reporte['mes_label'] }}</div>
                <div class="ep-twrap" style="max-height:220px">
                    <table>
                        <thead><tr><th>Legajo</th><th>Nombre</th><th>Centro</th><th>Ingreso</th></tr></thead>
                        <tbody>
                            @forelse ($reporte['altas'] as $alta)
                                <tr>
                                    <td>{{ $alta['legajo'] }}</td>
                                    <td>{{ $alta['nombre'] }}</td>
                                    <td>{{ $alta['cc'] }}</td>
                                    <td>{{ $alta['fecha_label'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ep-muted">Sin altas en el mes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="ep-card">
            <div class="ep-ct">Rotación por área — {{ $reporte['periodo_bajas'] }}</div>
            <p class="ep-note">Índice = bajas del año / promedio entre la dotación al 31/12 del año anterior y la dotación de este corte.</p>
            <div class="ep-twrap">
                <table>
                    <thead>
                        <tr>
                            <th>Centro de costos</th>
                            <th>Dirección</th>
                            <th class="ep-n">Hoy</th>
                            <th class="ep-n">1/1</th>
                            <th class="ep-n">Bajas</th>
                            <th class="ep-n">Promedio</th>
                            <th class="ep-n">Rotación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reporte['rotacion'] as $fila)
                            <tr>
                                <td>{{ $fila['nombre'] }}</td>
                                <td>{{ $fila['direccion'] }}</td>
                                <td class="ep-n">{{ $fila['dotacion'] }}</td>
                                <td class="ep-n">{{ $fila['inicio_anio'] }}</td>
                                <td class="ep-n">{{ $fila['bajas'] }}</td>
                                <td class="ep-n">{{ number_format($fila['promedio'], 1, ',', '.') }}</td>
                                <td class="ep-n {{ $fila['indice'] >= 20 ? 'ep-var-neg' : '' }}">{{ number_format($fila['indice'], 1, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ep-pg" id="ep-composicion">
        <div class="ep-grid2">
            <div class="ep-card">
                <div class="ep-ct">Género</div>
                <div class="ep-donut-w">
                    <div class="ep-donut" style="background:conic-gradient(#2A6DB5 0 {{ $corteGeneroM }}%, #C9603A {{ $corteGeneroM }}% {{ $corteGeneroF }}%, #C8C2BA {{ $corteGeneroF }}% 100%)"></div>
                    <div>
                        <div class="ep-leg"><span class="ep-dot" style="background:#2A6DB5"></span> Masculino <strong>{{ $genero['m'] }}</strong> <span class="ep-muted">{{ number_format($genero['m_pct'], 1, ',', '.') }}%</span></div>
                        <div class="ep-leg"><span class="ep-dot" style="background:#C9603A"></span> Femenino <strong>{{ $genero['f'] }}</strong> <span class="ep-muted">{{ number_format($genero['f_pct'], 1, ',', '.') }}%</span></div>
                        <div class="ep-leg"><span class="ep-dot" style="background:#C8C2BA"></span> Sin dato <strong>{{ $genero['nd'] }}</strong></div>
                    </div>
                </div>
            </div>
            <div class="ep-card">
                <div class="ep-ct">Convenio</div>
                @php $maxConv = 1; foreach ($reporte['composicion']['convenio'] as $item) { $maxConv = max($maxConv, (int) $item['cantidad']); } @endphp
                @foreach ($reporte['composicion']['convenio'] as $item)
                    <div class="ep-bar">
                        <div class="ep-bar-l">{{ $item['nombre'] }}</div>
                        <div class="ep-bar-t"><div class="ep-bar-f" style="width:{{ round($item['cantidad'] * 100 / $maxConv) }}%;background:var(--ep-olive)"></div></div>
                        <div class="ep-bar-v">{{ $item['cantidad'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="ep-grid2">
            <div class="ep-card">
                <div class="ep-ct">Edad</div>
                @php $maxEdad = 1; foreach ($reporte['composicion']['edad'] as $item) { $maxEdad = max($maxEdad, (int) $item['cantidad']); } @endphp
                @foreach ($reporte['composicion']['edad'] as $item)
                    <div class="ep-bar">
                        <div class="ep-bar-l">{{ $item['nombre'] }}</div>
                        <div class="ep-bar-t"><div class="ep-bar-f" style="width:{{ round($item['cantidad'] * 100 / $maxEdad) }}%;background:var(--ep-navy)"></div></div>
                        <div class="ep-bar-v">{{ $item['cantidad'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="ep-card">
                <div class="ep-ct">Antigüedad</div>
                @php $maxAnt = 1; foreach ($reporte['composicion']['antiguedad'] as $item) { $maxAnt = max($maxAnt, (int) $item['cantidad']); } @endphp
                @foreach ($reporte['composicion']['antiguedad'] as $item)
                    <div class="ep-bar">
                        <div class="ep-bar-l">{{ $item['nombre'] }}</div>
                        <div class="ep-bar-t"><div class="ep-bar-f" style="width:{{ round($item['cantidad'] * 100 / $maxAnt) }}%;background:var(--ep-gold)"></div></div>
                        <div class="ep-bar-v">{{ $item['cantidad'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="ep-card">
            <div class="ep-ct">Conducción</div>
            <p class="ep-note">Categorías con director o manager. Siguen contadas en el centro de costo del consolidado.</p>
            <div class="ep-twrap">
                <table>
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            @foreach ($siglas as $sigla)
                                <th class="ep-n">{{ $sigla }}</th>
                            @endforeach
                            <th class="ep-n">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reporte['composicion']['conduccion'] as $fila)
                            <tr>
                                <td>{{ $fila['nombre'] }}</td>
                                @foreach ($siglas as $sigla)
                                    <td class="ep-n">{{ (int) ($fila['por_sigla'][$sigla] ?? 0) }}</td>
                                @endforeach
                                <td class="ep-n"><strong>{{ $fila['total'] }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 2 + count($siglas) }}" class="ep-muted">Sin categorías de conducción en el corte.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ep-pg" id="ep-base">
        <div class="ep-card">
            <div class="ep-ct">Nómina vigente</div>
            <div class="ep-fbar">
                <input type="search" id="ep-q" class="ep-q" placeholder="Nombre, legajo o centro de costos">
                <button type="button" class="ep-chip ep-cc" data-cc="">Todos los centros</button>
                @foreach ($reporte['sectores'] as $sector)
                    <button type="button" class="ep-chip ep-cc" data-cc="{{ $sector['nombre'] }}">{{ $sector['nombre'] }}</button>
                @endforeach
            </div>
            <div class="ep-twrap">
                <table id="ep-base">
                    <thead>
                        <tr>
                            <th>Legajo</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Categoría</th>
                            <th>Centro de costos</th>
                            <th>Sindicato</th>
                            <th>Ingreso</th>
                            <th class="ep-n">Años</th>
                            <th>Sexo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reporte['base'] as $persona)
                            <tr data-cc="{{ $persona['cc'] }}" data-txt="{{ mb_strtolower($persona['nombre'].' '.$persona['legajo'].' '.$persona['cc'].' '.$persona['categoria']) }}">
                                <td>{{ $persona['legajo'] }}</td>
                                <td>
                                    @if ($reporte['url_empleado'])
                                        <a class="ep-link" target="_blank" rel="noopener" href="{{ str_replace('__ID__', (string) $persona['id'], $reporte['url_empleado']) }}">{{ $persona['nombre'] }}</a>
                                    @else
                                        {{ $persona['nombre'] }}
                                    @endif
                                    @if ($persona['confidencial'])
                                        <span class="ep-badge ep-badge-fin">Conf.</span>
                                    @endif
                                </td>
                                <td>{{ $persona['sigla'] }}</td>
                                <td>{{ $persona['categoria'] }}</td>
                                <td>{{ $persona['cc'] }}</td>
                                <td>{{ $persona['sindicato'] }}</td>
                                <td>{{ $persona['ingreso'] }}</td>
                                <td class="ep-n">{{ $persona['anios'] === null ? '—' : number_format($persona['anios'], 1, ',', '.') }}</td>
                                <td>{{ $persona['sexo'] !== '' ? $persona['sexo'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
