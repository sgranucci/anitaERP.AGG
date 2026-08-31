@extends("theme.$theme.layout")
@section('titulo')
    Venta de m&aacute;quinas
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Venta de m&aacute;quinas (listado Anita)</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('cierre_rendicion_maquina_contable', $retornoListadoQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 small mb-3">
                    R&eacute;plica del listado <strong>Venta de m&aacute;quinas</strong> de Anita (<code>p-vtamaquina</code>).
                    Una fila por jornada con Completo (turno C): recaudaci&oacute;n real vs online, medios de pago y caja transitoria.
                    Fuente: rendiciones ERP + flash del d&iacute;a.
                </div>

                <form method="get" action="{{ route('cierre_rendicion_maquina_venta_listado') }}" class="mb-4">
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
                            <label for="fecha_desde">Jornada desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                   value="{{ $fecha_desde ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="fecha_hasta">Jornada hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                   value="{{ $fecha_hasta ?? '' }}" required>
                        </div>
                        <div class="form-group col-md-2">
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
                        $totales = $resultado['totales'] ?? null;
                    @endphp

                    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
                        <div>
                            <strong>{{ $resultado['empresa_nombre'] ?? '' }}</strong>
                            — {{ \Carbon\Carbon::parse($resultado['fecha_desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($resultado['fecha_hasta'])->format('d/m/Y') }}
                            <br>
                            <span class="text-muted">
                                {{ (int) ($resultado['cantidad_dias'] ?? 0) }} jornada(s) con Completo
                            </span>
                        </div>
                        @if (can('exportar-cierre-rendicion-maquina-contable', false))
                            <div class="mr-2 mb-1">
                                @include('includes.exportar-tabla-queryparams', [
                                    'ruta' => 'listar_cierre_rendicion_maquina_venta_listado',
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
                        @include('contable.cierre_rendicion_maquina.partials.tabla_venta_listado', [
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
