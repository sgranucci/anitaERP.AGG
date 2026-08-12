@extends("theme.$theme.layout")
@section('titulo')
    Auditoría propuesta #{{ $propuesta->id }}
@endsection

@section('contenido')
@php $r = $resumen; @endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Auditoría / compliance — Propuesta #{{ $propuesta->id }}</h3>
                <div class="card-tools">
                    <a href="{{ route('exportar_auditoria_propuesta_pago', $propuesta->id) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('editar_propuesta_pago', $propuesta->id) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Empresa: <strong>{{ $propuesta->empresas->nombre ?? '' }}</strong>
                    — Estado: <strong>{{ $propuesta->estado }}</strong>
                    — Total {{ number_format((float)$r['monto_total'], 2, ',', '.') }}
                    — Autorizado {{ number_format((float)$r['monto_autorizado'], 2, ',', '.') }}
                </p>
                <p class="small text-muted">
                    Incluidas {{ $r['lineas_incluidas'] }} · Ejecutadas {{ $r['lineas_ejecutadas'] }}
                    · Pendientes {{ $r['lineas_pendientes'] }} · Excluidas {{ $r['lineas_excluidas'] }}
                    · OP bloqueadas banco {{ $r['ops_bloqueadas'] }}
                    @if ($r['lote_enviado']) · <span class="text-danger">Lote ENVIADO</span> @endif
                </p>

                <h5>Historia de estados</h5>
                <ul>
                    @forelse($estados as $est)
                        <li>
                            {{ optional($est->fecha)->format('d/m/Y H:i') }} —
                            <strong>{{ $est->estado }}</strong>
                            {{ $est->observacion }}
                            ({{ $est->usuarios->nombre ?? '' }})
                        </li>
                    @empty
                        <li class="text-muted">Sin historia</li>
                    @endforelse
                </ul>

                <h5>Firmas árbol (PP)</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Nivel</th><th>Estado</th><th>Destinatario</th><th>Enviado por</th>
                                <th>Envío</th><th>Proceso</th><th>Obs.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($firmas_arbol as $f)
                                <tr>
                                    <td>{{ $f->nivel }}</td>
                                    <td>{{ $f->estado }}</td>
                                    <td>{{ $f->destinatario }}</td>
                                    <td>{{ $f->enviado_por }}</td>
                                    <td>{{ $f->fecha_envio }}</td>
                                    <td>{{ $f->fecha_proceso }}</td>
                                    <td>{{ $f->observacion }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center">Sin movimientos de árbol</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5>Órdenes de pago</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>OP</th><th>Proveedor</th><th>Estado</th><th class="text-right">Monto</th><th>Banco</th></tr>
                        </thead>
                        <tbody>
                            @forelse($ops as $op)
                                <tr>
                                    <td>
                                        <a class="text-primary" href="{{ route('editar_pagoproveedor', $op->id) }}" target="_blank" rel="noopener">#{{ $op->id }}</a>
                                    </td>
                                    <td>{{ $op->proveedores->nombre ?? '' }}</td>
                                    <td>{{ $op->estado }}</td>
                                    <td class="text-right">{{ number_format((float)$op->monto, 2, ',', '.') }}</td>
                                    <td>
                                        @if ($op->bloqueada_banco)
                                            <span class="badge badge-danger">Bloqueada</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center">Sin OP</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5>Lotes bancarios</h5>
                <ul class="mb-0">
                    @forelse($lotes as $lote)
                        <li>
                            #{{ $lote->id }} — {{ $lote->estado }} —
                            {{ $lote->cantidad_lineas }} líneas —
                            {{ number_format((float)$lote->monto_total, 2, ',', '.') }}
                            @if ($lote->archivo_nombre) — {{ $lote->archivo_nombre }} @endif
                            @if ($lote->enviado_banco_at) — enviado {{ optional($lote->enviado_banco_at)->format('d/m/Y H:i') }} @endif
                            @if ($lote->convenio_driver) — driver {{ $lote->convenio_driver }} @endif
                        </li>
                    @empty
                        <li class="text-muted">Sin lotes</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
