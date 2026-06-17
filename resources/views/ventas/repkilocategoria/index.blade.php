@extends("theme.$theme.layout")
@section('titulo')
    Kilos por categoría
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/ventas/repkilocategoria/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte de kilos por categoría</h3>
                <div class="card-tools">
                    <a href="{{ route('rep_kilocategoria') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('rep_kilocategoria') }}" id="form-kilo-categoria" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Totaliza por artículo todos los pedidos del rango seleccionado y subtotaliza por categoría.
                        <strong>Todos los repartos:</strong> deje vacíos Desde y Hasta.
                        <strong>Repartos puntuales:</strong> en Desde indique códigos separados por coma (ej. <strong>1,4,6</strong>).
                        <strong>Rango:</strong> Desde y Hasta (ej. <strong>1</strong> y <strong>10</strong>) o atajo <strong>1/10</strong> en Desde.
                    </p>

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label requerido">Desde fecha entrega</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-d') }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label requerido">Hasta fecha entrega</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label">Rango repartos</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-5">
                                    <label class="small text-muted mb-1" for="reparto_desde">Desde</label>
                                    <div class="kilo-categoria-reparto-campo" data-campo="desde">
                                        <div class="input-group input-group-sm mb-1">
                                            <input type="text" name="reparto_desde" id="reparto_desde"
                                                class="form-control codigoreparto"
                                                placeholder="1,4,6 o 1/10" autocomplete="off"
                                                value="{{ $filtros['reparto_desde'] ?? '' }}">
                                            <div class="input-group-append">
                                                <button type="button" title="Consulta repartos"
                                                    class="btn btn-outline-secondary consultareparto tooltipsC">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control form-control-sm nombrereparto" readonly
                                            placeholder="Nombre reparto" value="{{ $meta_reparto_desde['nombre'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="small text-muted mb-1" for="reparto_hasta">Hasta</label>
                                    <div class="kilo-categoria-reparto-campo" data-campo="hasta">
                                        <div class="input-group input-group-sm mb-1">
                                            <input type="text" name="reparto_hasta" id="reparto_hasta"
                                                class="form-control codigoreparto"
                                                placeholder="Hasta (rango)" autocomplete="off"
                                                value="{{ ($filtros['reparto_hasta'] ?? '') === '999999' ? '' : ($filtros['reparto_hasta'] ?? '') }}">
                                            <div class="input-group-append">
                                                <button type="button" title="Consulta repartos"
                                                    class="btn btn-outline-secondary consultareparto tooltipsC">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control form-control-sm nombrereparto" readonly
                                            placeholder="Nombre reparto" value="{{ $meta_reparto_hasta['nombre'] ?? '' }}">
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">
                                Enter en el código valida el reparto. Vacío en ambos incluye todos los repartos.
                                Lista en Desde: <strong>1,4,6</strong>. Rango: <strong>1/10</strong> en Desde o Desde <strong>1</strong> y Hasta <strong>10</strong>.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estado" class="col-lg-2 control-label requerido">Estado</label>
                        <div class="col-lg-4">
                            <select name="estado" id="estado" class="form-control" required>
                                @foreach ($estado_enum as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['estado'] ?? 'PENDIENTE') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Reparto:</strong> {{ $reparto_texto ?? '' }}
                            · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Estado:</strong> {{ $estado_enum[$filtros['estado'] ?? 'PENDIENTE'] ?? '' }}
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_rep_kilocategoria',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (!empty($totales))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Artículos:</span>
                                <strong>{{ (int) ($totales['cantidad_detalle'] ?? 0) }}</strong>
                                · Piezas <strong>{{ number_format((float) ($totales['total_pieza'] ?? 0), 2, ',', '.') }}</strong>
                                · Kilos <strong>{{ number_format((float) ($totales['total_kilo'] ?? 0), 2, ',', '.') }}</strong>
                                · Cajas <strong>{{ number_format((float) ($totales['total_caja'] ?? 0), 2, ',', '.') }}</strong>
                            </div>
                        @endif
                    </div>

                    @php $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect()); @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        #tabla-kilo-categoria thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-kilo-categoria thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-kilo-categoria" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.78rem;">
                            @include('ventas.repkilocategoria.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_categoria' => $puede_ver_categoria ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} filas
                                @else
                                    Sin registros
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultatransporte')
@endsection
