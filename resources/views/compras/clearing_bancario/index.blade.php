@extends("theme.$theme.layout")
@section('titulo')
    Clearing bancario
@endsection

@section('contenido')
@php $c = $contadores ?? []; @endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Clearing bancario (OP ↔ IB)</h3>
                <div class="card-tools">
                    @include('includes.compras.boton-manual-propuesta-pago')
                    <a href="{{ route('tesoreria_cockpit') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-tachometer-alt"></i> Cockpit
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('clearing_bancario') }}" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected((int)$empresa_id === (int)$e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Días</label>
                            <input type="number" name="dias" value="{{ $dias }}" min="7" max="90" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                        </div>
                    </div>
                </form>

                <div class="row mb-3">
                    <div class="col-md-2"><div class="alert alert-warning py-2 mb-0"><strong>OP pend.</strong><br>{{ $c['ops'] ?? 0 }}</div></div>
                    <div class="col-md-2"><div class="alert alert-info py-2 mb-0"><strong>Transf. libres</strong><br>{{ $c['transferencias'] ?? 0 }}</div></div>
                    <div class="col-md-2"><div class="alert alert-secondary py-2 mb-0"><strong>Mov. extracto</strong><br>{{ $c['movimientos'] ?? 0 }}</div></div>
                    <div class="col-md-3"><div class="alert alert-success py-2 mb-0"><strong>Sugerencias</strong><br>{{ $c['sugerencias'] ?? 0 }}</div></div>
                    <div class="col-md-3"><div class="alert alert-danger py-2 mb-0"><strong>Excepciones</strong><br>{{ $c['excepciones'] ?? 0 }}</div></div>
                </div>

                @if (can('ejecutar-propuesta-pago', false))
                    <form method="post" action="{{ route('procesar_clearing_bancario') }}" class="form-inline mb-4">
                        @csrf
                        <label class="mr-2 small">Reprocesar propuesta #</label>
                        <input type="number" name="propuesta_pago_id" class="form-control form-control-sm mr-2" style="width:110px" required>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Correr clearing</button>
                    </form>
                @endif

                <h5>Sugerencias / excepciones</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th><th>Estado</th><th>Score</th><th>Regla</th>
                                <th>OP</th><th>Lado banco</th><th class="text-right">ERP</th><th class="text-right">Banco</th>
                                <th>CBU</th><th>Motivo</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($sugerencias ?? collect()) as $s)
                                <tr>
                                    <td>{{ $s->id }}</td>
                                    <td>{{ $s->estado }}</td>
                                    <td>{{ $s->score }}</td>
                                    <td>{{ $s->regla }}</td>
                                    <td>
                                        <a class="text-primary" href="{{ route('editar_pagoproveedor', $s->pagoproveedor_id) }}" target="_blank" rel="noopener">#{{ $s->pagoproveedor_id }}</a>
                                        @if ($s->propuesta_pago_id)
                                            <div class="small"><a class="text-primary" href="{{ route('editar_propuesta_pago', $s->propuesta_pago_id) }}" target="_blank" rel="noopener">PP #{{ $s->propuesta_pago_id }}</a></div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $s->lado_banco }}
                                        @if ($s->interbanking_transferencia_id) #T{{ $s->interbanking_transferencia_id }} @endif
                                        @if ($s->interbanking_movimiento_id) #M{{ $s->interbanking_movimiento_id }} @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float)$s->monto_erp, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float)($s->monto_banco ?? 0), 2, ',', '.') }}</td>
                                    <td class="small">{{ $s->cbu_erp }}<br>{{ $s->cbu_banco }}</td>
                                    <td class="small">{{ $s->motivo }}</td>
                                    <td class="text-nowrap">
                                        @if (can('ejecutar-propuesta-pago', false) && in_array($s->estado, ['SUGERIDO','EXCEPCION'], true) && ($s->interbanking_transferencia_id || $s->interbanking_movimiento_id))
                                            <form action="{{ route('confirmar_clearing_bancario', $s->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-success btn-xs btn-sm" type="submit">OK</button>
                                            </form>
                                            <form action="{{ route('rechazar_clearing_bancario', $s->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-outline-danger btn-xs btn-sm" type="submit">X</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted">Sin sugerencias abiertas</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h5>OP pendientes de clearing</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr><th>OP</th><th>Prov.</th><th>Fecha</th><th class="text-right">Neto</th><th>CBU</th></tr>
                                </thead>
                                <tbody>
                                    @forelse(($ops_pendientes ?? collect()) as $op)
                                        <tr>
                                            <td><a class="text-primary" href="{{ route('editar_pagoproveedor', $op->id) }}" target="_blank" rel="noopener">#{{ $op->id }}</a></td>
                                            <td>{{ $op->proveedor }}</td>
                                            <td>{{ $op->fecha }}</td>
                                            <td class="text-right">{{ number_format((float)$op->neto, 2, ',', '.') }}</td>
                                            <td class="small">{{ $op->cbu }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted text-center">Sin OP pendientes</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Banco libre (transferencias)</h5>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr><th>ID</th><th>Fecha</th><th class="text-right">Monto</th><th>CBU</th><th>CUIT</th></tr>
                                </thead>
                                <tbody>
                                    @forelse(($banco_transferencias ?? collect()) as $t)
                                        <tr>
                                            <td>#T{{ $t->id }}</td>
                                            <td>{{ $t->fecha }}</td>
                                            <td class="text-right">{{ number_format((float)$t->monto, 2, ',', '.') }}</td>
                                            <td class="small">{{ $t->cbu }}</td>
                                            <td class="small">{{ $t->cuit }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted text-center">Sin transferencias libres</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <h5>Extracto (movimientos débito)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr><th>ID</th><th>Fecha</th><th class="text-right">Monto</th><th>CUIT</th><th>Desc</th></tr>
                                </thead>
                                <tbody>
                                    @forelse(($banco_movimientos ?? collect()) as $m)
                                        <tr>
                                            <td>#M{{ $m->id }}</td>
                                            <td>{{ $m->fecha }}</td>
                                            <td class="text-right">{{ number_format((float)$m->monto, 2, ',', '.') }}</td>
                                            <td class="small">{{ $m->cuit }}</td>
                                            <td class="small">{{ $m->desc }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted text-center">Sin movimientos libres</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if (can('ejecutar-propuesta-pago', false))
                    <hr>
                    <h5>Match manual</h5>
                    <form method="post" action="{{ route('forzar_clearing_bancario') }}" class="form-row align-items-end">
                        @csrf
                        <div class="form-group col-md-2">
                            <label class="small">OP id</label>
                            <input type="number" name="pagoproveedor_id" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Transferencia id</label>
                            <input type="number" name="interbanking_transferencia_id" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Movimiento id</label>
                            <input type="number" name="interbanking_movimiento_id" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-warning btn-sm"
                                    onclick="return confirm('¿Forzar vínculo?');">Forzar match</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
