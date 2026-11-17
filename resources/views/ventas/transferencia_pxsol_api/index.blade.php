@extends("theme.$theme.layout")

@section('titulo')
    Facturas PXSOL (API)
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
                <h3 class="card-title">Importar ventas de hotel desde PXSOL</h3>
            </div>
            <form method="post" action="{{ route('previsualizar_transferencia_pxsol_api_crown') }}" id="form-pxsol-api" data-enter-campos="1">
                @csrf
                <div class="card-body pb-2">
                    <p class="text-muted mb-3">
                        Trae los comprobantes por la API de PXSOL (con forma de pago).
                        Cuenta corriente va a la cuenta puente / cliente por CUIT; el resto, a efectivo del punto de venta.
                        Si el comprobante ya está en el ERP, la reimportación lo omite y no lo pisa.
                    </p>
                    <div class="form-group row mb-2">
                        <label for="hotel" class="col-lg-3 control-label text-right pr-2 requerido">Hotel</label>
                        <div class="col-lg-5">
                            <select name="hotel" id="hotel" class="form-control" required>
                                <option value="">Elegir</option>
                                @foreach ($hoteles as $item)
                                    <option
                                        value="{{ $item['codigo'] }}"
                                        data-pvs="{{ e(json_encode($item['puntoventas'])) }}"
                                        @selected(($hotel ?? '') === $item['codigo'])
                                    >
                                        {{ $item['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row mb-2">
                        <label for="puntoventa" class="col-lg-3 control-label text-right pr-2">Punto de venta</label>
                        <div class="col-lg-5">
                            <select name="puntoventa" id="puntoventa" class="form-control">
                                <option value="">Todos los del hotel</option>
                            </select>
                            <small class="form-text text-muted">Vacío = todos los PV fiscales del hotel en el rango.</small>
                        </div>
                    </div>
                    <div class="form-group row mb-2">
                        <label for="desde" class="col-lg-3 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="desde" id="desde" class="form-control" required value="{{ $desde }}">
                        </div>
                        <label for="hasta" class="col-lg-1 control-label text-right pr-2 requerido">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="hasta" id="hasta" class="form-control" required value="{{ $hasta }}">
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-cloud-download"></i> Consultar API
                    </button>
                </div>
            </form>
        </div>

        @php $precargaFlash = session('precarga_clientes', $preview['precarga_clientes'] ?? null); @endphp
        @if (is_array($precargaFlash) && (! empty($precargaFlash['creados']) || ! empty($precargaFlash['errores'])))
            <div class="alert alert-info small">
                <strong>Precarga de clientes</strong>
                @if (! empty($precargaFlash['creados']))
                    <div class="mt-1">Creados:
                        @foreach ($precargaFlash['creados'] as $c)
                            <span class="badge badge-success">{{ $c['codigo'] ?? '' }} — {{ $c['nombre'] ?? '' }}</span>
                        @endforeach
                    </div>
                @endif
                @if (! empty($precargaFlash['errores']))
                    <ul class="mb-0 mt-1 pl-3">
                        @foreach ($precargaFlash['errores'] as $err)
                            <li>{{ $err['mensaje'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if (is_array($preview))
            <div class="card card-outline card-info">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem;">
                    <h3 class="card-title mb-0">
                        Preview
                        @if (! empty($preview['hotel_nombre']))
                            — {{ $preview['hotel_nombre'] }}
                        @endif
                        @if (! empty($preview['puntoventa']) && ($preview['puntoventa'] ?? '') !== 'todos')
                            / PV {{ $preview['puntoventa'] }}
                        @endif
                    </h3>
                    <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:.35rem;">
                        @include('ventas.transferencia_crown.partials.boton_generar', [
                            'preview' => $preview,
                            'token' => $token,
                            'ruta' => route('confirmar_transferencia_pxsol_api_crown'),
                            'formId' => 'form-pxsol-api-confirmar-arriba',
                        ])
                        <a href="{{ route('transferencia_pxsol_api_crown') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </div>
                <div class="card-body">
                    @if (! empty($preview['resumen']['cuenta_corriente']) || ! empty($preview['resumen']['efectivo']))
                        <div class="alert alert-light border small mb-2">
                            Cuenta corriente: {{ (int) ($preview['resumen']['cuenta_corriente'] ?? 0) }}
                            · Efectivo / otros medios: {{ (int) ($preview['resumen']['efectivo'] ?? 0) }}
                            · Ya existentes (omitidos): {{ (int) ($preview['resumen']['existen'] ?? 0) }}
                        </div>
                    @endif
                    @include('ventas.transferencia_crown.partials.preview', [
                        'preview' => $preview,
                        'token' => $token,
                        'rutaPrecargaClientes' => route('precargar_clientes_transferencia_pxsol_api_crown'),
                    ])
                </div>
                @if ((int) ($preview['resumen']['nuevos'] ?? 0) > 0)
                    <div class="card-footer">
                        @include('ventas.transferencia_crown.partials.boton_generar', [
                            'preview' => $preview,
                            'token' => $token,
                            'ruta' => route('confirmar_transferencia_pxsol_api_crown'),
                            'formId' => 'form-pxsol-api-confirmar',
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
                'subtitulo' => 'Revisión automática de la importación PXSOL API.',
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
    'overlayId' => 'pxsol-api-overlay',
    'tituloId' => 'pxsol-api-overlay-titulo',
    'subtituloId' => 'pxsol-api-overlay-subtitulo',
    'titulo' => 'Consultando PXSOL…',
    'subtitulo' => 'Puede demorar según el rango. No cierre la página.',
])
@endsection

@section('scripts')
<script>
(function () {
    var hotelSel = document.getElementById('hotel');
    var pvSel = document.getElementById('puntoventa');
    var pvActual = @json($puntoventa === 'todos' ? '' : ($puntoventa ?? ''));

    function cargarPvs() {
        if (!hotelSel || !pvSel) return;
        var opt = hotelSel.options[hotelSel.selectedIndex];
        var pvs = [];
        try {
            pvs = JSON.parse(opt.getAttribute('data-pvs') || '[]') || [];
        } catch (e) {
            pvs = [];
        }
        pvSel.innerHTML = '';
        var todos = document.createElement('option');
        todos.value = '';
        todos.textContent = 'Todos los del hotel';
        pvSel.appendChild(todos);
        pvs.forEach(function (pv) {
            var o = document.createElement('option');
            o.value = pv.codigo || '';
            o.textContent = (pv.nombre || pv.codigo) + ' (' + (pv.fiscal || '') + ')';
            if (String(pvActual) === String(pv.codigo)) {
                o.selected = true;
            }
            pvSel.appendChild(o);
        });
    }
    if (hotelSel) {
        hotelSel.addEventListener('change', function () {
            pvActual = '';
            cargarPvs();
        });
        cargarPvs();
    }

    var overlay = document.getElementById('pxsol-api-overlay');
    function mostrar(titulo) {
        if (!overlay) return;
        if (titulo) document.getElementById('pxsol-api-overlay-titulo').textContent = titulo;
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
    var form = document.getElementById('form-pxsol-api');
    if (form) {
        form.addEventListener('submit', function () {
            if (form.checkValidity()) mostrar('Consultando PXSOL…');
        });
    }
    document.querySelectorAll('.js-confirmar-transferencia').forEach(function (confirmar) {
        confirmar.addEventListener('submit', function () {
            mostrar('Generando facturas…');
            document.getElementById('pxsol-api-overlay-subtitulo').textContent =
                'Se graban solo las nuevas. Las existentes no se pisan.';
        });
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
