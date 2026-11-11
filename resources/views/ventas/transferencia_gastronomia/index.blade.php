@extends("theme.$theme.layout")

@section('titulo')
    Ventas de gastronomía
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        @if (session('mensaje_error'))
            <div class="alert alert-danger">{{ session('mensaje_error') }}</div>
        @endif
        @if (! empty($errorPreview))
            <div class="alert alert-warning">{{ $errorPreview }}</div>
        @endif

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Transferencia de ventas por sala</h3>
            </div>
            <form method="post" action="{{ route('previsualizar_transferencia_gastronomia_crown') }}" id="form-gastro" data-enter-campos="1">
                @csrf
                <div class="card-body pb-2">
                    <p class="text-muted mb-3">
                        Lee la venta de la sala en Anita (una sola lectura) y la deja solo en el ERP: factura con impuestos y asiento.
                        No escribe nada en Anita. Si la factura ya está y no tiene asiento, se lo engancha.
                    </p>
                    <div class="form-group row mb-2">
                        <label for="sala" class="col-lg-3 control-label text-right pr-2 requerido">Sala</label>
                        <div class="col-lg-4">
                            <select name="sala" id="sala" class="form-control" required>
                                <option value="">Elegir</option>
                                @foreach ($salas as $item)
                                    @php
                                        $salaSeleccionada = ($sala ?? '') === $item['codigo'] || ($preview['sala'] ?? '') === $item['codigo'];
                                    @endphp
                                    <option value="{{ $item['codigo'] }}" @selected($salaSeleccionada)>
                                        {{ $item['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row mb-2">
                        <label for="desde" class="col-lg-3 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="desde" id="desde" class="form-control" required value="{{ $preview['desde'] ?? $desde }}">
                        </div>
                        <label for="hasta" class="col-lg-1 control-label text-right pr-2 requerido">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="hasta" id="hasta" class="form-control" required value="{{ $preview['hasta'] ?? $hasta }}">
                        </div>
                    </div>
                    @php
                        $criterioFechaActivo = $preview['criterio_fecha'] ?? $criterio_fecha ?? \App\Support\Ventas\TransferenciaCrown\TransferenciaCrownCriterioFecha::JORNADA;
                        $criterioFechaActivo = \App\Support\Ventas\TransferenciaCrown\TransferenciaCrownCriterioFecha::normalizar($criterioFechaActivo);
                    @endphp
                    <div class="form-group row mb-2">
                        <label class="col-lg-3 control-label text-right pr-2">Criterio de fecha</label>
                        <div class="col-lg-8">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio"
                                       id="criterio_fecha_jornada"
                                       name="criterio_fecha"
                                       value="jornada"
                                       class="custom-control-input"
                                       @checked($criterioFechaActivo === 'jornada')>
                                <label class="custom-control-label" for="criterio_fecha_jornada">
                                    Fecha de jornada <span class="text-muted">(ven_fecha_vto, default)</span>
                                </label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio"
                                       id="criterio_fecha_fisica"
                                       name="criterio_fecha"
                                       value="fisica"
                                       class="custom-control-input"
                                       @checked($criterioFechaActivo === 'fisica')>
                                <label class="custom-control-label" for="criterio_fecha_fisica">
                                    Fecha física <span class="text-muted">(ven_fecha)</span>
                                </label>
                            </div>
                            <small class="form-text text-muted d-block">
                                Si el cierre pasa la medianoche, la emisión queda al día siguiente y la jornada en el día de la venta.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
            </form>
        </div>

        @if (is_array($preview))
            <div class="card card-outline card-info">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem;">
                    <h3 class="card-title mb-0">{{ $preview['titulo'] ?? 'Preview' }}</h3>
                    <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:.35rem;">
                        @include('ventas.transferencia_crown.partials.boton_generar', [
                            'preview' => $preview,
                            'token' => $token,
                            'ruta' => route('confirmar_transferencia_gastronomia_crown'),
                            'formId' => 'form-gastro-confirmar-arriba',
                        ])
                        <a href="{{ route('transferencia_gastronomia_crown') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </div>
                <div class="card-body">
                    @include('ventas.transferencia_crown.partials.preview', ['preview' => $preview])
                </div>
                @if ((int) ($preview['resumen']['nuevos'] ?? 0) + (int) ($preview['resumen']['enganchar'] ?? 0) > 0)
                    <div class="card-footer">
                        @include('ventas.transferencia_crown.partials.boton_generar', [
                            'preview' => $preview,
                            'token' => $token,
                            'ruta' => route('confirmar_transferencia_gastronomia_crown'),
                            'formId' => 'form-gastro-confirmar',
                        ])
                    </div>
                @endif
            </div>
        @endif

        @include('ventas.transferencia_control.partials.resultado_carga', [
            'resumenCarga' => $resumenCarga ?? null,
        ])

        @if (is_array($controlPostImport ?? null))
            @php
                $cf = $controlPostImport['filtros'] ?? [];
                $linkControl = route('transferencia_control_crown', [
                    'consultar' => 1,
                    'desde' => $cf['desde'] ?? '',
                    'hasta' => $cf['hasta'] ?? '',
                    'alcance' => 'todo',
                    'origen' => $cf['origen'] ?? 'gastro',
                ]);
            @endphp
            @include('ventas.transferencia_control.partials.resultado_pantalla', [
                'resultado' => $controlPostImport,
                'titulo' => 'Control luego de importar',
                'subtitulo' => 'Revisión automática del rango transferido (configuración + asientos/facturas generados).',
                'linkControlCompleto' => $linkControl,
                'filtrosQuery' => [
                    'desde' => $cf['desde'] ?? '',
                    'hasta' => $cf['hasta'] ?? '',
                    'alcance' => 'todo',
                    'origen' => $cf['origen'] ?? 'gastro',
                    'consultar' => 1,
                ],
                'mostrarExport' => true,
                'limiteFilas' => 80,
            ])
        @endif
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'gastro-overlay',
    'tituloId' => 'gastro-overlay-titulo',
    'subtituloId' => 'gastro-overlay-subtitulo',
    'titulo' => 'Consultando la sala…',
    'subtitulo' => 'Lee la venta de la sala. Puede demorar. No cierre la página.',
])
@endsection

@section('scripts')
<script>
(function () {
    var overlay = document.getElementById('gastro-overlay');
    function mostrar(titulo, subtitulo) {
        if (!overlay) return;
        if (titulo) document.getElementById('gastro-overlay-titulo').textContent = titulo;
        if (subtitulo) document.getElementById('gastro-overlay-subtitulo').textContent = subtitulo;
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    var form = document.getElementById('form-gastro');
    if (form) {
        form.addEventListener('submit', function () {
            if (form.checkValidity()) {
                mostrar('Consultando la sala…', 'Lee la venta de Anita. Puede demorar. No cierre la página.');
            }
        });
    }
    document.querySelectorAll('.js-confirmar-transferencia').forEach(function (confirmar) {
        confirmar.addEventListener('submit', function () {
            mostrar('Generando facturas…', 'Se graba solo en el ERP (factura + asiento). No escribe en Anita.');
        });
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
