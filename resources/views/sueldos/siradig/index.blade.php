@extends("theme.$theme.layout")
@section('titulo')
    SiRADIG - F572
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/siradig/filtro.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/siradig/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/siradig/importar.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/siradig/importar.js')) ?: time() }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\SiradigListadoFiltros; use App\Support\Sueldos\SiradigTablas; ?>

@section('contenido')
@php
    $unaEmpresa = ($empresa_query ?? collect())->count() === 1;
    $empresaSel = $filtros['empresa_id'] ?? optional(($empresa_query ?? collect())->first())->id;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')

        @if ($puedeImportar)
        <div class="card card-primary card-outline">
            <div class="card-header py-2">
                <h3 class="card-title"><i class="fa fa-file-import"></i> Importar F572 (SiRADIG - ARCA)</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('importar_siradig_sueldos') }}" method="POST" enctype="multipart/form-data" id="form-importar-siradig" class="form-row align-items-end">
                    @csrf
                    <div class="form-group col-md-4 mb-2">
                        <label class="small mb-1" for="empresa_id">Empresa (agente de retención)</label>
                        @if ($unaEmpresa)
                            <input type="hidden" name="empresa_id" value="{{ $empresaSel }}">
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
                    <div class="form-group col-md-5 mb-2">
                        <label class="small mb-1" for="archivo">Archivo XML o ZIP (resultadosXML.zip de ARCA)</label>
                        <input type="file" name="archivo" id="archivo" class="form-control form-control-sm" accept=".xml,.zip" required>
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                            <i class="fa fa-upload"></i> Importar
                        </button>
                    </div>
                </form>
                <p class="text-muted small mb-0">
                    Descargue desde ARCA &rarr; <strong>SiRADIG Empleador</strong> el archivo del período (un XML por empleado, o el ZIP con todos).
                    La última presentación reemplaza (marca como vigente) la anterior del mismo año fiscal; el histórico se conserva.
                </p>
            </div>
        </div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Presentaciones F572</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-siradig',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => SiradigListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_siradig_sueldos'),
                        'placeholder' => 'Buscar (CUIL, apellido, nombre)…',
                        'toggleTarget' => '#panel-filtros-siradig',
                        'toggleId' => 'btn-toggle-filtros-siradig',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_siradig_sueldos') }}" id="form-filtros-siradig" class="mb-0">
                @php
                    $fScope = $filtros['empresa_scope'] ?? 'una';
                    $fEmp = $filtros['empresa_id'] ?? null;
                @endphp
                <div class="collapse border-bottom" id="panel-filtros-siradig" data-listado-filtros-panel>
                    <div class="card-body bg-light py-2 text-body">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small mb-1" for="filtro_valor_panel">Buscar</label>
                                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm"
                                       value="{{ $filtros['valor'] ?? '' }}" placeholder="CUIL, apellido, nombre" autocomplete="off">
                            </div>
                            @if (! $unaEmpresa)
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small mb-1" for="empresa_id_filtro">Empresa</label>
                                <select name="empresa_id" id="empresa_id_filtro" class="form-control form-control-sm">
                                    <option value="">Todas mis empresas</option>
                                    @foreach ($empresa_query as $emp)
                                        <option value="{{ $emp->id }}" {{ (int) ($fEmp ?? 0) === (int) $emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
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
                                <label class="small mb-1" for="filtro_seccion">Sección</label>
                                <select name="filtro_seccion" id="filtro_seccion" class="form-control form-control-sm">
                                    <option value="">Todas</option>
                                    <option value="A" {{ ($filtros['seccion'] ?? '') === 'A' ? 'selected' : '' }}>A - Agente retención</option>
                                    <option value="B" {{ ($filtros['seccion'] ?? '') === 'B' ? 'selected' : '' }}>B - Otros empleadores</option>
                                </select>
                            </div>
                            <div class="form-group col-md-auto mb-2">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input" id="filtro_vigentes_chk"
                                           {{ ($filtros['solo_vigentes'] ?? true) ? 'checked' : '' }}>
                                    <input type="hidden" name="filtro_vigentes" id="filtro_vigentes" value="{{ ($filtros['solo_vigentes'] ?? true) ? '1' : '0' }}">
                                    <label class="custom-control-label small" for="filtro_vigentes_chk">Solo vigentes</label>
                                </div>
                            </div>
                            <div class="form-group col-md-auto mb-2">
                                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                                    <i class="fa fa-search"></i> Aplicar filtros
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_siradig_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Per&iacute;odo</th>
                            <th>Secc.</th>
                            <th>Nro</th>
                            <th>Fecha</th>
                            <th>CUIL</th>
                            <th>Empleado</th>
                            <th>Empresa</th>
                            <th class="text-right">Deducciones</th>
                            <th>Vig.</th>
                            <th class="text-nowrap" style="width:90px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $p)
                        <tr class="{{ $p->vigente ? '' : 'text-muted' }}">
                            <td>{{ $p->periodo }}</td>
                            <td><span class="badge badge-{{ $p->seccion === 'A' ? 'primary' : 'secondary' }}">{{ $p->seccion }}</span></td>
                            <td>{{ $p->nro_presentacion }}</td>
                            <td>{{ optional($p->fecha_presentacion)->format('d/m/Y') }}</td>
                            <td>{{ $p->empleado_cuit }}</td>
                            <td>
                                {{ trim(($p->empleado_apellido ?? '').' '.($p->empleado_nombre ?? '')) }}
                                @if ($p->empleado_id)
                                    <span class="badge badge-success" title="Legajo vinculado">Legajo {{ optional($p->empleado)->legajo }}</span>
                                @else
                                    <span class="badge badge-warning" title="Sin legajo vinculado por CUIL">Sin legajo</span>
                                @endif
                            </td>
                            <td>{{ optional($p->empresa)->nombre }}</td>
                            <td class="text-right">{{ number_format((float) $p->conceptos->where('grupo', SiradigTablas::GRUPO_DEDUCCION)->sum('monto_total'), 2, ',', '.') }}</td>
                            <td>
                                @if ($p->vigente)
                                    <span class="badge badge-success">Sí</span>
                                @else
                                    <span class="badge badge-light">No</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if ($puedeVer)
                                    <a href="{{ route('ver_siradig_sueldos', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                @endif
                                @if ($puedeBorrar)
                                    <form action="{{ route('eliminar_siradig_sueldos', ['id' => $p->id]) }}" class="d-inline form-eliminar" method="POST"
                                          onsubmit="return confirm('¿Eliminar esta presentación F572? Se recalculará la vigencia del período.');">
                                        @csrf @method('delete')
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">Sin presentaciones. Importe un XML o ZIP de SiRADIG.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $datas->appends($filtrosQuery ?? [])->links() }}
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'siradig-import-overlay',
    'tituloId' => 'siradig-import-titulo',
    'subtituloId' => 'siradig-import-subtitulo',
    'titulo' => 'Importando SiRADIG…',
    'subtitulo' => 'Leyendo el/los XML del F572. Puede demorar con ZIP de muchos empleados.',
])
@endsection
