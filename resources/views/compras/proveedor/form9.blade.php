@php
    $esCrownSalas = !empty($mostrar_solapa_salas);
    $salasSelector = $sala_query ?? collect();
    $salaIdsSeleccionados = collect();
    if (old('sala_ids') !== null) {
        $salaIdsSeleccionados = collect((array) old('sala_ids'))->map(fn ($id) => (int) $id);
    } elseif (isset($data) && $data->relationLoaded('proveedor_salas')) {
        $salaIdsSeleccionados = $data->proveedor_salas->pluck('sala_id')->map(fn ($id) => (int) $id);
    } elseif (isset($data)) {
        $salaIdsSeleccionados = $data->proveedor_salas()->pluck('sala_id')->map(fn ($id) => (int) $id);
    }
@endphp
@if ($esCrownSalas)
<div id="tab9" class="card form9 tab-content" style="display: none">
    <div class="card-body">
        <h3>Salas</h3>
        <p class="text-muted small mb-3">
            Vac&iacute;o = aplica a <strong>todas</strong> las salas (equivalente Anita &laquo;Todos los casinos&raquo;).
            Marc&aacute; solo las salas donde opera este proveedor.
        </p>
        <input type="hidden" name="salas_presentes" value="1">
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="tabla-proveedor-salas">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:4rem;" class="text-center">Incluir</th>
                        <th style="width:6rem;">C&oacute;digo</th>
                        <th>Sala</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salasSelector as $sala)
                        <tr>
                            <td class="text-center align-middle">
                                <input type="checkbox"
                                    name="sala_ids[]"
                                    value="{{ $sala->id }}"
                                    class="proveedor-sala-check"
                                    @if ($salaIdsSeleccionados->contains((int) $sala->id)) checked @endif>
                            </td>
                            <td class="align-middle">{{ $sala->codigo }}</td>
                            <td class="align-middle">{{ $sala->nombre }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted text-center">No hay salas cargadas. Ejecut&aacute; la migraci&oacute;n de salas Crown.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
