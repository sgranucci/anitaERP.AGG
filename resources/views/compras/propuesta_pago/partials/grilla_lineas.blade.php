@php
    use App\Support\Compras\PropuestaPagoLineaPresentacionSupport;
    $lineas = $data->lineas ?? collect();
    $editableGrilla = isset($data->id) && in_array((string) ($data->estado ?? ''), \App\Models\Compras\PropuestaPago::estadosEditables(), true);
    $exclusionPostArbol = isset($data->id) && (string) ($data->estado ?? '') === 'AUTORIZADA';
    $resumen = PropuestaPagoLineaPresentacionSupport::resumenBuckets($lineas, $data ?? null);
@endphp
@if (isset($data->id))
@if ($data->monto_autorizado)
<div class="alert alert-secondary py-2 mb-2">
    Monto autorizado (árbol): <strong>{{ number_format((float)$data->monto_autorizado, 2, ',', '.') }}</strong>
    — A ejecutar (líneas incluidas): <strong>{{ number_format((float)$data->monto_total, 2, ',', '.') }}</strong>
    @if ($exclusionPostArbol)
        <span class="text-muted">— Puede desmarcar líneas sin alterar el monto autorizado.</span>
    @endif
</div>
@endif
<div class="row mb-2">
    <div class="col-md-3">
        <div class="alert alert-danger mb-2 py-2">
            <strong>Vencidos</strong> ({{ $resumen['cant_vencidos'] }}):
            {{ number_format($resumen['vencidos'], 2, ',', '.') }}
        </div>
    </div>
    <div class="col-md-3">
        <div class="alert alert-warning mb-2 py-2">
            <strong>A vencer</strong> ({{ $resumen['cant_a_vencer'] }}):
            {{ number_format($resumen['a_vencer'], 2, ',', '.') }}
        </div>
    </div>
    <div class="col-md-3">
        <div class="alert alert-info mb-2 py-2">
            <strong>Total propuesto</strong>:
            {{ number_format($resumen['total'], 2, ',', '.') }}
        </div>
    </div>
    <div class="col-md-3">
        <div class="alert alert-secondary mb-2 py-2">
            <strong>Por medio</strong>
            <ul class="mb-0 pl-3 small">
                @forelse(($resumen['por_medio'] ?? []) as $pm)
                    <li>{{ $pm['medio'] }}: {{ number_format($pm['monto'], 2, ',', '.') }} ({{ $pm['cant'] }})</li>
                @empty
                    <li class="text-muted">Sin líneas</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="card card-outline card-info mt-1">
    <div class="card-header">
        <h3 class="card-title">Proyección de pagos (estilo Anita l-proy) — forma/medio de pago de la OC/cuota</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-bordered table-hover" id="tabla-propuesta-lineas" style="font-size:12px;">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Incl.</th>
                    <th>N.Pro.</th>
                    <th>Nombre</th>
                    <th>Tip</th>
                    <th>Comprobante</th>
                    <th>F.Comp.</th>
                    <th>F.Iva</th>
                    <th>F.Vto.</th>
                    <th class="text-right">Días</th>
                    <th>Condición pago</th>
                    <th>N.Refer</th>
                    <th>M.Pago</th>
                    <th>Detalle pago</th>
                    <th>Cta.egreso</th>
                    <th>Mon.</th>
                    <th class="text-right">Saldo</th>
                    <th class="text-right">Monto prop.</th>
                    <th>Bucket</th>
                    <th>OP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lineas as $linea)
                    @php $p = PropuestaPagoLineaPresentacionSupport::enriquecer($linea, $data); @endphp
                    <tr class="{{ $p['bucket'] === 'vencido' ? 'table-danger' : '' }}">
                        <td class="text-center">
                            <input type="hidden" name="linea_ids[]" value="{{ $linea->id }}">
                            @if ($editableGrilla || $exclusionPostArbol)
                                <input type="checkbox" name="incluidos[]" value="{{ $linea->id }}" {{ $linea->incluido ? 'checked' : '' }} class="pp-incluido">
                            @else
                                {{ $linea->incluido ? 'Sí' : 'No' }}
                            @endif
                        </td>
                        <td>{{ $p['codigo_proveedor'] }}</td>
                        <td>{{ $p['nombre_proveedor'] }}</td>
                        <td>{{ $p['tipo'] }}</td>
                        <td class="text-nowrap">{{ $p['comprobante'] }}</td>
                        <td>{{ $p['fecha_comprobante'] }}</td>
                        <td>{{ $p['fecha_iva'] }}</td>
                        <td>{{ $p['fecha_vto'] }}</td>
                        <td class="text-right">{{ $p['dias'] !== null ? $p['dias'] : '' }}</td>
                        <td>{{ $p['condicion_pago'] }}</td>
                        <td>
                            @if (! empty($p['nro_refer']) && can('editar-ordencompra', false))
                                <a href="{{ route('editar_ordencompra', $p['nro_refer']) }}" class="text-primary" target="_blank" rel="noopener">{{ $p['nro_refer'] }}</a>
                            @else
                                {{ $p['nro_refer'] }}
                            @endif
                        </td>
                        <td><strong>{{ $p['medio_pago'] }}</strong></td>
                        <td>{{ $p['detalle_pago'] }}</td>
                        <td style="min-width:140px;">
                            @if ($editableGrilla || $exclusionPostArbol)
                                <select name="linea_cuentacaja_ids[]" class="form-control form-control-sm">
                                    <option value="">(cabecera)</option>
                                    @foreach(($cuentacaja_query ?? []) as $cc)
                                        <option value="{{ $cc->id }}" @selected((int)($linea->cuentacaja_id ?? 0) === (int)$cc->id)>
                                            {{ $cc->codigo ?? $cc->id }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                {{ $linea->cuentacajas->codigo ?? ($linea->cuentacaja_id ?: '') }}
                            @endif
                        </td>
                        <td>{{ $p['moneda'] }}</td>
                        <td class="text-right">{{ number_format((float)$linea->saldo_deuda, 2, ',', '.') }}</td>
                        <td class="text-right">
                            @if ($editableGrilla)
                                <input type="number" step="0.01" min="0" name="montos_propuestos[]" class="form-control form-control-sm text-right pp-monto"
                                       value="{{ number_format((float)$linea->monto_propuesto, 2, '.', '') }}">
                            @else
                                {{ number_format((float)$linea->monto_propuesto, 2, ',', '.') }}
                            @endif
                        </td>
                        <td>
                            @if ($p['bucket'] === 'vencido')
                                <span class="badge badge-danger">Vencido</span>
                            @elseif ($p['bucket'] === 'a_vencer')
                                <span class="badge badge-warning">A vencer</span>
                            @else
                                <span class="badge badge-secondary">S/vto</span>
                            @endif
                        </td>
                        <td>
                            @if ($linea->pagoproveedor_id)
                                <a href="{{ route('editar_pagoproveedor', $linea->pagoproveedor_id) }}" class="text-primary" target="_blank" rel="noopener">#{{ $linea->pagoproveedor_id }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="19" class="text-center text-muted">Sin líneas. Rearme desde deuda o amplíe el rango de vencimientos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        M.Pago / Detalle pago: cuota factura/OC.
        Al ejecutar: OP por proveedor+medio; CBU del proveedor en detalle;
        retenciones G/I/S/B calculadas; si hay cuenta egreso del lote y medio Transf → renglón de caja (neto).
        Cheques: completar en cada OP.
    </div>
</div>
@endif
