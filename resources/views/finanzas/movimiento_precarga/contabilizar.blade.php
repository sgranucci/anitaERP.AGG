@extends("theme.$theme.layout")
@section('titulo', 'Contabilizar precarga '.$precarga->id)

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/finanzas-precarga.css') }}?v={{ filemtime(public_path('assets/css/finanzas-precarga.css')) }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/finanzas/movimiento_precarga/contabilizar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/finanzas/movimiento_precarga/contabilizar.js')) }}"></script>
@endsection

@section('contenido')
@php
    $tipo = (string) $precarga->tipo;
    $moneda = trim((string) ($precarga->moneda->abreviatura ?? $precarga->moneda->nombre ?? ''));
    $accion = $tipo === 'egreso' ? 'egreso' : ($tipo === 'transferencia' ? 'transferencia' : 'ingreso');
@endphp
<div class="conta-stage">
    <div class="conta-top">
        <div>
            <div class="conta-kicker">Finanzas · cash flow</div>
            <h1>Contabilizar precarga #{{ $precarga->id }}</h1>
        </div>
        <a href="{{ route('editar_finanza_movimiento_precarga', $precarga->id) }}" class="btn btn-outline-light btn-sm">
            <i class="fa fa-fw fa-reply-all"></i> Salir
        </a>
    </div>

    @include('includes.form-error')
    @include('includes.mensaje')

    <form id="form-conta" method="POST" action="{{ route('convertir_finanza_movimiento_precarga', $precarga->id) }}" class="conta-grid">
        @csrf
        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $precarga->empresa_id }}">

        <aside class="conta-mov">
            <div class="conta-kicker">Movimiento</div>
            <div class="conta-monto" id="conta-objetivo" data-monto="{{ number_format($monto, 2, '.', '') }}">
                {{ number_format($monto, 2, ',', '.') }}
                <span>{{ $moneda !== '' ? $moneda : 'moneda' }}</span>
            </div>
            <div class="conta-chips">
                <span class="conta-chip tono-{{ $tipo }}">{{ $precarga->etiquetaTipo() }}</span>
                <span class="conta-chip">{{ $precarga->etiquetaRubro() }}</span>
            </div>
            <dl class="conta-meta">
                <div>
                    <dt>Empresa</dt>
                    <dd>{{ $precarga->empresa->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd>{{ $precarga->fecha?->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt>Cuenta de caja</dt>
                    <dd>{{ $precarga->etiquetaCuenta() }}</dd>
                </div>
                <div>
                    <dt>Cotización</dt>
                    <dd>{{ number_format((float) $precarga->cotizacion, 4, ',', '.') }}</dd>
                </div>
            </dl>
            <p class="conta-detalle">{{ $precarga->detalle }}</p>
            <p class="conta-nota">El total del movimiento queda fijo. Podés cambiar cuentas o agregar renglones en cero y repartir el mismo importe.</p>
        </aside>

        <section class="conta-asiento">
            <div class="conta-asiento-head">
                <div>
                    <div class="conta-kicker">Asiento</div>
                    <h2>Debe y haber del {{ $accion }}</h2>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="conta-agregar">
                    <i class="fa fa-plus"></i> Agregar renglón
                </button>
            </div>
            <div class="conta-scroll">
                <table class="table table-sm conta-tabla mb-0">
                    <thead>
                        <tr>
                            <th>Cuenta contable</th>
                            <th class="text-right conta-col-imp">Debe</th>
                            <th class="text-right conta-col-imp">Haber</th>
                            <th class="conta-col-acc"></th>
                        </tr>
                    </thead>
                    <tbody id="conta-lineas">
                        @foreach ($lineas as $linea)
                            @include('finanzas.movimiento_precarga.partials.linea_asiento', ['linea' => $linea])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="conta-pie">
                <div class="conta-totales">
                    <div>
                        <span>Debe</span>
                        <strong id="conta-debe">0,00</strong>
                    </div>
                    <div>
                        <span>Haber</span>
                        <strong id="conta-haber">0,00</strong>
                    </div>
                    <div>
                        <span>Diferencia</span>
                        <strong id="conta-dif">0,00</strong>
                    </div>
                </div>
                <p class="conta-aviso" id="conta-aviso">El debe y el haber tienen que sumar el total del movimiento.</p>
                <button type="submit" class="btn btn-success btn-block" id="conta-generar" disabled>
                    <i class="fa fa-check"></i> Generar {{ $accion }}
                </button>
            </div>
        </section>
    </form>
</div>

<template id="conta-template-linea">
    @include('finanzas.movimiento_precarga.partials.linea_asiento', [
        'linea' => ['cuentacontable_id' => 0, 'codigo' => '', 'nombre' => '', 'debe' => 0, 'haber' => 0],
    ])
</template>

@include('includes.contable.modalconsultacuentacontable')
@endsection
