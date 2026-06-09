<div class="modal fade" id="modalSeleccionSalida" tabindex="-1" role="dialog" aria-labelledby="modalSeleccionSalidaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalSeleccionSalidaTitulo">Seleccionar impresora</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small mb-2">
                    Busque por nombre o ubicación física de la impresora y pulse <strong>Elegir</strong>.
                </p>
                <div class="form-group row mb-2">
                    <label for="modal_salida_busqueda" class="col-sm-2 col-form-label col-form-label-sm">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text"
                               class="form-control form-control-sm"
                               id="modal_salida_busqueda"
                               placeholder="Nombre, ubicación…"
                               autocomplete="off"
                               autofocus>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-striped table-bordered table-hover table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Ubicación</th>
                                <th>Usos</th>
                                <th class="width80"></th>
                            </tr>
                        </thead>
                        <tbody id="modal_salida_lista">
                            @foreach ($salidas as $salida)
                                <tr class="fila-salida-modal"
                                    data-id="{{ $salida->id }}"
                                    data-nombre="{{ e($salida->nombre) }}"
                                    data-ubicacion="{{ e($salida->ubicacion) }}"
                                    data-usos="{{ e($salida->usos_etiqueta) }}">
                                    <td>{{ $salida->nombre }}</td>
                                    <td>{{ $salida->ubicacion }}</td>
                                    <td>{{ $salida->usos_etiqueta }}</td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-warning btn-sm btn-elegir-salida">Elegir</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p id="modal_salida_sin_resultados" class="text-muted small mb-0 mt-2 d-none">No hay impresoras que coincidan con la búsqueda.</p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
