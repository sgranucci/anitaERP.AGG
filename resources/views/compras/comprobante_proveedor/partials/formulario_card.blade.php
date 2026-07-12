<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-danger">
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

                <div class="text-center py-2 border-bottom rounded-top bg-white">
                    <button type="button" id="cp-boton-principal" class="btn btn-primary btn-sm mx-1 cp-tab-solapa font-weight-bold">Datos principales</button>
                    <button type="button" id="cp-boton-conceptos" class="btn btn-info btn-sm mx-1 cp-tab-solapa">Conceptos IVA</button>
                    <button type="button" id="cp-boton-cuotas" class="btn btn-info btn-sm mx-1 cp-tab-solapa">Cuotas / condición</button>
                    @if ($mostrarSolapaAsiento ?? false)
                    <button type="button" id="cp-boton-asiento-contable" class="btn btn-info btn-sm mx-1 cp-tab-solapa">
                        <span class="fa fa-calculator"></span> Asiento contable
                        @if(! empty($asientoPreview['error']) || ! empty($asientoPreview['avisos'] ?? []))
                        <span class="badge badge-warning ml-1 cp-badge-asiento-error" title="Revise el cuadre antes de contabilizar">!</span>
                        @elseif(! empty($data->asiento_id))
                        <span class="badge badge-light ml-1">OK</span>
                        @endif
                    </button>
                    @endif
                    @if ($esEdicion)
                    <button type="button" id="cp-boton-estados" class="btn btn-info btn-sm mx-1 cp-tab-solapa">Estados e historia</button>
                    <button type="button" id="cp-boton-archivos" class="btn btn-info btn-sm mx-1 cp-tab-solapa">
                        <span class="fa fa-paperclip"></span> Archivos
                    </button>
                    @endif
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

                    <div id="cp-solapa-principal" class="cp-solapa">
                        @include('compras.comprobante_proveedor.partials.solapa_datos')
                        @include('compras.comprobante_proveedor.partials.solapa_recepciones_com')
                    </div>
                    <div id="cp-solapa-conceptos" class="cp-solapa" style="display:none;">
                        @include('compras.comprobante_proveedor.partials.solapa_conceptos')
                    </div>
                    <div id="cp-solapa-cuotas" class="cp-solapa" style="display:none;">
                        @include('compras.comprobante_proveedor.partials.solapa_cuotas')
                    </div>
                    @if ($mostrarSolapaAsiento ?? false)
                    <div id="cp-solapa-asiento-contable" class="cp-solapa" style="display:none;">
                        @include('compras.comprobante_proveedor.partials.solapa_asiento_contable', [
                            'asientoPreview' => $asientoPreview ?? ['activo' => false],
                            'data' => $data,
                        ])
                    </div>
                    @endif
                    @if ($esEdicion)
                    <div id="cp-solapa-estados" class="cp-solapa" style="display:none;">
                        @include('compras.comprobante_proveedor.partials.solapa_estados')
                    </div>
                    <div id="cp-solapa-archivos" class="cp-solapa" style="display:none;">
                        @include('compras.comprobante_proveedor.partials.solapa_archivos')
                    </div>
                    @endif
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
                            @if ($esEdicion)
                                @include('includes.boton-form-editar')
                            @else
                                @include('includes.boton-form-crear')
                            @endif
                        </div>
                    </div>
                    @elseif (! $esEdicion)
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.compras.modalconsultaproveedor')
@include('includes.compras.arca_impuestos_validacion_modal')
@include('includes.compras.arca_apoc_validacion_modal')
@include('compras.comprobante_proveedor.partials.proveedor_arca_support')
@include('compras.comprobante_proveedor.partials.proveedor_arca_apoc_support')
@include('compras.comprobante_proveedor.template_concepto')
@include('compras.comprobante_proveedor.partials.template_cp_archivos')
<script type="application/json" id="cp-conceptos-cuenta-meta">@json($conceptos_cuenta_meta ?? [])</script>
