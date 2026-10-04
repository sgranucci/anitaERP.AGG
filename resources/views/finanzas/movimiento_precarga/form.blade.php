@extends("theme.$theme.layout")
@section('titulo', $precarga ? 'Precarga '.$precarga->id : 'Nueva precarga')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/finanzas-precarga.css') }}?v={{ filemtime(public_path('assets/css/finanzas-precarga.css')) }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/contable/cuentacontable/consulta.js')) }}"></script>
<script>
window.PRECARG_IMPACTO_URL = @json(route('impacto_finanza_movimiento_precarga'));
window.PRECARG_CUENTA_URL = @json(url('finanzas/movimiento-precarga/cuenta'));
</script>
<script src="{{ asset('assets/pages/scripts/finanzas/movimiento_precarga/form.js') }}?v={{ filemtime(public_path('assets/pages/scripts/finanzas/movimiento_precarga/form.js')) }}"></script>
@endsection

@section('contenido')
@php
    $esNueva = $precarga === null;
    $p = $precarga ?? new \App\Models\Finanzas\FinanzaMovimientoPrecarga();
    $cerrada = ! empty($cerrada);
    $tipo = old('tipo', $p->tipo ?? 'ingreso');
    $rubro = old('rubro', $p->rubro ?? 'trf_otros_bancos');
    $fechaValor = old('fecha', $fecha ?? date('Y-m-d'));
    $puedeGuardar = $esNueva
        ? can('crear-finanza-movimiento-precarga', false)
        : can('actualizar-finanza-movimiento-precarga', false);
    $accion = $esNueva
        ? route('guardar_finanza_movimiento_precarga')
        : route('actualizar_finanza_movimiento_precarga', $p->id);
