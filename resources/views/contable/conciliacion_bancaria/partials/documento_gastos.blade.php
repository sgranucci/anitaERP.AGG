@php
    $doc = $documento ?? [];
    $lineas = $doc['lineas'] ?? [];
    $puedeGenerar = can('crear-ingresos-egresos-caja', false);
    $ya = $doc['ya_generado'] ?? null;
@endphp
<div class="card card-outline card-primary mb-3">
    <div class="card-header">
        <h3 class="card-title">Egreso e ICO de gastos bancarios</h3>
    </div>
    <div class="card-body">
        @if ($lineas === [])
            <p class="text-muted mb-0">Este mes no tiene gastos bancarios codificados para documentar.</p>
        @else
            <p class="mb-2">
                Un egreso <strong>{{ $doc['tipo_caja'] ?? 'EGR' }}</strong> del
                <strong>{{ number_format((float) ($doc['total'] ?? 0), 2, ',', '.') }}</strong>
                al {{ isset($doc['fecha']) ? \Carbon\Carbon::parse($doc['fecha'])->format('d/m/Y') : '' }},
                con un comprobante <strong>{{ $doc['tipo_compra'] ?? 'ICO' }}</strong>
                por los conceptos impositivos. Las comisiones y el impuesto al débito van como gasto del mismo egreso.
            </p>
            <table class="table table-sm table-bordered mb-3">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Código</th>
                        <th>Concepto</th>
                        <th>Cuenta</th>
                        <th class="text-right">Importe</th>
                        <th>Va a</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as $linea)
                        <tr>
                            <td>{{ $linea['codigo'] ?? '' }}</td>
                            <td>{{ $linea['descripcion'] ?? '' }}</td>
                            <td>{{ trim(($linea['cuenta_codigo'] ?? '').' '.($linea['cuenta_nombre'] ?? '')) }}</td>
                            <td class="text-right">{{ number_format((float) ($linea['importe'] ?? 0), 2, ',', '.') }}</td>
                            <td>{{ ! empty($linea['en_ico']) ? 'ICO' : 'Egreso' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (! empty($doc['faltantes']))
                <div class="alert alert-warning">
                    @foreach ($doc['faltantes'] as $falta)
                        <div>{{ $falta }}</div>
                    @endforeach
                </div>
            @endif
            @if (! empty($ya['id']))
                <a class="btn btn-outline-primary" href="{{ route('editar_ingresoegreso', ['id' => $ya['id'], 'origen' => 'ingresoegreso']) }}" target="_blank" rel="noopener">
                    Abrir egreso {{ $ya['numero'] ?? '' }}
                </a>
            @elseif ($puedeGenerar && ! empty($doc['listo']))
                <button type="button" class="btn btn-primary" id="btn-generar-gastos"
                    data-url="{{ route('conciliacion_bancaria_api_generar_gastos') }}"
                    data-empresa="{{ (int) $empresa_id }}"
                    data-cuenta="{{ (int) $cuentacaja_id }}"
                    data-mes="{{ (int) $mes }}"
                    data-anio="{{ (int) $anio }}"
                    data-total="{{ number_format((float) ($doc['total'] ?? 0), 2, ',', '.') }}">
                    <i class="fa fa-file-invoice"></i> Generar egreso e ICO
                </button>
            @endif
        @endif
    </div>
</div>
