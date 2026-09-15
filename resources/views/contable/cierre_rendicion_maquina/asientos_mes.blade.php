@extends("theme.$theme.layout")
@section('titulo')
    Asientos cierre m&aacute;quinas
@endsection

@section('contenido')
@php
    $mesesPeriodo = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Asientos de cierres de m&aacute;quinas</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierre_rendicion_maquina_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    Lista los asientos tipo <strong>MAQ</strong> de la empresa en ERP cuya
                    <strong>fecha de asiento</strong> cae en el mes seleccionado
                    (cierres contables ERP e históricos importados).
                </div>

                <form method="get" action="{{ route('cierre_rendicion_maquina_asientos_mes') }}" class="mb-4">
                    @foreach ($retornoListadoQuery ?? [] as $retornoKey => $retornoVal)
                        @if (! is_array($retornoVal))
                            <input type="hidden" name="retorno[{{ $retornoKey }}]" value="{{ $retornoVal }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="empresa_id">Empresa</label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) ($empresa_id ?? 0) === (int) $emp->id)>
                                        {{ $emp->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="mes">Mes</label>
                            <select name="mes" id="mes" class="form-control" required>
                                @foreach ($mesesPeriodo as $num => $nombre)
                                    <option value="{{ $num }}" @selected((int) ($mes ?? 0) === (int) $num)>
                                        {{ str_pad((string) $num, 2, '0', STR_PAD_LEFT) }} — {{ $nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="anio">A&ntilde;o</label>
                            <input type="number" name="anio" id="anio" class="form-control"
                                   min="2000" max="2100" step="1" required
                                   value="{{ (int) ($anio ?? date('Y')) }}">
                        </div>
                        <div class="form-group col-md-3">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </form>

                @if (! empty($error_reporte))
                    <div class="alert alert-danger">{{ $error_reporte }}</div>
                @endif

                @if ($consultar && empty($error_reporte) && $resultado !== null)
                    @php
                        $filas = $resultado['filas'] ?? [];
                        $totales = [
                            'debe' => $resultado['total_debe'] ?? 0,
                            'haber' => $resultado['total_haber'] ?? 0,
                            'cantidad' => $resultado['cantidad_asientos'] ?? 0,
                        ];
                    @endphp

                    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                        <div>
                            <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                            — {{ $resultado['periodo_label'] ?? '' }}
                            <br>
                            <span class="text-muted">
                                {{ (int) ($resultado['cantidad_asientos'] ?? 0) }} asiento(s)
                                @if ((int) ($resultado['cantidad_cierre_erp'] ?? 0) > 0 || (int) ($resultado['cantidad_otros'] ?? 0) > 0)
                                    — {{ (int) ($resultado['cantidad_cierre_erp'] ?? 0) }} cierre ERP,
                                    {{ (int) ($resultado['cantidad_otros'] ?? 0) }} otros
                                @endif
                                ({{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                                al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }})
                            </span>
                        </div>
                        @if (can('exportar-cierre-rendicion-maquina-contable', false) && $filas !== [])
                            <div class="mr-2 mb-1">
                                @include('includes.exportar-tabla-queryparams', [
                                    'ruta' => 'listar_cierre_rendicion_maquina_asientos_mes',
                                    'queryparams' => $filtrosQuery ?? [],
                                ])
                            </div>
                        @endif
                    </div>

                    @if (! empty($resultado['empresa_nombre']))
                        @php
                            $logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                                collect([(object) ['nombreempresa' => $resultado['empresa_nombre']]])
                            );
                        @endphp
                        @if ($logos !== [])
                            <div class="mb-2">
                                @foreach ($logos as $logo)
                                    <img src="{{ is_array($logo) ? ($logo['uri'] ?? '') : $logo }}" alt="" style="height:40px;margin-right:8px;">
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <div class="table-responsive">
                        @include('contable.cierre_rendicion_maquina.partials.tabla_asientos_mes', [
                            'filas' => $filas,
                            'totales' => $totales,
                            'mostrarTotal' => true,
                            'esExport' => false,
                        ])
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
