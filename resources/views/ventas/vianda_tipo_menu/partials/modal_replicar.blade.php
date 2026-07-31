@php
    $empresasReplicar = $empresa_query_replicar ?? collect();
    $puedeReplicarModal = ! empty($puede_replicar_vianda_tipo_menu) && $empresasReplicar->count() > 1;
@endphp
@if ($puedeReplicarModal)
<div class="modal fade" id="modal-replicar-vianda-tipo-menu" tabindex="-1" role="dialog" aria-labelledby="modal-replicar-vianda-tipo-menu-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="#" id="form-replicar-vianda-tipo-menu">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-replicar-vianda-tipo-menu-label">Replicar men&uacute; a otras empresas</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Origen: <strong id="replicar-menu-origen-nombre"></strong>
                        <span class="text-muted">(<span id="replicar-menu-origen-empresa"></span>)</span>
                    </p>
                    <p class="small text-muted">
                        Se copian los art&iacute;culos por d&iacute;a al men&uacute; hom&oacute;logo (mismo c&oacute;d. Anita)
                        de cada empresa destino. <strong>Pisa</strong> lo que esa empresa tenga cargado actualmente.
                        Si no existe el men&uacute; destino, se crea.
                    </p>
                    <label class="d-block font-weight-bold mb-2">Empresa(s) destino</label>
                    @foreach ($empresasReplicar as $emp)
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox"
                                   class="custom-control-input vianda-empresa-destino-check"
                                   id="vianda-empresa-destino-{{ $emp->id }}"
                                   name="empresa_destino_ids[]"
                                   value="{{ $emp->id }}">
                            <label class="custom-control-label" for="vianda-empresa-destino-{{ $emp->id }}">
                                {{ $emp->nombre }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-copy"></i> Replicar y pisar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
