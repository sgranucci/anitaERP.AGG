@php
    $totalesCot = $totalesCot ?? [
        'consulta' => ['cantidad' => 0, 'kilos' => 0.0, 'importe' => 0.0],
        'pendientes' => ['cantidad' => 0, 'kilos' => 0.0, 'importe' => 0.0],
        'bloqueados' => ['cantidad' => 0, 'kilos' => 0.0, 'importe' => 0.0],
        'emitidos' => ['cantidad' => 0, 'kilos' => 0.0, 'importe' => 0.0],
        'por_reparto' => [],
    ];
    $totSel = $totalesCot['pendientes'];
    $totConsulta = $totalesCot['consulta'];
@endphp
<div class="card card-outline card-info mt-3" id="cot-control-totales">
    <div class="card-header py-2">
        <strong>Control COT a enviar</strong>
        <span class="small text-muted ml-2">
            Importe sin IVA (el que declara ARBA) y kilos de cada remito. Los parciales siguen la selecci&oacute;n.
        </span>
    </div>
    <div class="card-body py-3">
        <div class="row">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="border rounded p-2 h-100" style="background:#eaf6fb;">
                    <div class="small text-muted mb-1">A enviar (seleccionados)</div>
                    <div class="mb-1">
                        <strong id="cot-ctrl-sel-cant">{{ (int) $totSel['cantidad'] }}</strong>
                        remito(s)
                    </div>
                    <div class="h5 mb-1" id="cot-ctrl-sel-kilos">
                        {{ number_format((float) $totSel['kilos'], 2, ',', '.') }}
                        <span class="small font-weight-normal">kg</span>
                    </div>
                    <div class="h5 mb-0" id="cot-ctrl-sel-importe">
                        $ {{ number_format((float) $totSel['importe'], 2, ',', '.') }}
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="border rounded p-2 h-100">
                    <div class="small text-muted mb-1">Total de la consulta</div>
                    <div class="mb-1">
                        <strong id="cot-ctrl-consulta-cant">{{ (int) $totConsulta['cantidad'] }}</strong>
                        remito(s)
                    </div>
                    <div class="mb-1" id="cot-ctrl-consulta-kilos">
                        {{ number_format((float) $totConsulta['kilos'], 2, ',', '.') }}
                        kg
                    </div>
                    <div class="mb-0" id="cot-ctrl-consulta-importe">
                        $ {{ number_format((float) $totConsulta['importe'], 2, ',', '.') }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted mb-1">Estado de la consulta</div>
                <ul class="small mb-0 pl-3">
                    <li>
                        Pendientes listos:
                        <strong>{{ (int) $totSel['cantidad'] }}</strong>
                    </li>
                    <li>
                        Sin importe:
                        <strong>{{ (int) ($totalesCot['bloqueados']['cantidad'] ?? 0) }}</strong>
                    </li>
                    <li>
                        Ya emitidos:
                        <strong>{{ (int) ($totalesCot['emitidos']['cantidad'] ?? 0) }}</strong>
                    </li>
                </ul>
                <p class="small text-muted mb-0 mt-2">
                    Desmarcar un remito baja el parcial. El total de consulta no cambia.
                </p>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-sm table-bordered mb-0" id="tabla-cot-totales-reparto">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Parcial por reparto</th>
                        <th class="text-center">Remitos a enviar</th>
                        <th class="text-right">Kilos</th>
                        <th class="text-right">Importe ARBA</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($totalesCot['por_reparto'] as $parcial)
                        @php
                            $etiquetaReparto = trim(($parcial['codigo'] ?? '').' '.($parcial['nombre'] ?? ''));
                        @endphp
                        <tr>
                            <td>
                                @if ($etiquetaReparto !== '')
                                    {{ $etiquetaReparto }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center">{{ (int) $parcial['cantidad'] }}</td>
                            <td class="text-right">{{ number_format((float) $parcial['kilos'], 2, ',', '.') }}</td>
                            <td class="text-right">$ {{ number_format((float) $parcial['importe'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted">No hay remitos listos para enviar.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold">
                        <td>Total a enviar</td>
                        <td class="text-center" id="cot-reparto-total-cant">{{ (int) $totSel['cantidad'] }}</td>
                        <td class="text-right" id="cot-reparto-total-kilos">{{ number_format((float) $totSel['kilos'], 2, ',', '.') }}</td>
                        <td class="text-right" id="cot-reparto-total-importe">$ {{ number_format((float) $totSel['importe'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
