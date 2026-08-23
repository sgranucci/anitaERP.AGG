@extends("theme.$theme.layout")
@section('titulo')
    Abono mensual sin ingresos
@endsection

@section('contenido')
@php
    $k = $resultado['kpis'] ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Proveedores con abono mensual sin ingresos registrados</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Contratos vigentes en el per&iacute;odo y cantidad de tickets <strong>Finalizado</strong>.
                    Checklist previo a autorizar el pago del abono.
                </p>
                <form method="get" action="{{ route('reporte_abono_sin_ingresos') }}" class="mb-4">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => false,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])
                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2">Desde</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="col-lg-1 control-label text-right pr-2">Hasta</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="resultado" class="col-lg-2 control-label text-right pr-2">Resultado</label>
                        <div class="col-lg-2">
                            <select name="resultado" id="resultado" class="form-control">
                                <option value="">Todos</option>
                                <option value="OK" @selected(($filtros['resultado'] ?? '') === 'OK')>OK</option>
                                <option value="REVISAR" @selected(($filtros['resultado'] ?? '') === 'REVISAR')>Sin ingresos - revisar</option>
                            </select>
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
                </form>

                @if ($consultado && $resultado)
                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($resultado['filas'] ?? []);
                    @endphp
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Contratos</div>
                            <div class="h4 mb-0">{{ $k['contratos'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">OK</div>
                            <div class="h4 mb-0 text-success">{{ $k['ok'] ?? 0 }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Sin ingresos</div>
                            <div class="h4 mb-0 text-danger">{{ $k['revisar'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="border-bottom pb-2 mb-2 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                        <span class="badge badge-info ml-auto">Registros: {{ count($resultado['filas'] ?? []) }}</span>
                    </div>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_abono_sin_ingresos',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                    <style>
                        #tabla-paginada tbody tr.ingreso-reporte-rechazado td { background-color: #fdedec !important; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.85rem;">
                            @include('seguridad.ingreso_proveedor_reporte.abono.partials.tabla_datos', [
                                'filas' => $filasPaginadas ?? [],
                                'en_pantalla' => true,
                                'puede_ver_oc' => $puede_ver_oc ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                            ])
                        </table>
                    </div>
                    @if ($filasPaginadas)
                        <div class="d-flex flex-wrap align-items-center mt-2">
                            <small class="text-muted mr-3">
                                {{ $filasPaginadas->firstItem() }}–{{ $filasPaginadas->lastItem() }}
                                de {{ $filasPaginadas->total() }}
                            </small>
                            {{ $filasPaginadas->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
