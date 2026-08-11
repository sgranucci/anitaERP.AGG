@extends("theme.$theme.layout")
@section('titulo')
Cert. SENASA Surmar
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/certificado_senasa_surmar/filtro.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Stock\CertificadoSenasaSurmarListadoFiltros;
    $tieneCriterios = CertificadoSenasaSurmarListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-certificate"></i> Certificado SENASA Surmar</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cert-senasa-surmar',
                        'filtroValor' => $filtros['filtro_valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios,
                        'limpiarUrl' => route('certificado_senasa_surmar'),
                        'placeholder' => 'Nº, remito AFIP, estado…',
                        'toggleTarget' => '#panel-filtros-cert-senasa-surmar',
                        'toggleId' => 'btn-toggle-filtros-cert-senasa-surmar',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_certificado_senasa_surmar'),
                        'nuevoRegistroCan' => 'crear-certificado-senasa-surmar',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('certificado_senasa_surmar') }}" id="form-filtros-cert-senasa-surmar" class="mb-0">
                @include('stock.certificado_senasa_surmar.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_certificado_senasa_surmar',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table id="tabla-paginada" class="table table-striped table-bordered table-hover">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nº</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Remito AFIP</th>
                            <th>Ítems</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coleccion as $item)
                            <tr>
                                <td>{{ $item->etiqueta }}</td>
                                <td>{{ optional($item->fecha)->format('d/m/Y') }}</td>
                                <td>
                                    @if ($item->estado === 'BORRADOR')
                                        <span class="badge badge-warning">Provisorio</span>
                                    @elseif ($item->estado === 'CONFIRMADO')
                                        <span class="badge badge-success">Confirmado</span>
                                    @elseif ($item->estado === 'ANULADO')
                                        <span class="badge badge-danger">Anulado</span>
                                    @else
                                        <span class="badge badge-secondary">{{ $item->estado }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->cod_remito ?: '—' }}</td>
                                <td>{{ $item->articulos_count ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-certificado-senasa-surmar', false))
                                        <a href="{{ route('cargar_certificado_senasa_surmar', $item->id) }}" class="btn-accion-tabla tooltipsC" title="Abrir / continuar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if ($item->xml_path && can('listar-certificado-senasa-surmar', false))
                                        <a href="{{ route('descargar_xml_certificado_senasa_surmar', $item->id) }}" class="btn-accion-tabla tooltipsC" title="XML SENASA">
                                            <i class="fa fa-file-code-o"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Sin certificados SENASA Surmar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($coleccion, 'links'))
                <div class="card-footer clearfix">
                    {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
