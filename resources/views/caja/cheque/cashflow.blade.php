@extends("theme.$theme.layout")
@section('titulo')
    Cashflow semanal cheques
@endsection

@section('contenido')
@php
    $puedeEditarCheque = can('editar-cheque', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cashflow semanal — CHT cartera + CHP diferidos</h3>
                <div class="card-tools">
                    <a href="{{ route('cheque') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a cheques
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('cashflow_cheque') }}" class="mb-3">
                    <div class="form-row align-items-end">
                        @if (($empresa_query ?? collect())->count() > 1)
                        <div class="form-group col-md-3 mb-2">
                            <label class="small mb-0">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)($filtros['empresa_id'] ?? 0) === (int)$emp->id)>{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Desde (semana)</label>
                            <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-0">Semanas</label>
                            <select name="semanas" class="form-control form-control-sm">
                                @foreach ([4,6,8,12,16] as $s)
                                    <option value="{{ $s }}" @selected((int)($filtros['semanas'] ?? 8) === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <button type="submit" class="btn btn-info btn-sm">Consultar</button>
                        </div>
                    </div>
                </form>

                <div class="mb-3">
                    <strong>Totales {{ $resumen['desde'] }} → {{ $resumen['hasta'] }}:</strong>
                    CHT {{ number_format($resumen['totales']['cht_monto'], 2, ',', '.') }}
                    · CHP {{ number_format($resumen['totales']['chp_monto'], 2, ',', '.') }}
                    · Total {{ number_format($resumen['totales']['monto'], 2, ',', '.') }}
                    ({{ $resumen['totales']['cantidad'] }} cheques)
                </div>

                <div class="row mb-3">
                    @foreach ($resumen['semanas'] as $sem)
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-muted">{{ $sem['label'] }}</div>
                                <div><strong>{{ number_format($sem['total_monto'], 2, ',', '.') }}</strong></div>
                                <div class="small">
                                    CHT {{ $sem['cht']['cantidad'] }} / {{ number_format($sem['cht']['monto'], 2, ',', '.') }}
                                    · CHP {{ $sem['chp']['cantidad'] }} / {{ number_format($sem['chp']['monto'], 2, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @foreach ($resumen['semanas'] as $sem)
                    @if ($sem['total_cantidad'] === 0)
                        @continue
                    @endif
                    <h5 class="mt-3">{{ $sem['label'] }}</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered table-striped">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Número</th>
                                    <th>Pago</th>
                                    <th class="text-right">Monto</th>
                                    <th>Banco</th>
                                    <th>Contraparte</th>
                                    <th>Empresa</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sem['filas'] as $f)
                                    <tr>
                                        <td>
                                            @if ($puedeEditarCheque)
                                                <a class="text-primary" href="{{ route('editar_cheque', $f['id']) }}" target="_blank" rel="noopener">{{ $f['id'] }}</a>
                                            @else
                                                {{ $f['id'] }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($f['origen'] === 'E')
                                                <span class="badge badge-primary">CHP</span>
                                            @else
                                                <span class="badge badge-info">CHT</span>
                                            @endif
                                            @if ($f['echeq'])
                                                <span class="badge badge-dark">eCheq</span>
                                            @endif
                                        </td>
                                        <td>{{ $f['numerocheque'] }}</td>
                                        <td>{{ $f['fechapago'] }}</td>
                                        <td class="text-right">{{ number_format($f['monto'], 2, ',', '.') }} {{ $f['moneda'] }}</td>
                                        <td>{{ $f['banco'] }}</td>
                                        <td>{{ $f['contraparte'] }}</td>
                                        <td>{{ $f['empresa'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
