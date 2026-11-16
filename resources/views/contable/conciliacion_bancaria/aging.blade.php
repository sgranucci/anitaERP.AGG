@extends("theme.$theme.layout")
@section('titulo')
    Aging conciliación bancaria
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionBancariaCuentacajaPorCodigoUrl = @json(route('conciliacion_bancaria_api_cuentacaja_por_codigo', ['codigo' => '__CODIGO__']));
</script>
<script src="{{ asset('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/conciliacion_bancaria/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($errores))
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errores as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Aging de pendientes</h3>
            </div>
            <form method="get" action="{{ route('conciliacion_bancaria_aging') }}" class="mb-0" data-enter-campos="1">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id ?: null,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2 requerido">Cuenta de caja</label>
                        <input type="hidden" name="cuentacaja_id" id="cuentacaja_id" value="{{ $cuentacaja_id ?: '' }}">
                        <div class="col-lg-2">
                            <input type="text" class="form-control" id="codigo_cuentacaja" autocomplete="off"
                                value="{{ $cuentacaja->codigo ?? '' }}" placeholder="Código"
                                title="C&oacute;digo. F1 = consulta">
                        </div>
                        <div class="col-lg-1">
                            <button type="button" class="btn-accion-tabla consultacuentacaja tooltipsC" title="Consultar cuentas de caja (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                        <div class="col-lg-5">
                            <input type="text" class="form-control" id="nombre_cuentacaja" readonly
                                value="{{ $cuentacaja->nombre ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Período</label>
                        <div class="col-lg-2">
                            <select name="mes" class="form-control">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <input type="number" name="anio" class="form-control" value="{{ $anio }}">
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary">Consultar</button>
                        </div>
                    </div>

                    @if ($resultado)
                        @php $r = $resultado['resumen']; @endphp
                        <div class="d-flex flex-wrap align-items-center mb-2">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_conciliacion_bancaria_aging',
                                'queryparams' => $filtrosQuery ?? [],
                                'variant' => 'compact',
                            ])
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">0–7 días</div><strong>{{ $r['0-7'] }}</strong></div></div>
                            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">8–30</div><strong>{{ $r['8-30'] }}</strong></div></div>
                            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">31–60</div><strong>{{ $r['31-60'] }}</strong></div></div>
                            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">61+</div><strong>{{ $r['61+'] }}</strong></div></div>
                            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Total</div><strong>{{ $r['total'] }}</strong></div></div>
                            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">|Importe|</div><strong>{{ number_format($r['importe_abs'], 2, ',', '.') }}</strong></div></div>
                        </div>
                        @if (!empty($forecast))
                            <div class="alert alert-info py-2">
                                <strong>Forecast uncleared (banco):</strong>
                                0–7 {{ number_format($forecast['ventanas']['0-7']['importe'], 2, ',', '.') }}
                                · 8–30 {{ number_format($forecast['ventanas']['8-30']['importe'], 2, ',', '.') }}
                                · 31–60 {{ number_format($forecast['ventanas']['31-60']['importe'], 2, ',', '.') }}
                                · 61+ {{ number_format($forecast['ventanas']['61+']['importe'], 2, ',', '.') }}
                                · total {{ number_format($forecast['total_importe'], 2, ',', '.') }}
                                ({{ $forecast['total_n'] }} mov.)
                                <a class="ml-2" href="{{ route('conciliacion_bancaria_excepciones', ['empresa_id' => $empresa_id, 'cuentacaja_id' => $cuentacaja_id, 'mes' => $mes, 'anio' => $anio, 'consultar' => 1]) }}">Worklist</a>
                            </div>
                        @endif
                        <table class="table table-sm table-striped" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Lado</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Días</th>
                                    <th>Bucket</th>
                                    <th>Detalle</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resultado['filas'] as $fila)
                                    <tr>
                                        <td>{{ $fila['lado'] }}</td>
                                        <td>{{ $fila['fecha'] }}</td>
                                        <td class="text-right">{{ $fila['dias'] }}</td>
                                        <td>{{ $fila['bucket'] }}</td>
                                        <td>{{ $fila['detalle'] }}</td>
                                        <td class="text-right">{{ number_format($fila['importe'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@endsection
