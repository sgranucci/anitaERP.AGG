@extends("theme.$theme.layout")
@section('titulo')
    Cola de facturación de abonos
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}" type="text/javascript"></script>
<script>
(function ($) {
    'use strict';
    $(function () {
        var $form = $('#form-cola-contrato-venta');
        if (!$form.length) {
            return;
        }
        $('#btn-prefill-seleccionados').on('click', function () {
            var ids = [];
            $('.cv-cola-check:checked').each(function () {
                ids.push(parseInt($(this).val(), 10));
            });
            if (!ids.length) {
                alert('Marque al menos un abono.');
                return;
            }
            $.ajax({
                url: carpetaBase + '/ventas/contrato-venta-cola/prefill-batch',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    _token: $form.find('input[name="_token"]').val() || $('input[name="_token"]').first().val(),
                    fecha: $('#fecha').val(),
                    contrato_ids: ids
                }
            }).done(function (resp) {
                if (!resp || !resp.ok) {
                    alert('No se pudo armar el prefill.');
                    return;
                }
                try {
                    sessionStorage.setItem('anita_contrato_venta_prefill_batch', JSON.stringify(resp));
                } catch (e) {}
                if (confirm('Prefill listo: ' + (resp.cantidad || 0) + ' línea(s). ¿Abrir facturación mostrador?')) {
                    window.location.href = carpetaBase + '/ventas/factura/crear?origen=cola_abonos';
                }
            }).fail(function () {
                alert('Error al solicitar prefill.');
            });
        });
        $('#cv-cola-check-all').on('change', function () {
            $('.cv-cola-check').prop('checked', $(this).is(':checked'));
        });
    });
})(jQuery);
</script>
@endsection

@section('contenido')
@php
    $clienteSel = null;
    if (!empty($cliente_id)) {
        $clienteSel = \App\Models\Ventas\Cliente::query()->find($cliente_id);
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cola de facturación de abonos</h3>
            </div>
            <form method="get" action="{{ route('contrato_venta_cola') }}" id="form-cola-contrato-venta" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            @include('includes.form-empresa-asignada-control', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $empresa_id,
                                'required' => false,
                                'permite_vacio' => true,
                                'opcion_vacia' => 'Todas',
                            ])
                        </div>
                        <div class="form-group col-md-4 tm-cliente-campo">
                            <label class="small mb-1">Cliente</label>
                            <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                                <input type="hidden" class="cliente_id" name="cliente_id" id="cliente_id"
                                    value="{{ $cliente_id ?? '' }}">
                                <button type="button" class="btn-accion-tabla consultacliente flex-shrink-0" title="Consulta clientes (F1)">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm codigocliente" id="codigocliente"
                                    value="{{ $clienteSel->codigo ?? '' }}" placeholder="Cód." style="width:5.5rem;">
                                <input type="text" class="form-control form-control-sm nombrecliente" id="nombrecliente"
                                    value="{{ $clienteSel->nombre ?? '' }}" placeholder="Todos" readonly>
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="fecha">Fecha facturación</label>
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" value="{{ $fecha }}" required>
                        </div>
                        <div class="form-group col-md-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            @if ($consultar)
            <div class="card-body table-responsive p-0 border-top">
                <div class="px-2 py-2">
                    @if ($puede_facturar ?? false)
                        <button type="button" id="btn-prefill-seleccionados" class="btn btn-outline-success btn-sm">
                            Prefill seleccionados (JSON)
                        </button>
                    @endif
                    <span class="text-muted small ml-2">Abonos vigentes sin período facturado para la fecha.</span>
                </div>
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">
                                <input type="checkbox" id="cv-cola-check-all" title="Marcar todos">
                            </th>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Concepto</th>
                            <th>Período</th>
                            <th>Precio</th>
                            <th>Empresa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                            @php
                                $periodo = $periodosPorContrato[$data->id] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" class="cv-cola-check" value="{{ $data->id }}">
                                </td>
                                <td>{{ $data->codigo }}</td>
                                <td>{{ $data->cliente->nombre ?? '' }}</td>
                                <td>{{ $data->conceptoVenta->codigo ?? '' }} — {{ $data->conceptoVenta->nombre ?? '' }}</td>
                                <td>{{ $periodo['etiqueta'] ?? '' }}</td>
                                <td class="text-right">{{ $data->precio !== null ? number_format((float) $data->precio, 2, ',', '.') : '' }}</td>
                                <td>{{ $data->empresa->nombre ?? '' }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-contratos-venta', false))
                                        <a href="{{ route('editar_contrato_venta', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Abrir abono" target="_blank" rel="noopener">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('prefill_contrato_venta', ['contrato_id' => $data->id, 'fecha' => $fecha]) }}"
                                       class="btn-accion-tabla tooltipsC text-primary" title="Prefill JSON" target="_blank" rel="noopener">
                                        <i class="fa fa-file-invoice"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-muted">Sin abonos pendientes para los filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultacliente')
@endsection
