@extends("theme.$theme.layout")
@section('titulo')
    Cockpit tesorería
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cockpit / workbench de tesorería</h3>
                <div class="card-tools">
                    @include('includes.compras.boton-manual-propuesta-pago')
                    @if (can('listar-propuesta-pago', false) || can('ejecutar-propuesta-pago', false))
                        <a href="{{ route('clearing_bancario') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-balance-scale"></i> Clearing
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('tesoreria_cockpit') }}" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small">Empresa</label>
                            <select name="empresa_id" class="form-control form-control-sm">
                                <option value="">Todas asignadas</option>
                                @foreach($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected((int)($empresa_id ?? 0) === (int)$e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Tipo</label>
                            <select name="tipo" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                <option value="PP" @selected(($tipo ?? '') === 'PP')>Propuesta</option>
                                <option value="SP" @selected(($tipo ?? '') === 'SP')>Solicitud</option>
                                <option value="IE" @selected(($tipo ?? '') === 'IE')>Ing/Egr</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small">Días</label>
                            <input type="number" name="dias" value="{{ $dias ?? 60 }}" min="14" max="180" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                        </div>
                    </div>
                </form>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="alert alert-success py-2 mb-2"><strong>Saldos IB</strong><br>{{ number_format((float)$total_saldos_ib, 2, ',', '.') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-danger py-2 mb-2"><strong>Deuda vencida</strong><br>{{ number_format((float)$total_deuda, 2, ',', '.') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning py-2 mb-2"><strong>Propuestas abiertas</strong><br>{{ number_format((float)$total_propuestas, 2, ',', '.') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-info py-2 mb-2"><strong>Disponible vs deuda</strong><br>{{ number_format((float)$disponible_vs_deuda, 2, ',', '.') }}</div>
                    </div>
                </div>

                @if (!empty($forecast['buckets']))
                    <h5>Forecast 7 / 15 / 30</h5>
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

                <h5>
                    Workbench operativo
                    <small class="text-muted">
                        PP {{ $contadores_wb['PP'] ?? 0 }} ·
                        SP {{ $contadores_wb['SP'] ?? 0 }} ·
                        IE {{ $contadores_wb['IE'] ?? 0 }} ·
                        monto {{ number_format((float)($total_monto_wb ?? 0), 2, ',', '.') }}
                    </small>
                </h5>
                <div class="table-responsive mb-4">
                    <table id="tabla-paginada" class="table table-sm table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Tipo</th>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Empresa</th>
                                <th>Estado</th>
                                <th class="text-right">Monto</th>
                                <th>Detalle</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($filas ?? collect()) as $f)
                                <tr>
                                    <td>
                                        <span class="badge badge-{{ $f->tipo === 'PP' ? 'primary' : ($f->tipo === 'SP' ? 'warning' : 'secondary') }}">
                                            {{ $f->tipo_label }}
                                        </span>
                                    </td>
                                    <td>{{ $f->id }}</td>
                                    <td>{{ $f->fecha }}</td>
                                    <td>{{ $f->empresa }}</td>
                                    <td>{{ $f->estado }}</td>
                                    <td class="text-right">{{ number_format((float)$f->monto, 2, ',', '.') }}</td>
                                    <td class="small">{{ $f->detalle }}</td>
                                    <td>
                                        @if ($f->url)
                                            <a href="{{ $f->url }}" class="btn-accion-tabla text-primary" target="_blank" rel="noopener" title="Abrir">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">Sin documentos en la ventana</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5>Accesos</h5>
                <div class="row">
                    @foreach($accesos as $a)
                        @if ($a['can'])
                            <div class="col-md-4 mb-3">
                                <a href="{{ $a['ruta'] }}" class="btn btn-outline-primary btn-block text-left p-3 h-100">
                                    <i class="fa {{ $a['icono'] }} mr-2"></i>
                                    <strong>{{ $a['titulo'] }}</strong>
                                    <div class="small text-muted mt-1">{{ $a['desc'] }}</div>
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
