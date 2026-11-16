@extends("theme.$theme.layout")
@section('titulo')
    Tablero conciliación bancaria
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tablero de avance</h3>
                <div class="card-tools">
                    <a href="{{ route('conciliacion_bancaria') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-university"></i> Conciliación
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('conciliacion_bancaria_tablero') }}" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $empresa_ids ?? [],
                        'mostrar_consolidar' => false,
                        'id_prefix' => 'conc_tablero',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Período</label>
                        <div class="col-lg-2">
                            <select name="mes" class="form-control" required>
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <input type="number" name="anio" class="form-control" min="2000" max="2100" value="{{ $anio }}" required>
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary">Consultar</button>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_conciliacion_bancaria_tablero',
                            'queryparams' => $filtrosQuery ?? [],
                            'variant' => 'compact',
                        ])
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Cuentas</div><strong>{{ $resumen['cuentas'] }}</strong></div></div>
                        <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Cerradas</div><strong>{{ $resumen['cerradas'] }}</strong></div></div>
                        <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Aprobadas</div><strong>{{ $resumen['aprobadas'] }}</strong></div></div>
                        <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Pares mes</div><strong>{{ $resumen['pares'] }}</strong></div></div>
                        <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Grupos multi</div><strong>{{ $resumen['grupos'] }}</strong></div></div>
                        <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">% conciliado (prom.)</div><strong>{{ $resumen['pct_promedio'] ?? '—' }}</strong></div></div>
                    </div>
                    <table class="table table-sm table-bordered" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Cuenta</th>
                                <th>Extracto</th>
                                <th class="text-right">Pares</th>
                                <th class="text-right">Auto/Man</th>
                                <th class="text-right">% conc.</th>
                                <th class="text-right">Días cierre</th>
                                <th class="text-right">Grupos</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr>
                                    <td>{{ $fila['codigo'] }}</td>
                                    <td>{{ $fila['nombre'] }}</td>
                                    <td>
                                        @if ($fila['tiene_interbanking'])
                                            <span class="badge badge-success">IB/EXT</span>
                                        @else
                                            <span class="badge badge-secondary">Sin extracto</span>
                                        @endif
                                        @if (!empty($fila['agenda_cerrada']))
                                            <span class="badge badge-danger" title="Agenda Interbanking cerrada">Agenda</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $fila['pares_mes'] }}</td>
                                    <td class="text-right">{{ $fila['pares_auto'] ?? 0 }}/{{ $fila['pares_manual'] ?? 0 }}</td>
                                    <td class="text-right">{{ $fila['pct_conciliado'] !== null ? number_format($fila['pct_conciliado'], 1, ',', '.').' %' : '—' }}</td>
                                    <td class="text-right">{{ $fila['dias_a_cierre'] !== null ? $fila['dias_a_cierre'] : '—' }}</td>
                                    <td class="text-right">{{ $fila['grupos_mes'] }}</td>
                                    <td>
                                        @php $est = $fila['estado'] ?? 'sin_ejecucion'; @endphp
                                        @if ($est === 'aprobado')
                                            <span class="badge badge-primary">Aprobado</span>
                                        @elseif ($est === 'cerrado')
                                            <span class="badge badge-success">Cerrado</span>
                                        @elseif ($est === 'borrador')
                                            <span class="badge badge-warning">Borrador</span>
                                        @else
                                            <span class="badge badge-light">Sin corrida</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn-accion-tabla tooltipsC" title="Manual"
                                           href="{{ route('conciliacion_bancaria_manual', ['empresa_id' => $fila['empresa_id'], 'cuentacaja_id' => $fila['cuentacaja_id'], 'mes' => $mes, 'anio' => $anio]) }}">
                                            <i class="fa fa-columns"></i>
                                        </a>
                                        <a class="btn-accion-tabla tooltipsC" title="Aging"
                                           href="{{ route('conciliacion_bancaria_aging', ['empresa_id' => $fila['empresa_id'], 'cuentacaja_id' => $fila['cuentacaja_id'], 'mes' => $mes, 'anio' => $anio, 'consultar' => 1]) }}">
                                            <i class="fa fa-hourglass-half"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-muted">Sin cuentas para el filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/reportes/empresas_checkboxes.js')) ?: time() }}"></script>
@endsection
