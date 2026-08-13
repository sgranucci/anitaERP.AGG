@extends("theme.$theme.layout")
@section('titulo')
    Certificado sanitario SENASA
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/certificado_sanitario/filtro.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Ventas\CertificadoSanitarioListadoFiltros;
    $tieneCriterios = CertificadoSanitarioListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
    $limpiarUrl = route('consultar_certificado_sanitario');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Certificados sanitarios SENASA</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-certificado-sanitario',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios,
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (nro, precinto, camión…)',
                        'toggleTarget' => '#panel-filtros-certificado-sanitario',
                        'toggleId' => 'btn-toggle-filtros-certificado-sanitario',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_certificado_sanitario'),
                        'nuevoRegistroCan' => 'crear-certificado-sanitario',
                        'nuevoRegistroLabel' => 'Generar certificado WEB',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_certificado_sanitario') }}" id="form-filtros-certificado-sanitario" class="mb-0">
                @include('ventas.certificado_sanitario.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_certificado_sanitario',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nro</th>
                            <th>Fecha</th>
                            <th>Cami&oacute;n</th>
                            <th>Reparto</th>
                            <th>Precinto</th>
                            <th>Est. destino</th>
                            <th class="text-right">Kilos</th>
                            <th class="text-right">Cajas</th>
                            <th class="text-nowrap">XML</th>
                            <th class="width160 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>
                                {{$data->etiqueta}}
                                @if ($data->nro_cert_interno)
                                    <div class="text-muted small">Int. {{ $data->nro_cert_interno }}</div>
                                @endif
                                @if ($data->nro_cert_patagonico)
                                    <div class="text-muted small">Pat. {{ $data->nro_cert_patagonico }}</div>
                                @endif
                            </td>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                            <td>{{$data->camion->dominio ?? ''}}</td>
                            <td>{{$data->transporte->nombre ?? ''}}</td>
                            <td>{{$data->precinto}}</td>
                            <td>{{ $data->establecimiento_destino ?: '' }}</td>
                            <td class="text-right">{{ number_format((float) ($data->kilos_total ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($data->cajas_total ?? 0), 2, ',', '.') }}</td>
                            <td class="text-nowrap">
                                @if ($data->xml_frio)
                                <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'S']) }}"
                                    class="text-primary" title="Descargar ZIP para SENASA (frío)">
                                    <i class="fa fa-download"></i> ZIP frío
                                </a>
                                <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'S', 'ver' => 1]) }}"
                                    class="text-primary ml-1" target="_blank" rel="noopener" title="Ver XML frío">
                                    Ver
                                </a>
                                @endif
                                @if ($data->xml_sin_frio)
                                @if ($data->xml_frio)
                                    |
                                @endif
                                <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'N']) }}"
                                    class="text-primary" title="Descargar ZIP para SENASA (sin frío)">
                                    <i class="fa fa-download"></i> ZIP sin frío
                                </a>
                                <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'N', 'ver' => 1]) }}"
                                    class="text-primary ml-1" target="_blank" rel="noopener" title="Ver XML sin frío">
                                    Ver
                                </a>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{route('ver_certificado_sanitario', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Ver certificado">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="{{ route('pdf_certificado_sanitario', ['id' => $data->id]) }}"
                                    class="btn-accion-tabla tooltipsC" title="Solicitud PDF para emitir" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @if (can('borrar-certificado-sanitario', false))
                                <form action="{{route('eliminar_certificado_sanitario', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Sin certificados sanitarios.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @php $totalesListado = $totalesListado ?? ['certificados' => 0, 'kilos' => 0.0, 'cajas' => 0.0]; @endphp
            <div class="card-footer clearfix">
                <div class="float-left font-weight-bold">
                    Totales
                    @if ((int) $totalesListado['certificados'] > 0)
                        ({{ (int) $totalesListado['certificados'] }} certificado{{ (int) $totalesListado['certificados'] === 1 ? '' : 's' }})
                    @endif
                    :
                    {{ number_format((float) $totalesListado['kilos'], 2, ',', '.') }} kg
                    · {{ number_format((float) $totalesListado['cajas'], 2, ',', '.') }} cajas
                </div>
                @if (method_exists($datas, 'links'))
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
