@extends("theme.$theme.layout")

@section('titulo')
    Reportes Local — Ventas por artículo
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/consulta.js') }}" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof activa_eventos_consultapuntoventa === 'function') {
        activa_eventos_consultapuntoventa();
    }
    var form = document.getElementById('form-fl-ventas-articulos');
    if (form) {
        form.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.classList && e.target.classList.contains('codigopuntoventa')) {
                e.preventDefault();
            }
        });
        form.addEventListener('submit', function () {
            var cod = document.getElementById('puntoventa_id_codigo');
            var nom = document.getElementById('puntoventa_id_nombre');
            var qsCod = document.getElementById('fl_reporte_puntoventa_codigo_qs');
            var qsNom = document.getElementById('fl_reporte_puntoventa_nombre_qs');
            if (cod && qsCod) qsCod.value = cod.value || '';
            if (nom && qsNom) qsNom.value = nom.value || '';
        });
    }
});
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Reportes Local — Ventas por artículo</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('listar-facturas-facturacion-local', false))
                        <a href="{{ route('facturacion_local_facturas') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-file-text-o"></i> Facturas Local
                        </a>
                    @endif
                    @if (can('editar-facturacion-local-parametro', false))
                        <a href="{{ route('facturacion_local_parametros') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-cogs"></i> Parámetros
                        </a>
                    @endif
                    <a href="{{ route('facturacion_local_reportes') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_reportes') }}" id="form-fl-ventas-articulos" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Ventas netas del local (facturas menos notas de crédito) por artículo,
                        combinación/color y talle. Elija el punto de venta, el rango de fechas y si abre por talle
                        o cierra por artículo/combinación. El tilde de costo agrega P.Vta., P.Costo e importe al costo
                        ({{ \App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport::etiquetaFormula() }}).
                        @if (can('editar-facturacion-local-parametro', false))
                            ·
                            <a href="{{ route('facturacion_local_parametros') }}" class="text-primary">
                                Configurar listas fábrica y descuento
                            </a>
                        @endif
                    </p>

                    @include('ventas.partials.campo_consulta_puntoventa', [
                        'prefix' => 'fl_reporte',
                        'layout' => 'form_row',
                        'label' => 'Punto de venta',
                        'inputName' => 'puntoventa_id',
                        'inputId' => 'puntoventa_id',
                        'puntoventaId' => $filtros['puntoventa_id'] ?? '',
                        'codigo' => $filtros['puntoventa_codigo'] ?? '',
                        'nombre' => $filtros['puntoventa_nombre'] ?? '',
                        'required' => true,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'mostrar_editar' => true,
                    ])
                    {{-- Espejo código/nombre para conservar en GET/paginación/export --}}
                    <input type="hidden" name="puntoventa_codigo" id="fl_reporte_puntoventa_codigo_qs"
                        value="{{ $filtros['puntoventa_codigo'] ?? '' }}">
                    <input type="hidden" name="puntoventa_nombre" id="fl_reporte_puntoventa_nombre_qs"
                        value="{{ $filtros['puntoventa_nombre'] ?? '' }}">

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="modo" class="col-lg-2 control-label text-right pr-2">Apertura</label>
                        <div class="col-lg-4">
                            <select name="modo" id="modo" class="form-control">
                                @foreach ($modos as $op)
                                    <option value="{{ $op['valor'] }}" @selected(($filtros['modo'] ?? '') === $op['valor'])>
                                        {{ $op['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-5">
                            <div class="custom-control custom-checkbox pt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_costo" name="incluir_costo" value="1"
                                    @checked(! empty($filtros['incluir_costo']))>
                                <label class="custom-control-label" for="incluir_costo">
                                    Incluir valorización al costo (P.Vta. / P.Costo)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
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
                            @if (($resultado['nombreempresa'] ?? '') !== '')
                                <strong>Empresa:</strong> {{ $resultado['nombreempresa'] }}
                                ·
                            @endif
                            <strong>Punto de venta:</strong> {{ $resultado['puntoventa_texto'] ?? $resultado['local_texto'] ?? '' }}
                            · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Modo:</strong> {{ $modo_texto ?? '' }}
                            @if (! empty($resultado['incluir_costo']))
                                · <strong>Costo:</strong> {{ $resultado['costo_formula'] ?? '' }}
                            @endif
                            @if (! empty($resultado))
                                · <strong>Líneas:</strong> {{ count($resultado['filas'] ?? []) }}
                            @endif
                        </p>
                        @if (! empty($resultado['incluir_costo']) && (int) ($resultado['sin_precio_fabrica'] ?? 0) > 0)
                            <p class="mb-0 small text-warning mt-1">
                                <i class="fa fa-exclamation-triangle"></i>
                                {{ (int) $resultado['sin_precio_fabrica'] }} línea(s) sin precio en listas fábrica
                                (códigos {{ implode(',', \App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport::codigosListasFabrica()) }}):
                                P.Costo queda en 0. Cargá el precio fábrica del artículo
                                @if (can('editar-facturacion-local-parametro', false))
                                    o revisá
                                    <a href="{{ route('facturacion_local_parametros') }}" class="text-primary">Parámetros Facturación Local</a>.
                                @else
                                    o pedí revisar Parámetros Facturación Local.
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_facturacion_local',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (! empty($resultado['totales']))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Totales filtro:</span>
                                Cant. <strong>{{ number_format((float) ($resultado['totales']['cantidad'] ?? 0), 0, ',', '.') }}</strong>
                                · Imp. venta
                                <strong>${{ number_format((float) ($resultado['totales']['importe'] ?? 0), 2, ',', '.') }}</strong>
                                @if (! empty($resultado['incluir_costo']))
                                    · Imp. costo
                                    <strong>${{ number_format((float) ($resultado['totales']['importe_costo'] ?? 0), 2, ',', '.') }}</strong>
                                @endif
                            </div>
                        @endif
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            ! empty($resultado['nombreempresa'])
                                ? collect([(object) ['nombreempresa' => $resultado['nombreempresa']]])
                                : collect()
                        );
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        .tabla-fl-ventas-articulos thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-fl-ventas-articulos thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.85rem; }
                    </style>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover mb-0 tabla-fl-ventas-articulos" id="tabla-paginada">
                            @include('ventas.facturacion_local.reportes.partials.tabla_datos', [
                                'filas' => $filas_vista ?? [],
                                'totales' => $resultado['totales'] ?? [],
                                'abierto_talle' => $resultado['abierto_talle'] ?? true,
                                'incluir_costo' => $resultado['incluir_costo'] ?? false,
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            ])
                        </table>
                    </div>
                    @if ($filas_pag ?? null)
                        <div class="card-footer py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    @if ($filas_pag->total() > 0)
                                        Mostrando {{ $filas_pag->firstItem() }}–{{ $filas_pag->lastItem() }}
                                        de {{ $filas_pag->total() }}
                                    @endif
                                </small>
                                {{ $filas_pag->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultapuntoventa')
@endsection
