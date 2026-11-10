<div class="card card-outline card-info mt-3" id="coeficientes">
    <div class="card-header">
        <h3 class="card-title">Cargar valores mensuales</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Un valor por mes. Un casillero vacío no borra el mes ya cargado: para sacarlo use Quitar.
            @if (! empty($es_serie_ajuste))
                Esta serie es el índice de ajuste por inflación: al guardar, el valor también queda en Ajuste por inflación.
            @endif
        </p>
        @if ($ultimo_coeficiente)
            <p class="mb-2">
                Último valor cargado:
                <strong>{{ str_pad((string) $ultimo_coeficiente->mes, 2, '0', STR_PAD_LEFT) }}/{{ $ultimo_coeficiente->anio }}</strong>
                — {{ number_format((float) $ultimo_coeficiente->coeficiente, 6, ',', '.') }}
            </p>
        @else
            <p class="mb-2 text-muted">Todavía no hay valores cargados en este índice.</p>
        @endif

        <form method="get" action="{{ route('editar_bien_uso_indice', $data->id) }}" class="form-inline mb-3">
            @if (! empty($soloConsulta))
                <input type="hidden" name="origen" value="modal_consulta">
                <input type="hidden" name="vista" value="consulta">
            @endif
            <label for="anio_coeficiente" class="mr-2">Año</label>
            <input type="number" name="anio" id="anio_coeficiente" class="form-control form-control-sm mr-2"
                   min="1900" max="2100" required value="{{ $anio }}" style="width: 6rem;">
            <button type="submit" class="btn btn-outline-info btn-sm">Mostrar</button>
        </form>

        @if (! empty($puede_cargar))
            <form method="post" action="{{ route('guardar_coeficientes_bien_uso_indice', $data->id) }}"
                  id="form-coeficientes" data-enter-campos="1">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Mes</th>
                                <th class="text-right">Valor</th>
                                <th class="text-center">Quitar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($meses as $numeroMes => $nombreMes)
                                @php
                                    $fila = $coeficientes[$numeroMes] ?? null;
                                    $valorMes = old('coeficiente.'.$numeroMes, $fila ? $fila->coeficiente : '');
                                @endphp
                                <tr>
                                    <td>{{ $nombreMes }}</td>
                                    <td>
                                        <input type="number" name="coeficiente[{{ $numeroMes }}]"
                                               class="form-control form-control-sm text-right"
                                               step="0.000001" min="0.000001"
                                               value="{{ $valorMes }}">
                                    </td>
                                    <td class="text-center">
                                        @if ($fila)
                                            <button type="submit" class="btn-accion-tabla" title="Quitar {{ $nombreMes }}"
                                                    form="form-quitar-coef-{{ $numeroMes }}">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa fa-save"></i> Guardar valores de {{ $anio }}
                </button>
            </form>
            @foreach ($meses as $numeroMes => $nombreMes)
                @if (isset($coeficientes[$numeroMes]))
                    <form id="form-quitar-coef-{{ $numeroMes }}" method="post"
                          action="{{ route('quitar_coeficiente_bien_uso_indice', ['id' => $data->id, 'mes' => $numeroMes]) }}"
                          onsubmit="return confirm('¿Quitar el valor de {{ $nombreMes }} {{ $anio }}?');">
                        @csrf
                        @method('delete')
                        <input type="hidden" name="anio" value="{{ $anio }}">
                    </form>
                @endif
            @endforeach
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Mes</th>
                            <th class="text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($meses as $numeroMes => $nombreMes)
                            @php $fila = $coeficientes[$numeroMes] ?? null; @endphp
                            <tr>
                                <td>{{ $nombreMes }}</td>
                                <td class="text-right">
                                    @if ($fila)
                                        {{ number_format((float) $fila->coeficiente, 6, ',', '.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
