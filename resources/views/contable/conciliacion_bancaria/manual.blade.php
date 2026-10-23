@extends("theme.$theme.layout")
@section('titulo')
    Conciliación manual
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.conciliacionManual = {
        empresaId: {{ (int) $empresa_id }},
        cuentacajaId: {{ (int) $cuentacaja_id }},
        mes: {{ (int) $mes }},
        anio: {{ (int) $anio }},
        tableroUrl: @json(route('conciliacion_bancaria_api_tablero')),
        conciliarUrl: @json(route('conciliacion_bancaria_api_conciliar')),
        desconciliarUrl: @json(route('conciliacion_bancaria_api_desconciliar'))
    };
</script>
<script src="{{ asset('assets/pages/scripts/contable/conciliacion_bancaria/manual.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/conciliacion_bancaria/manual.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Conciliación manual
                    @if ($cuentacaja)
                        — {{ $cuentacaja->codigo }} {{ $cuentacaja->nombre }}
                    @endif
                    ({{ sprintf('%02d/%d', $mes, $anio) }})
                </h3>
                <div class="card-tools">
                    <a href="{{ $volver }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body pb-2">
                <div class="d-flex flex-wrap align-items-end mb-2" style="gap:8px;">
                    <div>
                        <label class="mb-0 small" for="filtro-texto">Texto</label>
                        <input type="text" id="filtro-texto" class="form-control form-control-sm" placeholder="Detalle, tipo, comprobante" style="width:220px;">
                    </div>
                    <div>
                        <label class="mb-0 small" for="filtro-importe">Importe</label>
                        <input type="text" id="filtro-importe" class="form-control form-control-sm" placeholder="-381225,12" style="width:140px;" title="Acepta 381225,12 o 381225.12">
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="solo-pendientes" checked>
                        <label class="form-check-label" for="solo-pendientes">Solo pendientes</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="incluir-anteriores">
                        <label class="form-check-label" for="incluir-anteriores">Incluir pendientes anteriores</label>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-sugerir">Sugerir par</button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-conciliar">Conciliar</button>
                    <span class="small text-muted" id="estado-cruce"></span>
                </div>
                <div class="row">
                    <div class="col-lg-6 pr-lg-1">
                        <div class="d-flex justify-content-between align-items-center px-2 py-1" style="background:#1B4F72;color:#fff;">
                            <strong>anitaERP</strong>
                            <span class="small" id="cuenta-contable"></span>
                        </div>
                        <div id="lista-contable" class="border" style="height:62vh;overflow:auto;background:#fff;"></div>
                    </div>
                    <div class="col-lg-6 pl-lg-1">
                        <div class="d-flex justify-content-between align-items-center px-2 py-1" style="background:#148F77;color:#fff;">
                            <strong>Interbanking</strong>
                            <span class="small" id="cuenta-banco"></span>
                        </div>
                        <div id="lista-banco" class="border" style="height:62vh;overflow:auto;background:#fff;"></div>
                    </div>
                </div>
                <div class="mt-3">
                    <h5 class="mb-2">Conciliados del período</h5>
                    <div id="lista-pares" class="border" style="max-height:28vh;overflow:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'conciliacion-manual-overlay',
    'tituloId' => 'conciliacion-manual-titulo',
    'subtituloId' => 'conciliacion-manual-subtitulo',
    'titulo' => 'Cargando movimientos…',
    'subtitulo' => 'Leyendo el mayor del mes y el extracto.',
])
@endsection
