@php
    $dataEntrega = $data ?? null;
    $entregasCliente = collect(optional($dataEntrega)->cliente_entregas ?? []);
    if (old('nombres') !== null && is_array(old('nombres'))) {
        $entregasForm = collect(old('nombres'))->values();
    } else {
        $entregasForm = $entregasCliente->count() ? $entregasCliente : collect();
    }
@endphp
<style>
    #tab-lugares-entrega #tabla-entregas-resumen thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    #tab-lugares-entrega #tabla-entregas-resumen tbody tr.entrega-resumen {
        cursor: pointer;
    }
    #tab-lugares-entrega #tabla-entregas-resumen tbody tr.entrega-resumen.activa {
        background: #D6EAF8;
        color: #1B4F72;
        font-weight: 600;
    }
    #tab-lugares-entrega #tabla-entregas-resumen tbody tr.entrega-resumen:hover {
        background: #EAF2F8;
    }
    #tab-lugares-entrega #cuotas-table {
        margin-bottom: 0;
    }
    #tab-lugares-entrega #tbody-tabla > tr.item-entrega {
        display: none;
    }
    #tab-lugares-entrega #tbody-tabla > tr.item-entrega.activa {
        display: table-row;
    }
    #tab-lugares-entrega #cuotas-table > tbody > tr > td {
        padding: 0;
        border: 0;
        vertical-align: top;
    }
</style>
<div id="tab-lugares-entrega" class="tab-pane fade card form3" role="tabpanel">
    <div class="card-body">
        <div class="form-group row">
            <label for="lugarentrega" class="col-lg-3 control-label text-right pr-2">Lugar de entrega (texto)</label>
            <div class="col-lg-6">
                <input type="text" name="lugarentrega" id="lugarentrega" class="form-control"
                    value="{{ old('lugarentrega', optional($dataEntrega)->lugarentrega ?? '') }}"
                    placeholder="Leyenda libre; no reemplaza los destinos de abajo">
                <small class="form-text text-muted">
                    Pedido y remito usan el destino elegido en la lista. Este texto es una leyenda de cabecera.
                </small>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-3 mb-lg-0">
                <div class="card card-outline card-info mb-0 h-100">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">Destinos</h3>
                        <button type="button" id="agrega_renglon" class="btn btn-outline-primary btn-sm">
                            + Agrega renglón
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="entregas-vacio" class="text-center text-muted py-4 px-3"
                            @if ($entregasForm->isNotEmpty())
                                style="display: none;"
                            @endif
                        >
                            No hay lugares de entrega. Agregá uno para cargarlo a la derecha.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" id="tabla-entregas-resumen"
                                @if ($entregasForm->isEmpty())
                                    style="display: none;"
                                @endif
                            >
                                <thead>
                                    <tr>
                                        <th style="width: 12%;">#</th>
                                        <th>Nombre</th>
                                        <th>Localidad</th>
                                        <th style="width: 12%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-entregas-resumen">
                                    @foreach ($entregasForm as $entrega)
                                        @php
                                            $nomResumen = is_object($entrega)
                                                ? trim((string) ($entrega->nombre ?? ''))
                                                : trim((string) (old('nombres.'.$loop->index) ?? ''));
                                            $locResumen = is_object($entrega)
                                                ? trim((string) (optional($entrega->localidades)->nombre ?? ''))
                                                : '';
                                        @endphp
                                        <tr class="entrega-resumen{{ $loop->first ? ' activa' : '' }}" data-entrega-idx="{{ $loop->index }}">
                                            <td class="entrega-resumen-nro text-center">{{ $loop->iteration }}</td>
                                            <td class="entrega-resumen-nombre">{{ $nomResumen !== '' ? $nomResumen : '(sin nombre)' }}</td>
                                            <td class="entrega-resumen-localidad">{{ $locResumen !== '' ? $locResumen : '—' }}</td>
                                            <td class="text-nowrap text-center">
                                                <button type="button" title="Quitar este lugar" class="btn-accion-tabla eliminar tooltipsC">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card card-outline card-secondary mb-0" id="panel-entrega-detalle">
                    <div class="card-header py-2">
                        <h3 class="card-title mb-0" id="entrega-detalle-titulo">
                            @php
                                $tituloDetalle = 'Datos del lugar';
                                $primera = $entregasForm->first();
                                if (is_object($primera) && trim((string) ($primera->nombre ?? '')) !== '') {
                                    $tituloDetalle = $primera->nombre;
                                } elseif (is_string($primera) && trim($primera) !== '') {
                                    $tituloDetalle = $primera;
                                }
                            @endphp
                            {{ $tituloDetalle }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="entrega-detalle-vacio" class="text-center text-muted py-4"
                            @if ($entregasForm->isNotEmpty())
                                style="display: none;"
                            @endif
                        >
                            Elegí un destino a la izquierda para ver y editar todos los datos.
                        </div>
                        <div id="panel-entrega-detalle-campos"
                            @if ($entregasForm->isEmpty())
                                style="display: none;"
                            @endif
                        >
                            <table class="table table-borderless" id="cuotas-table">
                                <tbody id="tbody-tabla">
                                    @foreach ($entregasForm as $entrega)
                                        @include('ventas.cliente.partials.renglon_entrega', [
                                            'entrega' => is_object($entrega) ? $entrega : null,
                                            'idx' => $loop->index,
                                            'esTemplate' => false,
                                            'activa' => $loop->first,
                                        ])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('ventas.cliente.template3')
    </div>
</div>
