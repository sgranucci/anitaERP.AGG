@extends("theme.$theme.layout")

@section('titulo')
    Listado canjes marketing
@endsection

@section('scripts')
<script>
    window.CANJE_MARKETING_LISTADO = {
        urlIndex: @json(route('canje_marketing_listado')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/canjes/listado_marketing_filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/canjes/listado_marketing_filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Listado canjes marketing</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('canje_marketing_listado') }}" class="btn btn-outline-secondary btn-sm ml-1" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('canje_marketing_listado') }}" id="form-filtros-canje-marketing" class="mb-0">
                @include('ventas.gastronomia.canjes.listado_marketing.partials.filtros_listado')
            </form>
            <div class="card-body p-0">
                @php $tot = $totales ?? []; @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'lista_canje_marketing_gastronomia',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small mb-1 mb-md-0 text-md-right">
                        <span class="text-muted">Totales filtro:</span>
                        <strong>{{ (int) ($tot['cantidad_filas'] ?? 0) }}</strong> filas
                        · Cant.
                        <strong>{{ number_format((float) ($tot['cantidad_total'] ?? 0), 3, ',', '.') }}</strong>
                        · CMV ({{ $listaprecio_cmv_etiqueta ?? 'cód. 50' }})
                        <strong>${{ number_format((float) ($tot['cmv_total'] ?? 0), 2, ',', '.') }}</strong>
                        · P. venta
                        <strong>${{ number_format((float) ($tot['precio_venta_total'] ?? 0), 2, ',', '.') }}</strong>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Empresa</th>
                                <th>Id VIP</th>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>Nickname</th>
                                <th>Mozo</th>
                                <th>Producto</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right" title="Lista {{ $listaprecio_cmv_etiqueta ?? 'cód. 50' }}">CMV</th>
                                <th class="text-right">P. venta</th>
                                <th>Sala</th>
                                <th class="width100" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $f)
                                <tr>
                                    <td>{{ $f->fechacanje_fmt ?? '—' }}</td>
                                    <td>{{ $f->nombreempresa ?? '—' }}</td>
                                    <td>
                                        @if (($puede_ver_cliente_vip ?? false) && (int) ($f->cliente_vip_gastronomia_id ?? 0) > 0)
                                            <a href="{{ route('editar_cliente_vip_gastronomia', ['id' => $f->cliente_vip_gastronomia_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               target="_blank" rel="noopener" class="text-primary">
                                                {{ $f->numeroid_vip ?? '—' }}
                                            </a>
                                        @else
                                            {{ $f->numeroid_vip ?? '—' }}
                                        @endif
                                    </td>
                                    <td>{{ $f->nombre_vip ?? '—' }}</td>
                                    <td>{{ $f->apellido_vip ?? '—' }}</td>
                                    <td>{{ $f->nickname ?? '' }}</td>
                                    <td>
                                        @if (($puede_ver_mozo ?? false) && (int) ($f->mozo_gastronomia_id ?? 0) > 0)
                                            <a href="{{ route('editar_mozo_gastronomia', ['id' => $f->mozo_gastronomia_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               target="_blank" rel="noopener" class="text-primary">
                                                {{ $f->mozo_etiqueta !== '' ? $f->mozo_etiqueta : '—' }}
                                            </a>
                                        @else
                                            {{ $f->mozo_etiqueta !== '' ? $f->mozo_etiqueta : '—' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if (($puede_ver_articulo ?? false) && (int) ($f->articulo_id ?? 0) > 0)
                                            <a href="{{ route('editar_articulo', ['id' => $f->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               target="_blank" rel="noopener" class="text-primary">
                                                {{ $f->producto ?? '—' }}
                                            </a>
                                        @else
                                            {{ $f->producto ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((float) ($f->cantidad ?? 0), 3, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($f->cmv ?? 0), 2, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float) ($f->precio_venta ?? 0), 2, ',', '.') }}</td>
                                    <td>{{ $f->sala !== '' ? $f->sala : '—' }}</td>
                                    <td class="text-nowrap">
                                        @if (($puede_ver_factura ?? false) && (int) ($f->venta_id ?? 0) > 0)
                                            <a href="{{ route('gastronomia_facturas_dia_ver', ['ventaId' => $f->venta_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               class="btn btn-info btn-sm" target="_blank" rel="noopener" title="Ver comprobante">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center text-muted py-4">
                                        No hay canjes marketing en el período y filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                        <span class="small text-muted mb-2 mb-md-0">
                            @if ($filas->total() > 0)
                                Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} registros
                            @else
                                Sin registros
                            @endif
                        </span>
                        {{ $filas->appends($filtrosQuery ?? [])->links() }}
                    </div>
                @endif
            </div>
        </div>
        <p class="small text-muted mt-2">
            CMV provisorio desde lista de precios <strong>{{ $listaprecio_cmv_etiqueta ?? 'cód. 50' }}</strong> vigente a la fecha del canje.
        </p>
    </div>
</div>
@endsection
