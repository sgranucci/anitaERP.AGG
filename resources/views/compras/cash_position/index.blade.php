@extends("theme.$theme.layout")
@section('titulo')
    Cash position — vencimientos
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cash position / proyección de pagos</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_cockpit') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-tachometer-alt"></i> Cockpit
                    </a>
                    @if (can('crear-propuesta-pago', false))
                        <a href="{{ route('crear_propuesta_pago') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-plus"></i> Nueva propuesta
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('cash_position') }}" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="small">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas asignadas</option>
                                @foreach($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected((int)($empresa_id ?? 0) === (int)$e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                        </div>
                    </div>
                </form>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="alert alert-success py-2 mb-2">
                            <strong>Saldos IB</strong><br>
                            {{ number_format((float)($total_saldos_ib ?? 0), 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-danger py-2 mb-2">
                            <strong>Deuda vencida</strong><br>
                            {{ number_format((float)($total_deuda ?? 0), 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning py-2 mb-2">
                            <strong>Propuestas abiertas</strong><br>
                            {{ number_format((float)($total_propuestas ?? 0), 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-info py-2 mb-2">
                            <strong>Disponible vs deuda</strong><br>
                            {{ number_format((float)($disponible_vs_deuda ?? 0), 2, ',', '.') }}
                            <div class="small">vs propuestas: {{ number_format((float)($disponible_vs_propuestas ?? 0), 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                @if (!empty($forecast['buckets']))
                    <h5>Cash forecast (7 / 15 / 30 días)</h5>
                    <p class="small text-muted">Saldo IB {{ number_format((float)($forecast['saldo_ib'] ?? 0), 2, ',', '.') }} menos vencimientos por ventana calendario.</p>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Ventana</th>
                                    <th class="text-right">Deuda</th>
                                    <th class="text-right">Cant.</th>
                                    <th class="text-right">Saldo proyectado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($forecast['buckets'] as $i => $b)
                                    <tr>
                                        <td>{{ $b['etiqueta'] }}</td>
                                        <td class="text-right">{{ number_format((float)$b['monto'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ $b['cantidad'] }}</td>
                                        <td class="text-right">{{ number_format((float)($forecast['proyeccion'][$i]['saldo_proyectado'] ?? 0), 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h5>Saldos Interbanking</h5>
                <table class="table table-sm table-bordered mb-4">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Cuenta</th><th>Nombre</th><th>Fecha</th><th class="text-right">Saldo</th></tr>
                    </thead>
                    <tbody>
                        @forelse(($saldos_interbanking ?? collect()) as $s)
                            <tr>
                                <td>{{ $s->cuenta }}</td>
                                <td>{{ $s->nombre }}</td>
                                <td>{{ $s->fecha }}</td>
                                <td class="text-right">{{ number_format((float)$s->saldo, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">Sin saldos Interbanking (sincronice o vincule cuenta_interbanking en cuentas de caja)</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h5>Propuestas abiertas</h5>
                <table class="table table-sm table-bordered mb-4">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>ID</th><th>Fecha</th><th>Empresa</th><th>Estado</th><th class="text-right">Monto</th><th class="text-right">Autorizado</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse(($propuestas_abiertas ?? collect()) as $p)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td>{{ optional($p->fecha)->format('d/m/Y') }}</td>
                                <td>{{ $p->empresas->nombre ?? '' }}</td>
                                <td>{{ $p->estado }}</td>
                                <td class="text-right">{{ number_format((float)$p->monto_total, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float)($p->monto_autorizado ?: 0), 2, ',', '.') }}</td>
                                <td>
                                    <a class="text-primary" href="{{ route('editar_propuesta_pago', $p->id) }}">Abrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted text-center">Sin propuestas abiertas</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h5>Deuda por vencimiento</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Vencimiento</th>
                                <th>Proveedor</th>
                                <th>Empresa</th>
                                <th class="text-right">Saldo</th>
                                <th>Moneda</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($deuda_vencida ?? collect()) as $cc)
                                <tr>
                                    <td>{{ optional($cc->fechavencimiento)->format('d/m/Y') }}</td>
                                    <td>{{ $cc->proveedores->nombre ?? ($cc->nombre_proveedor ?? '') }}</td>
                                    <td>{{ $cc->empresas->nombre ?? ($cc->nombre_empresa ?? '') }}</td>
                                    <td class="text-right">{{ number_format((float)($cc->saldo ?? $cc->total ?? 0), 2, ',', '.') }}</td>
                                    <td>{{ $cc->monedas->abreviatura ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">Sin deuda</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
