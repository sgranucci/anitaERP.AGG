@if ($data->ordencompra_id ?? null)
<div class="card card-outline card-info mb-3 border-info" id="cp-bloque-ordencompra-destacada">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
            <div>
                <div class="text-muted small mb-1"><i class="fa fa-file-text-o"></i> Orden de compra del legajo</div>
                <div class="h4 mb-0 text-primary font-weight-bold">
                    OC #{{ $data->ordencompras->numeroordencompra ?? $data->ordencompra_id }}
                </div>
                @if (optional($data->ordencompras->sector_legajocompras ?? null)->nombre)
                <div class="small mt-1">
                    Sector legajo:
                    <span class="badge badge-secondary">{{ $data->ordencompras->sector_legajocompras->nombre }}</span>
                </div>
                @endif
            </div>
            <div class="d-flex flex-wrap" style="gap:6px;">
                @if (can('editar-ordencompra', false) || can('listar-ordencompra', false))
                <a href="{{ route('editar_ordencompra', ['id' => $data->ordencompra_id]) }}"
                   class="btn btn-primary btn-sm" target="_blank" rel="noopener">
                    <i class="fa fa-external-link"></i> Abrir OC
                </a>
                @endif
                @if ($mostrarSolapaCom ?? false)
                <button type="button" class="btn btn-outline-info btn-sm" id="cp-abrir-solapa-com-desde-oc">
                    <i class="fa fa-truck"></i> Ver COM
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
@php
    $cpColLabel = 'col-lg-4 col-form-label control-label text-right pr-2';
    $cpColInput = 'col-lg-8';
    $tipoSelId = (int) old('tipotransaccion_compra_id', $data->tipotransaccion_compra_id ?? 0);
    $tipoAbrevActual = (string) ($data->tipotransaccion_compras->abreviatura ?? '');
    $tipoNombreActual = (string) ($data->tipotransaccion_compras->nombre ?? '');
    if ($tipoAbrevActual === '' && $tipoSelId > 0) {
        foreach ($tipotransaccion_compra_query ?? [] as $tipoOptAbrev) {
            if ((int) $tipoOptAbrev->id === $tipoSelId) {
                $tipoAbrevActual = (string) ($tipoOptAbrev->abreviatura ?? '');
                $tipoNombreActual = (string) ($tipoOptAbrev->nombre ?? '');
                break;
            }
        }
    }
    $centrocostoOcId = (int) ($data->ordencompras->centrocosto_id ?? 0);
    $desdePrecarga = (int) ($data->precarga_comprobante_proveedor_id ?? 0) > 0;
