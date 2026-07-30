@extends("theme.$theme.layout")
@section('titulo')
    Conciliación bancaria
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionBancariaEngancheUrl = @json(route('conciliacion_bancaria_api_enganche'));
    window.conciliacionBancariaCuentacajaPorCodigoUrl = @json(route('conciliacion_bancaria_api_cuentacaja_por_codigo', ['codigo' => '__CODIGO__']));
</script>
<script src="{{ asset('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conciliación bancaria</h3>
                <div class="card-tools">
                    @if (!empty($filtrosQuery) && ($resultado ?? null))
                        @php
                            $paramsExport = array_filter($filtrosQuery, fn ($v) => $v !== null && $v !== '' && $v !== 1);
                            $suffixExport = count($paramsExport) ? '?'.http_build_query($paramsExport) : '';
                        @endphp
                        <a href="{{ route('exportar_conciliacion_bancaria', ['formato' => 'EXCEL']).$suffixExport }}"
                           class="btn btn-app bg-success" title="Exportar conciliación Excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('conciliacion_bancaria') }}" id="form-conciliacion-bancaria" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cruza el mayor analítico (subdiario + ctamov vía bridge Anita) contra movimientos Interbanking
                        persistidos. La solapa <strong>Pendientes</strong> lista cheques propios (cpromae), como Contaduría;
                        la carátula usa los de vencimiento en el mes. Los emparejamientos quedan guardados para períodos posteriores.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Cuenta de caja</label>
                        <input type="hidden" name="cuentacaja_id" id="cuentacaja_id" value="{{ old('cuentacaja_id', $cuentacaja_id) }}">
                        <div class="col-lg-2">
                            <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                value="{{ $cuentacaja->codigo ?? '' }}" placeholder="Código">
                        </div>
                        <div class="col-lg-1">
                            <button type="button" class="btn-accion-tabla consultacuentacaja tooltipsC" title="Consultar cuentas de caja">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                        <div class="col-lg-5">
                            <input type="text" class="form-control" id="nombre_cuentacaja" readonly
                                value="{{ $cuentacaja->nombre ?? '' }}">
                        </div>
                    </div>

                    <div id="enganche-cuenta-wrapper">
                        @if (! empty($enganche) && ($enganche['ok'] ?? false))
                            @include('contable.conciliacion_bancaria.partials.enganche_cuenta', ['enganche' => $enganche])
                        @endif
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Período</label>
                        <div class="col-lg-2">
                            <select name="mes" id="mes" class="form-control" required>
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <input type="number" name="anio" id="anio" class="form-control" min="2000" max="2100"
                                value="{{ $anio }}" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-consultar">
                        <i class="fa fa-balance-scale"></i> Conciliar
                    </button>
                    <a href="{{ route('conciliacion_bancaria') }}" class="btn btn-outline-secondary">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        @if ($resultado)
            @php $c = $resultado['caratula'] ?? []; @endphp
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title">Resumen — {{ $c['cuentacaja_nombre'] ?? '' }} ({{ sprintf('%02d/%d', $mes, $anio) }})</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-bordered">
                                <tbody>
                                    <tr><th>Saldo banco (extracto)</th><td class="text-right">{{ number_format($c['saldo_banco_extracto'] ?? 0, 2, ',', '.') }}</td></tr>
                                    <tr><th>Cheques no acreditados</th><td class="text-right">{{ number_format($c['cheques_no_acreditados'] ?? 0, 2, ',', '.') }}</td></tr>
                                    <tr><th>Pendientes banco</th><td class="text-right">{{ number_format($c['movimientos_pendientes_banco'] ?? 0, 2, ',', '.') }}</td></tr>
                                    <tr><th>Saldo banco ajustado</th><td class="text-right">{{ number_format($c['saldo_banco_ajustado'] ?? 0, 2, ',', '.') }}</td></tr>
                                    <tr><th>Saldo contable</th><td class="text-right">{{ number_format($c['saldo_contable'] ?? 0, 2, ',', '.') }}</td></tr>
                                    <tr class="{{ abs($c['diferencia'] ?? 0) < 1 ? 'table-success' : 'table-warning' }}">
                                        <th>Diferencia</th>
                                        <td class="text-right"><strong>{{ number_format($c['diferencia'] ?? 0, 2, ',', '.') }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Pares conciliados en esta corrida:</strong> {{ count($resultado['pares_nuevos'] ?? []) }}</p>
                            <p><strong>Pendientes cheques (cpromae):</strong> {{ count($resultado['pendientes_cheques_cpromae'] ?? []) }}
                                <span class="text-muted small">(fuente: {{ $resultado['pendientes_cheques_fuente'] ?? '—' }})</span>
                            </p>
                            <p><strong>En carátula (F.Dev del mes):</strong> {{ count($resultado['pendientes_cheques_caratula'] ?? []) }}
                                · {{ number_format($resultado['suma_pendientes_cheques_caratula'] ?? 0, 2, ',', '.') }}
                            </p>
                            <p><strong>Mayor sin match (otros):</strong> {{ count($resultado['pendientes_contables_otros'] ?? []) }}</p>
                            <p><strong>Pendientes banco:</strong> {{ count($resultado['pendientes_banco'] ?? []) }}</p>
                        </div>
                    </div>

                    @include('contable.conciliacion_bancaria.partials.panel_ia_anomalias', ['resultado' => $resultado])

                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-pendientes">Pendientes (cheques)</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-mayor">Mayor sin match</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-saldo-banco">Saldo banco (codificado)</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-banco-pend">Pendientes banco</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-gastos">Gastos bancarios</a></li>
                    </ul>
                    <div class="tab-content border border-top-0 p-2">
                        <div class="tab-pane active" id="tab-pendientes">
                            <p class="text-muted small mb-2">
                                Solapa Contaduría: cheques propios (cpromae). La carátula toma los de F.Dev en el mes
                                (marcados). Sin semilla Excel usa el último snapshot persistido de la cuenta.
                            </p>
                            <table class="table table-sm table-striped" id="tabla-paginada">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr>
                                        <th>Tip</th><th>Número</th><th>F.Mov.</th><th>F.Dev.</th><th>F.Entr.</th>
                                        <th>Detalle</th><th class="text-right">Créditos</th><th>Carátula</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($resultado['pendientes_cheques_cpromae'] ?? [] as $ch)
                                        <tr class="{{ ! empty($ch['incluye_caratula']) ? 'table-warning' : '' }}">
                                            <td>{{ $ch['tip'] ?? 'CHP' }}</td>
                                            <td>{{ $ch['numero_cheque'] ?? '' }}</td>
                                            <td>{{ ! empty($ch['fecha_emision']) ? \Carbon\Carbon::parse($ch['fecha_emision'])->format('d/m/Y') : '' }}</td>
                                            <td>{{ ! empty($ch['fecha_cheque']) ? \Carbon\Carbon::parse($ch['fecha_cheque'])->format('d/m/Y') : '' }}</td>
                                            <td>{{ ! empty($ch['fecha_entrega']) ? \Carbon\Carbon::parse($ch['fecha_entrega'])->format('d/m/Y') : '' }}</td>
                                            <td>{{ $ch['entregado_a'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format(abs((float) ($ch['importe'] ?? 0)), 2, ',', '.') }}</td>
                                            <td>{{ ! empty($ch['incluye_caratula']) ? 'Sí' : '' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-muted">Sin pendientes de cheques. Ejecutá con semilla Excel Contaduría o un snapshot previo.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="tab-pane" id="tab-mayor">
                            <p class="text-muted small mb-2">Movimientos del mayor sin emparejar con Interbanking (no definen la carátula de cheques).</p>
                            <table class="table table-sm table-striped">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr>
                                        <th>F.Mov.</th><th>Tip</th><th>Asiento</th><th>Detalle</th><th>Débitos</th><th>Créditos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (array_slice($resultado['pendientes_contables'] ?? [], 0, 200) as $mov)
                                        @php $imp = ($mov['debe'] ?? 0) - ($mov['haber'] ?? 0); @endphp
                                        <tr>
                                            <td>{{ $mov['fecha_fmt'] ?? '' }}</td>
                                            <td>{{ $mov['tipo_comp'] ?? '' }}</td>
                                            <td>{{ $mov['nro_asiento_fmt'] ?? '' }}</td>
                                            <td>{{ $mov['descripcion'] ?? '' }}</td>
                                            <td class="text-right">{{ $imp >= 0 ? number_format($imp, 2, ',', '.') : '' }}</td>
                                            <td class="text-right">{{ $imp < 0 ? number_format(abs($imp), 2, ',', '.') : '' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-muted">Sin pendientes de mayor.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            @if (count($resultado['pendientes_contables'] ?? []) > 200)
                                <p class="text-muted small">Mostrando 200 de {{ count($resultado['pendientes_contables']) }} — exporte Excel para el listado completo.</p>
                            @endif
                        </div>
                        <div class="tab-pane" id="tab-saldo-banco">
                            <p class="text-muted small">
                                Movimientos Interbanking codificados (solapa Saldo): P.C.C. + concepto → código según tabla Codificacion bcos.
                                Saldo inicial período: {{ number_format($resultado['saldo_inicial_periodo'] ?? 0, 2, ',', '.') }}
                            </p>
                            <table class="table table-sm table-striped">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr>
                                        <th>Fecha</th><th>Referencia</th><th>Código</th><th>Concepto</th><th>Importe</th><th>Saldo</th><th>P.C.C.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_slice($resultado['movimientos_saldo'] ?? [], 0, 100) as $fila)
                                        <tr>
                                            <td>{{ isset($fila['fecha']) ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
                                            <td>{{ $fila['referencia'] ?? '' }}</td>
                                            <td>{{ $fila['codigo'] ?? '' }}</td>
                                            <td>{{ $fila['concepto'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format($fila['importe'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="text-right">{{ number_format($fila['saldo'] ?? 0, 2, ',', '.') }}</td>
                                            <td class="small text-muted">{{ $fila['pcc'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (count($resultado['movimientos_saldo'] ?? []) > 100)
                                <p class="text-muted small">Mostrando 100 de {{ count($resultado['movimientos_saldo']) }} — exporte Excel para el listado completo.</p>
                            @endif
                        </div>
                        <div class="tab-pane" id="tab-banco-pend">
                            <table class="table table-sm table-striped">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr><th>Fecha</th><th>Concepto</th><th>Comprobante</th><th>Importe</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($resultado['pendientes_banco'] ?? [] as $mov)
                                        @php
                                            $tipo = strtoupper($mov['debit_credit_type'] ?? '');
                                            $monto = (float) ($mov['amount'] ?? 0);
                                            $imp = $tipo === 'D' ? -abs($monto) : abs($monto);
                                        @endphp
                                        <tr>
                                            <td>{{ isset($mov['process_date']) ? \Carbon\Carbon::parse($mov['process_date'])->format('d/m/Y') : '' }}</td>
                                            <td>{{ $mov['code_description_ib'] ?? '' }}</td>
                                            <td>{{ $mov['voucher_number'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format($imp, 2, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">Sin pendientes banco.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="tab-pane" id="tab-gastos">
                            <table class="table table-sm table-striped">
                                <thead style="background:#85C1E9;color:#17202A">
                                    <tr><th>Código</th><th>Concepto</th><th>Importe</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($resultado['gastos_resumen'] ?? [] as $g)
                                        <tr>
                                            <td>{{ $g['codigo'] ?? '' }}</td>
                                            <td>{{ $g['descripcion'] ?? '' }}</td>
                                            <td class="text-right">{{ number_format($g['importe'] ?? 0, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')
@endsection
