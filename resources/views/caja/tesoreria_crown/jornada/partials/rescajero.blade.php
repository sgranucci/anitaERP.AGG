@php
    $pesos = function ($importe) {
        if ($importe === null || $importe === '' || abs((float) $importe) < 0.001) {
            return '';
        }

        return number_format((float) $importe, 2, ',', '.');
    };
    $celdasAudit = [
        'egresos', 'ingresos', 'fichas_casino', 'pago_cashless', 'vta_cashless',
        'pago_voucher', 'venta_voucher', 'online', 'resultado', 'diferencia',
    ];
    $auditCelda = function (float $valor, string $columna, string $scope, ?int $asignacionId = null, ?string $tipo = null) use ($pesos) {
        $texto = $pesos($valor);
        if ($texto === '') {
            return '';
        }
        $attrs = ' type="button" class="btn btn-link btn-sm p-0 text-primary js-tc-audit"';
        $attrs .= ' data-columna="'.e($columna).'" data-scope="'.e($scope).'"';
        if ($asignacionId) {
            $attrs .= ' data-asignacion-id="'.(int) $asignacionId.'"';
        }
        if ($tipo) {
            $attrs .= ' data-tipo="'.e($tipo).'"';
        }
        $attrs .= ' title="Ver origen y movimientos de '.e($columna).'"';

        return '<button'.$attrs.'>'.$texto.'</button>';
    };
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-users"></i> Resumen de cajeros</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-rescajero.c</code>: agrupado por tipo de caja. Ingresos = efectivo rendido (sin fichas ni cashless).
            Tocá un importe para ver cómo se calcula y los documentos que lo arman.
        </p>
        @if ($rescajero === null)
            <div class="alert alert-warning mb-0">No se pudo armar el resumen. {{ $rescajeroError }}</div>
        @else
            @foreach ($rescajero['avisos'] as $aviso)
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
                                <th class="text-right">Egresos</th>
                                <th class="text-right">Ingresos</th>
                                <th class="text-right">Fichas</th>
                                <th class="text-right">Pago cashless</th>
                                <th class="text-right">Vta. cashless</th>
                                <th class="text-right">Pago voucher</th>
                                <th class="text-right">Venta voucher</th>
                                <th class="text-right">Online</th>
                                <th class="text-right">Resultado</th>
                                <th class="text-right">Dif. cajero</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rescajero['grupos'] as $grupo)
                                <tr style="background:#D6EAF8;font-weight:bold;" data-tc-fija="1">
                                    <td colspan="12">{{ $grupo['titulo'] }}</td>
                                </tr>
                                @foreach ($grupo['filas'] as $fila)
                                    @php
                                        $scopeFila = ! empty($fila['propina']) ? 'propina' : 'fila';
                                        $asignacionId = (int) ($fila['asignacion_id'] ?? 0) ?: null;
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($asignacionId && empty($fila['propina']))
                                                <a class="text-primary" target="_blank" rel="noopener"
                                                   href="{{ route('ver_tesoreria_crown_cierre', ['jornada' => $jornada->id, 'cajero' => ($fila['cajero_crown_id'] ?? $asignacionId)]) }}">{{ $fila['caja'] }}</a>
                                            @else
                                                {{ $fila['caja'] }}
                                            @endif
                                        </td>
                                        <td>{{ $fila['cajero'] }}</td>
                                        @foreach ($celdasAudit as $columna)
                                            <td class="text-right">{!! $auditCelda((float) ($fila[$columna] ?? 0), $columna, $scopeFila, $asignacionId) !!}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                    <td colspan="2">Total {{ $grupo['titulo'] }}</td>
                                    @foreach ($celdasAudit as $columna)
                                        <td class="text-right">{!! $auditCelda((float) ($grupo['totales'][$columna] ?? 0), $columna, 'grupo', null, (string) $grupo['tipo']) !!}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr data-tc-fija="1"><td colspan="12" class="text-center text-muted">Sin filas.</td></tr>
                            @endforelse
                            @if (($rescajero['grupos'] ?? []) !== [])
                                <tr style="font-weight:bold;background:#D5F5E3;" data-tc-fija="1">
                                    <td colspan="2">Total general</td>
                                    @foreach ($celdasAudit as $columna)
                                        <td class="text-right">{!! $auditCelda((float) ($rescajero['totales'][$columna] ?? 0), $columna, 'total') !!}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@include('caja.tesoreria_crown.jornada.partials.modal_auditoria_importe', [
    'urlAuditoria' => route('auditoria_rescajero_tesoreria_crown_jornada', $jornada->id),
])
