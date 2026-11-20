@extends("theme.$theme.layout")
@section('titulo')
    SICORE — presentación ARCA
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">SICORE (formato versión 8)</h3>
                <div class="card-tools">
                    @if (can('listar-sicore-config', false))
                        <a href="{{ route('sicore_config') }}" class="btn btn-outline-secondary btn-sm" title="Configuración">
                            <i class="fa fa-cogs"></i> Configuración
                        </a>
                    @endif
                    <a href="{{ route('sicore') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('sicore') }}" id="form-sicore" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Generación del archivo RG 738/99 para presentación en ARCA.
                        Ventas: percepciones desde <code>venta_impuesto</code>.
                        Compras: retenciones desde Anita (<code>retmov</code> / <code>retimov</code>)
                        y, si faltan, las OPs nativas del ERP (<code>pagoproveedor_retencion</code>) sin duplicar.
                        Sueldos: retenciones 4ta categoría desde <code>auxrec</code>.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                    @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                        <label for="criterio" class="{{ $colLabel }} requerido">Exporta</label>
                        <div class="{{ $colInput }}">
                            <select name="criterio" id="criterio" class="form-control" required>
                                @foreach ($criterios_enum as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['criterio'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-01') }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }}"></div>
                        <div class="col-lg-10">
                            <div class="custom-control custom-checkbox d-inline-block mr-4">
                                <input type="checkbox" class="custom-control-input" name="conciliar_contable" id="conciliar_contable" value="1"
                                    @checked(($filtros['conciliar_contable'] ?? true))>
                                <label class="custom-control-label" for="conciliar_contable">
                                    Conciliar contra mayor contable (saldo de ejercicio)
                                </label>
                            </div>
                            <div class="custom-control custom-checkbox d-inline-block">
                                <input type="checkbox" class="custom-control-input" name="control_periodo" id="control_periodo" value="1"
                                    @checked(! empty($filtros['control_periodo']))>
                                <label class="custom-control-label" for="control_periodo">
                                    + Control del período (retenciones emitidas vs mayor del mes)
                                </label>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                El control del período suma solo movimientos del rango consultado (sin arrastrar el saldo desde principio de ejercicio).
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && can('exportar-sicore', false))
                        <a href="{{ route('exportar_sicore', $filtrosQuery) }}" class="btn btn-success">
                            <i class="fa fa-download"></i> Descargar archivo SICORE v8
                        </a>
                        <button type="button"
                            class="btn btn-warning js-sicore-liquidacion-abrir"
                            title="1Q o DJ: PDF + TXT compras y sueldos en un ZIP">
                            <i class="fa fa-file-archive"></i> Liquidación completa
                        </button>
                    @endif
                </div>
            </form>
        </div>

        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'sicore-liquidacion-overlay',
            'tituloId' => 'sicore-liquidacion-titulo',
            'subtituloId' => 'sicore-liquidacion-subtitulo',
            'titulo' => 'Generando liquidación SICORE…',
            'subtitulo' => 'Puede demorar. Esta pantalla sigue usable; el ZIP (PDF + TXT) se descarga al terminar.',
        ])

        {{-- Iframe oculto: la descarga no navega la pantalla original ni deja el overlay pegado. --}}
        <iframe name="sicore_liq_dl" id="sicore-liq-dl-frame" title="Descarga liquidación SICORE"
            style="position:absolute;width:0;height:0;border:0;visibility:hidden;"></iframe>

        @php
            $criterioPantalla = (string) ($filtros['criterio'] ?? 'compras');
            $desdePantalla = (string) ($filtros['fecha_desde'] ?? '');
            $hastaPantalla = (string) ($filtros['fecha_hasta'] ?? '');
            $refMesLiq = $desdePantalla !== '' ? $desdePantalla : date('Y-m-d');
            $def1q = \App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport::sugerirRangos('1q', $refMesLiq);
            $defDj = \App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport::sugerirRangos('dj', $refMesLiq);
        @endphp

        <div class="modal fade" id="modal-sicore-liquidacion" tabindex="-1" role="dialog"
             aria-labelledby="modal-sicore-liquidacion-titulo" aria-hidden="true"
             data-ref-mes="{{ $refMesLiq }}"
             data-rangos-1q="{{ e(json_encode($def1q)) }}"
             data-rangos-dj="{{ e(json_encode($defDj)) }}">
            <div class="modal-dialog modal-lg" role="document">
                <form method="get" action="{{ route('liquidacion_sicore') }}" id="form-sicore-liquidacion"
                      class="modal-content" target="sicore_liq_dl" rel="noopener" data-enter-campos="1">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="modal-sicore-liquidacion-titulo">
                            Liquidación completa SICORE
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">
                            Elige <strong>1ra quincena</strong> (VEP a cuenta) o <strong>DJ mensual</strong>.
                            Descarga un ZIP con el PDF, el TXT de compras y el TXT de sueldos (787).
                        </p>
                        <input type="hidden" name="empresa_id" value="{{ (int) ($filtros['empresa_id'] ?? 0) }}">
                        <input type="hidden" name="criterio" value="{{ $criterioPantalla }}">
                        <input type="hidden" name="fecha_desde" value="{{ $desdePantalla }}">
                        <input type="hidden" name="fecha_hasta" value="{{ $hastaPantalla }}">
                        @if (! empty($filtros['conciliar_contable']))
                            <input type="hidden" name="conciliar_contable" value="1">
                        @endif
                        @if (! empty($filtros['control_periodo']))
                            <input type="hidden" name="control_periodo" value="1">
                        @endif

                        <div class="form-group mb-3">
                            <label class="d-block font-weight-bold">Tipo de liquidación</label>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="tipo_liq_1q" name="tipo_liquidacion" value="1q"
                                    class="custom-control-input js-sicore-tipo-liq" checked>
                                <label class="custom-control-label" for="tipo_liq_1q">1ra quincena — pago a cuenta</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="tipo_liq_dj" name="tipo_liquidacion" value="dj"
                                    class="custom-control-input js-sicore-tipo-liq">
                                <label class="custom-control-label" for="tipo_liq_dj">DJ mensual</label>
                            </div>
                        </div>

                        <div class="card card-outline card-secondary mb-3">
                            <div class="card-header py-2">
                                <strong>Compras</strong>
                                <span class="text-muted small">(códigos 217 y 767)</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-2">
                                        <label for="compras_fecha_desde">Desde</label>
                                        <input type="date" class="form-control" name="compras_fecha_desde"
                                            id="compras_fecha_desde" required value="{{ $def1q['compras_desde'] }}">
                                    </div>
                                    <div class="form-group col-md-6 mb-2">
                                        <label for="compras_fecha_hasta">Hasta</label>
                                        <input type="date" class="form-control" name="compras_fecha_hasta"
                                            id="compras_fecha_hasta" required value="{{ $def1q['compras_hasta'] }}">
                                    </div>
                                </div>
                                <p class="small text-muted mb-0 js-sicore-hint-compras">
                                    1Q: del 01 al 15 · DJ: mes completo (editable).
                                </p>
                            </div>
                        </div>

                        <div class="card card-outline card-secondary mb-3">
                            <div class="card-header py-2">
                                <strong>Sueldos — 4ta categoría</strong>
                                <span class="text-muted small">(código 787)</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="form-row">
                                    <div class="form-group col-md-6 mb-2">
                                        <label for="sueldos_fecha_desde">Desde</label>
                                        <input type="date" class="form-control" name="sueldos_fecha_desde"
                                            id="sueldos_fecha_desde" required value="{{ $def1q['sueldos_desde'] }}">
                                    </div>
                                    <div class="form-group col-md-6 mb-2">
                                        <label for="sueldos_fecha_hasta">Hasta</label>
                                        <input type="date" class="form-control" name="sueldos_fecha_hasta"
                                            id="sueldos_fecha_hasta" required value="{{ $def1q['sueldos_hasta'] }}">
                                    </div>
                                </div>
                                <p class="small text-muted mb-0">
                                    Desde: mes anterior. Hasta: a mano (fecha de retención 4ta en el TXT SIAP).
                                </p>
                            </div>
                        </div>

                        <div class="card card-outline card-secondary mb-0" id="sicore-saf-panel">
                            <div class="card-header py-2">
                                <strong>Saldo a favor e ingresos a cuenta</strong>
                                <span class="badge badge-secondary ml-1 js-sicore-saf-badge">solo DJ</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-2">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Código</th>
                                                <th class="text-right">Saldo a favor período anterior</th>
                                                <th>Ingresos a cuenta — fecha</th>
                                                <th class="text-right">Ingresos a cuenta — importe</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (['217', '767', '787'] as $codSaf)
                                                <tr>
                                                    <td class="align-middle">{{ $codSaf }}</td>
                                                    <td>
                                                        <input type="number" step="0.01" min="0"
                                                            class="form-control form-control-sm text-right js-sicore-saf"
                                                            name="saf[{{ $codSaf }}]" value="0"
                                                            disabled>
                                                    </td>
                                                    <td>
                                                        <input type="date"
                                                            class="form-control form-control-sm js-sicore-ing-fecha"
                                                            name="ingreso_fecha[{{ $codSaf }}]"
                                                            disabled>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" min="0"
                                                            class="form-control form-control-sm text-right js-sicore-ing-imp"
                                                            name="ingreso_importe[{{ $codSaf }}]" value="0"
                                                            disabled>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="small text-muted mb-0 js-sicore-saf-ayuda">
                                    En 1ra quincena estos campos quedan deshabilitados. En DJ: un ingreso a cuenta por código (VEP de la 1Q); si hay importe, la fecha es obligatoria.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        @if (can('crear-solicitud-pago', false))
                            <button type="button" class="btn btn-success js-sicore-solicitudpago-abrir"
                                title="Arma la solicitud de pago a ARCA con el cuadro de esta liquidación">
                                <i class="fa fa-file-invoice-dollar"></i> Generar solicitud de pago
                            </button>
                        @endif
                        <button type="submit" class="btn btn-warning js-sicore-liquidacion-generar">
                            <i class="fa fa-file-archive"></i> Generar liquidación (ZIP)
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @include('contable.sicore.partials.modal_solicitudpago')

        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'sicore-solicitudpago-overlay',
            'tituloId' => 'sicore-solicitudpago-titulo',
            'subtituloId' => 'sicore-solicitudpago-subtitulo',
            'titulo' => 'Generando solicitud de pago SICORE…',
            'subtitulo' => 'Recalcula la liquidación, adjunta el PDF y envía a aprobación. No cierre la página.',
        ])

        @if ($consultado && $resultado)
            @if (can('exportar-sicore', false))
                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_sicore',
                        'queryparams' => $filtrosQuery,
                    ])
                </div>
            @endif

            @include('contable.sicore.partials.conciliacion', [
                'conciliacion' => $resultado['conciliacion'] ?? [],
                'periodo_texto' => $periodo_texto ?? '',
            ])

            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        SICORE a presentar — {{ $periodo_texto }}
                        <span class="badge badge-info ml-2">{{ $resultado['totales']['registros'] ?? 0 }}</span>
                    </h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-striped mb-0" id="tabla-sicore">
                        @php
                            $ocultarRazonSocial = (($filtros['criterio'] ?? '') === 'sueldos');
                            $colsDetalle = $ocultarRazonSocial ? 8 : 9;
                            $colsTotalesLabel = $ocultarRazonSocial ? 5 : 6;
                        @endphp
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Reg.</th>
                                <th>Imp.</th>
                                <th>Proveedor</th>
                                <th>Documento</th>
                                @if (! $ocultarRazonSocial)
                                    <th>Razón social</th>
                                @endif
                                <th>Fecha</th>
                                <th class="text-right">Base</th>
                                <th class="text-right">Importe</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resultado['registros'] ?? [] as $reg)
                                <tr>
                                    <td>{{ str_pad((string) ($reg['cod_regimen'] ?? ''), 3, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $reg['cod_impuesto'] ?? '' }}</td>
                                    <td class="text-nowrap">{{ $reg['codigo_proveedor'] ?? '' }}</td>
                                    <td>{{ $reg['nro_documento'] ?? '' }}</td>
                                    @if (! $ocultarRazonSocial)
                                        <td>{{ $reg['razon_social'] ?? '' }}</td>
                                    @endif
                                    <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
                                    <td class="text-right">{{ number_format((float) ($reg['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right @if (($reg['importe'] ?? 0) < 0) text-danger @endif">{{ number_format((float) ($reg['importe'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="small text-muted">{{ $reg['referencia'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $colsDetalle }}" class="text-center text-muted py-4">Sin registros en el período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (($resultado['totales']['registros'] ?? 0) > 0)
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="{{ $colsTotalesLabel }}">Totales</td>
                                    <td class="text-right">{{ number_format((float) ($resultado['totales']['base_calculo'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($resultado['totales']['importe'] ?? 0), 2, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
window.SICORE = {
    urlPreviewSolicitud: @json(route('preview_sicore_solicitudpago')),
    urlGenerarSolicitud: @json(route('generar_sicore_solicitudpago')),
    puedeCrearSolicitud: @json(can('crear-solicitud-pago', false)),
};
</script>
<script src="{{ asset('assets/pages/scripts/contable/sicore/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/sicore/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
