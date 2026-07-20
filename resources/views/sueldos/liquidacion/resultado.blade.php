@extends("theme.$theme.layout")
@section('titulo')
    Resultado corrida N&deg; {{ $liq->numero }}
@endsection

@section('contenido')
@php
    $estadoBadge = [
        'borrador' => 'secondary', 'calculada' => 'info', 'revisada' => 'primary',
        'cerrada' => 'success', 'contabilizada' => 'dark', 'pagada' => 'success', 'anulada' => 'danger',
    ];
    $columnaBadge = ['haber' => 'success', 'descuento' => 'danger', 'neto' => 'primary', 'informativo' => 'secondary'];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if(session('error'))<div class="alert alert-danger">{!! session('error') !!}</div>@endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Corrida N&deg; {{ $liq->numero }} &mdash; {{ $liq->descripcion }}
                    <span class="badge badge-{{ $estadoBadge[$liq->estado] ?? 'secondary' }} ml-1">{{ $liq->estadoLabel() }}</span>
                </h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_liquidacion_sueldos') }}" class="btn btn-sm btn-secondary">
                        <i class="fa fa-arrow-left"></i> Volver
                    </a>
                    @if (can('editar-liquidacion-sueldos', false) && $liq->esEditable())
                        <form action="{{ route('calcular_liquidacion_sueldos', ['id' => $liq->id]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-info"><i class="fa fa-calculator"></i> Recalcular</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row text-center mb-2">
                    <div class="col"><small class="text-muted d-block">Empresa</small>{{ optional($liq->empresa)->nombre }}</div>
                    <div class="col"><small class="text-muted d-block">Per&iacute;odo</small>{{ $liq->periodo_mes ? sprintf('%02d/%04d', $liq->periodo_mes, $liq->periodo_anio) : $liq->periodo }}</div>
                    <div class="col"><small class="text-muted d-block">Recibos</small>{{ $liq->cantidad_recibos }}</div>
                    <div class="col"><small class="text-muted d-block">Remunerativo</small>$ {{ number_format((float) $liq->total_remunerativo, 2, ',', '.') }}</div>
                    <div class="col"><small class="text-muted d-block">Descuentos</small>$ {{ number_format((float) $liq->total_descuentos, 2, ',', '.') }}</div>
                    <div class="col"><small class="text-muted d-block">Neto</small><strong>$ {{ number_format((float) $liq->total_neto, 2, ',', '.') }}</strong></div>
                </div>

                <div class="table-responsive p-0">
                    <table class="table table-sm table-bordered table-hover">
                        <thead>
                            <tr style="background-color:#85C1E9;color:#17202A;">
                                <th style="width:60px">Recibo</th>
                                <th style="width:70px">Legajo</th>
                                <th>Empleado</th>
                                <th style="width:120px">CUIL</th>
                                <th class="text-right" style="width:120px">Remunerativo</th>
                                <th class="text-right" style="width:110px">Descuentos</th>
                                <th class="text-right" style="width:120px">Neto</th>
                                <th class="text-nowrap" style="width:130px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recibos as $rec)
                                <tr>
                                    <td>{{ $rec->numero_recibo }}</td>
                                    <td>{{ $rec->legajo }}</td>
                                    <td>{{ $rec->apellido_nombre }}</td>
                                    <td>{{ $rec->cuil }}</td>
                                    <td class="text-right">{{ number_format((float) $rec->total_remunerativo, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) $rec->total_descuentos, 2, ',', '.') }}</td>
                                    <td class="text-right"><strong>{{ number_format((float) $rec->neto_a_pagar, 2, ',', '.') }}</strong></td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-xs btn-outline-secondary" type="button" data-toggle="collapse" data-target="#det-{{ $rec->id }}">
                                            <i class="fa fa-list"></i> Detalle
                                        </button>
                                        <a href="{{ route('trazar_liquidacion_sueldos', ['id' => $liq->id, 'empleadoId' => $rec->empleado_id]) }}"
                                           class="btn btn-xs btn-outline-info" target="_blank" rel="noopener" title="Depurar cálculo paso a paso">
                                            <i class="fa fa-bug"></i> Trazar
                                        </a>
                                    </td>
                                </tr>
                                <tr class="collapse" id="det-{{ $rec->id }}">
                                    <td colspan="8" class="p-0">
                                        <table class="table table-sm mb-0" style="background:#fbfcfd;">
                                            <thead>
                                                <tr class="text-muted">
                                                    <th style="width:70px">C&oacute;d.</th>
                                                    <th>Concepto</th>
                                                    <th style="width:110px">Columna</th>
                                                    <th class="text-right" style="width:90px">Cant.</th>
                                                    <th class="text-right" style="width:110px">Valor</th>
                                                    <th class="text-right" style="width:120px">Importe</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($rec->detalles as $d)
                                                    <tr>
                                                        <td>{{ $d->concepto_codigo }}</td>
                                                        <td>{{ $d->concepto_descripcion }}</td>
                                                        <td><span class="badge badge-{{ $columnaBadge[$d->columna] ?? 'secondary' }}">{{ \App\Models\Sueldos\Liquidacion_Detalle_Sueldos::COLUMNAS[$d->columna] ?? $d->columna }}</span></td>
                                                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $d->cantidad, 4, ',', '.'), '0'), ',') }}</td>
                                                        <td class="text-right">{{ number_format((float) $d->valor, 2, ',', '.') }}</td>
                                                        <td class="text-right">{{ number_format((float) $d->importe, 2, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">La corrida no tiene recibos calculados. Use el bot&oacute;n Calcular.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $recibos->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
