@extends("theme.$theme.layout")
@section('titulo')
    @if (! empty($modo_surmar))
        Movimientos Surmar
    @else
        Movimientos de Stock
    @endif
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/revertir.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/revertir.js')) ?: time() }}" type="text/javascript"></script>
@php $surmarImprimir = session('surmar_imprimir_etiquetas', []); @endphp
@if (is_array($surmarImprimir) && count($surmarImprimir))
<script>
(function ($) {
    var ids = @json(array_values(array_map('intval', $surmarImprimir)));
    var url = @json(route('movimientostock_zpl_etiquetas_surmar'));
    function enviarZpl(zpl) {
        if (!zpl) return;
        if (window.BrowserPrint) {
            try {
                BrowserPrint.getDefaultDevice('printer', function (device) {
                    if (device) device.send(zpl);
                    else descargar(zpl);
                }, function () { descargar(zpl); });
                return;
            } catch (e) {}
        }
        descargar(zpl);
    }
    function descargar(zpl) {
        var blob = new Blob([zpl], { type: 'text/plain' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'etiqueta_surmar.zpl';
        a.click();
    }
    $(function () {
        $.ajax({
            url: url,
            method: 'POST',
            data: { _token: $('meta[name="csrf-token"]').attr('content'), ids: ids },
            success: function (resp) {
                if (!resp || !resp.ok || !resp.etiquetas) return;
                resp.etiquetas.forEach(function (e) { enviarZpl(e.zpl); });
            }
        });
    });
})(jQuery);
</script>
@endif
@endsection

<?php use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Stock\MovimientoStockListadoFiltros;
use App\Support\Stock\Surmar\MovimientoSurmarPermisoSupport;

$modoSurmar = ! empty($modo_surmar);
$rutaIndex = $ruta_index_movimientostock ?? ($modoSurmar ? 'movimiento_surmar' : 'movimientostock');
$rutaCrear = $ruta_crear_movimientostock ?? ($modoSurmar ? 'crear_movimiento_surmar' : 'crear_movimientostock');
$rutaLista = $ruta_lista_movimientostock ?? ($modoSurmar ? 'lista_movimiento_surmar' : 'lista_movimientostock');
$nuevoCan = can('crear-movimientos-de-stock', false)
    ? 'crear-movimientos-de-stock'
    : (can('crear-movimiento-surmar', false) ? 'crear-movimiento-surmar' : 'crear-movimientos-de-stock');
?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($modoSurmar)
                        <i class="fa fa-exchange"></i> Movimientos Surmar
                    @else
                        Movimientos de Stock
                    @endif
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.stock.boton-manual-recepcion-movstock')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-movimientostock',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => MovimientoStockListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => route($rutaIndex, MovimientoStockListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                        'placeholder' => 'Búsqueda rápida (movimientos y transferencias)…',
                        'toggleTarget' => '#panel-filtros-movimientostock',
                        'toggleId' => 'btn-toggle-filtros-movimientostock',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route($rutaCrear),
                        'nuevoRegistroCan' => $nuevoCan,
                    ])
                </div>
            </div>
            <form method="get" action="{{ route($rutaIndex) }}" id="form-filtros-movimientostock" class="mb-0">
                @include('stock.movimientostock.partials.filtros_listado')
            </form>
            @include('stock.movimientostock.partials.filtros_externos')
            @if(!empty($alcance_centro_costo))
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-filter"></i>
                    Listado limitado (movimientos de usuarios de su centro de costo): <strong>{{ $alcance_centro_costo }}</strong>
                </div>
            @endif
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => $rutaLista,
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th>Tipo de transacci&oacute;n</th>
                            <th>N&uacute;mero</th>
                            @if (MovimientoStockFerliSupport::esCalzadosFerli())
                                <th>Marca</th>
                            @endif
                            <th>Lote</th>
                            <th title="Transferencia: dep&oacute;sito o bien de origen. Movimiento: no aplica.">Dep. origen</th>
                            <th title="Transferencia: dep&oacute;sito o bien destino. Movimiento: dep&oacute;sito (c&oacute;digo &mdash; nombre).">Dep. destino</th>
                            <th>Empresa</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right" title="Producto de venta (SKU V…): lista 5000+mes. Resto: precio de última compra.">Costo</th>
                            <th class="text-center">&Iacute;tems</th>
                            <th>Estado</th>
                            <th class="width120" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('stock.movimientostock.partials.tabla_datos', [
                            'datas' => $datas,
                            'estado_enum' => $estado_enum,
                            'puede_editar' => MovimientoSurmarPermisoSupport::puedeEditar(false),
                            'puede_borrar' => MovimientoSurmarPermisoSupport::puedeAnular(false),
                            'puede_revertir' => MovimientoSurmarPermisoSupport::puedeRevertir(false),
                            'puede_listar' => MovimientoSurmarPermisoSupport::puedeListar(false),
                        ])
                    </tbody>
                </table>
            </div>
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
