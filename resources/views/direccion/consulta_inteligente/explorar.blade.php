@extends("theme.$theme.layout")

@section('titulo')
    {{ $entidad->nombre() }} · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('scripts')
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ciJs = public_path('assets/pages/scripts/direccion/consulta_inteligente.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/direccion/consulta_inteligente.js') }}?v={{ file_exists($ciJs) ? filemtime($ciJs) : time() }}"></script>
@endsection

@section('contenido')
@php
    $camposQbeJson = [];
    foreach ($camposFiltro as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $etiquetas[$k] ?? ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
    $valorRapido = (string) ($filtros['valor'] ?? $filtros['busqueda'] ?? '');
@endphp
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente · {{ $entidad->modulo() }}</div>
            <h1>{{ $entidad->nombre() }}</h1>
            <p>{{ $entidad->descripcion() }}</p>
            <div class="ci-hero-meta">
                <span class="ci-badge"><i class="fas {{ $entidad->icono() }}"></i> {{ $entidad->recurso() }}</span>
                <span class="ci-badge">QBE multi-grupo</span>
            </div>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
            <a href="{{ route('tablero_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-th-large"></i> Tablero
            </a>
            <a href="{{ route('cruzar_consulta_inteligente', ['entidades' => [$entidad->clave()]]) }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-columns"></i> Cruce
            </a>
            <a href="{{ route('unir_consulta_inteligente', ['desde' => $entidad->clave()]) }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-link"></i> Join
            </a>
            <a href="{{ $urlNativa }}" class="btn btn-light btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-external-link-alt"></i> Workbench completo
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    <div class="card card-outline card-info mb-3 shadow-sm">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:.6rem;">
                <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                    <label class="mb-0 small text-muted" for="ci-vista-sel">Vista</label>
                    <select id="ci-vista-sel" class="form-control form-control-sm" style="width:auto;min-width:12rem;"
                            @if (! ($workbenchListo ?? false)) disabled @endif>
                        <option value="">Consulta actual</option>
                        @foreach (($vistas ?? []) as $v)
                            <option value="{{ $v->id }}" @selected(($vistaActiva->id ?? null) == $v->id)>
                                {{ $v->nombre }}{{ $v->es_default ? ' ★' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($vistaActiva)
                        <form method="post" action="{{ route('eliminar_vista_consulta_inteligente', ['entidad' => $entidad->clave(), 'id' => $vistaActiva->id]) }}"
                              class="d-inline" onsubmit="return confirm('¿Eliminar esta vista?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar vista">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    @endif
                </div>
                <div class="d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    @if ($consultado)
                        <a href="{{ route('explorar_consulta_inteligente', array_merge($filtrosQuery, ['entidad' => $entidad->clave(), 'refresh_dataset' => 1])) }}"
                           class="btn btn-sm btn-outline-secondary" title="Recalcular dataset (omite cache {{ \App\Support\ConsultaInteligente\ConsultaInteligenteDatasetSupport::TTL_SEGUNDOS }}s)">
                            <i class="fa fa-sync"></i>
                        </a>
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_consulta_inteligente',
                            'queryparams' => array_merge($filtrosQuery, ['entidad' => $entidad->clave()]),
                            'variant' => 'compact',
                        ])
                    @endif
                </div>
            </div>
            @if ($workbenchListo ?? false)
                <form method="post" action="{{ route('guardar_vista_consulta_inteligente', ['entidad' => $entidad->clave()]) }}"
                      class="mt-2" id="ci-form-guardar-vista">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-5 mb-0">
                            <label class="small text-muted mb-1" for="ci-vista-nombre">Guardar consulta como vista</label>
                            <input type="text" name="nombre" id="ci-vista-nombre" class="form-control form-control-sm"
                                   placeholder="Ej. Cheques para depositar" required maxlength="120">
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <div class="custom-control custom-checkbox mt-4">
                                <input type="checkbox" class="custom-control-input" id="ci-vista-default" name="es_default" value="1">
                                <label class="custom-control-label small" for="ci-vista-default">Default</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <div class="custom-control custom-checkbox mt-4">
                                <input type="checkbox" class="custom-control-input" id="ci-vista-compartida" name="compartida" value="1">
                                <label class="custom-control-label small" for="ci-vista-compartida">Compartida</label>
                            </div>
                        </div>
                        <div class="form-group col-md-1 mb-0">
                            <button type="submit" class="btn btn-sm btn-primary btn-block" title="Guardar vista">
                                <i class="fa fa-save"></i>
                            </button>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <button type="button" class="btn btn-sm btn-outline-info btn-block" data-toggle="collapse" data-target="#ci-disenador-columnas">
                                <i class="fa fa-columns"></i> Columnas
                            </button>
                        </div>
                    </div>
                    <div class="collapse mt-2" id="ci-disenador-columnas">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th style="width:3rem;">Ver</th>
                                        <th style="width:4rem;">Orden</th>
                                        <th>Columna</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (($grillaLayout ?? []) as $i => $col)
                                        <tr>
                                            <td class="text-center">
                                                <input type="hidden" name="grilla[{{ $i }}][key]" value="{{ $col['key'] }}">
                                                <input type="hidden" name="grilla[{{ $i }}][titulo]" value="{{ $col['titulo'] }}">
                                                <input type="hidden" name="grilla[{{ $i }}][ancho]" value="{{ $col['ancho'] ?? 100 }}">
                                                <input type="hidden" name="grilla[{{ $i }}][alinea]" value="{{ $col['alinea'] ?? 'izquierda' }}">
                                                <input type="hidden" name="grilla[{{ $i }}][visible]" value="0">
                                                <input type="checkbox" name="grilla[{{ $i }}][visible]" value="1"
                                                       @checked(! empty($col['visible']))>
                                            </td>
                                            <td>
                                                <input type="number" name="grilla[{{ $i }}][orden]" class="form-control form-control-sm"
                                                       value="{{ (int) ($col['orden'] ?? $i) }}" min="0" step="1">
                                            </td>
                                            <td class="small">{{ $col['titulo'] ?? $col['key'] }} <span class="text-muted">({{ $col['key'] }})</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-1">El orden y las columnas visibles se guardan con la vista.</div>
                    </div>
                </form>
            @endif

            <form method="post" action="{{ route('programar_envio_consulta_inteligente', ['entidad' => $entidad->clave()]) }}"
                  class="form-row align-items-end mt-2" id="ci-form-envio">
                @csrf
                <input type="hidden" name="filtros_json" value="{{ e(json_encode($filtrosQuery ?? [])) }}">
                <div class="form-group col-md-5 mb-0">
                    <label class="small text-muted mb-1" for="ci-envio-email">Programar Excel por mail</label>
                    <input type="email" name="email" id="ci-envio-email" class="form-control form-control-sm"
                           value="{{ auth()->user()->email ?? '' }}" required>
                </div>
                <div class="form-group col-md-3 mb-0">
                    <label class="small text-muted mb-1" for="ci-envio-freq">Frecuencia</label>
                    <select name="frecuencia" id="ci-envio-freq" class="form-control form-control-sm">
                        <option value="diario">Diario</option>
                        <option value="semanal">Semanal (lunes)</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-0">
                    <button type="submit" class="btn btn-sm btn-outline-success btn-block">
                        <i class="fa fa-envelope"></i> Programar
                    </button>
                </div>
            </form>

            <form method="post" action="{{ route('pin_tablero_consulta_inteligente', ['entidad' => $entidad->clave()]) }}"
                  class="form-row align-items-end mt-2" id="ci-form-pin">
                @csrf
                <input type="hidden" name="filtros_json" value="{{ e(json_encode($filtrosQuery ?? [])) }}">
                <div class="form-group col-md-4 mb-0">
                    <label class="small text-muted mb-1" for="ci-pin-titulo">Fijar en tablero personal</label>
                    <input type="text" name="titulo" id="ci-pin-titulo" class="form-control form-control-sm"
                           value="{{ $entidad->nombre() }}" maxlength="80" required>
                </div>
                <div class="form-group col-md-2 mb-0">
                    <label class="small text-muted mb-1" for="ci-pin-tipo">Tipo</label>
                    <select name="tipo" id="ci-pin-tipo" class="form-control form-control-sm"
                            @if (! empty($esPuenteReporte)) disabled @endif>
                        <option value="atajo">Atajo</option>
                        <option value="kpi">KPI</option>
                        @if (! empty($soportaAging))
                            <option value="aging">Aging (antigüedad)</option>
                        @endif
                        @if (! empty($soportaGraficoTemporal))
                            <option value="barras">Gráfico barras</option>
                            <option value="linea">Gráfico línea</option>
                            <option value="torta">Gráfico torta</option>
                        @endif
                    </select>
                    @if (! empty($esPuenteReporte))
                        <input type="hidden" name="tipo" value="atajo">
                    @endif
                </div>
                @if (! empty($campoMedidaSuma))
                    <div class="form-group col-md-2 mb-0" id="ci-pin-medida-wrap">
                        <label class="small text-muted mb-1" for="ci-pin-medida">Medida</label>
                        <select name="medida" id="ci-pin-medida" class="form-control form-control-sm">
                            <option value="conteo">Conteo</option>
                            <option value="suma">Suma ({{ $campoMedidaSuma }})</option>
                            <option value="promedio">Promedio ({{ $campoMedidaSuma }})</option>
                        </select>
                    </div>
                @endif
                <div class="form-group col-md-2 mb-0">
                    <button type="submit" class="btn btn-sm btn-outline-primary btn-block">
                        <i class="fas fa-thumbtack"></i> Fijar
                    </button>
                </div>
                <div class="form-group col-md-2 mb-0">
                    <a href="{{ route('tablero_consulta_inteligente') }}" class="btn btn-sm btn-link">Ver tablero</a>
                </div>
                <div class="form-group col-md-3 mb-0">
                    <label class="small text-muted mb-1 d-block">&nbsp;</label>
                    <label class="small mb-0 text-muted">
                        <input type="checkbox" name="compartir" value="1" id="ci-pin-compartir">
                        Compartir con otros
                    </label>
                </div>
                <div class="form-group col-md-3 mb-0" id="ci-pin-empresa-wrap" style="display:none;">
                    <label class="small text-muted mb-1" for="ci-pin-empresa">Empresa (opcional)</label>
                    <select name="compartido_empresa_id" id="ci-pin-empresa" class="form-control form-control-sm">
                        <option value="">Todas mis empresas / global</option>
                        @foreach (($empresasPin ?? []) as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @if (empty($esPuenteReporte) && empty($soportaGraficoTemporal))
                    <div class="col-12">
                        <p class="small text-muted mb-0 mt-1">
                            Esta entidad no tiene fecha para gráfico temporal: usá Atajo o KPI.
                        </p>
                    </div>
                @elseif (! empty($soportaGraficoTemporal))
                    <div class="col-12">
                        <p class="small text-muted mb-0 mt-1">
                            Barras/línea = por mes (últimos 6). Torta = distribución por mes. Aging = buckets de vencimiento (cheques).
                            KPI admite conteo, suma o promedio. Compartido: otros con permiso lo ven (solo lectura).
                        </p>
                    </div>
                @endif
            </form>
            <script>
            (function () {
                var chk = document.getElementById('ci-pin-compartir');
                var wrap = document.getElementById('ci-pin-empresa-wrap');
                if (chk && wrap) {
                    function sync() { wrap.style.display = chk.checked ? '' : 'none'; }
                    chk.addEventListener('change', sync);
                    sync();
                }
                var tipo = document.getElementById('ci-pin-tipo');
                var med = document.getElementById('ci-pin-medida-wrap');
                if (tipo && med) {
                    function syncMed() {
                        var t = tipo.value;
                        med.style.display = (t === 'barras' || t === 'linea' || t === 'torta' || t === 'kpi') ? '' : 'none';
                    }
                    tipo.addEventListener('change', syncMed);
                    syncMed();
                }
            })();
            </script>

            @if ($consultado && empty($esPuenteReporte))
            <form method="post" action="{{ route('guardar_snapshot_consulta_inteligente', ['entidad' => $entidad->clave()]) }}"
                  class="form-row align-items-end mt-2" id="ci-form-snapshot">
                @csrf
                <input type="hidden" name="filtros_json" value="{{ e(json_encode($filtrosQuery ?? [])) }}">
                <div class="form-group col-md-5 mb-0">
                    <label class="small text-muted mb-1" for="ci-snap-titulo">Guardar snapshot (consulta pesada)</label>
                    <input type="text" name="titulo" id="ci-snap-titulo" class="form-control form-control-sm"
                           value="{{ $entidad->nombre() }} {{ now()->format('d/m H:i') }}" maxlength="120" required>
                </div>
                <div class="form-group col-md-3 mb-0">
                    <button type="submit" class="btn btn-sm btn-outline-secondary btn-block">
                        <i class="fa fa-camera"></i> Guardar snapshot
                    </button>
                </div>
                <div class="form-group col-md-3 mb-0">
                    <a href="{{ route('snapshots_consulta_inteligente') }}" class="btn btn-sm btn-link">Ver snapshots</a>
                </div>
            </form>
            @endif
            <p class="small text-muted mb-0 mt-1">
                El cron <code>direccion:enviar-consultas-inteligentes</code> corre a las 07:10 (diario; semanal = lunes).
            </p>
            @if (! empty($enviosProgramados) && $enviosProgramados->isNotEmpty())
                <div class="small text-muted mt-2">
                    Activos:
                    @foreach ($enviosProgramados as $envio)
                        <span class="badge badge-light" title="Último: {{ $envio->ultimo_envio_at?->format('d/m/Y H:i') ?? 'nunca' }}">
                            {{ $envio->email }} · {{ $envio->frecuencia }}
                            @if ($envio->ultimo_envio_at)
                                · ok {{ $envio->ultimo_envio_at->format('d/m') }}
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($plantillas !== [])
        <div class="ci-atajos">
            @foreach ($plantillas as $atajo)
                <a href="{{ $atajo['url'] }}" class="ci-atajo" title="{{ $atajo['descripcion'] }}">
                    {{ $atajo['titulo'] }}
                </a>
            @endforeach
        </div>
    @endif

    @if (! empty($cruces))
        <div class="ci-atajos" aria-label="Cruces relacionados">
            @foreach ($cruces as $cruce)
                <a href="{{ $cruce['url'] }}" class="ci-atajo" title="{{ $cruce['descripcion'] }}"
                   style="border-color: {{ $cruce['color'] }};">
                    <span class="ci-atajo-dot" style="background:{{ $cruce['color'] }}"></span>
                    {{ $cruce['titulo'] }}
                </a>
            @endforeach
        </div>
    @endif

    @if (! empty($esPuenteReporte))
    <div class="card card-info shadow-sm">
        <div class="card-header"><strong><i class="fa fa-book mr-1"></i> Abrir reporte Contable</strong></div>
        <div class="card-body">
            <p class="ci-hint">{{ $ayudaPuente }}</p>
            <form method="get" action="{{ route('explorar_consulta_inteligente', ['entidad' => $entidad->clave()]) }}" class="form-row align-items-end">
                <input type="hidden" name="abrir_reporte" value="1">
                <input type="hidden" name="modo_periodo" value="mes">
                <div class="form-group col-md-3 mb-0">
                    <label class="small text-muted mb-1" for="ci-mayor-mes">Mes</label>
                    <select name="mes" id="ci-mayor-mes" class="form-control">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) ($filtros['mes'] ?? date('n')) === $m)>{{ $m }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-group col-md-3 mb-0">
                    <label class="small text-muted mb-1" for="ci-mayor-anio">Año</label>
                    <input type="number" name="anio" id="ci-mayor-anio" class="form-control"
                           value="{{ (int) ($filtros['anio'] ?? date('Y')) }}" min="2000" max="2100">
                </div>
                <div class="form-group col-md-4 mb-0">
                    <button type="submit" class="btn btn-info btn-block">
                        <i class="fa fa-external-link-alt"></i> Abrir mayor nativo
                    </button>
                </div>
            </form>
            @if (! empty($urlReporte))
                <div class="mt-2">
                    <a href="{{ $urlReporte }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        Abrir en solapa nueva
                    </a>
                </div>
            @endif
        </div>
    </div>
    @else
    <div class="card card-info ci-result-card shadow-sm">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="gap:.5rem;">
            <strong><i class="fa fa-filter mr-1"></i> Criterios</strong>
            <button type="button" class="btn btn-sm btn-outline-info" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="true">
                Mostrar / ocultar QBE
            </button>
        </div>
        <div class="card-body">
            <form method="get" action="{{ route('explorar_consulta_inteligente', ['entidad' => $entidad->clave()]) }}"
                  id="ci-form-explorar" class="lw-workbench">
                <input type="hidden" name="consultar" value="1">
                <input type="hidden" name="filtro_modo" value="qbe">

                <div class="form-row align-items-end mb-3">
                    <div class="form-group col-md-8 mb-md-0">
                        <label class="small text-muted mb-1" for="ci-filtro-valor">Búsqueda rápida</label>
                        <input type="text" name="filtro_valor" id="ci-filtro-valor" class="form-control"
                               value="{{ $valorRapido }}"
                               placeholder="Texto o número (Enter consulta)">
                    </div>
                    <div class="form-group col-md-4 mb-0">
                        <button type="submit" class="btn btn-info btn-block" id="btn-lw-aplicar-qbe">
                            <i class="fa fa-search"></i> Consultar
                        </button>
                    </div>
                </div>

                <div class="lw-qbe collapse show" id="lw-qbe-panel"
                     data-ops-texto='@json($opsTexto)'
                     data-ops-entero='@json($opsEntero)'
                     data-ops-fecha='@json($opsFecha)'
                     data-ops-decimal='@json($opsDecimal)'
                     data-ops-bool='@json($opsBool)'
                     data-campos='@json($camposQbeJson)'>
                    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="1">
                    <div class="lw-qbe-title mb-2">
                        <div>
                            <h4 class="h6 mb-1"><i class="fa fa-filter text-info"></i> Consulta avanzada (QBE)</h4>
                            <div class="lw-hint">Misma lógica que el workbench del módulo. Grupos Y/O, NOT y operadores por tipo de campo.</div>
                        </div>
                        <div class="d-flex" style="gap:.35rem;">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-add-criterio">
                                <i class="fa fa-plus"></i> Criterio
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-add-grupo">
                                <i class="fa fa-plus"></i> Grupo
                            </button>
                        </div>
                    </div>
                    @include('includes.listado.workbench_qbe_grupos', [
                        'qbeEstructura' => $qbeEstructura,
                        'camposQbe' => $camposFiltro,
                        'etiquetasColumnas' => $etiquetas,
                        'opsTexto' => $opsTexto,
                        'opsEntero' => $opsEntero,
                        'opsFecha' => $opsFecha,
                        'opsDecimal' => $opsDecimal,
                        'opsBool' => $opsBool,
                    ])
                </div>
            </form>
        </div>
    </div>

    <div class="card card-info shadow-sm mt-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:.5rem;">
            <strong>
                Resultado
                @if ($consultado && $filas)
                    <span class="badge badge-light ml-1">{{ $filas->total() }} registro(s)</span>
                @endif
            </strong>
            @if ($consultado && $filas && $filas->total() > 0)
                <span class="small text-muted">
                    {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                </span>
            @endif
        </div>
        <div class="card-body p-0">
            @if (! $consultado)
                <div class="p-4 ci-hint text-center">
                    <i class="fa fa-compass fa-2x mb-2 d-block"></i>
                    Armá un criterio o usá un atajo de arriba, y pulsá <strong>Consultar</strong>.
                </div>
            @elseif (! $filas || $filas->total() === 0)
                <div class="p-4 text-center text-muted">
                    No hay registros para estos filtros.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 ci-tabla" id="tabla-paginada">
                        <thead>
                            <tr>
                                @foreach ($columnasVisibles as $keyCol)
                                    <th>{{ $etiquetas[$keyCol] ?? $keyCol }}</th>
                                @endforeach
                                <th style="width:3.5rem;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $row)
                                <tr>
                                    @foreach ($columnasVisibles as $keyCol)
                                        <td>{!! $entidad->valorCelda($row, $keyCol) !!}</td>
                                    @endforeach
                                    <td class="text-nowrap text-center">
                                        @php
                                            $urlDet = $entidad->urlDetalle($row);
                                            $linksCruce = \App\Support\ConsultaInteligente\ConsultaInteligenteJoinCatalogo::linksDesdeFila($entidad->clave(), $row);
                                        @endphp
                                        @if ($urlDet)
                                            <a href="{{ $urlDet }}" class="btn-accion-tabla tooltipsC text-primary"
                                               target="_blank" rel="noopener" title="Consultar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @foreach ($linksCruce as $lc)
                                            <a href="{{ $lc['url'] }}" class="btn-accion-tabla tooltipsC text-info"
                                               title="{{ $lc['titulo'] }}">
                                                <i class="fa fa-link"></i>
                                            </a>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($consultado && $filas && method_exists($filas, 'links'))
            <div class="card-footer">
                {{ $filas->appends($filtrosQuery)->links() }}
            </div>
        @endif
    </div>
    @endif
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ci-proceso-overlay',
    'tituloId' => 'ci-proceso-titulo',
    'subtituloId' => 'ci-proceso-subtitulo',
    'titulo' => 'Consultando…',
    'subtitulo' => 'Puede demorar según los filtros. No cierre la página.',
])
@endsection
