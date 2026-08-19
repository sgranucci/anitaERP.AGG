@extends("theme.$theme.layout")
@section('titulo')
    Flash — Contabilidad e Impuestos
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}" type="text/javascript"></script>
<script>
(function () {
    var form = document.getElementById('form-flash-contable');
    if (! form) {
        return;
    }
    form.addEventListener('submit', function () {
        var btn = document.getElementById('btn-consultar-flash-contable');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Consultando…';
        }
    });
})();
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Flash — Contabilidad e Impuestos</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_contable') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('flash_contable') }}" id="form-flash-contable" class="mb-0" autocomplete="off">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Recorte diario del flash principal para Contaduría e Impuestos: slots, ruletas, win,
                        bingo, F&amp;B, parking y tilde de flash cerrado. Cada empresa queda en su bloque de columnas.
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'mostrar_consolidar' => false,
                        'reporte_clave' => 'flash_contable',
                        'id_prefix' => 'flc',
                        'col_label' => 'col-lg-2 text-right',
                    ])

                    @php
                        $mesesPeriodo = [
                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                        ];
                        $anioMin = 2015;
                        $anioMax = (int) date('Y') + 1;
                    @endphp

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right requerido">Mes / Año</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="mes" id="mes" class="form-control" required aria-label="Mes">
                                        @foreach ($mesesPeriodo as $num => $nombre)
                                            <option value="{{ $num }}" @selected((int) ($filtros['mes'] ?? $mes_actual) === $num)>
                                                {{ $nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="anio" id="anio" class="form-control" required aria-label="Año">
                                        @for ($y = $anioMax; $y >= $anioMin; $y--)
                                            <option value="{{ $y }}" @selected((int) ($filtros['anio'] ?? $anio_actual) === $y)>
                                                {{ $y }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0 mt-3">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-flash-contable">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body border-top pt-3">
                    @if ($reporte !== null)
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_flash_contable',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                        <p class="text-muted small mb-3">
                            {{ $subtitulo ?? '' }}
                            — {{ $reporte['cantidad_dias'] ?? 0 }} día(s) con flash
                        </p>

                        @if (! empty($reporte['filas']))
                            @include('contable.flash_contable.partials.tabla', ['reporte' => $reporte])
                        @else
                            <div class="alert alert-warning">No hay registros flash en el mes seleccionado.</div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
