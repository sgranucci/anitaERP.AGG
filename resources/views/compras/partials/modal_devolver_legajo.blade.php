{{--
  Modal unificado para devolver un legajo (CxP y/o Compras).
  Params:
    modalId, formId
    urlCxp (nullable), urlCompras (nullable)
    puedeCxp (bool), puedeCompras (bool)
    actionInicial (optional)
    dinamico (bool): si true, deja ambas opciones en el DOM para que el JS las muestre según la fila
--}}
@php
    $modalId = $modalId ?? 'modalDevolverLegajo';
    $formId = $formId ?? 'formDevolverLegajo';
    $puedeCxp = ! empty($puedeCxp);
    $puedeCompras = ! empty($puedeCompras);
    $dinamico = ! empty($dinamico);
    $mostrarSelector = $dinamico ? ($puedeCxp && $puedeCompras) : ($puedeCxp && $puedeCompras && ! empty($urlCxp) && ! empty($urlCompras));
    $soloCxp = ! $mostrarSelector && $puedeCxp && ! empty($urlCxp);
    $soloCompras = ! $mostrarSelector && $puedeCompras && ! empty($urlCompras);
    $defaultDestino = ($mostrarSelector || $soloCxp || ($dinamico && $puedeCxp)) ? 'cxp' : 'compras';
    $actionInicial = $actionInicial
        ?? ($defaultDestino === 'cxp' ? ($urlCxp ?? '') : ($urlCompras ?? ''));
    $titulo = ($mostrarSelector || $dinamico) ? 'Devolver legajo' : ($soloCxp ? 'Devolver a Cuentas a pagar' : 'Devolver a Compras');
@endphp
@if ($puedeCxp || $puedeCompras)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST"
                  id="{{ $formId }}"
                  action="{{ $actionInicial }}"
                  class="js-devolver-legajo-form"
                  data-url-cxp="{{ $urlCxp ?? '' }}"
                  data-url-compras="{{ $urlCompras ?? '' }}"
                  data-dinamico="{{ $dinamico ? '1' : '0' }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title js-devolver-titulo">{{ $titulo }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2 js-devolver-intro" @if (! $mostrarSelector && ! $dinamico) style="display:none" @endif>
                        Elegí a dónde vuelve el legajo. El comentario es obligatorio.
                    </p>
                    <p class="text-muted small js-devolver-ayuda-cxp" @if (! $soloCxp) style="display:none" @endif>
                        Vuelve el legajo de Pagos a <strong>CUENTAS A PAGAR</strong>. El comentario es obligatorio.
                    </p>
                    <p class="text-muted small js-devolver-ayuda-compras" @if (! $soloCompras) style="display:none" @endif>
                        Vuelve el legajo a <strong>COMPRAS</strong>. El comentario es obligatorio.
                    </p>

                    <div class="list-group mb-3 js-devolver-destinos" role="radiogroup" aria-label="Destino de la devolución"
                         @if (! $mostrarSelector && ! $dinamico) style="display:none" @endif>
                        @if ($puedeCxp)
                        <label class="list-group-item list-group-item-action d-flex align-items-start mb-0 js-devolver-destino-item" data-destino="cxp">
                            <input type="radio" name="_destino_devolver" value="cxp" class="mt-1 mr-2 flex-shrink-0" @if ($defaultDestino === 'cxp') checked @endif>
                            <span>
                                <strong>Cuentas a pagar</strong>
                                <br><small class="text-muted">Para revisión o corrección en CxP sin pasar por Compras.</small>
                            </span>
                        </label>
                        @endif
                        @if ($puedeCompras)
                        <label class="list-group-item list-group-item-action d-flex align-items-start mb-0 js-devolver-destino-item" data-destino="compras">
                            <input type="radio" name="_destino_devolver" value="compras" class="mt-1 mr-2 flex-shrink-0" @if ($defaultDestino === 'compras') checked @endif>
                            <span>
                                <strong>Compras</strong>
                                <br><small class="text-muted">Para que asignen las COM que faltan y vuelvan a enviar a CxP.</small>
                            </span>
                        </label>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="{{ $formId }}_obs">Comentario / motivo</label>
                        <input type="text" name="observacion" id="{{ $formId }}_obs" class="form-control" maxlength="255" required autocomplete="off">
                    </div>
                    <div class="form-group mb-0">
                        <label for="{{ $formId }}_leyenda">Detalle</label>
                        <textarea name="leyenda" id="{{ $formId }}_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning js-devolver-submit">
                        {{ $defaultDestino === 'compras' ? 'Devolver a Compras' : 'Devolver a Cuentas a pagar' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
#{{ $modalId }} .js-devolver-destino-item { cursor: pointer; border-left: 3px solid transparent; }
#{{ $modalId }} .js-devolver-destino-item.active {
    border-left-color: #ffc107;
    background: #fffdf5;
}
#{{ $modalId }} .js-devolver-destino-item + .js-devolver-destino-item { margin-top: -1px; }
</style>
@endif
