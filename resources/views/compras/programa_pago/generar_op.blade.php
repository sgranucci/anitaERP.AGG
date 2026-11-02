@extends("theme.$theme.layout")
@section('titulo')
    Generar órdenes de pago
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/programa_pago/generar_op.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/programa_pago/generar_op.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $pendientes = 0;
    foreach ($plan as $filaPlan) {
        if (empty($filaPlan['pagoproveedor_id'])) {
            $pendientes++;
        }
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Generar OP · {{ $data->titulo ?: 'Programa #'.$data->id }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('editar_programa_pago', $data->id) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al programa
                    </a>
                </div>
            </div>
            <form id="form-generar-op-programa" method="POST" action="{{ route('generar_op_programa_pago', $data->id) }}" data-enter-campos="1">
                @csrf
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $data->empresa_id }}">
                <div class="card-body">
                    <p class="mb-2">
                        Una orden de pago por proveedor. Entran juntos los cheques de todos los meses.
                        Se aplican las facturas abiertas más viejas y se calculan las retenciones.
                        La transferencia es lo que falta, sobre el total, después de retenciones.
                        Si los cheques superan ese neto, no se transfiere y el sobrante queda como anticipo.
                    </p>
                    @if ($necesitaCuenta)
                        @include('caja.partials.campo_consulta_cuentacaja', [
                            'prefix' => 'ppgen',
                            'layout' => 'form_row',
                            'label' => 'Cuenta de la transferencia',
                            'inputName' => 'cuentacaja_id',
                            'inputId' => 'ppgen_cuentacaja_id',
                            'required' => true,
                            'col_label' => 'col-lg-3 control-label text-right pr-2',
                            'col_input' => 'col-lg-6',
                            'ayuda' => 'Sale de acá la diferencia de todas las OP de esta tanda que no cubren los cheques.',
                        ])
                    @else
                        <p class="text-muted">Ninguna OP de esta tanda necesita transferencia. Los cheques cubren el programado.</p>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th>Proveedor</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Programado</th>
                                    <th class="text-right">Cheques</th>
                                    <th class="text-right">Diferencia</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plan as $i => $fila)
                                    <tr>
                                        <td class="text-center">
                                            @if ($fila['pagoproveedor_id'])
                                                <input type="checkbox" disabled>
                                            @else
                                                <input type="checkbox" name="ops[{{ $i }}][incluir]" value="1" checked>
                                            @endif
                                            <input type="hidden" name="ops[{{ $i }}][linea_id]" value="{{ $fila['linea_id'] }}">
                                        </td>
                                        <td>
                                            <strong>{{ $fila['codigo'] }}</strong> {{ $fila['nombre'] }}
                                        </td>
                                        <td>
                                            @if ($fila['pagoproveedor_id'])
                                                —
                                            @else
                                                <input type="date" class="form-control form-control-sm" name="ops[{{ $i }}][fecha]" value="{{ $fechaHoy }}" required>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($fila['programado'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($fila['suma_cheques'], 2, ',', '.') }}</td>
                                        <td class="text-right {{ $fila['diferencia'] < -0.009 ? 'text-danger' : '' }}">
                                            {{ number_format($fila['diferencia'], 2, ',', '.') }}
                                        </td>
                                        <td class="small">
                                            @if ($fila['pagoproveedor_id'])
                                                <a class="text-primary" target="_blank" rel="noopener"
                                                   href="{{ route('editar_pagoproveedor', ['id' => $fila['pagoproveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                                    Ya generada
                                                </a>
                                            @elseif ($fila['sin_deuda'])
                                                Sin facturas abiertas: queda como anticipo.
                                            @else
                                                Aplica {{ number_format($fila['aplicado'], 2, ',', '.') }}
                                                en {{ $fila['comprobantes'] }} comprobante(s).
                                                @if ($fila['necesita_cuenta'])
                                                    Pide transferencia.
                                                @elseif ($fila['suma_cheques'] > $fila['programado'] + 0.009)
                                                    Los cheques superan lo programado.
                                                @endif
                                            @endif
                                            @if (count($fila['cheques']) > 0)
                                                <div class="text-muted">
                                                    @foreach ($fila['cheques'] as $ch)
                                                        Int. {{ $ch['nro_interno_anita'] ?? '—' }}
                                                        nro {{ $ch['numerocheque'] }}
                                                        {{ number_format($ch['monto'], 2, ',', '.') }}
                                                        @if (! $loop->last)
                                                            ;
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    @if ($pendientes > 0)
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check"></i> Generar {{ $pendientes }} orden(es) de pago
                        </button>
                    @else
                        <span class="text-muted">Estos proveedores ya tienen orden de pago.</span>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'pp-generar-op-overlay',
    'tituloId' => 'pp-generar-op-titulo',
    'subtituloId' => 'pp-generar-op-subtitulo',
    'titulo' => 'Generando órdenes de pago…',
    'subtitulo' => 'Aplica facturas, retenciones, cheques y asiento. No cierre la página.',
])
@endsection
