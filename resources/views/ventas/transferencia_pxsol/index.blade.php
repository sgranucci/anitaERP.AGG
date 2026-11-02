@extends("theme.$theme.layout")

@section('titulo')
    Facturas ARCA
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
                <h3 class="card-title">Facturas que vienen de ARCA</h3>
            </div>
            <form method="post" action="{{ route('previsualizar_transferencia_pxsol_crown') }}" enctype="multipart/form-data" id="form-pxsol" data-enter-campos="1">
                @csrf
                <div class="card-body pb-2">
                    <p class="text-muted mb-3">
                        Subí el Excel o CSV de Mis Comprobantes. Havanna y PXSOL se leen igual: el punto de venta sale de cada fila y el asiento usa las cuentas de ese PV.
                        Se graba solo en el ERP; no escribe en Anita. Lo que elijas acá queda recordado para tu usuario.
                    </p>
                    <div class="form-group row mb-2">
                        <label class="col-lg-3 control-label text-right pr-2">Cómo entra cada tipo</label>
                        <div class="col-lg-9">
                            <div class="row">
                                @foreach ($codigosArca as $codigoAfip => $metaTipo)
                                    <div class="col-md-6 col-xl-4 mb-2">
                                        <div class="border rounded p-2 h-100 bg-light">
                                            <div class="d-flex justify-content-between">
                                                <strong>{{ $metaTipo['titulo'] }}</strong>
                                                <span class="badge badge-info">AFIP {{ $codigoAfip }}</span>
                                            </div>
                                            <select name="tipos[{{ $codigoAfip }}]" class="form-control form-control-sm mt-2" aria-label="Tipo para AFIP {{ $codigoAfip }}">
                                                @foreach ($opcionesTipo as $abreviatura => $nombreTipo)
                                                    <option value="{{ $abreviatura }}" @selected(($mapeo[$codigoAfip] ?? $metaTipo['defecto']) === $abreviatura)>
                                                        {{ $abreviatura }} — {{ $nombreTipo }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="form-group row mb-2">
                        <label for="archivo" class="col-lg-3 control-label text-right pr-2 requerido">Archivo ARCA</label>
                        <div class="col-lg-6">
                            <input type="file" name="archivo" id="archivo" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                            <small class="form-text text-muted">El mismo archivo que Anita lee como pxsol.csv o havanna.csv. Punto de venta, número y CAE salen de cada fila.</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-search"></i> Ver preview
                    </button>
                </div>
            </form>
        </div>

        @if (is_array($preview))
            <div class="card card-outline card-info">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem;">
                    <h3 class="card-title mb-0">Preview</h3>
                    <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:.35rem;">
                        @include('ventas.transferencia_crown.partials.boton_generar', [
                            'preview' => $preview,
                            'token' => $token,
                            'ruta' => route('confirmar_transferencia_pxsol_crown'),
                            'formId' => 'form-pxsol-confirmar-arriba',
                        ])
                        <a href="{{ route('transferencia_pxsol_crown') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
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
                            'ruta' => route('confirmar_transferencia_pxsol_crown'),
                            'formId' => 'form-pxsol-confirmar',
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
                    'origen' => $cf['origen'] ?? 'arca',
                ]);
            @endphp
            @include('ventas.transferencia_control.partials.resultado_pantalla', [
                'resultado' => $controlPostImport,
                'titulo' => 'Control luego de importar',
                'subtitulo' => 'Revisión automática del archivo transferido (configuración + asientos/facturas generados).',
                'linkControlCompleto' => $linkControl,
                'filtrosQuery' => [
                    'desde' => $cf['desde'] ?? '',
                    'hasta' => $cf['hasta'] ?? '',
                    'alcance' => 'todo',
                    'origen' => $cf['origen'] ?? 'arca',
                    'consultar' => 1,
                ],
                'mostrarExport' => true,
                'limiteFilas' => 80,
            ])
        @endif
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'pxsol-overlay',
    'tituloId' => 'pxsol-overlay-titulo',
    'subtituloId' => 'pxsol-overlay-subtitulo',
    'titulo' => 'Leyendo el archivo…',
    'subtitulo' => 'Puede demorar si hay muchas facturas. No cierre la página.',
])
@endsection

@section('scripts')
<script>
(function () {
    var overlay = document.getElementById('pxsol-overlay');
    function mostrar(titulo) {
        if (!overlay) return;
        if (titulo) document.getElementById('pxsol-overlay-titulo').textContent = titulo;
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
    var form = document.getElementById('form-pxsol');
    if (form) {
        form.addEventListener('submit', function () {
            if (form.checkValidity()) mostrar('Leyendo el archivo…');
        });
    }
    document.querySelectorAll('.js-confirmar-transferencia').forEach(function (confirmar) {
        confirmar.addEventListener('submit', function () {
            mostrar('Generando facturas…');
            document.getElementById('pxsol-overlay-subtitulo').textContent = 'Se graba solo en el ERP (factura + asiento). No escribe en Anita.';
        });
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
