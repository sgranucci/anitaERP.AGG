@extends("theme.$theme.layout")
@section('titulo')
    Certificado {{ $data->etiqueta }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Certificado {{ $data->etiqueta }}</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_certificado_sanitario')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                    <a href="{{ route('pdf_certificado_sanitario', ['id' => $data->id]) }}"
                        class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf-o"></i> Solicitud PDF
                    </a>
                    @if ($data->xml_frio)
                    <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'S']) }}"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-download"></i> ZIP fr&iacute;o
                    </a>
                    <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'S', 'ver' => 1]) }}"
                        class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        Ver XML fr&iacute;o
                    </a>
                    @endif
                    @if ($data->xml_sin_frio)
                    <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'N']) }}"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-download"></i> ZIP sin fr&iacute;o
                    </a>
                    <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'N', 'ver' => 1]) }}"
                        class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        Ver XML sin fr&iacute;o
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @php
                    $kilosTotal = (float) $data->articulos->sum('cantidad');
                    $cajasTotal = (float) $data->articulos->sum('cajas');
                    $piezasTotal = (float) $data->articulos->sum('piezas');
                @endphp
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Fecha:</strong> {{ optional($data->fecha)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Cami&oacute;n:</strong> {{ $data->camion->dominio ?? '' }} ({{ $data->camion->habilitacion ?? '' }})</div>
                    <div class="col-md-4"><strong>Temp.:</strong> {{ $data->temperatura }} · <strong>Precinto:</strong> {{ $data->precinto }}</div>
                    @if ($data->nro_cert_interno)
                    <div class="col-md-4"><strong>Nro. interno Anita:</strong> {{ $data->nro_cert_interno }}</div>
                    @endif
                    @if ($data->nro_cert_patagonico)
                    <div class="col-md-4"><strong>Nro. patag&oacute;nico Anita:</strong> {{ $data->nro_cert_patagonico }}</div>
                    @endif
                    @if ($data->establecimiento_destino)
                    <div class="col-md-4"><strong>Establ. destino:</strong> {{ $data->establecimiento_destino }}</div>
                    @endif
                    @if ($data->transporte)
                    <div class="col-md-4"><strong>Reparto:</strong> {{ $data->transporte->codigo }} {{ $data->transporte->nombre }}</div>
                    @endif
                    <div class="col-md-4">
                        <strong>Totales:</strong>
                        {{ number_format($kilosTotal, 2, ',', '.') }} kg
                        · {{ number_format($cajasTotal, 2, ',', '.') }} cajas
                        · {{ number_format($piezasTotal, 2, ',', '.') }} piezas
                    </div>
                </div>

                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2"><strong>Art&iacute;culos</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>L&iacute;nea</th>
                                    <th class="text-nowrap">SKU</th>
                                    <th>Art&iacute;culo</th>
                                    <th class="text-right">Kilos</th>
                                    <th class="text-right">Cajas</th>
                                    <th class="text-right">Piezas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data->articulos as $a)
                                <tr>
                                    <td>{{ $a->linea }}</td>
                                    <td class="text-nowrap">{{ $a->sku }}</td>
                                    <td>{{ optional($a->articulo)->descripcion ?? optional($a->articulo)->nombre }}</td>
                                    <td class="text-right text-nowrap">{{ number_format($a->cantidad, 2, ',', '.') }}</td>
                                    <td class="text-right text-nowrap">{{ number_format($a->cajas, 2, ',', '.') }}</td>
                                    <td class="text-right text-nowrap">{{ number_format((float) ($a->piezas ?? 0), 2, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted">Sin art&iacute;culos.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($data->articulos->isNotEmpty())
                            <tfoot>
                                <tr class="font-weight-bold" style="background-color:#d6eaf8;">
                                    <td colspan="3">TOTAL</td>
                                    <td class="text-right">{{ number_format($kilosTotal, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($cajasTotal, 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format($piezasTotal, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2"><strong>Clientes</strong></div>
                    <div class="card-body py-2">
                        @forelse($data->clientes as $c)
                            <div>{{ $c->codigo_cliente }} {{ optional($c->cliente)->nombre }}</div>
                        @empty
                            <div class="text-muted">Sin clientes.</div>
                        @endforelse
                    </div>
                </div>

                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2"><strong>Destinos</strong></div>
                    <div class="card-body py-2">
                        @forelse($data->destinos as $d)
                            <div>
                                {{ $d->localidad }} - {{ $d->provincia }}
                                @if ($d->patagonico)
                                    <span class="badge badge-info">patag&oacute;nico</span>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted">Sin destinos.</div>
                        @endforelse
                    </div>
                </div>

                @if (! empty($xmls['frio']) || ! empty($xmls['sin_frio']))
                <div class="card card-outline card-info">
                    <div class="card-header py-2"><strong>XML SENASA</strong></div>
                    <div class="card-body">
                        @if (! empty($xmls['frio']))
                        <p class="mb-1"><strong>Fr&iacute;o</strong></p>
                        <pre class="bg-light border p-2" style="max-height: 360px; overflow: auto; white-space: pre-wrap;">{{ $xmls['frio'] }}</pre>
                        @endif
                        @if (! empty($xmls['sin_frio']))
                        <p class="mb-1"><strong>Sin fr&iacute;o</strong></p>
                        <pre class="bg-light border p-2" style="max-height: 360px; overflow: auto; white-space: pre-wrap;">{{ $xmls['sin_frio'] }}</pre>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
