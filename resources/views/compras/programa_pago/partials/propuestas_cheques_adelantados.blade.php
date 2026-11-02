@if (count($propuestas) > 0)
    <p class="small mb-2">
        El mes no cubre el programado dentro de ±{{ (int) round(\App\Support\Caja\ProgramaPagoAsignarChequesSupport::TOLERANCIA * 100) }}%. La propuesta junta cheques de ese mes y, si cubren mejor, de cualquier mes posterior.
        No se graba hasta que la aceptes. Aceptar graba el paquete completo de la fila.
    </p>
    <form id="{{ $formId }}" method="POST" action="{{ route('aceptar_cheques_adelantados_programa_pago', $programaId) }}">
        @csrf
        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:36px;"></th>
                        <th>Proveedor</th>
                        <th>Mes</th>
                        <th class="text-right">Programado</th>
                        <th class="text-right">Propuesto</th>
                        <th>Cheques</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($propuestas as $i => $prop)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="propuestas[]" value="{{ $i }}" title="Aceptar esta propuesta">
                            </td>
                            <td>{{ $prop['proveedor'] ?? '' }}</td>
                            <td>{{ $prop['clave'] ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($prop['monto_programado'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right text-nowrap">
                                {{ number_format((float) ($prop['suma'] ?? 0), 2, ',', '.') }}
                                @if (! empty($prop['dentro_tolerancia']))
                                    <span class="badge badge-success">±{{ (int) round(\App\Support\Caja\ProgramaPagoAsignarChequesSupport::TOLERANCIA * 100) }}%</span>
                                @else
                                    <span class="badge badge-warning">fuera de ±{{ (int) round(\App\Support\Caja\ProgramaPagoAsignarChequesSupport::TOLERANCIA * 100) }}%</span>
                                @endif
                            </td>
                            <td>
                                @foreach ($prop['cheques'] ?? [] as $ch)
                                    <div>
                                        @if (! empty($ch['adelantado']))
                                            <span class="badge badge-warning">Adelanto</span>
                                        @else
                                            <span class="badge badge-secondary">Mes</span>
                                        @endif
                                        int {{ $ch['nro_interno_anita'] ?? '—' }}
                                        · nro {{ $ch['numerocheque'] ?? '' }}
                                        · {{ ! empty($ch['fechapago']) ? \Carbon\Carbon::parse($ch['fechapago'])->format('d/m/Y') : '' }}
                                        · {{ number_format((float) ($ch['monto'] ?? 0), 2, ',', '.') }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-sm btn-success mr-1">Aceptar marcadas</button>
        <button type="button" class="btn btn-sm btn-outline-secondary pp-marcar-propuestas" data-form="{{ $formId }}">Marcar todas</button>
    </form>
    <form method="POST" action="{{ route('descartar_cheques_adelantados_programa_pago', $programaId) }}" class="mt-2">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-danger"
                onclick="return confirm('¿Descartar las propuestas? No se asigna ningún cheque de meses posteriores.');">
            Descartar propuestas
        </button>
    </form>
@endif
