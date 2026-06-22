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
    @if(str_contains($errorTexto, 'concepto IVA') && can('editar-concepto-iva-compra', false))
    <div class="mt-2 small">
        Revise las cuentas contables en
        <a href="{{ route('concepto_ivacompra') }}" class="text-primary" target="_blank" rel="noopener">Conceptos IVA compra</a>
        y vuelva a recalcular la vista previa.
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
</div>
@else
@if(! empty($preview['es_preview']))
<div class="alert alert-info py-2 mb-2">
    Vista previa en tiempo real: el asiento se grabará al <strong>Contabilizar</strong> el comprobante.
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
    <a href="{{ $urlAsiento }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Consultar asiento contable (sin menú)">
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

<div class="table-responsive">
    <table class="table table-bordered table-sm" id="tabla-asiento-comprobante-proveedor">
        <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
                <th>Cuenta</th>
                <th>Centro costo</th>
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($preview['lineas'] ?? []) as $linea)
            <tr>
                <td>
                    <span class="font-weight-bold">{{ $linea['cuenta_codigo'] ?? '—' }}</span>
                    @if(! empty($linea['cuenta_nombre']))
                    <span class="d-block small text-muted">{{ $linea['cuenta_nombre'] }}</span>
                    @endif
                </td>
                <td>{{ $linea['centrocosto_codigo'] ?: '—' }}</td>
                <td class="text-right">
                    @if(($linea['debe'] ?? null) !== null)
                    {{ number_format((float) $linea['debe'], 2, ',', '.') }}
                    @endif
                </td>
                <td class="text-right">
                    @if(($linea['haber'] ?? null) !== null)
                    {{ number_format((float) $linea['haber'], 2, ',', '.') }}
                    @endif
                </td>
                <td>{{ $linea['observacion'] ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center text-muted">Sin líneas de asiento para mostrar.</td>
            </tr>
            @endforelse
        </tbody>
        @if(! empty($preview['total_debe']) || ! empty($preview['total_haber']))
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="2" class="text-right">Totales</td>
                <td class="text-right">{{ number_format((float) ($preview['total_debe'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($preview['total_haber'] ?? 0), 2, ',', '.') }}</td>
                <td></td>
            </tr>
            @if(! empty($preview['es_preview']) && isset($preview['total_comprobante']))
            <tr>
                <td colspan="2" class="text-right text-muted">Total comprobante</td>
                <td colspan="3" class="text-muted">{{ number_format((float) $preview['total_comprobante'], 2, ',', '.') }}</td>
            </tr>
            @endif
        </tfoot>
        @endif
    </table>
</div>
@endif
