@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Stock
@endsection

@section("scripts")
<script>
    window.movimientoStockModoFerli = @json($movimientoStockModoFerli ?? false);
    window.movimientoStockPreviewConversionFormulaUrl = @json(route('preview_conversion_formula_movimientostock'));
    window.movimientoStockSaldoOrigenUrl = @json(route('movimientostock_saldo_articulo'));
    window.movimientoStockPrecioLineaUrl = @json(route('movimientostock_precio_linea'));
    window.movimientoStockSugerirTipoTransferenciaContableUrl = @json(route('movimientostock_sugerir_tipo_transferencia_contable'));
    window.movimientoStockResolverNpuUrl = @json(route('movimientostock_resolver_npu_baja'));
    window.movimientoStockConsultaNpuUrl = @json(route('movimientostock_consulta_npu_baja'));
    window.msColoresOpciones = @json(($color_query ?? collect())->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values());
    window.msTallesOpciones = @json(($talle_query ?? collect())->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre])->values());
    window.MS_TRANSFERENCIA_URLS = {
        destinatarios: @json(route('transferencia_mercaderia_destinatarios')),
        validarDestinatario: @json(route('transferencia_mercaderia_validar_destinatario')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-items.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/form-items.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-color-talle.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/form-color-talle.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-asiento.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-tipo-transaccion.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/tipotransaccion_stock/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/transferencia/aviso-modal.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/transferencia/aviso-modal.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-transferencia.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/form-transferencia.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/atajos-consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-formula-conversion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/form-formula-conversion.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-saldo-origen.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/proceso-overlay.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-baja-npu.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-alta-npu.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/consulta-npu-baja.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-grabando.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/surmar_etiquetas.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/surmar_etiquetas.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/revertir.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/movimientostock/revertir.js')) ?: time() }}" type="text/javascript"></script>
@if(\App\Support\Stock\MovimientosArticuloDepositoSupport::puedeConsultar())
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/form-kardex-linea.js') }}" type="text/javascript"></script>
@endif

<script>
	function sub()
	{
        $('#formgeneral').submit();
    }
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @if (! ($puedeModificarVentana ?? true))
            <div class="alert alert-warning">
                <i class="fa fa-lock"></i>
                {{ \App\Support\Stock\MovimientoStockEdicionVentanaSupport::mensajeBloqueo() }}
            </div>
        @endif
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if (! empty($modo_surmar))
                        Editar Movimiento Surmar
                    @else
                        Editar Movimiento de Stock
                    @endif
                </h3>
				&nbsp;- ID: {{ $movimientostock->id }} - Movimiento: {{$movimientostock->codigo}}
                <div class="card-tools">
                    @include('stock.movimientostock.partials.boton_imprimir_com_pdf', [
                        'movimientoStockId' => $movimientostock->id,
                    ])
                    @if (\App\Support\Stock\Surmar\MovimientoSurmarPermisoSupport::puedeRevertir(false)
                        && ($movimientostock->estado ?? 'A') === 'A'
                        && ! ($movimientostock->movimientostock_revertido_por_id ?? null)
                        && ! ($movimientostock->movimientostock_origen_id ?? null)
                        && ! ($transferenciaVinculada ?? null))
                        <form action="{{ route('revertir_movimientostock', ['id' => $movimientostock->id]) }}" class="d-inline form-revertir-movstock" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-info btn-sm">
                                <i class="fa fa-undo"></i> Revertir
                            </button>
                        </form>
                    @endif
                    <a href="{{ route($ruta_index_movimientostock ?? 'movimientostock') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route($ruta_actualizar_movimientostock ?? 'actualizar_movimientostock', ['id' => $movimientostock->id]) }}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off"
                data-preview-url="{{ route('preview_asiento_movimientostock', ['id' => $movimientostock->id]) }}"
                data-tiene-asiento-grabado="{{ (int) ($movimientostock->asiento_id ?? 0) > 0 ? '1' : '0' }}">
                @csrf @method("put")
                @if (! empty($modo_surmar))
                    <input type="hidden" name="modo_surmar" value="1">
                @endif
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$movimientostock->codigo}}" >
        			<input type="hidden" id="movimientostockid" name="movimientostockid" value="{{$movimientostock->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('stock.movimientostock.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							@if ($puedeModificarVentana ?? true)
							    <button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
							@endif
                            @include('stock.movimientostock.partials.boton_imprimir_com_pdf', [
                                'movimientoStockId' => $movimientostock->id,
                            ])
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.proceso-overlay-pedido')
@include('includes.admin.modalconsultausuario')
@endsection
