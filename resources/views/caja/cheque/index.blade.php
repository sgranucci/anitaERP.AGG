@extends("theme.$theme.layout")
@section('titulo')
    Cheques
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cheque/filtro.js")}}" type="text/javascript"></script>
@if ($puede_nd_cheque ?? false)
<script>
window.chequeRechazoNdUrls = {
    datos: @json(url('caja/cheque/:id/rechazo-nd')),
    emitir: @json(url('caja/cheque/:id/rechazar-nd'))
};
</script>
<script src="{{asset("assets/pages/scripts/caja/cheque/rechazo_nd.js")}}" type="text/javascript"></script>
@endif
@if ($puede_depositar_cheque ?? false)
<script>
window.chequeDepositoUrls = {
    depositar: @json(url('caja/cheque/:id/depositar')),
    depositarMasivo: @json(route('depositar_masivo_cheque'))
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/deposito.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cheque/deposito.js')) ?: time() }}" type="text/javascript"></script>
@endif
@if ($puede_caucionar_cheque ?? false)
<script>
window.chequeCaucionUrls = {
    caucionar: @json(url('caja/cheque/:id/caucionar')),
    caucionarMasivo: @json(route('caucionar_masivo_cheque')),
    liberar: @json(url('caja/cheque/:id/liberar-caucion'))
};
</script>
<script src="{{asset("assets/pages/scripts/caja/cheque/caucion.js")}}" type="text/javascript"></script>
@endif
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Caja\ChequeListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('cheque', ChequeListadoFiltros::paraQueryStringExternos($filtros ?? []));
    $ordenActual = $filtros['orden'] ?? 'fechapago';
    $ordenDir = $filtros['orden_dir'] ?? 'desc';
    $urlOrden = function (string $col) use ($filtrosQuery, $ordenActual, $ordenDir) {
        $q = $filtrosQuery ?? [];
        $q['orden'] = $col;
        if ($ordenActual === $col) {
            $q['orden_dir'] = $ordenDir === 'asc' ? 'desc' : 'asc';
        } else {
            $q['orden_dir'] = in_array($col, ['numerocheque'], true) ? 'asc' : 'desc';
        }

        return route('cheque', $q);
    };
    $marcaOrden = function (string $col) use ($ordenActual, $ordenDir) {
        if ($ordenActual !== $col) {
            return '';
        }

        return $ordenDir === 'asc' ? ' ↑' : ' ↓';
    };
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cheques</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-cheque', false))
                    <a href="{{ route('importar_cheque') }}" class="btn btn-outline-primary btn-sm mr-2" title="Ingreso masivo">
                        <i class="fa fa-upload"></i> Importar
                    </a>
                    @endif
                    <a href="{{ route('reporte_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Emitidos y recibidos por separado">
                        <i class="fa fa-list-alt"></i> Reporte
                    </a>
                    <a href="{{ route('aging_cheque_cartera') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Aging cartera">
                        <i class="fa fa-hourglass-half"></i> Aging
                    </a>
                    <a href="{{ route('historial_deposito_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Historial boletas de depósito">
                        <i class="fa fa-university"></i> Depósitos
                    </a>
                    <a href="{{ route('conciliacion_deposito_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Conciliación depósitos">
                        <i class="fa fa-balance-scale"></i> Conciliación
                    </a>
                    <a href="{{ route('cashflow_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="Cashflow semanal">
                        <i class="fa fa-calendar"></i> Cashflow
                    </a>
                    <a href="{{ route('echeq_cheque') }}" class="btn btn-outline-secondary btn-sm mr-2" title="eCheq">
                        <i class="fa fa-mobile"></i> eCheq
                    </a>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cheque',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ChequeListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-cheque',
                        'toggleId' => 'btn-toggle-filtros-cheque',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cheque', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-cheque',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cheque') }}" id="form-filtros-cheque" class="mb-0">
                @include('caja.cheque.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('caja.cheque.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cheque',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @if (($puede_depositar_cheque ?? false) || ($puede_caucionar_cheque ?? false))
                            <th class="text-center" style="width:2.2rem;" data-orderable="false">
                                <input type="checkbox" id="cheque-select-all" title="Seleccionar página" />
                            </th>
                            @endif
                            <th style="width:3.5rem;"><a href="{{ $urlOrden('id') }}" class="text-dark">ID{{ $marcaOrden('id') }}</a></th>
                            <th><a href="{{ $urlOrden('numerocheque') }}" class="text-dark">Número{{ $marcaOrden('numerocheque') }}</a></th>
                            <th style="width:5rem;">Int.</th>
                            <th style="width:5.5rem;">Origen</th>
                            <th style="width:4.5rem;" title="Físico / e-cheq">Tipo</th>
                            <th>Estado</th>
                            <th style="width:6.5rem;"><a href="{{ $urlOrden('fechaemision') }}" class="text-dark">Emisión{{ $marcaOrden('fechaemision') }}</a></th>
                            <th style="width:6.5rem;"><a href="{{ $urlOrden('fechapago') }}" class="text-dark">Pago{{ $marcaOrden('fechapago') }}</a></th>
                            <th>Banco / Cta</th>
                            @if (($empresa_query ?? collect())->count() > 1)
                            <th>Empresa</th>
                            @endif
                            <th class="text-right" style="width:6.5rem;"><a href="{{ $urlOrden('monto') }}" class="text-dark">Monto{{ $marcaOrden('monto') }}</a></th>
                            <th style="width:2.8rem;" title="Moneda">Mon</th>
                            <th>Beneficiario</th>
                            <th style="width:6rem;" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $origenLabel = collect($origen_enum ?? [])->firstWhere('valor', $data->origen);
                            $estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
                            $enCartera = ($data->origen ?? '') === 'R'
                                && empty($data->pagoproveedor_id)
                                && in_array((string) ($data->estado ?? ' '), [' ', 'N', ''], true);
                            $puedeRechazarNd = ($puede_nd_cheque ?? false)
                                && ($data->origen ?? '') === 'R'
                                && ! in_array((string) ($data->estado ?? ''), ['R', 'A'], true)
                                && empty($data->venta_nd_id)
                                && ! empty($data->cliente_id);
                            $puedeDepositar = ($puede_depositar_cheque ?? false)
                                && ($data->origen ?? '') === 'R'
                                && empty($data->fecha_deposito)
                                && empty($data->pagoproveedor_id)
                                && ! in_array((string) ($data->estado ?? ''), ['R', 'A', '*'], true)
                                && (trim((string) ($data->nro_caucion ?? '')) === '' || trim((string) ($data->nro_caucion ?? '')) === '0')
                                && (
                                    empty($filtros['para_depositar'])
                                    || (
                                        ! empty($data->fechapago)
                                        && (string) $data->fechapago <= (string) ($filtros['para_depositar_hasta'] ?? date('Y-m-d'))
                                    )
                                );
                            $puedeCaucionar = ($puede_caucionar_cheque ?? false)
                                && ($data->origen ?? '') === 'R'
                                && empty($data->fecha_deposito)
                                && empty($data->pagoproveedor_id)
                                && ! in_array((string) ($data->estado ?? ''), ['R', 'A', '*'], true)
                                && (trim((string) ($data->nro_caucion ?? '')) === '' || trim((string) ($data->nro_caucion ?? '')) === '0');
                            $estaCaucionado = trim((string) ($data->nro_caucion ?? '')) !== ''
                                && trim((string) ($data->nro_caucion ?? '')) !== '0';
                        @endphp
                        <tr>
                            @if (($puede_depositar_cheque ?? false) || ($puede_caucionar_cheque ?? false))
                            <td class="text-center">
                                @if ($puedeDepositar || $puedeCaucionar)
                                    <input type="checkbox" class="cheque-select-row" value="{{ $data->id }}"
                                           data-monto="{{ number_format((float) $data->monto, 2, '.', '') }}"
                                           data-moneda="{{ $data->monedas->abreviatura ?? '$' }}"
                                           data-empresa-id="{{ (int) ($data->empresa_id ?? 0) }}" />
                                @endif
                            </td>
                            @endif
                            <td>
                                <a href="{{ route('editar_cheque', ['id' => $data->id] + $retornoListadoQuery) }}" class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
                            </td>
                            <td>{{$data->numerocheque}}</td>
                            <td>{{$data->nro_interno_anita}}</td>
                            <td>
                                @if (($data->origen ?? '') === 'E')
                                    <span class="badge badge-primary">{{ $origenLabel['nombre'] ?? 'Emitido' }}</span>
                                @else
                                    <span class="badge badge-info">{{ $origenLabel['nombre'] ?? 'Recibido' }}</span>
                                @endif
                            </td>
                            <td>
                                @if (strtoupper(trim((string) ($data->negociable ?? ''))) === 'E')
                                    <span class="badge badge-warning">e-cheq</span>
                                @else
                                    <span class="badge badge-secondary">Físico</span>
                                @endif
                            </td>
                            <td>
                                {{ $estadoLabel['nombre'] ?? $data->estado }}
                                @if ($enCartera)
                                    <span class="badge badge-success">Cartera</span>
                                @endif
                                @if ($estaCaucionado)
                                    <span class="badge badge-warning" title="Caución {{ $data->nro_caucion }}">Cauc.</span>
                                @endif
                                @if (!empty($data->fecha_deposito))
                                    <span class="badge badge-secondary">Dep</span>
                                @endif
                                @if (!empty($data->fecha_acreditacion))
                                    <span class="badge badge-success">Acr</span>
                                @endif
                                @if (!empty($data->venta_nd_id))
                                    <a href="{{ route('lista_una_factura', ['id' => $data->venta_nd_id]) }}"
                                       class="badge badge-danger text-white"
                                       target="_blank"
                                       rel="noopener"
                                       title="Ver ND">
                                        ND
                                    </a>
                                @endif
                            </td>
                            <td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
                            <td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
                            <td>
                                @if (($data->origen ?? '') === 'E')
                                    {{$data->cuentacajas->nombre ?? ''}}
                                @else
                                    {{$data->bancos->nombre ?? ''}}
                                @endif
                            </td>
                            @if (($empresa_query ?? collect())->count() > 1)
                            <td>{{$data->empresas->nombre ?? ''}}</td>
                            @endif
                            <td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
                            <td class="text-center small">{{$data->monedas->abreviatura ?? ''}}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($data->entregado ?? $data->anombrede, 28) }}</td>
                            <td>
                       			@if (can('editar-cheque', false))
                                	<a href="{{route('editar_cheque', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                                @if ($puedeDepositar)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-deposito-cheque"
                                            title="Depositar"
                                            data-cheque-id="{{ $data->id }}"
                                            data-cheque-ref="{{ $data->numerocheque }} / {{ $data->bancos->nombre ?? '' }}"
                                            data-cheque-monto="{{ number_format((float) $data->monto, 2, '.', '') }}"
                                            data-cheque-moneda="{{ $data->monedas->abreviatura ?? '$' }}"
                                            data-empresa-id="{{ (int) ($data->empresa_id ?? 0) }}">
                                        <i class="fa fa-university text-primary"></i>
                                    </button>
                                @endif
                                @if (! empty($data->fecha_deposito))
                                    <a href="{{ route('comprobante_deposito_cheque', ['ids' => $data->id]) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="PDF boleta de depósito"
                                       target="_blank" rel="noopener">
                                        <i class="fa fa-file-pdf-o text-danger"></i>
                                    </a>
                                @endif
                                @if ($puedeCaucionar)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-caucion-cheque"
                                            title="Caucionar"
                                            data-cheque-id="{{ $data->id }}"
                                            data-cheque-ref="{{ $data->numerocheque }} / {{ $data->bancos->nombre ?? '' }}">
                                        <i class="fa fa-lock text-warning"></i>
                                    </button>
                                @endif
                                @if ($estaCaucionado && ($puede_caucionar_cheque ?? false) && empty($data->fecha_deposito))
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-liberar-caucion-cheque"
                                            title="Liberar caución {{ $data->nro_caucion }}"
                                            data-cheque-id="{{ $data->id }}">
                                        <i class="fa fa-unlock text-warning"></i>
                                    </button>
                                @endif
                                @if ($puedeRechazarNd)
                                    <button type="button"
                                            class="btn-accion-tabla tooltipsC btn-rechazo-nd-cheque"
                                            title="Rechazar y emitir ND"
                                            data-cheque-id="{{ $data->id }}">
                                        <i class="fa fa-ban text-danger"></i>
                                    </button>
                                @endif
                       			@if (can('borrar-cheque', false))
                                <form action="{{route('eliminar_cheque', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
								@endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@if ($puede_nd_cheque ?? false)
    @include('caja.cheque.modal_rechazo_nd')
@endif
@if ($puede_depositar_cheque ?? false)
    @include('caja.cheque.modal_deposito')
    @include('includes.caja.modalconsultacuentacaja')
@endif
@if ($puede_caucionar_cheque ?? false)
    @include('caja.cheque.modal_caucion')
@endif
@endsection
