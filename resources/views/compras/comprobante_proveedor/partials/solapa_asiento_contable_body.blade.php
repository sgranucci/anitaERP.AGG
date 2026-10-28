@php
    $preview = $asientoPreview ?? ['activo' => false];
    $puedeVerAsiento = can('listar-asiento', false) || can('editar-asiento', false);
    $urlAsiento = (! empty($preview['asiento_id']) && $puedeVerAsiento)
        ? route('editar_asiento', [
            'id' => (int) $preview['asiento_id'],
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : null;
    $proveedorId = (int) ($data->proveedor_id ?? 0);
    $errorTexto = (string) ($preview['error'] ?? '');
    $permiteRepartoGasto = ! empty($preview['es_preview']) && ! empty($preview['permite_reparto_gasto']);
    $netoImputableGasto = (float) ($preview['neto_imputable_gasto'] ?? 0);
    $tieneRepartoGasto = ! empty($preview['tiene_reparto_gasto']);
    $lineasDebeGastoUi = collect($preview['lineas'] ?? [])->filter(function ($l) {
        return (string) ($l['origen'] ?? '') === 'debe_gasto';
    })->values();
    $cantDebeGastoUi = $lineasDebeGastoUi->count();
@endphp

@if(empty($preview['activo']))
<div class="alert alert-secondary mb-0">
    La vista previa del asiento no está disponible.
</div>
@elseif(! empty($preview['error']))
<div class="alert alert-danger">
    <strong>No se puede generar el asiento:</strong> {{ $preview['error'] }}
    @if(str_contains($errorTexto, 'tipo de asiento') || str_contains($errorTexto, 'COM'))
    <div class="mt-2 small">
        Verifique en Contable &rarr; Tipos de asiento que exista la abreviatura <strong>COM</strong> (Compras).
    </div>
    @endif
    @if(str_contains($errorTexto, 'concepto IVA') || str_contains($errorTexto, 'cuenta contable del neto') || str_contains($errorTexto, 'solapa Asiento') || str_contains($errorTexto, 'reparto de cuentas'))
    <div class="mt-2 small">
        @if(str_contains($errorTexto, 'neto') || str_contains($errorTexto, 'Asiento') || str_contains($errorTexto, 'reparto'))
        Indique la cuenta (y el importe, si reparte el gasto) en las líneas editables de esta solapa
        (o asocie una OC para tomar las cuentas de sus artículos).
        @else
        Asigne la cuenta en el maestro
        @if(can('editar-concepto-iva-compra', false))
        <a href="{{ route('concepto_ivacompra') }}" class="text-primary" target="_blank" rel="noopener">Conceptos IVA compra</a>
        @else
        Conceptos IVA compra
        @endif
        y vuelva a recalcular la vista previa.
        @endif
    </div>
    @endif
    @if(str_contains($errorTexto, 'proveedor no tiene cuenta') && $proveedorId > 0 && can('editar-proveedor', false))
    <div class="mt-2 small">
        Configure la cuenta contable del proveedor en
        <a href="{{ route('editar_proveedor', ['id' => $proveedorId]) }}" class="text-primary" target="_blank" rel="noopener">ABM Proveedor</a>.
    </div>
    @endif
    @if(str_contains($errorTexto, 'provisión de facturas a recibir'))
    <div class="mt-2 small">
        Configure la cuenta en Stock &rarr; Configuración recepción proveedor para la empresa del comprobante.
    </div>
    @endif
    @if(str_contains($errorTexto, 'anticipo a proveedores'))
    <div class="mt-2 small">
        Configure las cuentas de anticipo (factura anticipada / bienes de uso) en Stock &rarr; Configuración recepción proveedor para la empresa del comprobante.
    </div>
    @endif
</div>
@else
@if(! empty($preview['es_preview']))
<div class="alert alert-info py-2 mb-2">
    Vista previa en tiempo real: el asiento se grabará al <strong>Contabilizar</strong> el comprobante.
    @if($permiteRepartoGasto)
    <span class="d-block small mt-1">
        Gasto sin COM: puede repartir el Debe en varias cuentas con
        <strong>+ Agregar cuenta de gasto</strong>. La suma no puede superar el neto
        ({{ number_format($netoImputableGasto, 2, ',', '.') }}).
        Por defecto no recalcula el prorrateo (cada importe queda como se carga, para abrir por centro de costo).
        Tilde <strong>Recalcular prorrateo</strong> si quiere que el neto se reparta solo entre las cuentas.
        Si la cuenta de IVA pide centro de costo, toma el de las cuentas de gasto.
        Al abrir el gasto con <strong>+ Agregar cuenta de gasto</strong>, el IVA se parte en la misma proporción.
        Con una sola línea de IVA puede cambiar el centro en esa fila.
    </span>
    @elseif(collect($preview['lineas'] ?? [])->contains(fn ($l) => ! empty($l['editable_cuenta'])))
    <span class="d-block small mt-1">La cuenta de un neto se precarga en los otros netos vacíos. Si una línea necesita otra cuenta, cámbiela en esa fila.</span>
    @endif
</div>
@else
<div class="d-flex flex-wrap align-items-center mb-3" style="gap: 8px;">
    <div>
        <strong>Nº asiento:</strong> {{ $preview['numeroasiento'] ?? '—' }}
        @if(! empty($preview['tipo_asiento']))
        <span class="text-muted ml-2">({{ $preview['tipo_asiento'] }})</span>
        @endif
        @if(! empty($preview['fecha']))
        <span class="text-muted ml-2">Fecha {{ $preview['fecha'] }}</span>
        @endif
    </div>
    @if($urlAsiento)
    <a href="{{ $urlAsiento }}" class="btn btn-sm btn-outline-primary" title="Consultar asiento contable (sin menú)"
       onclick="window.open(this.href, '_blank'); return false;">
        <i class="fa fa-external-link"></i> Abrir asiento
    </a>
    @endif
    @if(! empty($preview['asiento_id']) && $puedeVerAsiento)
    <a href="{{ route('imprimir_pdf_asiento', ['id' => (int) $preview['asiento_id']]) }}"
       class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" title="PDF del asiento">
        <i class="fa fa-file-pdf-o"></i> PDF
    </a>
    @endif
</div>
@endif

<div class="table-responsive"
     id="cp-asiento-tabla-wrap"
     data-permite-reparto-gasto="{{ $permiteRepartoGasto ? '1' : '0' }}"
     data-neto-imputable-gasto="{{ $netoImputableGasto }}"
     data-tiene-reparto-gasto="{{ $tieneRepartoGasto ? '1' : '0' }}"
     data-total-comprobante="{{ (float) ($preview['total_comprobante'] ?? 0) }}">
    <table class="table table-bordered table-sm" id="tabla-asiento-comprobante-proveedor">
        <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
                <th>Cuenta</th>
                <th>Centro costo</th>
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
                <th>Observación</th>
                @if($permiteRepartoGasto)
                <th style="width:3rem;"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $idxDebeGasto = 0;
                $cantImpuestoPorConcepto = [];
                foreach ($preview['lineas'] ?? [] as $lineaConteo) {
                    if ((string) ($lineaConteo['origen'] ?? '') !== 'impuesto') {
                        continue;
                    }
                    $conceptoConteo = (int) ($lineaConteo['concepto_ivacompra_id'] ?? 0);
                    if ($conceptoConteo <= 0) {
                        continue;
                    }
                    $cantImpuestoPorConcepto[$conceptoConteo] = ($cantImpuestoPorConcepto[$conceptoConteo] ?? 0) + 1;
                }
            @endphp
            @forelse(($preview['lineas'] ?? []) as $linea)
            @php
                $origenLinea = (string) ($linea['origen'] ?? '');
                // Solo origen debe_gasto es reparto multi-cuenta. neto_manual NO lleva
                // cp-debe-gasto-row: si no, el JS lo reenvía y duplica el Debe.
                $esDebeGasto = $origenLinea === 'debe_gasto';
                $editableCuenta = ! empty($preview['es_preview']) && ! empty($linea['editable_cuenta']);
                $editableImporte = $permiteRepartoGasto && $esDebeGasto;
                $cuentaLineaId = (int) ($linea['cuentacontable_id'] ?? 0);
                $conceptoLineaId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
                $debeVal = ($linea['debe'] ?? null) !== null ? (float) $linea['debe'] : null;
                if ($esDebeGasto) {
                    $idxDebeGasto++;
                }
                $debeGastoIdx = $esDebeGasto ? $idxDebeGasto : 0;
                $ivaUnaLinea = $origenLinea === 'impuesto'
                    && $conceptoLineaId > 0
                    && ($cantImpuestoPorConcepto[$conceptoLineaId] ?? 0) === 1;
                $muestraSelectorCentro = ! empty($preview['es_preview'])
                    && $cuentaLineaId > 0
                    && ($editableCuenta || $ivaUnaLinea);
            @endphp
            <tr class="{{ $editableCuenta ? 'cp-asiento-linea-editable' : '' }}{{ $esDebeGasto ? ' cp-debe-gasto-row' : '' }}"
                data-origen="{{ $origenLinea }}"
                @if($conceptoLineaId > 0) data-concepto-ivacompra-id="{{ $conceptoLineaId }}" @endif
                @if($esDebeGasto) data-debe-gasto="1" data-debe-gasto-idx="{{ $debeGastoIdx }}" @endif>
                <td>
                    @if($editableCuenta)
                    <div class="tm-cuentacontable-campo cp-asiento-cuenta-editable d-flex flex-nowrap align-items-center" style="gap:4px;"
                         data-concepto-ivacompra-id="{{ $conceptoLineaId }}"
                         @if($esDebeGasto) data-debe-gasto="1" data-debe-gasto-idx="{{ $debeGastoIdx }}" @endif>
                        <input type="hidden" class="cuentacontable_id" value="{{ $cuentaLineaId > 0 ? $cuentaLineaId : '' }}">
                        <button type="button" title="Elegir cuenta del neto" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5rem;flex-shrink:0;"
                               value="{{ $linea['cuenta_codigo'] !== '—' ? ($linea['cuenta_codigo'] ?? '') : '' }}"
                               placeholder="Cód." autocomplete="off">
                        <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly
                               value="{{ $linea['cuenta_nombre'] ?? '' }}" placeholder="Cuenta del neto" style="min-width:0;flex:1 1 auto;">
                    </div>
                    @else
                    <span class="font-weight-bold">{{ $linea['cuenta_codigo'] ?? '—' }}</span>
                    @if(! empty($linea['cuenta_nombre']))
                    <span class="d-block small text-muted">{{ $linea['cuenta_nombre'] }}</span>
                    @endif
                    @endif
                </td>
                <td>
                    @if($muestraSelectorCentro)
                    <select class="form-control form-control-sm cp-asiento-centrocosto"
                            data-cuenta-id="{{ $cuentaLineaId }}"
                            data-cc-actual="{{ (int) ($linea['centrocosto_id'] ?? 0) }}"
                            title="Centros de costo de la cuenta">
                        @if((int) ($linea['centrocosto_id'] ?? 0) > 0)
                        <option value="{{ (int) $linea['centrocosto_id'] }}" selected>{{ $linea['centrocosto_codigo'] ?: $linea['centrocosto_id'] }}</option>
                        @else
                        <option value="">—</option>
                        @endif
                    </select>
                    @else
                    {{ $linea['centrocosto_codigo'] ?: '—' }}
                    @endif
                </td>
                <td class="text-right">
                    @if($editableImporte)
                    <input type="text" inputmode="decimal"
                           class="form-control form-control-sm text-right js-monto-ar cp-debe-gasto-importe"
                           value="{{ number_format((float) ($debeVal ?? 0), 2, ',', '.') }}"
                           title="Importe Debe de esta cuenta de gasto">
                    @elseif($debeVal !== null)
                    {{ number_format($debeVal, 2, ',', '.') }}
                    @endif
                </td>
                <td class="text-right">
                    @if(($linea['haber'] ?? null) !== null)
                    {{ number_format((float) $linea['haber'], 2, ',', '.') }}
                    @endif
                </td>
                <td>{{ $linea['observacion'] ?? '' }}</td>
                @if($permiteRepartoGasto)
                <td class="text-center align-middle">
                    @if($esDebeGasto && $cantDebeGastoUi > 1)
                    <button type="button" class="btn-accion-tabla cp-debe-gasto-quitar tooltipsC" title="Quitar cuenta de gasto">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                    @endif
                </td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ $permiteRepartoGasto ? 6 : 5 }}" class="text-center text-muted">Sin líneas de asiento para mostrar.</td>
            </tr>
            @endforelse
        </tbody>
        @if(! empty($preview['total_debe']) || ! empty($preview['total_haber']))
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="2" class="text-right">Totales</td>
                <td class="text-right cp-asiento-total-debe">{{ number_format((float) ($preview['total_debe'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right cp-asiento-total-haber">{{ number_format((float) ($preview['total_haber'] ?? 0), 2, ',', '.') }}</td>
                <td @if($permiteRepartoGasto) colspan="2" @endif></td>
            </tr>
            @if(! empty($preview['es_preview']) && isset($preview['total_comprobante']))
            <tr>
                <td colspan="2" class="text-right text-muted">Total comprobante</td>
                <td colspan="{{ $permiteRepartoGasto ? 4 : 3 }}" class="text-muted">{{ number_format((float) $preview['total_comprobante'], 2, ',', '.') }}</td>
            </tr>
            @endif
        </tfoot>
        @endif
    </table>
</div>

@if($permiteRepartoGasto)
<div class="mt-2 mb-1">
    <button type="button" class="btn btn-outline-primary btn-sm" id="cp-debe-gasto-agregar">
        <i class="fa fa-plus"></i> Agregar cuenta de gasto
    </button>
    <label class="small mb-0 ml-3 align-middle font-weight-normal" title="Destildado: no reparte el neto entre las cuentas. La suma no puede superarlo.">
        <input type="checkbox" class="cp-debe-gasto-recalcular-prorrateo align-middle mr-1">
        Recalcular prorrateo
    </label>
    <span class="small text-muted ml-2" id="cp-debe-gasto-aviso-suma"></span>
</div>
@endif
@endif
