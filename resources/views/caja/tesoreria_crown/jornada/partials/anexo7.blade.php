@php
    $pesos = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-exclamation-triangle"></i> Anexo VII — Fallos y excedentes</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-anexo7.c</code>: fallos, anticipos y excedentes por cajero.
           
        </p>
        @if ($anexo7 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo VII. {{ $anexo7Error }}</div>
        @else
            @foreach ($anexo7['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <div class="tc-orden-wrap">
                @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 tc-tabla-ordenable" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Caja</th>
                                <th>Cajero</th>
                                <th>Legajo</th>
                                <th>Concepto</th>
                                <th class="text-right">Fallos</th>
                                <th class="text-right">Anticipos</th>
                                <th class="text-right">Excedentes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($anexo7['filas'] as $fila)
                                <tr>
                                    <td>{{ $fila['caja'] }}</td>
                                    <td>{{ $fila['cajero'] }}</td>
                                    <td>{{ $fila['legajo'] ?: '' }}</td>
                                    <td>{{ $fila['concepto'] }}</td>
                                    <td class="text-right">{{ $pesos($fila['fallos']) }}</td>
                                    <td class="text-right">{{ $pesos($fila['anticipos']) }}</td>
                                    <td class="text-right">{{ $pesos($fila['excedentes']) }}</td>
                                </tr>
                            @empty
                                <tr data-tc-fija="1"><td colspan="7" class="text-center text-muted">Sin filas.</td></tr>
                            @endforelse
                            @if ($anexo7['filas'] !== [])
                                <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                    <td colspan="4">Total</td>
                                    <td class="text-right">{{ $pesos($anexo7['totales']['fallos']) }}</td>
                                    <td class="text-right">{{ $pesos($anexo7['totales']['anticipos']) }}</td>
                                    <td class="text-right">{{ $pesos($anexo7['totales']['excedentes']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