@endphp
<div class="card card-primary precarga-shell">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            @if (! $esNueva)
                Precarga #{{ $p->id }}
            @else
                Nueva precarga de cash flow
            @endif
        </h3>
        <a href="{{ route('finanza_movimiento_precarga') }}" class="btn btn-outline-info btn-sm">
            <i class="fa fa-reply-all"></i> Volver al listado
        </a>
    </div>
    <div class="card-body">
        @include('includes.form-error')
        @include('includes.mensaje')
        @if ($cerrada)
            <div class="alert alert-success">
                Contabilizada en el ingreso/egreso
                @if ($p->cajaMovimiento)
                    <a class="alert-link" target="_blank" rel="noopener" href="{{ route('editar_ingresoegreso', ['id' => $p->caja_movimiento_id]) }}">
                        {{ $p->cajaMovimiento->numerotransaccion ?: $p->caja_movimiento_id }}
                    </a>
                @endif
                . Para modificarla hay que revertir ese comprobante.
            </div>
        @elseif ($p && $p->movimientoRevertido())
            <div class="alert alert-warning">El ingreso/egreso fue revertido. La precarga volvió a quedar editable.</div>
        @endif

        <div class="row">
            <div class="col-xl-5 mb-3">
                <form id="form-precarga" method="post" action="{{ $accion }}" class="form-horizontal precarga-form {{ $cerrada ? 'pe-none' : '' }}">
                    @csrf
                    @if (! $esNueva)
                        @method('PUT')
                    @endif
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => old('empresa_id', $p->empresa_id ?? null),
                        'solo_lectura' => $cerrada || ! $esNueva,
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="form-group row">
                        <label for="fecha" class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                        <div class="col-lg-8">
                            <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ $fechaValor }}" @if ($cerrada) readonly @endif>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="tipo" class="col-lg-4 control-label text-right pr-2 requerido">Tipo</label>
                        <div class="col-lg-8">
                            <select name="tipo" id="tipo" class="form-control" @if ($cerrada) disabled @endif>
                                @foreach ($tipos as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @if ($tipo === $clave) selected @endif>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="rubro" class="col-lg-4 control-label text-right pr-2 requerido">Rubro posición</label>
                        <div class="col-lg-8">
                            <select name="rubro" id="rubro" class="form-control" @if ($cerrada) disabled @endif>
                                @foreach ($rubros as $clave => $etiqueta)
                                    <option value="{{ $clave }}" @if ($rubro === $clave) selected @endif>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Fila de la posición bancaria. En transferencias, el detalle puede decir el banco de origen (TRF DE BI, TRF DE BM).</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="detalle" class="col-lg-4 control-label text-right pr-2 requerido">Detalle</label>
                        <div class="col-lg-8">
                            <input type="text" name="detalle" id="detalle" maxlength="255" class="form-control" required
                                   value="{{ old('detalle', $p->detalle ?? '') }}" @if ($cerrada) readonly @endif>
                        </div>
                    </div>

                    <div id="bloque-cuenta-unica">
                        @include('caja.partials.campo_consulta_cuentacaja', [
                            'prefix' => 'precarga',
                            'layout' => 'form_row',
                            'label' => 'Cuenta de caja',
                            'inputName' => 'cuentacaja_id',
                            'inputId' => 'precarga_cuentacaja_id',
                            'cuentacajaId' => old('cuentacaja_id', $p->cuentacaja_id ?? ''),
                            'codigo' => old('cuentacaja_id', null) !== null && old('cuentacaja_id') != ($p->cuentacaja_id ?? null) ? '' : ($p?->cuentacaja?->codigo ?? ''),
                            'nombre' => old('cuentacaja_id', null) !== null && old('cuentacaja_id') != ($p->cuentacaja_id ?? null) ? '' : ($p?->cuentacaja?->nombre ?? ''),
                            'col_label' => 'col-lg-4 control-label text-right pr-2',
                            'col_input' => 'col-lg-8',
                            'solo_lectura' => $cerrada,
                            'mostrar_editar' => true,
                        ])
                    </div>
                    <div id="bloque-transferencia" class="d-none">
                        @include('caja.partials.campo_consulta_cuentacaja', [
                            'prefix' => 'precarga_desde',
                            'layout' => 'form_row',
                            'label' => 'Desde cuenta',
                            'inputName' => 'cuentacaja_desde_id',
                            'inputId' => 'precarga_cuentacaja_desde_id',
                            'cuentacajaId' => old('cuentacaja_desde_id', $p->cuentacaja_desde_id ?? ''),
                            'codigo' => $p?->cuentacajaDesde?->codigo ?? '',
                            'nombre' => $p?->cuentacajaDesde?->nombre ?? '',
                            'col_label' => 'col-lg-4 control-label text-right pr-2',
                            'col_input' => 'col-lg-8',
                            'solo_lectura' => $cerrada,
                        ])
                        @include('caja.partials.campo_consulta_cuentacaja', [
                            'prefix' => 'precarga_hasta',
                            'layout' => 'form_row',
                            'label' => 'Hasta cuenta',
                            'inputName' => 'cuentacaja_hasta_id',
                            'inputId' => 'precarga_cuentacaja_hasta_id',
                            'cuentacajaId' => old('cuentacaja_hasta_id', $p->cuentacaja_hasta_id ?? ''),
                            'codigo' => $p?->cuentacajaHasta?->codigo ?? '',
                            'nombre' => $p?->cuentacajaHasta?->nombre ?? '',
                            'col_label' => 'col-lg-4 control-label text-right pr-2',
                            'col_input' => 'col-lg-8',
                            'solo_lectura' => $cerrada,
                            'ayuda' => 'Puede ser de otra empresa (transferencia intercompany).',
                        ])
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Moneda</label>
                        <div class="col-lg-8">
                            <input type="text" id="precarga_moneda" class="form-control" readonly value="{{ old('moneda_vista', $p?->moneda?->abreviatura ?? '') }}">
                            <small class="form-text text-muted" id="precarga_hoja"></small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="monto" class="col-lg-4 control-label text-right pr-2 requerido">Monto</label>
                        <div class="col-lg-8">
                            <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="form-control text-right" required
                                   value="{{ old('monto', $p->monto ?? '') }}" @if ($cerrada) readonly @endif>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="cotizacion" class="col-lg-4 control-label text-right pr-2">Cotización</label>
                        <div class="col-lg-8">
                            <input type="number" step="0.0001" min="0" name="cotizacion" id="cotizacion" class="form-control text-right"
                                   value="{{ old('cotizacion', $p->cotizacion ?? 1) }}" @if ($cerrada) readonly @endif>
                            <small class="form-text text-muted">En pesos queda en 1. En moneda extranjera se propone la vigente.</small>
                        </div>
                    </div>
                    <div id="bloque-contrapartida">
                        @include('sueldos.partials.campo_consulta_cuentacontable', [
                            'label' => 'Contrapartida',
                            'inputName' => 'cuentacontable_contrapartida_id',
                            'inputId' => 'precarga_cuentacontable_id',
                            'cuentaId' => old('cuentacontable_contrapartida_id', $p->cuentacontable_contrapartida_id ?? ''),
                            'codigo' => $contraCodigo ?? '',
                            'descripcion' => $contraNombre ?? '',
                            'col_label' => 'col-lg-4 control-label text-right pr-2',
                            'col_input' => 'col-lg-8',
                        ])
                        <p class="text-muted small pl-1">Hace falta solo al contabilizar un ingreso o egreso. La transferencia usa las cuentas contables de las dos cajas.</p>
                    </div>
                </form>
            </div>
            <div class="col-xl-7 mb-3">
                <div class="precarga-board" id="precarga-board">
                    <div class="precarga-board-head">
                        <div>
                            <div class="precarga-kicker">Posición del día</div>
                            <h4 id="precarga-board-fecha">{{ \Carbon\Carbon::parse($fechaValor)->format('d/m/Y') }}</h4>
                        </div>
                        <div class="precarga-leyenda">
                            <span class="tono-rrhh">RRHH / SUSS</span>
                            <span class="tono-descubierto">Descubierto</span>
                            <span class="tono-trf_otros_bancos">TRF bancos</span>
                            <span class="tono-trf_intercompany">Intercompany</span>
                        </div>
                    </div>
                    <div id="precarga-board-body">
                        @include('finanzas.movimiento_precarga.partials.tablero', ['tablero' => $tablero])
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap" style="gap:.5rem;">
        @if ($puedeGuardar && ! $cerrada)
            <button type="submit" form="form-precarga" class="btn btn-primary">
                <i class="fa fa-save"></i> Guardar
            </button>
        @endif
        @if (! $esNueva && ! $cerrada && can('convertir-finanza-movimiento-precarga', false))
            <form action="{{ route('convertir_finanza_movimiento_precarga', $p->id) }}" method="post" class="d-inline" onsubmit="return confirm('Se genera el ingreso/egreso contabilizado y esta precarga se cierra.');">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-check"></i> Contabilizar
                </button>
            </form>
        @endif
    </div>
</div>
@include('includes.caja.modalconsultacuentacaja')
@include('includes.contable.modalconsultacuentacontable')
@endsection
