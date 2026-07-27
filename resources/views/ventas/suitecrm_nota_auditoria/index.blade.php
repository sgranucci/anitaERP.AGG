@extends("theme.$theme.layout")
@section('titulo')
    Auditoría de notas CRM
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Auditoría de notas CRM (SuiteCRM)</h3>
            </div>
            <div class="card-body">
                @if (! $integracionActiva)
                    <div class="alert alert-warning mb-0">
                        La integración SuiteCRM no está habilitada. Active <code>SUITECRM_HABILITADO</code> para usar este reporte.
                    </div>
                @else
                    <p class="text-muted mb-3">
                        Lista las notas de SuiteCRM (cuentas, clientes potenciales y contactos), agrupables por fecha
                        como en los reportes AOR. Cruza con clientes del ERP cuando hay código/CUIT;
                        las notas sin vínculo ERP también se incluyen si tienen contenido (relacionado, asunto o texto).
                    </p>

                    <form method="get" action="{{ route('auditoria_notas_suitecrm') }}" class="mb-4" id="form-auditoria-notas-crm">
                        <div class="form-group row">
                            <label for="vendedor_crm_id" class="col-lg-2 control-label">Vendedor CRM</label>
                            <div class="col-lg-4">
                                <select name="vendedor_crm_id" id="vendedor_crm_id" class="form-control">
                                    <option value="">— Todos —</option>
                                    @foreach ($vendedores as $v)
                                        <option value="{{ $v->id }}" @selected(($filtros['vendedor_crm_id'] ?? '') === $v->id)>
                                            {{ $v->label }} — {{ $v->notas }} notas
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="fecha_desde" class="col-lg-2 control-label">Fecha desde</label>
                            <div class="col-lg-3">
                                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                       value="{{ $filtros['fecha_desde'] ?? '' }}">
                            </div>
                            <label for="fecha_hasta" class="col-lg-1 control-label text-right">Hasta</label>
                            <div class="col-lg-3">
                                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                       value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="parent_type" class="col-lg-2 control-label">Tipo relacionado</label>
                            <div class="col-lg-3">
                                <select name="parent_type" id="parent_type" class="form-control">
                                    @foreach ($tipos as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(($filtros['parent_type'] ?? '') === $valor)>
                                            {{ $etiqueta }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <label for="texto" class="col-lg-1 control-label text-right">Texto</label>
                            <div class="col-lg-4">
                                <input type="text" name="texto" id="texto" class="form-control"
                                       value="{{ $filtros['texto'] ?? '' }}"
                                       placeholder="Asunto, nota o relacionado">
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-lg-2"></div>
                            <div class="col-lg-8">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="solo_vinculo_erp"
                                           name="solo_vinculo_erp" value="1"
                                           @checked(! empty($filtros['solo_vinculo_erp']))>
                                    <label class="custom-control-label" for="solo_vinculo_erp">
                                        Solo notas con cliente vinculado en anitaERP
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-lg-2"></div>
                            <div class="col-lg-10">
                                <input type="hidden" name="consultar" value="1">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa fa-search"></i> Consultar
                                </button>
                                <a href="{{ route('auditoria_notas_suitecrm') }}" class="btn btn-default btn-sm">
                                    Limpiar
                                </a>
                            </div>
                        </div>
                    </form>

                    @include('includes.proceso_overlay_aviso', [
                        'overlayId' => 'auditoria-notas-crm-overlay',
                        'tituloId' => 'auditoria-notas-crm-titulo',
                        'subtituloId' => 'auditoria-notas-crm-subtitulo',
                        'titulo' => 'Consultando notas CRM…',
                        'subtitulo' => 'Puede demorar según el rango. No cierre la página.',
                    ])

                    @if ($consultado && $resultado)
                        <div class="mb-2">
                            <span class="badge badge-info mr-1">Notas: {{ $resultado['total'] ?? 0 }}</span>
                            @if (! empty($subtitulo))
                                <span class="text-muted small">{{ $subtitulo }}</span>
                            @endif
                        </div>

                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_auditoria_notas_suitecrm',
                            'queryparams' => $filtrosQuery ?? [],
                        ])

                        @php
                            $filasVista = $resultado['filas'] ?? [];
                            $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasVista);
                        @endphp
                        <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                            <div class="ml-auto text-muted small">
                                Generado {{ date('d/m/Y H:i') }}
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                                @include('ventas.suitecrm_nota_auditoria.partials.tabla_datos', [
                                    'filas' => $paginator?->items() ?? [],
                                    'mostrarLinks' => $mostrarLinks ?? true,
                                    'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                    'modo' => 'pantalla',
                                ])
                            </table>
                        </div>

                        @if ($paginator)
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="text-muted small">
                                    @if ($paginator->total() > 0)
                                        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
                                    @endif
                                </div>
                                <div>
                                    {{ $paginator->links() }}
                                </div>
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    var form = document.getElementById('form-auditoria-notas-crm');
    var overlay = document.getElementById('auditoria-notas-crm-overlay');
    function mostrar() {
        if (!overlay) return;
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
    if (form) {
        form.addEventListener('submit', function () {
            if (form.checkValidity()) {
                mostrar();
            }
        });
    }
    document.querySelectorAll('a[href*="listar-auditoria-notas-suitecrm"]').forEach(function (a) {
        a.addEventListener('click', function () { mostrar(); });
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
