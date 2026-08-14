@extends("theme.$theme.layout")
@section('titulo')
Exportación de Clientes UIF
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@if (!empty($autoDescargarXml) && !empty($urlDescargaXmlZip))
<script>
(function () {
    var url = @json($urlDescargaXmlZip);
    window.setTimeout(function () {
        window.location.href = url;
    }, 600);
})();
</script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (session('mensaje_error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <h4><i class="icon fa fa-ban"></i> Error</h4>
                {{ session('mensaje_error') }}
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe de datos de clientes UIF</h3>
                <div class="card-tools">
                    @include('includes.uif.boton-manual')
                    <a href="{{ route('crear_exporta_operacion') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Nueva consulta
                    </a>
                    @if (can('exportar-operacion-uif', false))
                        <a href="{{ route('exporta_cliente_uif_excel', ['periodo' => $periodo, 'limiteinformeuif' => $limiteinformeuif, 'empresa_id' => $empresaId]) }}"
                           class="btn btn-success btn-sm">
                            <i class="fa fa-fw fa-file-excel-o"></i> Excel
                        </a>
                        <a href="{{ route('exporta_cliente_uif_pdf', ['periodo' => $periodo, 'limiteinformeuif' => $limiteinformeuif, 'empresa_id' => $empresaId]) }}"
                           class="btn btn-danger btn-sm">
                            <i class="fa fa-fw fa-file-pdf-o"></i> PDF
                        </a>
                        <a href="{{ route('exporta_cliente_uif', ['periodo' => $periodo, 'limiteinformeuif' => $limiteinformeuif, 'empresa_id' => $empresaId]) }}"
                           class="btn btn-primary btn-sm">
                            <i class="fa fa-fw fa-download"></i> Generar y descargar XML (ZIP)
                        </a>
                        @if (!empty($xmlDisponible))
                            <a href="{{ $urlDescargaXmlZip }}"
                               class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-file-archive-o"></i> Volver a descargar ZIP
                            </a>
                        @endif
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-3">
                        <strong>Empresa:</strong> {{ $empresaInforme ?? '' }}
                    </div>
                    <div class="col-md-3">
                        <strong>Periodo:</strong> {{ $periodo }}
                    </div>
                    <div class="col-md-3">
                        <strong>Importe mayor a:</strong> {{ number_format((float) $limiteinformeuif, 2, ',', '.') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Premios reportables:</strong> {{ $resumen['cantidad'] ?? count($cliente_premio_uifs) }}
                    </div>
                </div>
                @if (!empty($resumen['total']))
                    <div class="text-center text-muted small mb-2">
                        Total premios: <strong>{{ number_format((float) $resumen['total'], 2, ',', '.') }}</strong>
                    </div>
                @endif
                <div class="d-flex justify-content-center mb-3">
                    <div class="px-4 py-2 rounded border border-secondary bg-light text-center" style="font-size: 0.9rem;">
                        <span class="text-muted">Proceso:</span>
                        <span class="{{ !empty($xmlDisponible) ? 'text-success' : 'font-weight-bold' }}">1. Consulta</span>
                        <span class="mx-2 text-muted">|</span>
                        <span class="text-muted">2. Excel</span>
                        <span class="mx-2 text-muted">|</span>
                        <span class="{{ !empty($xmlDisponible) ? 'font-weight-bold text-success' : 'font-weight-bold' }}">3. XML &rarr; ZIP en su PC</span>
                        @if (!empty($xmlDisponible))
                            <span class="ml-2 badge badge-success">{{ $xmlCantidad }} XML</span>
                        @endif
                    </div>
                </div>
                @if (!empty($xmlDisponible))
                    <p class="text-center small text-muted mb-3">
                        @if (!empty($xmlRecienGenerado))
                            Descarga del ZIP iniciada en su PC.
                        @else
                            XML generados para esta consulta.
                        @endif
                        <a href="{{ $urlDescargaXmlZip }}" class="font-weight-bold ml-1">Descargar ZIP</a>
                    </p>
                @endif
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Número de doc.</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Pais</th>
                            <th class="width10">Teléfono</th>
                            <th class="width10">Email</th>
                            <th>Monto Premio</th>
                            <th>Fecha Entrega</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cliente_premio_uifs as $data)
                        <tr>
                            <td>{{ $data->premioid }}</td>
                            <td>{{ $data->nombrecliente }}</td>
                            <td>{{ $data->abreviaturatipodocumento }}</td>
                            <td><small>{{ $data->numerodocumento }}</small></td>
                            <td><small>{{ $data->domicilio }}</small></td>
                            <td><small>{{ $data->nombrelocalidad ?? '' }}</small></td>
                            <td><small>{{ $data->nombreprovincia ?? '' }}</small></td>
                            <td><small>{{ $data->nombrepais ?? '' }}</small></td>
                            <td><small>{{ $data->telefono }}</small></td>
                            <td><small>{{ $data->email }}</small></td>
                            <td><small>{{ number_format($data->monto, 2, ',', '.') }}</small></td>
                            <td><small>{{ \App\Support\Uif\ClienteUifInformeReportablesSupport::fechaInforme($data->fechaentrega ?? null) }}</small></td>
                            <td>
                                @if (can('editar-cliente-uif', false))
                                    <a href="{{ route('edita_cliente_premio_uif', ['id' => $data->premioid]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted">No hay premios reportables para la empresa, periodo y monto indicados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
