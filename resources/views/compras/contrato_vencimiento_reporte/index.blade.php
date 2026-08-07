@extends("theme.$theme.layout")
@section('titulo')
    Contratos y OC abiertas por vencer
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'contrato-venc-overlay',
    'tituloId' => 'contrato-venc-overlay-titulo',
    'subtituloId' => 'contrato-venc-overlay-subtitulo',
    'titulo' => 'Consultando contratos…',
    'subtitulo' => 'Puede demorar según el volumen de órdenes de compra. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Contratos y OC abiertas por vencer</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_contrato_vencimiento') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('reporte_contrato_vencimiento') }}" id="form-contrato-venc" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        &Oacute;rdenes de compra marcadas como <strong>contrato / OC abierta</strong> (abonos, honorarios, servicios).
                        Se vigilan dos ejes: el <strong>tiempo</strong> (fin de vigencia y fecha l&iacute;mite de preaviso de no renovaci&oacute;n)
                        y el <strong>consumo</strong> (recibido y facturado contra el monto contratado).
                        Los avisos autom&aacute;ticos salen del cron diario; ac&aacute; se trabaja la lista completa.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'reporte_clave' => 'contrato_vencimiento_reporte',
                        'mostrar_consolidar' => false,
                        'id_prefix' => 'ctovenc',
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="tipo_alerta" class="{{ $colLabel }}">Tipo de alerta</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo_alerta" id="tipo_alerta" class="form-control">
                                @foreach ($opciones_alerta as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected(($filtros['tipo_alerta'] ?? '') === $valor)>
                                        {{ $etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="dias_horizonte" class="{{ $colLabel }}">Horizonte (días)</label>
                        <div class="{{ $colInput }}">
                            <input type="number" min="0" max="1095" step="1" name="dias_horizonte" id="dias_horizonte"
                                class="form-control" style="max-width:10rem;"
                                value="{{ $filtros['dias_horizonte'] ?? 90 }}">
                            <small class="form-text text-muted">Contratos que vencen dentro de esa cantidad de días. 0 = sin tope.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="proveedor" class="{{ $colLabel }}">Proveedor contiene</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="proveedor" id="proveedor" class="form-control" maxlength="100"
                                value="{{ $filtros['proveedor'] ?? '' }}" placeholder="Vac&iacute;o = todos">
                        </div>
                        <label for="responsable_id" class="{{ $colLabel }}">Responsable</label>
                        <div class="{{ $colInput }}">
                            <select name="responsable_id" id="responsable_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($usuario_query as $usuario)
                                    <option value="{{ $usuario->id }}" @selected((int) ($filtros['responsable_id'] ?? 0) === (int) $usuario->id)>
                                        {{ $usuario->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="hidden" name="solo_sin_responsable" value="0">
                                <input type="checkbox" class="custom-control-input" id="solo_sin_responsable"
                                    name="solo_sin_responsable" value="1" @checked(! empty($filtros['solo_sin_responsable']))>
                                <label class="custom-control-label" for="solo_sin_responsable">Solo contratos sin responsable asignado</label>
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
                            <strong>Contratos:</strong> {{ (int) $totales['cantidad'] }}
                            &middot; <strong>Vencidos:</strong> {{ (int) $totales['vencidos'] }}
                            &middot; <strong>Vencen en 30 días:</strong> {{ (int) $totales['vencen_30'] }}
                            &middot; <strong>Entre 31 y 60:</strong> {{ (int) $totales['vencen_60'] }}
                            &middot; <strong>Sin vigencia cargada:</strong> {{ (int) $totales['sin_vigencia'] }}
                            &middot; <strong>Tope:</strong> {{ number_format((float) $totales['monto_tope'], 2, ',', '.') }}
                            &middot; <strong>Recibido:</strong> {{ number_format((float) ($totales['monto_recibido'] ?? 0), 2, ',', '.') }}
                            &middot; <strong>Facturado:</strong> {{ number_format((float) $totales['monto_facturado'], 2, ',', '.') }}
                            &middot; <strong>Consumido:</strong> {{ number_format((float) ($totales['monto_consumido'] ?? 0), 2, ',', '.') }}
                            &middot; <strong>Disponible:</strong> {{ number_format((float) $totales['monto_disponible'], 2, ',', '.') }}
                        </p>
                        <p class="mb-0 small text-muted">
                            Consumido = recepciones confirmadas + facturas sin recepción vinculada; si el total facturado es mayor, manda la factura.
                        </p>
                        <p class="mb-0 small text-muted">{{ $subtitulo }}</p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_contrato_vencimiento',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($filtros['empresa_ids'] ?? [])->map(function ($id) use ($empresa_query) {
                                $emp = ($empresa_query ?? collect())->firstWhere('id', (int) $id);

                                return $emp ? (object) ['nombreempresa' => $emp->nombre] : null;
                            })->filter()
                        );
                    @endphp
                    @if (! empty($logosVista))
                        <div class="px-3 pt-2">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height:44px;max-width:140px;margin-right:12px;">
                            @endforeach
                        </div>
                    @endif

                    <div class="table-responsive px-3 pb-3">
                        @include('compras.contrato_vencimiento_reporte.partials.tabla_datos', [
                            'filas' => $filasVista,
                            'puede_ver_ordencompra' => $puede_ver_ordencompra,
                            'para_excel' => false,
                        ])
                    </div>

                    @if ($filas)
                        <div class="px-3 pb-3 d-flex flex-wrap align-items-center justify-content-between">
                            <small class="text-muted">
                                Mostrando {{ $filas->firstItem() ?? 0 }}–{{ $filas->lastItem() ?? 0 }} de {{ $filas->total() }}
                            </small>
                            {{ $filas->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-contrato-venc');
    var overlay = document.getElementById('contrato-venc-overlay');
    if (!form || !overlay) {
        return;
    }

    function mostrar() {
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    form.addEventListener('submit', function () {
        if (form.checkValidity()) {
            mostrar();
        }
    });

    document.querySelectorAll('a[href*="listar-contrato-vencimiento-reporte"]').forEach(function (link) {
        link.addEventListener('click', mostrar);
    });

    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
