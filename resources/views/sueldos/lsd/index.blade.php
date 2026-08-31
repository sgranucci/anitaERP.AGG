@extends("theme.$theme.layout")
@section('titulo')
    Libro de Sueldos Digital
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/lsd/filtro.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/lsd/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/lsd/form.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/lsd/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\LsdPresentacionListadoFiltros; ?>

@section('contenido')
@php
    $unaEmpresa = ($empresa_query ?? collect())->count() === 1;
    $empresaSel = $filtros['empresa_id'] ?? optional(($empresa_query ?? collect())->first())->id;
    $periodoDefault = $filtros['periodo'] ?? (int) date('Ym');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')

        <div class="card card-outline card-info mb-3">
            <div class="card-header py-2">
                <h3 class="card-title"><i class="fa fa-link"></i> Parametrización de conceptos (fase 0)</h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual-lsd')
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="mb-1">
                            Conceptos exportables mapeados:
                            <strong>{{ $cobertura['mapeados'] }}</strong> / {{ $cobertura['exportables'] }}
                            ({{ number_format($cobertura['porcentaje'], 1, ',', '') }}%)
                            @if ($cobertura['sin_mapeo'] > 0)
                                · <span class="text-danger">{{ $cobertura['sin_mapeo'] }} sin código AFIP</span>
                            @endif
                        </p>
                        <p class="text-muted small mb-0">
                            Primero exporte el TXT de conceptos e impórtelo en ARCA → Conceptos.
                            Las contribuciones patronales no se exportan.
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="{{ route('cobertura_lsd_sueldos') }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-list"></i> Ver cobertura
                        </a>
                        @if ($puedeExportarConceptos)
                            <a href="{{ route('exportar_conceptos_lsd_sueldos') }}" class="btn btn-primary btn-sm" id="btn-exportar-conceptos-lsd">
                                <i class="fa fa-download"></i> Exportar TXT conceptos
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @php $wizard = $wizard ?? ['cobertura' => $cobertura, 'conceptos_exportado_at' => null, 'liquidaciones' => [], 'e_pendientes' => [], 'bloquea_mensual' => false]; @endphp
        <div class="card card-outline card-secondary mb-3">
            <div class="card-header py-2">
                <h3 class="card-title"><i class="fa fa-list-ol"></i> Circuito del período</h3>
            </div>
            <div class="card-body py-2">
                <ol class="mb-2 pl-3">
                    <li>
                        Exportar TXT de conceptos
                        @if (! empty($wizard['conceptos_exportado_at']))
                            <span class="text-success">— hecho {{ $wizard['conceptos_exportado_at'] }}</span>
                        @else
                            <span class="text-muted">— pendiente (bajar e importar en ARCA → Conceptos)</span>
                        @endif
                    </li>
                    <li>
                        Generar primero las liquidaciones tipo <strong>E</strong> (vacaciones, SAC, final)
                        @if (! empty($wizard['e_pendientes']))
                            <span class="text-danger">— faltan: {{ implode(', ', $wizard['e_pendientes']) }}</span>
                        @else
                            <span class="text-success">— no hay E pendientes</span>
                        @endif
                    </li>
                    <li>Recién entonces la mensual / quincena (M / Q)</li>
                    <li>Importar cada TXT en ARCA y marcar presentada. No se regenera una presentada (usar RE).</li>
                </ol>
                @if (($wizard['liquidaciones'] ?? []) !== [])
                    <p class="small text-muted mb-1">Cerradas del período {{ $periodoDefault }} (orden ARCA):</p>
                    <ul class="small mb-0">
                        @foreach ($wizard['liquidaciones'] as $wl)
                            <li>
                                {{ $wl['tipo_afip'] }} · #{{ $wl['numero'] }} {{ $wl['descripcion'] }}
                                @if ($wl['presentada'])
                                    <span class="text-success">presentada</span>
                                @elseif ($wl['generada'])
                                    <span class="text-info">generada</span>
                                @else
                                    <span class="text-muted">sin TXT</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        @if ($puedeGenerar)
        <div class="card card-primary card-outline mb-3">
            <div class="card-header py-2">
                <h3 class="card-title"><i class="fa fa-file-export"></i> Generar liquidación (TXT 01–06)</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('generar_lsd_sueldos') }}" method="POST" id="form-generar-lsd" class="form-horizontal">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1" for="empresa_id">Empresa</label>
                            @if ($unaEmpresa)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresaSel }}">
                                <input type="text" class="form-control form-control-sm" value="{{ optional(($empresa_query ?? collect())->first())->nombre }}" readonly>
                            @else
                                <select name="empresa_id" id="empresa_id" class="form-control form-control-sm" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresa_query as $emp)
                                        <option value="{{ $emp->id }}" {{ (int) $empresaSel === (int) $emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="periodo_generar">Período AAAAMM</label>
                            <input type="number" id="periodo_generar" class="form-control form-control-sm" min="200001" max="209912"
                                   value="{{ $periodoDefault }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small mb-1" for="liquidacion_id">Liquidación cerrada</label>
                            <select name="liquidacion_id" id="liquidacion_id" class="form-control form-control-sm" required>
                                <option value="">Seleccione período…</option>
                            </select>
                        </div>
                        <div class="form-group col-md-1">
                            <label class="small mb-1" for="nro_liquidacion_afip">Nro AFIP</label>
                            <input type="number" name="nro_liquidacion_afip" id="nro_liquidacion_afip" class="form-control form-control-sm"
                                   min="1" max="99999" value="{{ $proximoNro }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="identificacion">Envío</label>
                            <select name="identificacion" id="identificacion" class="form-control form-control-sm">
                                <option value="SJ">SJ — Libro + F.931</option>
                                <option value="RE">RE — Rectificativa F.931</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="fecha_pago">Fecha de pago</label>
                            <input type="date" name="fecha_pago" id="fecha_pago" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="fecha_rubrica">Fecha de rúbrica</label>
                            <input type="date" name="fecha_rubrica" id="fecha_rubrica" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" name="incluir_licencias_sin_recibo" id="incluir_licencias_sin_recibo" value="1">
                                <label class="custom-control-label" for="incluir_licencias_sin_recibo">Incluir licencias sin recibo (solo 04)</label>
                            </div>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm btn-block mb-2">
                                <i class="fa fa-cogs"></i> Generar y previsualizar
                            </button>
                        </div>
                    </div>
                </form>
                <p class="text-muted small mb-0">
                    Un archivo por liquidación. Importe el TXT en ARCA → Liquidaciones y DDJJ (ANSI).
                    El PDF del libro y el F.931 los emite el organismo.
                </p>
            </div>
        </div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Presentaciones LSD</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.sueldos.boton-manual-lsd')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-lsd',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => LsdPresentacionListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_lsd_sueldos'),
                        'placeholder' => 'Buscar (nro AFIP, archivo)…',
                        'toggleTarget' => '#panel-filtros-lsd',
                        'toggleId' => 'btn-toggle-filtros-lsd',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_lsd_sueldos') }}" id="form-filtros-lsd" class="mb-0">
                <div class="collapse border-bottom" id="panel-filtros-lsd" data-listado-filtros-panel>
                    <div class="card-body bg-light py-2 text-body">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small mb-1" for="filtro_valor_panel">Buscar</label>
                                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm"
                                       value="{{ $filtros['valor'] ?? '' }}" placeholder="Nro AFIP, archivo" autocomplete="off">
                            </div>
                            @if (! $unaEmpresa)
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small mb-1" for="empresa_id_filtro">Empresa</label>
                                <select name="empresa_id" id="empresa_id_filtro" class="form-control form-control-sm">
                                    <option value="">Todas mis empresas</option>
                                    @foreach ($empresa_query as $emp)
                                        <option value="{{ $emp->id }}" {{ (int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="form-group col-md-2 col-sm-6 mb-2">
                                <label class="small mb-1" for="filtro_periodo">Período</label>
                                <select name="filtro_periodo" id="filtro_periodo" class="form-control form-control-sm">
                                    <option value="">Todos</option>
                                    @foreach ($periodos as $per)
                                        <option value="{{ $per }}" {{ (int) ($filtros['periodo'] ?? 0) === (int) $per ? 'selected' : '' }}>{{ $per }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 col-sm-6 mb-2">
                                <label class="small mb-1" for="filtro_estado">Estado</label>
                                <select name="filtro_estado" id="filtro_estado" class="form-control form-control-sm">
                                    <option value="">Todos</option>
                                    <option value="generada" {{ ($filtros['estado'] ?? '') === 'generada' ? 'selected' : '' }}>Generada</option>
                                    <option value="presentada" {{ ($filtros['estado'] ?? '') === 'presentada' ? 'selected' : '' }}>Presentada</option>
                                    <option value="rechazada" {{ ($filtros['estado'] ?? '') === 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <button type="submit" class="btn btn-sm btn-primary btn-block">Aplicar filtros</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="card-body p-0">
                <div class="px-3 pt-2">
                    @include('includes.exportar-tabla-queryparams', ['ruta' => 'lista_lsd_sueldos', 'queryparams' => $filtrosQuery ?? []])
                </div>
                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-sm table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Período</th>
                                <th>Nro AFIP</th>
                                <th>Envío</th>
                                <th>Liquidación</th>
                                <th>Estado</th>
                                <th class="text-right">Reg. 04</th>
                                <th>Fecha pago</th>
                                <th>Empresa</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($datas as $p)
                                <tr>
                                    <td>{{ $p->periodo }}</td>
                                    <td>{{ $p->nro_liquidacion_afip }}</td>
                                    <td>{{ $p->identificacion }}</td>
                                    <td>
                                        @if ($p->liquidacion_id && can('listar-liquidacion-sueldos', false))
                                            <a class="text-primary" target="_blank" rel="noopener"
                                               href="{{ route('resultado_liquidacion_sueldos', $p->liquidacion_id) }}">
                                                {{ optional($p->liquidacion)->numero }} {{ optional($p->liquidacion)->descripcion }}
                                            </a>
                                        @else
                                            {{ optional($p->liquidacion)->numero }}
                                        @endif
                                    </td>
                                    <td>{{ $p->estadoLabel() }}</td>
                                    <td class="text-right">{{ $p->cantidad_registros_04 }}</td>
                                    <td>{{ optional($p->fecha_pago)->format('d/m/Y') }}</td>
                                    <td>{{ $p->nombreempresa }}</td>
                                    <td class="text-nowrap">
                                        @if ($puedeVer)
                                            <a class="btn-accion-tabla tooltipsC" title="Ver" href="{{ route('ver_lsd_sueldos', $p->id) }}">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if ($puedeVer)
                                            <a class="btn-accion-tabla tooltipsC" title="Descargar TXT" href="{{ route('descargar_lsd_sueldos', $p->id) }}">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No hay presentaciones LSD.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (method_exists($datas, 'links'))
                <div class="card-footer">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'lsd-proceso-overlay',
    'tituloId' => 'lsd-proceso-titulo',
    'subtituloId' => 'lsd-proceso-subtitulo',
    'titulo' => 'Generando archivo LSD…',
    'subtitulo' => 'Puede demorar según la cantidad de recibos. No cierre la página.',
])
<script>
window.lsdLiquidacionesUrl = @json(route('liquidaciones_periodo_lsd_sueldos'));
window.lsdBloqueaMensual = @json((bool) ($wizard['bloquea_mensual'] ?? false));
window.lsdEPendientes = @json($wizard['e_pendientes'] ?? []);
</script>
@endsection