@endphp
<div class="row">
    <div class="col-sm-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? session('empresa_id')),
            'mostrar_id' => true,
            'col_label' => $cpColLabel,
            'col_input' => $cpColInput,
        ])
        @include('compras.partials.campo_consulta_tipotransaccion_compra', [
            'prefix' => 'comprobante_proveedor',
            'tipoId' => $tipoSelId,
            'abreviatura' => $tipoAbrevActual,
            'nombre' => $tipoNombreActual,
            'inputName' => 'tipotransaccion_compra_id',
            'inputId' => 'tipotransaccion_compra_id',
            'required' => true,
            'solo_lectura' => ! empty($bloqueado_edicion),
            'col_label' => $cpColLabel,
            'col_input' => $cpColInput,
            'centrocosto_id' => $centrocostoOcId,
        ])
        <div class="form-group row">
            <label class="{{ $cpColLabel }} requerido">Número</label>
            <div class="{{ $cpColInput }}">
                <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                    <input type="text" name="letra" id="letra" class="form-control text-center" maxlength="1"
                        value="{{ old('letra', $data->letra ?? '') }}" required style="width:3rem; flex:0 0 3rem;">
                    <span class="text-muted">#</span>
                    <input type="number" name="sucursal" id="sucursal" class="form-control text-right"
                        value="{{ old('sucursal', $data->sucursal ?? '') }}" required style="width:5.5rem; flex:0 0 5.5rem;">
                    <span class="text-muted">#</span>
                    <input type="number" name="numerocomprobante" id="numerocomprobante" class="form-control text-right"
                        value="{{ old('numerocomprobante', $data->numerocomprobante ?? '') }}" required style="flex:1; min-width:7rem;">
                </div>
            </div>
        </div>
        @include('includes.compras.campo_proveedor_consulta', [
            'proveedor_id' => ($data ?? null)?->proveedor_id,
            'codigo_proveedor' => ($data ?? null)?->proveedores?->codigo,
            'nombre_proveedor' => ($data ?? null)?->proveedores?->nombre,
            'requerido' => true,
            'mostrar_aviso_cuenta' => true,
            'col_label' => $cpColLabel,
            'col_input' => $cpColInput,
        ])
        <div class="form-group row">
            <label for="modo_carga" class="{{ $cpColLabel }}">Modo de carga</label>
            <div class="{{ $cpColInput }}">
                @php
                    $comObligatoria = (bool) ($com_obligatoria ?? false);
                    $comPolitica = $com_politica ?? [];
                    $permiteAnticipada = (bool) ($comPolitica['permite_factura_anticipada'] ?? false);
                    $bloqueaSinCom = (bool) ($comPolitica['bloquea_sin_com'] ?? false);
                    $modoActual = old('modo_carga', $data->modo_carga ?? '');
                    if ($comObligatoria) {
                        $modoActual = \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
                    } elseif ($permiteAnticipada) {
                        $modoActual = \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_OC;
                    }
                @endphp
                @if ($comObligatoria)
                    <input type="hidden" name="modo_carga" id="modo_carga" value="{{ \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION }}">
                    <input type="text" class="form-control" readonly
                        value="{{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta(\App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) }}">
                    <small class="form-text text-muted">Fijado: hay recepciones COM disponibles (flujo OC/COM/factura).</small>
                @elseif ($permiteAnticipada)
                    <input type="hidden" name="modo_carga" id="modo_carga" value="{{ \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_OC }}">
                    <input type="text" class="form-control" readonly
                        value="{{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta(\App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_OC) }}">
                    <small class="form-text text-muted">
                        OC anticipada sin COM todavía → factura anticipada. Puede haber varias en el mismo legajo.
                        Cuando exista COM, pasará a ser obligatoria.
                    </small>
                @else
                    <select name="modo_carga" id="modo_carga" class="form-control">
                        @foreach ($modos_carga as $modo)
                            <option value="{{ $modo }}" @if ($modoActual === $modo) selected @endif>
                                {{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta($modo) }}
                            </option>
                        @endforeach
                    </select>
                    @if ($bloqueaSinCom)
                    <div class="alert alert-danger py-2 mt-2 mb-0 small">
                        Esta empresa exige COM y la OC no es anticipada: confirme una recepción con provisión antes de cargar la factura.
                    </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="fechacomprobante" class="{{ $cpColLabel }} requerido">Fecha comprobante</label>
            <div class="col-lg-5">
                <input type="date" name="fechacomprobante" id="fechacomprobante" class="form-control"
                    value="{{ old('fechacomprobante', $data->fechacomprobante instanceof \DateTimeInterface ? $data->fechacomprobante->format('Y-m-d') : ($data->fechacomprobante ?? date('Y-m-d'))) }}" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="fechaiva" class="{{ $cpColLabel }} requerido">Fecha IVA</label>
            <div class="col-lg-5">
                @php
                    $fechaIvaDefault = date('Y-m-d');
                    if ($esEdicion ?? false) {
                        $fechaIvaDefault = $data->fechaiva instanceof \DateTimeInterface
                            ? $data->fechaiva->format('Y-m-d')
                            : ($data->fechaiva ?? date('Y-m-d'));
                    }
                @endphp
                <input type="date" name="fechaiva" id="fechaiva" class="form-control"
                    value="{{ old('fechaiva', $fechaIvaDefault) }}" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="fechavencimiento" class="{{ $cpColLabel }}">Vencimiento</label>
            <div class="col-lg-5">
                <input type="date" name="fechavencimiento" id="fechavencimiento" class="form-control"
                    value="{{ old('fechavencimiento', $data->fechavencimiento instanceof \DateTimeInterface ? $data->fechavencimiento->format('Y-m-d') : ($data->fechavencimiento ?? '')) }}">
            </div>
        </div>
        @if ($data->precarga_comprobante_proveedor_id ?? null)
        <div class="form-group row">
            <label class="{{ $cpColLabel }}">Precarga</label>
            <div class="{{ $cpColInput }}">
                <p class="form-control-plaintext mb-0">
                    #{{ $data->precarga_comprobante_proveedor_id }}
                    @if (can('editar-precarga-proveedores', false))
                    <a href="{{ route('editar_precarga_comprobante_proveedor', ['id' => $data->precarga_comprobante_proveedor_id]) }}" target="_blank" rel="noopener" class="text-primary">Abrir precarga</a>
                    @endif
                </p>
            </div>
        </div>
        @endif
    </div>
    <div class="col-sm-6">
        @if ($desdePrecarga)
        <div class="form-group row">
            <label for="tipo_autorizacion" class="{{ $cpColLabel }}">Tipo autorización</label>
            <div class="col-lg-3">
                @php
                    $tipoAut = old('tipo_autorizacion', $data->tipo_autorizacion ?? (filled(old('numerocae', $data->numerocae ?? '')) ? 'CAE' : ''));
                @endphp
                <select name="tipo_autorizacion" id="tipo_autorizacion" class="form-control" readonly disabled>
                    <option value="">—</option>
                    @foreach (\App\Support\Compras\ComprobanteProveedorTipoAutorizacion::todos() as $tipoOpt)
                        <option value="{{ $tipoOpt }}" @selected($tipoAut === $tipoOpt)>{{ $tipoOpt }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="tipo_autorizacion" value="{{ $tipoAut }}">
            </div>
            <label for="numerocae" class="col-lg-2 col-form-label control-label text-right pr-2">Nº CAE/CAEA</label>
            <div class="col-lg-3">
                <input type="text" name="numerocae" id="numerocae" class="form-control" readonly
                    value="{{ old('numerocae', $data->numerocae ?? '') }}">
            </div>
        </div>
        <div class="form-group row">
            <div class="{{ $cpColLabel }}"></div>
            <div class="{{ $cpColInput }}">
                <small class="form-text text-muted mt-0">Datos de la precarga (solo lectura). CAEA puede repetirse; CAE/CAI se controlan como únicos.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="fechavencimientocae" class="{{ $cpColLabel }}">Vto. CAE</label>
            <div class="col-lg-5">
                <input type="date" name="fechavencimientocae" id="fechavencimientocae" class="form-control" readonly
                    value="{{ old('fechavencimientocae', $data->fechavencimientocae instanceof \DateTimeInterface ? $data->fechavencimientocae->format('Y-m-d') : ($data->fechavencimientocae ?? '')) }}">
            </div>
        </div>
        @endif
        <div class="form-group row">
            <label for="moneda_id" class="{{ $cpColLabel }} requerido">Moneda</label>
            <div class="col-lg-3">
                @php
                    $monedaSelId = (int) old('moneda_id', $data->moneda_id ?? 1);
                @endphp
                <select name="moneda_id" id="moneda_id" class="form-control" required
                    @if (! empty($bloqueado_edicion)) disabled @endif>
                    @foreach ($moneda_query ?? [] as $monedaOpt)
                        <option value="{{ $monedaOpt->id }}"
                            @selected($monedaSelId === (int) $monedaOpt->id)>
                            {{ $monedaOpt->nombre }}@if (filled($monedaOpt->abreviatura ?? null)) ({{ $monedaOpt->abreviatura }})@endif
                        </option>
                    @endforeach
                </select>
                @if (! empty($bloqueado_edicion))
                    <input type="hidden" name="moneda_id" value="{{ $monedaSelId }}">
                @endif
            </div>
            <label for="cotizacion" class="col-lg-2 col-form-label control-label text-right pr-2">Cotización</label>
            <div class="col-lg-3">
                @php
                    $cotDiaRef = (float) ($cotizacion_dia ?? 1);
                    $cotOrigenRef = (string) ($cotizacion_origen ?? 'dia');
                    $cotFacturaRef = $cotizacion_factura !== null ? (float) $cotizacion_factura : null;
                @endphp
                <input type="number" step="0.0001" name="cotizacion" id="cotizacion" class="form-control text-right"
                    value="{{ old('cotizacion', $data->cotizacion ?? 1) }}"
                    data-cotizacion-origen="{{ $cotOrigenRef }}"
                    data-cotizacion-dia="{{ $cotDiaRef }}"
                    data-cotizacion-factura="{{ $cotFacturaRef !== null ? $cotFacturaRef : '' }}">
            </div>
        </div>
        <div class="form-group row">
            <div class="{{ $cpColLabel }}"></div>
            <div class="{{ $cpColInput }}">
                <small id="cp-cotizacion-dia-hint" class="form-text text-muted mt-0">
                    @if ((int) old('moneda_id', $data->moneda_id ?? 1) > 1)
                        Cotización del día (venta): <strong id="cp-cotizacion-dia-valor">{{ number_format($cotDiaRef, 4, ',', '.') }}</strong>
                        @if ($cotFacturaRef !== null && abs($cotFacturaRef - $cotDiaRef) > 0.0000005)
                            · En factura/precarga: <strong id="cp-cotizacion-factura-valor">{{ number_format($cotFacturaRef, 4, ',', '.') }}</strong>
                            (campo usa la de factura/precarga)
                        @endif
                    @else
                        Moneda local: cotización = 1
                    @endif
                </small>
                <small id="cp-aviso-cotizacion-com" class="form-text text-muted">
                    En ME: si difiere de la COM del mismo mes, se actualiza la recepción; si es de otro mes, el legajo vuelve a Compras.
                </small>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="subtotal" class="{{ $cpColLabel }}">Subtotal</label>
            <div class="col-lg-5">
                <input type="text" inputmode="decimal" name="subtotal" id="subtotal"
                    class="form-control js-monto-ar text-right bg-light"
                    value="{{ number_format((float) old('subtotal', $data->subtotal ?? 0), 2, ',', '.') }}"
                    readonly tabindex="-1"
                    title="Se calcula con la suma de conceptos netos">
                <small class="form-text text-muted mt-0">Calculado desde conceptos (neto).</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="total" class="{{ $cpColLabel }}">Total</label>
            <div class="col-lg-5">
                <input type="text" inputmode="decimal" name="total" id="total"
                    class="form-control js-monto-ar text-right bg-light"
                    value="{{ number_format((float) old('total', $data->total ?? 0), 2, ',', '.') }}"
                    readonly tabindex="-1"
                    title="Se calcula con la suma de todos los conceptos">
                <small class="form-text text-muted mt-0">Calculado desde conceptos.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="leyenda" class="{{ $cpColLabel }}">Leyenda</label>
            <div class="{{ $cpColInput }}">
                <textarea name="leyenda" id="leyenda" class="form-control" rows="2">{{ old('leyenda', $data->leyenda ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-group row">
            <label for="pararevisar" class="{{ $cpColLabel }}">Para revisar</label>
            <div class="col-lg-5">
                <select name="pararevisar" id="pararevisar" class="form-control">
                    <option value="0" @if (! old('pararevisar', $data->pararevisar ?? false)) selected @endif>Sin errores</option>
                    <option value="1" @if (old('pararevisar', $data->pararevisar ?? false)) selected @endif>Para revisar</option>
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="es_fce" class="{{ $cpColLabel }}">FCE</label>
            <div class="col-lg-5">
                <select name="es_fce" id="es_fce" class="form-control">
                    <option value="0" @if (! old('es_fce', $data->es_fce ?? false)) selected @endif>No</option>
                    <option value="1" @if (old('es_fce', $data->es_fce ?? false)) selected @endif>Sí</option>
                </select>
            </div>
        </div>
    </div>
</div>

@php
    $idsComBanner = array_values(array_filter(array_map('intval', (array) old(
        'recepcion_proveedor_ids',
        $recepciones_seleccionadas ?? []
    ))));
    $etiquetasComBanner = [];
    foreach ($idsComBanner as $idComBanner) {
        $recepcionBanner = ($recepciones_disponibles ?? collect())->firstWhere('id', $idComBanner);
        if (! $recepcionBanner) {
            $vinculoBanner = optional($data->comprobante_proveedor_recepciones ?? collect())
                ->firstWhere('recepcion_proveedor_id', $idComBanner);
            $recepcionBanner = $vinculoBanner?->recepcion_proveedores;
        }
        $numeroBanner = $recepcionBanner->numerorecepcion ?? null;
        $etiquetasComBanner[] = filled($numeroBanner)
            ? (string) $numeroBanner.' (ID '.$idComBanner.')'
            : '#'.$idComBanner;
    }
    $mostrarBannerCom = ($mostrarSolapaCom ?? false)
        && (
            ($com_obligatoria ?? false)
            || count($idsComBanner) > 0
            || old('modo_carga', $data->modo_carga ?? '') === \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION
        );
@endphp
@if ($mostrarBannerCom)
<div class="alert alert-info mt-3 mb-0" id="cp-banner-com-datos"
     data-com-obligatoria="{{ ($com_obligatoria ?? false) ? '1' : '0' }}">
    <i class="fa fa-truck"></i>
    <span id="cp-banner-com-texto">
        @if (count($etiquetasComBanner) > 0)
            COM asignada(s):
            <strong>{{ implode(', ', $etiquetasComBanner) }}</strong>.
        @elseif ($com_obligatoria ?? false)
            Debe asignar recepción(es) COM obligatoria(s).
        @else
            Este comprobante usa modo asignación de recepción COM.
        @endif
    </span>
    <button type="button" class="btn btn-sm btn-outline-primary ml-2" id="cp-abrir-solapa-com-desde-datos">
        Ir a Recepciones COM
    </button>
</div>
@endif
