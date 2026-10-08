@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
<input type="hidden" name="ordentrabajo_ids[]" class="ordentrabajo_id" value="{{ $otIdLinea ?? '' }}">
<input type="hidden" name="pedido_combinacion_ids[]" class="pedido_combinacion_id" value="{{ $pedidoCombinacionIdLinea ?? '' }}">
<input type="hidden" name="ot_grupo_indices[]" class="ot_grupo_indice" value="{{ $otGrupoIndiceLinea ?? '' }}">
<input type="hidden" name="picking_pedido_combinacion_ids[]" class="picking_pedido_combinacion_id" value="{{ $pickingPedidoCombinacionIdLinea ?? '' }}">
<input type="hidden" name="picking_grupo_indices[]" class="picking_grupo_indice" value="{{ $pickingGrupoIndiceLinea ?? '' }}">
@endif
