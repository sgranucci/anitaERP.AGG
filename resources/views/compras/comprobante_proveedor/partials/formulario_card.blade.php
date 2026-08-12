<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($esEdicion)
                        Comprobante proveedor #{{ $data->id }}
                        <span class="badge badge-secondary ml-2">{{ $data->estado }}</span>
                    @else
                        Nuevo comprobante de proveedor
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('comprobante_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @if ($esEdicion && filled($ruta_factura_pdf ?? null))
                    <a href="{{ route('comprobante_proveedor_factura_pdf', ['id' => $data->id, 'inline' => 1]) }}"
                       class="btn btn-outline-light btn-sm" target="_blank" rel="noopener noreferrer">
                        <i class="fa fa-file-pdf-o"></i> Ver PDF
                    </a>
                    @endif
                    @if ($esEdicion && ($data->estado ?? '') !== \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO && can('contabilizar-comprobante-proveedor', false))
                    <form action="{{ route('contabilizar_comprobante_proveedor', ['id' => $data->id]) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('¿Contabilizar el comprobante? Genera asiento, cuenta corriente y sync Anita.');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fa fa-check"></i> Contabilizar
                        </button>
                    </form>
                    @endif
                    @if ($esEdicion && can('borrar-comprobante-proveedor', false))
                    <form action="{{ route('eliminar_comprobante_proveedor', ['id' => $data->id]) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('¿Borrar el comprobante #{{ $data->id }} en anitaERP y Anita (asiento, CC, compra/promov/ctamov)? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fa fa-times-circle"></i> Borrar factura
                        </button>
                    </form>
                    @if (($data->precarga_comprobante_proveedor_id ?? null) && can('borrar-precarga-proveedores', false))
                    <form action="{{ route('eliminar_comprobante_proveedor_con_precarga', ['id' => $data->id]) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('¿Borrar el comprobante #{{ $data->id }} y también la precarga #{{ $data->precarga_comprobante_proveedor_id }} (ERP + Anita)? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fa fa-trash"></i> Borrar factura y precarga
                        </button>
                    </form>
                    @endif
                    @endif
                </div>
            </div>

            <form
                action="{{ $esEdicion ? route('actualizar_comprobante_proveedor', ['id' => $data->id]) : route('guardar_comprobante_proveedor') }}"
                method="POST"
                id="form-comprobante-proveedor"
                class="form-horizontal form--label-right"
                enctype="multipart/form-data"
                autocomplete="off"
                @if ($esEdicion && ($data->id ?? null))
                data-comprobante-id="{{ (int) $data->id }}"
                @endif
                data-contabilizado="{{ (($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO) ? '1' : '0' }}"
                data-preview-url="{{ ($esEdicion && ($data->id ?? null)) ? route('preview_asiento_comprobante_proveedor', ['id' => $data->id]) : route('preview_asiento_comprobante_proveedor_nuevo') }}"
                data-puede-editar-concepto-iva="{{ can('editar-concepto-iva-compra', false) ? '1' : '0' }}"
                data-url-editar-concepto-iva="{{ url('compras/concepto_ivacompra/__ID__/editar') }}">
                @csrf
                @if ($esEdicion)
                    @method('PUT')
                @endif

                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas px-3 pt-2 bg-white border-bottom">
                    <ul class="nav nav-tabs" id="cp-tabs-comprobante" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active cp-tab-solapa" id="cp-boton-principal" data-toggle="tab"
                               href="#cp-solapa-principal" role="tab" aria-controls="cp-solapa-principal" aria-selected="true">
                                <i class="fa fa-file-text-o"></i> Datos principales
                            </a>
                        </li>
                        @if ($mostrarSolapaCom ?? false)
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-recepciones-com" data-toggle="tab"
                               href="#cp-solapa-recepciones-com" role="tab" aria-controls="cp-solapa-recepciones-com" aria-selected="false">
                                @if ($com_politica['permite_factura_anticipada'] ?? false)
                                    <i class="fa fa-clock-o"></i> Factura anticipada
                                @else
                                    <i class="fa fa-truck"></i> Recepciones COM
                                    @if ($com_obligatoria ?? false)
                                    <span class="badge badge-danger">Oblig.</span>
                                    @elseif ($com_politica['bloquea_sin_com'] ?? false)
                                    <span class="badge badge-danger">Falta COM</span>
                                    @endif
                                @endif
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-conceptos" data-toggle="tab"
                               href="#cp-solapa-conceptos" role="tab" aria-controls="cp-solapa-conceptos" aria-selected="false">
                                <i class="fa fa-list"></i> Conceptos IVA
                            </a>
                        </li>
                        @if ($mostrarSolapaArticulos ?? false)
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-articulos" data-toggle="tab"
                               href="#cp-solapa-articulos" role="tab" aria-controls="cp-solapa-articulos" aria-selected="false">
                                <i class="fa fa-cubes"></i> Artículos
                                @php
                                    $cantArt = old('articulo_skus')
                                        ? count((array) old('articulo_skus'))
                                        : (int) (($articulos ?? collect())->count());
                                @endphp
                                @if ($cantArt > 0)
                                <span class="badge badge-info">{{ $cantArt }}</span>
                                @endif
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-cuotas" data-toggle="tab"
                               href="#cp-solapa-cuotas" role="tab" aria-controls="cp-solapa-cuotas" aria-selected="false">
                                <i class="fa fa-calendar"></i> Cuotas / condición
                            </a>
                        </li>
                        @if ($mostrarSolapaAsiento ?? false)
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-asiento-contable" data-toggle="tab"
                               href="#cp-solapa-asiento-contable" role="tab" aria-controls="cp-solapa-asiento-contable" aria-selected="false">
                                <i class="fa fa-calculator"></i> Asiento contable
                                @if(! empty($asientoPreview['error']) || ! empty($asientoPreview['avisos'] ?? []))
                                <span class="badge badge-warning cp-badge-asiento-error" title="Revise el cuadre antes de contabilizar">!</span>
                                @elseif(! empty($data->asiento_id))
                                <span class="badge badge-success">OK</span>
                                @endif
                            </a>
                        </li>
                        @endif
                        @if ($esEdicion)
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-estados" data-toggle="tab"
                               href="#cp-solapa-estados" role="tab" aria-controls="cp-solapa-estados" aria-selected="false">
                                <i class="fa fa-history"></i> Estados e historia
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link cp-tab-solapa" id="cp-boton-archivos" data-toggle="tab"
                               href="#cp-solapa-archivos" role="tab" aria-controls="cp-solapa-archivos" aria-selected="false">
                                <i class="fa fa-paperclip"></i> Archivos
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>

                <div class="card-body">
                    @php
                        $avisosIniciales = $asientoPreview['avisos'] ?? [];
                        $errorInicial = $asientoPreview['error'] ?? null;
                        $hayAvisosIniciales = ! empty($avisosIniciales) || ! empty($errorInicial);
                    @endphp
                    <div id="cp-asiento-avisos-banner" class="alert alert-warning py-2 mb-3 {{ $hayAvisosIniciales ? '' : 'd-none' }}" role="alert">
                        @if ($hayAvisosIniciales)
                        <strong><i class="fa fa-exclamation-triangle"></i> Asiento contable:</strong>
                        <ul class="mb-0 mt-1 pl-3">
                            @if ($errorInicial)
                            <li>{{ $errorInicial }}</li>
                            @endif
                            @foreach ($avisosIniciales as $aviso)
                            <li>{{ $aviso['mensaje'] ?? '' }}</li>
                            @endforeach
                        </ul>
                        @endif
                    </div>

                    @if (! $esEdicion)
                    <div class="alert alert-info">
                        Origen de alta:
                        <strong>{{ \App\Support\Compras\ComprobanteProveedorOrigenEntrada::etiqueta($origen_entrada) }}</strong>
                    </div>
                    @endif

                    <input type="hidden" name="origen_entrada" value="{{ old('origen_entrada', $origen_entrada) }}">
                    <input type="hidden" name="precarga_comprobante_proveedor_id" value="{{ old('precarga_comprobante_proveedor_id', $data->precarga_comprobante_proveedor_id ?? '') }}">
                    <input type="hidden" name="ordencompra_id" value="{{ old('ordencompra_id', $data->ordencompra_id ?? '') }}">
                    <input type="hidden" name="ordencompra_comprobante_id" value="{{ old('ordencompra_comprobante_id', $data->ordencompra_comprobante_id ?? '') }}">
                    <input type="hidden" name="condicionpago_id" value="{{ old('condicionpago_id', $data->condicionpago_id ?? '') }}">

                    <div class="tab-content">
                        <div class="tab-pane fade show active cp-solapa" id="cp-solapa-principal" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_datos')
                            @if (! ($mostrarSolapaCom ?? false))
                                {{-- Sin solapa dedicada: bloque embebido por si el modo cambia a ASIGNA_RECEPCION --}}
                                <div id="cp-solapa-recepciones-com-inline" class="cp-solapa-inline mt-3">
                                    @include('compras.comprobante_proveedor.partials.solapa_recepciones_com')
                                </div>
                            @endif
                        </div>
                        @if ($mostrarSolapaCom ?? false)
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-recepciones-com" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_recepciones_com')
                        </div>
                        @endif
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-conceptos" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_conceptos')
                        </div>
                        @if ($mostrarSolapaArticulos ?? false)
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-articulos" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_articulos')
                        </div>
                        @endif
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-cuotas" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_cuotas')
                        </div>
                        @if ($mostrarSolapaAsiento ?? false)
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-asiento-contable" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_asiento_contable', [
                                'asientoPreview' => $asientoPreview ?? ['activo' => false],
                                'data' => $data,
                            ])
                        </div>
                        @endif
                        @if ($esEdicion)
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-estados" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_estados')
                        </div>
                        <div class="tab-pane fade cp-solapa" id="cp-solapa-archivos" role="tabpanel">
                            @include('compras.comprobante_proveedor.partials.solapa_archivos')
                        </div>
                        @endif
                    </div>
                </div>

                @if ($esEdicion && ($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO)
                    <div class="alert alert-success mx-3 mt-3">
                        Contabilizado. Asiento #{{ $data->asiento_id ?? '—' }}
                        @if ($data->anita_nro_interno)
                            · Anita nro interno {{ $data->anita_nro_interno }}
                        @endif
                    </div>
                @endif

                <div class="card-footer">
                    @if ($esEdicion && ($data->estado ?? '') !== \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO)
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" form="form-comprobante-proveedor" class="btn botonsubmit btn-success">Actualizar</button>
                        </div>
                    </div>
                    @elseif (! $esEdicion)
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" form="form-comprobante-proveedor" class="btn botonsubmit btn-success">Guardar</button>
                        </div>
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.compras.modalconsultaproveedor')
@include('includes.compras.modalconsultaconcepto_ivacompra')
@if ($mostrarSolapaArticulos ?? false)
@include('includes.stock.modalconsultaarticulo')
@endif
@include('includes.compras.arca_impuestos_validacion_modal')
@include('includes.compras.arca_apoc_validacion_modal')
@include('compras.comprobante_proveedor.partials.proveedor_arca_support')
@include('compras.comprobante_proveedor.partials.proveedor_arca_apoc_support')
@include('compras.comprobante_proveedor.template_concepto')
@if ($mostrarSolapaArticulos ?? false)
@include('compras.comprobante_proveedor.template_articulo')
@endif
@include('compras.comprobante_proveedor.partials.template_cp_archivos')
<script type="application/json" id="cp-conceptos-cuenta-meta">@json($conceptos_cuenta_meta ?? [])</script>
<script>
window.cpAbrirSolapaComAlInicio = {{
    ($mostrarSolapaCom ?? false) && (
        ($com_obligatoria ?? false)
        || count($recepciones_seleccionadas ?? []) > 0
        || old('modo_carga', $data->modo_carga ?? '') === \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION
    ) ? 'true' : 'false'
}};
</script>
