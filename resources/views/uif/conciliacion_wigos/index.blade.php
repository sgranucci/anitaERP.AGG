@extends("theme.$theme.layout")
@section('titulo')
    Conciliación Wigos UIF
@endsection

@section('scripts')
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
@if (session('descargar_excel_conciliacion'))
<script>
window.descargarExcelConciliacionUrl = @json(session('descargar_excel_conciliacion'));
</script>
@endif
<script src="{{ asset('assets/pages/scripts/uif/conciliacion_wigos/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/uif/conciliacion_wigos/form.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    use App\Support\Uif\UifWigosConciliacionEmpresaSupport;
    $paramsExport = \App\Support\Uif\UifWigosConciliacionFiltros::paraQueryStringExport($filtros ?? []);
    $suffixExport = count($paramsExport) ? '?'.http_build_query($paramsExport) : '';
    $paramsExportGlobal = \App\Support\Uif\UifWigosConciliacionFiltros::paraQueryStringExport([
        'anio' => $filtros['anio'] ?? 0,
        'mes' => $filtros['mes'] ?? 0,
    ]);
    $suffixExportGlobal = count($paramsExportGlobal) ? '?'.http_build_query($paramsExportGlobal) : '';
    $totalUnificadoGlobal = collect($resumen_empresas ?? [])->sum('unificado');
    $puedeExportar = can('exportar-conciliacion-wigos-uif', false) || can('listar-conciliacion-wigos-uif', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        @if (session('descargar_excel_conciliacion') && ($puedeExportar ?? false))
            <div class="alert alert-info alert-dismissible" id="alert-descarga-excel-conciliacion">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <strong>Libro listo.</strong>
                <a href="{{ session('descargar_excel_conciliacion') }}" class="btn btn-success btn-sm ml-2" id="link-descarga-excel-conciliacion">
                    <i class="fas fa-file-excel"></i> Descargar Excel (Titos + PM + UNIFICADO)
                </a>
            </div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conciliación final Wigos por período</h3>
                <div class="card-tools">
                    @if ($totalUnificadoGlobal > 0 && $puedeExportar)
                        <a href="{{ route('listar_conciliacion_wigos_uif', ['formato' => 'GLOBAL']).$suffixExportGlobal }}"
                           class="btn btn-app bg-primary" title="Libro global BSA + KSA + RSA (como Prueba global)">
                            <i class="fas fa-file-excel"></i> Excel global
                        </a>
                    @endif
                </div>
            </div>

            <form method="get" action="{{ route('conciliacion_wigos_uif') }}" id="form-consulta-conciliacion" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Elija el <strong>período</strong> y revise el estado de BSA, KSA y RSA.
                        Para cada empresa suba los dos exports Wigos (Titos + PM); el sistema arma el UNIFICADO.
                        Use <strong>Ver preview</strong> en la grilla para el detalle de una empresa, o
                        <strong>Excel global</strong> cuando cargó las tres.
                    </p>

                    <div class="form-group row mb-0">
                        <label for="periodo" class="col-lg-2 col-form-label requerido">Período</label>
                        <div class="col-lg-3">
                            <input type="text" name="periodo" id="periodo" class="form-control periodo"
                                value="{{ old('periodo', sprintf('%04d-%02d', $filtros['anio'], $filtros['mes'])) }}"
                                placeholder="AAAA-MM" autocomplete="off" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar período
                    </button>
                    <a href="{{ route('conciliacion_wigos_uif') }}" class="btn btn-outline-secondary">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <div class="card card-secondary">
            <div class="card-header">
                <h3 class="card-title">Carga por empresa — período {{ $periodo_texto }}</h3>
            </div>
            <form method="post" action="{{ route('cargar_conciliacion_wigos_uif') }}" enctype="multipart/form-data" id="form-carga-conciliacion">
                @csrf
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">Período</label>
                        <div class="col-lg-3">
                            <input type="text" name="periodo" class="form-control periodo"
                                value="{{ sprintf('%04d-%02d', $filtros['anio'], $filtros['mes']) }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">Empresa</label>
                        <div class="col-lg-4">
                            <select name="empresa_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($empresa_query as $emp)
                                    @php $cod = UifWigosConciliacionEmpresaSupport::codigoDesdeEmpresaId((int) $emp->id); @endphp
                                    <option value="{{ $emp->id }}" @selected($empresa_id === (int) $emp->id)>
                                        {{ $cod }} — {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">Titos Wigos</label>
                        <div class="col-lg-6">
                            <input type="file" name="archivo_titos" class="form-control-file" accept=".xls,.xlsx" required>
                            <small class="form-text text-muted">
                                Export Wigos de tickets. Acepta solapa <em>Sheet1</em> o el formato de la muestra
                                (ej. <em>BSA Tito Wigos</em>, <em>KSA Tito Wigos</em>).
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label requerido">PM Wigos</label>
                        <div class="col-lg-6">
                            <input type="file" name="archivo_pm" class="form-control-file" accept=".xls,.xlsx" required>
                            <small class="form-text text-muted">
                                Export Wigos de premios máquina. Acepta solapa <em>Sheet1</em> o el formato de la muestra
                                (ej. <em>BSA PM Wigos</em>, <em>KSA PM Wigos</em>).
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    @if (can('cargar-conciliacion-wigos-uif', false))
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-upload"></i> Cargar y conciliar
                        </button>
                    @endif
                </div>
            </form>
        </div>

        <div class="card card-light">
            <div class="card-header">
                <h3 class="card-title">Estado por empresa — {{ $periodo_texto }}</h3>
                @if ($totalUnificadoGlobal > 0 && $puedeExportar)
                    <div class="card-tools">
                        <a href="{{ route('listar_conciliacion_wigos_uif', ['formato' => 'GLOBAL']).$suffixExportGlobal }}"
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-file-excel"></i> Descargar Excel global (3 empresas)
                        </a>
                    </div>
                @endif
            </div>
            <div class="card-body table-responsive p-0">
                @if ($puedeExportar)
                    <p class="small text-muted px-3 pt-2 mb-0">
                        <strong>Excel global</strong> (arriba): las 3 empresas en un archivo.
                        <strong>Excel empresa</strong> y <strong>Pdf</strong>: botones en cada fila con datos unificados.
                    </p>
                @endif
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Empresa</th>
                            <th class="text-center">Titos</th>
                            <th class="text-center">PM</th>
                            <th class="text-center">Unificado</th>
                            <th>Última conciliación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumen_empresas as $res)
                            @php
                                $urlPreview = route('conciliacion_wigos_uif', [
                                    'periodo' => sprintf('%04d-%02d', $filtros['anio'], $filtros['mes']),
                                    'empresa_id' => $res['empresa_id'],
                                    'consultar' => 1,
                                ]);
                                $paramsEmpresaExport = \App\Support\Uif\UifWigosConciliacionFiltros::paraQueryStringExport([
                                    'anio' => $filtros['anio'],
                                    'mes' => $filtros['mes'],
                                    'empresa_id' => $res['empresa_id'],
                                ]);
                                $suffixEmpresaExport = count($paramsEmpresaExport) ? '?'.http_build_query($paramsEmpresaExport) : '';
                            @endphp
                            <tr @if ($empresa_id === (int) $res['empresa_id'] && !empty($filtrosQuery['consultar'])) class="table-active" @endif>
                                <td><strong>{{ $res['codigo'] }}</strong></td>
                                <td class="text-center">{{ $res['titos'] }}</td>
                                <td class="text-center">{{ $res['pm'] }}</td>
                                <td class="text-center">{{ $res['unificado'] }}</td>
                                <td>{{ $res['conciliado_at'] ?? '—' }}</td>
                                <td class="text-center">
                                    <a href="{{ $urlPreview }}" class="btn btn-xs btn-outline-primary" title="Ver UNIFICADO">
                                        <i class="fa fa-search"></i> Ver preview
                                    </a>
                                    @if ($res['unificado'] > 0 && $puedeExportar)
                                        <a href="{{ route('listar_conciliacion_wigos_uif', ['formato' => 'EXCEL']).$suffixEmpresaExport }}"
                                           class="btn btn-xs btn-outline-success" title="Excel Titos + PM + UNIFICADO de {{ $res['codigo'] }}">
                                            <i class="fas fa-file-excel"></i> Excel
                                        </a>
                                        <a href="{{ route('listar_conciliacion_wigos_uif', ['formato' => 'PDF']).$suffixEmpresaExport }}"
                                           class="btn btn-xs btn-outline-danger" title="PDF unificado de {{ $res['codigo'] }}">
                                            <i class="fas fa-file-pdf"></i> Pdf
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($filas ?? null)
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        Preview {{ UifWigosConciliacionEmpresaSupport::nombreSolapaUnificado($empresa_id) }} — {{ $periodo_texto }}
                    </h3>
                    <div class="card-tools">
                        @if ($puedeExportar && ($periodo->unificado()->count() > 0))
                            <a href="{{ route('listar_conciliacion_wigos_uif', ['formato' => 'EXCEL']).$suffixExport }}"
                               class="btn btn-sm btn-success" title="Excel Titos + PM + UNIFICADO">
                                <i class="fas fa-file-excel"></i> Excel empresa
                            </a>
                            <a href="{{ route('listar_conciliacion_wigos_uif', ['formato' => 'PDF']).$suffixExport }}"
                               class="btn btn-sm btn-danger" title="PDF unificado">
                                <i class="fas fa-file-pdf"></i> Pdf unificado
                            </a>
                        @endif
                        @if (can('conciliar-conciliacion-wigos-uif', false) && ($periodo ?? null) && (($periodo->titos()->count() + $periodo->premiosMaquina()->count()) > 0))
                            <form method="post" action="{{ route('conciliar_conciliacion_wigos_uif') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="periodo" value="{{ sprintf('%04d-%02d', $filtros['anio'], $filtros['mes']) }}">
                                <input type="hidden" name="empresa_id" value="{{ $empresa_id }}">
                                <button type="submit" class="btn btn-sm btn-warning">
                                    <i class="fa fa-random"></i> Re-conciliar
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if ($totales ?? null)
                        <div class="alert alert-light border mb-3">
                            Registros: <strong>{{ $totales['registros'] }}</strong>
                            — Total premios: <strong>$ {{ number_format($totales['monto'], 2, ',', '.') }}</strong>
                            — Con ticket: <strong>{{ $totales['con_ticket'] }}</strong>
                            — Sin ticket (PM): <strong>{{ $totales['sin_ticket'] }}</strong>
                        </div>
                    @endif
                    <p class="text-muted small mb-0">
                        <strong>Excel empresa</strong> descarga solo esta empresa (3 solapas).
                        <strong>Excel global</strong> (arriba) incluye BSA, KSA y RSA en un solo libro.
                    </p>
                </div>
                <div class="card-body table-responsive p-0">
                    @include('uif.conciliacion_wigos.partials.tabla_datos', [
                        'filas' => $filas,
                        'pantalla' => true,
                    ])
                </div>
                @if ($filas->hasPages())
                    <div class="card-footer">
                        {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        <span class="text-muted small ml-2">
                            {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                        </span>
                    </div>
                @endif
            </div>
        @elseif (!empty($filtrosQuery['consultar']) && $empresa_id > 0)
            <div class="alert alert-warning">
                No hay datos para {{ $codigo_empresa }} en {{ $periodo_texto }}.
                Suba los dos exports Wigos (Titos + PM) en el formulario de carga y pulse <strong>Cargar y conciliar</strong>.
            </div>
        @endif
    </div>
</div>
@endsection
