@php
    $pesos = fn ($importe) => number_format((float) $importe, 2, ',', '.');
    $enteros = fn ($cantidad) => number_format((int) $cantidad, 0, ',', '.');
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-power-off"></i> Stock de fichas</h3>
    </div>
    <div class="card-body pb-2">
        @php
            $poderIndexUrl = $poderIndexUrl ?? route('ver_tesoreria_crown_jornada', $jornada->id);
            $poderIndexQuery = $poderIndexQuery ?? ['panel' => 'poder'];
            $urlRecalcularPoder = $poderIndexUrl.'?'.http_build_query(array_merge($poderIndexQuery, ['recalcular' => 1]));
        @endphp
        <p class="text-muted small mb-2">La conversión final es la inicial más lo que movieron hoy las mesas y los cajeros. <strong>Reserva inicial</strong> = ingresos a reserva de esta jornada. <strong>Pagos por caja</strong> = rendición de pago del cajero (tipo P). El poder público del día, el que entra al anexo, es la diferencia de conversión por el valor de cada ficha. Un día negativo es ficha que volvió de la sala. <a href="{{ $urlRecalcularPoder }}">Recalcular</a>
            · <a href="{{ route('conversion_tesoreria_crown_valor', ['fecha' => optional($jornada->fecha)->format('Y-m-d')]) }}">Conversión del día</a>
            · catálogo en <a href="{{ route('tesoreria_crown_valor') }}">Valores (FVAL/FCOL)</a></p>
        @if ($poderPublico === null)
            <div class="alert alert-warning mb-0">No se pudo calcular el poder público. {{ $poderError }}</div>
        @else
            @foreach ($poderPublico['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-2">
                        <div class="text-muted small">Día · anexo I</div>
                        <strong>{{ $pesos($poderPublico['diario']) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-2">
                        <div class="text-muted small">Mes</div>
                        <strong>{{ $pesos($poderPublico['mes']) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-2">
                        <div class="text-muted small">Acumulado</div>
                        <strong>{{ $pesos($poderPublico['acumulado']) }}</strong>
                    </div>
                </div>
            </div>
            @if (isset($poderPublico['slot_dia']))
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="text-muted small">Slot día · anexo I</div>
                            <strong>{{ $pesos($poderPublico['slot_dia']) }}</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="text-muted small">Slot mes</div>
                            <strong>{{ $pesos($poderPublico['slot_mes'] ?? 0) }}</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="text-muted small">Slot acumulado</div>
                            <strong>{{ $pesos($poderPublico['slot_acumulado'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
                @if (! empty($poderPublico['origen_erp']))
                    <p class="small text-muted">Serie persistida en el ERP (origen {{ $poderPublico['origen_erp'] }}).</p>
                @endif
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-3" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Serie</th>
                            <th>Descripción</th>
                            <th class="text-right">Valor</th>
                            <th class="text-right">Reserva</th>
                            <th class="text-right" title="Ingresos a reserva de esta jornada (asignación + tipo I)">Reserva inicial</th>
                            <th class="text-right">Otros ing.</th>
                            <th class="text-right">Conv. inicial</th>
                            <th class="text-right">Conv. final</th>
                            <th class="text-right">Habilitación</th>
                            <th class="text-right" title="Rendición de pago de cajero (tipo P)">Pagos por caja</th>
                            <th class="text-right">P.P. diario</th>
                            <th class="text-right">Poder público</th>
                            <th class="text-right">Mesas</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($poderPublico['filas'] as $fila)
                            <tr>
                                <td>{{ $fila['serie'] }}</td>
                                <td>{{ $fila['descripcion'] }}</td>
                                <td class="text-right">{{ $pesos($fila['valor']) }}</td>
                                <td class="text-right">{{ $enteros($fila['reserva']) }}</td>
                                <td class="text-right">{{ $enteros($fila['reserva_inicial'] ?? 0) }}</td>
                                <td class="text-right">{{ $enteros($fila['otros']) }}</td>
                                <td class="text-right">{{ $enteros($fila['inicial']) }}</td>
                                <td class="text-right">{{ $enteros($fila['final']) }}</td>
                                <td class="text-right">{{ $enteros($fila['habilitacion']) }}</td>
                                <td class="text-right">{{ $enteros($fila['pagos_caja'] ?? 0) }}</td>
                                <td class="text-right">{{ $enteros($fila['pp_diario']) }}</td>
                                <td class="text-right">{{ $enteros($fila['poder']) }}</td>
                                <td class="text-right">{{ $enteros($fila['mesas']) }}</td>
                                <td class="text-right">{{ $enteros($fila['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mb-1">Total existente en conversión valorizado: <strong>{{ $pesos($poderPublico['conversion_valorizada']) }}</strong></p>
            <p class="mb-1">Total en poder del público valorizado: <strong>{{ $pesos($poderPublico['poder_valorizado']) }}</strong></p>
            <p class="mb-0">Total en poder del público diario: <strong>{{ $pesos($poderPublico['diario_valorizado']) }}</strong></p>
        @endif
    </div>
</div>
