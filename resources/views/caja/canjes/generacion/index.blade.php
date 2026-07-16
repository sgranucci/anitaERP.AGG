@extends("theme.$theme.layout")
@section('titulo')
    Generación de tickets canje
@endsection

@section('scripts')
@php
    $ctxScript = $contexto ?? [];
    $puedeOperarScript = ! empty($ctxScript['jornada_abierta'])
        && ! empty($ctxScript['fecha_jornada']);
@endphp
<script>
window.TICKET_CANJE_CAJA = {
    csrfToken: @json(csrf_token()),
    porcentaje: @json($porcentaje_ticket ?? 5),
    empresaId: @json((int) ($empresa_id ?? 0)),
    puedeCrear: @json((bool) ($puede_crear ?? false)),
    puedeReimprimir: @json((bool) ($puede_reimprimir ?? false)),
    puedeOperar: @json($puedeOperarScript),
    rutas: {
        contexto: @json(route('api_ticket_canje_caja_contexto')),
        resolverCliente: @json(route('api_ticket_canje_caja_resolver_cliente')),
        preview: @json(route('api_ticket_canje_caja_preview')),
        emitir: @json(route('api_ticket_canje_caja_emitir')),
        consultaVip: @json(route('api_ticket_canje_caja_consulta_vip')),
        reimprimirBase: @json(url('caja/canjes/generacion')),
        index: @json(route('ticket_canje_caja')),
    },
};
</script>
<style>
.tcc-cabecera .form-group { margin-bottom: 0.75rem; }
.tcc-cabecera .form-control { height: calc(2.25rem + 2px); }
.tcc-cliente-linea { display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem; }
.tcc-doc-wrap { max-width: 220px; flex: 0 0 220px; }
.tcc-cliente-nombre {
    font-size: 1.35rem;
    font-weight: 600;
    line-height: 1.2;
    color: #1b4f72;
    min-height: 1.6rem;
}
.tcc-cliente-nombre .badge { font-size: 0.75rem; vertical-align: middle; }
</style>
<script src="{{ asset('assets/pages/scripts/caja/canjes/generacion.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $ctx = $contexto ?? [];
    $puedeOperar = ! empty($ctx['jornada_abierta'])
        && ! empty($ctx['fecha_jornada']);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info mb-3">
            <div class="card-header">
                <h3 class="card-title">Generación de tickets canje</h3>
            </div>
            <div class="card-body">
                <div class="form-row align-items-end tcc-cabecera">
                    <div class="form-group col-md-4">
                        <label for="empresa_id">Empresa</label>
                        @include('includes.form-empresa-asignada-control', [
                            'empresa_query' => $empresa_query,
                            'empresa_id' => $empresa_id ?? null,
                            'solo_lectura' => false,
                        ])
                    </div>
                    <div class="form-group col-md-3">
                        <label for="fecha_jornada_fmt">Fecha jornada</label>
                        <input type="text" class="form-control" id="fecha_jornada_fmt" readonly
                               value="{{ $ctx['fecha_jornada_fmt'] ?? '—' }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="cajero_nombre">Cajero asignado</label>
                        <input type="text" class="form-control" id="cajero_nombre" readonly
                               value="{{ $ctx['cajero_nombre'] ?? '—' }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Estado</label>
                        <div id="estado-operativo" class="pt-1">
                            @if ($puedeOperar)
                                <span class="badge badge-success">Listo para emitir</span>
                            @else
                                <span class="badge badge-danger">Sin jornada abierta</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="tcc-aviso-bloqueo" class="alert alert-warning mt-2 mb-0 @if ($puedeOperar) d-none @endif">
                    Debe abrir la jornada de estacionamiento para esta empresa antes de emitir tickets.
                </div>

                @if ($puede_crear ?? false)
                <hr>
                <fieldset id="tcc-fieldset-emision" @if (! $puedeOperar) disabled @endif>
                    <div class="form-row align-items-end">
                        <div class="form-group col-lg-5 col-md-12">
                            <label for="nro_documento">
                                Documento
                                <span class="text-danger">*</span>
                            </label>
                            <div class="tcc-cliente-linea">
                                <div class="input-group tcc-doc-wrap">
                                    <input type="hidden" id="cliente_vip_caja_id" value="">
                                    <input type="text" class="form-control" id="nro_documento" name="nro_documento"
                                           placeholder="DNI / documento" autocomplete="off" autofocus>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary consultaclientevip"
                                                title="Consultar clientes VIP de la empresa"
                                                id="btn-consulta-vip">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="cliente_vip_nombre_txt" class="tcc-cliente-nombre text-muted flex-grow-1"></div>
                            </div>
                            <input type="hidden" id="es_vip" value="0">
                        </div>
                        <div class="form-group col-lg-3 col-md-4">
                            <label for="monto_venta">
                                Monto venta
                                <span class="text-danger">*</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" class="form-control text-right"
                                   id="monto_venta" name="monto_venta" autocomplete="off">
                        </div>
                        <div class="form-group col-lg-2 col-md-3">
                            <label for="cantidad">Cantidad tickets</label>
                            <input type="number" min="1" step="1" class="form-control text-right"
                                   id="cantidad" name="cantidad" value="1">
                        </div>
                        <div class="form-group col-lg-2 col-md-5">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block" id="btn-preview-emitir">
                                <i class="fa fa-ticket-alt"></i> Emite Ticket
                            </button>
                        </div>
                    </div>
                </fieldset>
                @endif
            </div>
        </div>

        <div class="card card-secondary">
            <div class="card-header">
                <h3 class="card-title">Mis tickets emitidos</h3>
                <div class="card-tools">
                    <form method="get" action="{{ route('ticket_canje_caja') }}" class="form-inline" id="form-filtros-grilla">
                        <input type="hidden" name="empresa_id" value="{{ (int) ($empresa_id ?? 0) }}">
                        <input type="text" name="filtro_valor" class="form-control form-control-sm mr-1"
                               value="{{ $filtros['filtro_valor'] ?? '' }}" placeholder="Buscar…">
                        <button type="submit" class="btn btn-sm btn-outline-light">Filtrar</button>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Vale</th>
                            <th>Fecha</th>
                            <th>Cliente / documento</th>
                            <th>VIP</th>
                            <th class="text-right">Monto venta</th>
                            <th class="text-right">Monto ticket</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $row)
                            <tr>
                                <td>{{ $row->etiquetaVale() }}</td>
                                <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                                <td>
                                    @if ($row->nombre_cliente)
                                        {{ $row->nombre_cliente }}
                                        <br>
                                    @endif
                                    <span class="text-muted">{{ $row->nro_documento }}</span>
                                </td>
                                <td>
                                    @if ($row->es_vip)
                                        <span class="badge badge-success">VIP</span>
                                    @else
                                        <span class="badge badge-secondary">No VIP</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format((float) $row->monto_venta, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $row->monto_ticket, 2, ',', '.') }}</td>
                                <td>
                                    @if ($row->estado === 'C')
                                        <span class="badge badge-info">Canjeado</span>
                                    @elseif ($row->estado === 'V' || $row->es_vip)
                                        <span class="badge badge-success">VIP</span>
                                    @else
                                        <span class="badge badge-warning">Pendiente</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if (($puede_reimprimir ?? false) && ! $row->es_vip && (float) $row->monto_ticket > 0)
                                        <button type="button" class="btn btn-sm btn-outline-primary js-reimprimir"
                                                data-id="{{ (int) $row->id }}" title="Imprimir nuevamente">
                                            <i class="fa fa-print"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Sin tickets para esta empresa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($datas, 'links'))
            <div class="card-footer clearfix">
                <div class="float-left">
                    @if ($datas->total() > 0)
                        Mostrando {{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }}
                    @endif
                </div>
                <div class="float-right">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@include('caja.canjes.generacion.partials.modal_confirmacion')
@include('includes.ventas.modalconsultaclientevip')
@endsection
