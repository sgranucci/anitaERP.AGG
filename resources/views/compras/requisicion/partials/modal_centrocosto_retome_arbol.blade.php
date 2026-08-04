{{-- Modal: elegir CC de destino para el árbol (varios en renglones + opcional fuera de lista). --}}
@include('includes.contable.modalconsultacentrocosto')

<div class="modal fade" id="modalRequisicionCentrocostoRetomeArbol" tabindex="-1" role="dialog" aria-labelledby="modalRequisicionCentrocostoRetomeArbolTitulo" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalRequisicionCentrocostoRetomeArbolTitulo">
                    <i class="fa fa-sitemap mr-1"></i> Centro de costo para el &aacute;rbol de aprobaci&oacute;n
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="requisicionCentrocostoRetomeArbolTexto">
                    La requisici&oacute;n tiene renglones con distintos centros de costo de destino. Elija con cu&aacute;l continuar el &aacute;rbol de aprobaci&oacute;n.
                </p>

                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2">
                        <strong class="card-title mb-0">
                            <i class="fa fa-list mr-1"></i> Centros de costo de los renglones
                        </strong>
                    </div>
                    <div class="card-body p-0">
                        <div id="requisicionCentrocostoRetomeArbolLista" class="requisicion-cc-arbol-lista"></div>
                    </div>
                </div>

                <div class="card card-outline card-secondary mb-0" id="requisicionCentrocostoRetomeArbolExtraCard">
                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between">
                        <strong class="card-title mb-0">
                            <i class="fa fa-plus-circle mr-1"></i> Otro centro de costo
                            <span class="badge badge-secondary ml-1">Opcional</span>
                        </strong>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="requisicionCentrocostoRetomeArbolExtraLimpiar" title="Quitar el centro de costo adicional">
                            <i class="fa fa-times"></i> Limpiar
                        </button>
                    </div>
                    <div class="card-body pb-2">
                        <p class="small text-muted mb-2">
                            Si el circuito debe autorizarlo un centro que no est&aacute; en los renglones, c&aacute;rguelo ac&aacute;.
                            Al completarlo, tiene prioridad sobre la lista de arriba.
                        </p>
                        @include('contable.partials.campo_consulta_centrocosto', [
                            'prefix' => 'arbol_extra',
                            'layout' => 'form_row',
                            'label' => 'Centro de costo',
                            'inputName' => 'centrocosto_arbol_extra_id',
                            'inputId' => 'centrocosto_arbol_extra_id',
                            'centrocostoId' => '',
                            'codigo' => '',
                            'descripcion' => '',
                            'required' => false,
                            'mostrar_editar' => true,
                            'col_label' => 'col-lg-3',
                            'col_input' => 'col-lg-9',
                            'ayuda' => 'F1 o lupa: consulta · Enter en código: selecciona · Enter en la grilla de consulta: elige el primero',
                        ])
                        <div class="custom-control custom-radio mt-1 d-none" id="requisicionCentrocostoRetomeArbolExtraRadioWrap">
                            <input type="radio" id="centrocosto_retome_arbol_extra" name="centrocosto_retome_arbol" class="custom-control-input" value="">
                            <label class="custom-control-label" for="centrocosto_retome_arbol_extra" id="requisicionCentrocostoRetomeArbolExtraRadioLabel">
                                Usar el centro de costo adicional
                            </label>
                        </div>
                    </div>
                </div>

                <div class="alert alert-danger d-none mt-3 mb-0" id="requisicionCentrocostoRetomeArbolError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="requisicionCentrocostoRetomeArbolConfirmar">
                    <i class="fa fa-check"></i> Continuar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #modalRequisicionCentrocostoRetomeArbol .requisicion-cc-arbol-lista .list-group-item {
        border-left: 0;
        border-right: 0;
        border-radius: 0;
        cursor: pointer;
    }
    #modalRequisicionCentrocostoRetomeArbol .requisicion-cc-arbol-lista .list-group-item:first-child {
        border-top: 0;
    }
    #modalRequisicionCentrocostoRetomeArbol .requisicion-cc-arbol-lista .list-group-item.active-cc {
        background: #D6EAF8;
        border-color: #85C1E9;
    }
    #modalRequisicionCentrocostoRetomeArbol #tm_centrocosto_arbol_extra .form-group.row {
        margin-bottom: 0.35rem;
    }
</style>
