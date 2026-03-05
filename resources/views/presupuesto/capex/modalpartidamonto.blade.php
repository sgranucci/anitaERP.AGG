<div class="modal fade" id="partidaMontoModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Montos de la Partida</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body">
            <form>
                <div class="form-group">
                    <table class="table" id="capex-partida-monto-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Período</th>
                                <th style="width: 30%;">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-capex-partida-monto-table" class="container-partida-monto">
                        </tbody>
                    </table>
                </div>
            </form>
            @include('presupuesto.capex.templatepartidamonto')
        </div>
        <div class="modal-footer">
            <button style="display: block; margin-right: auto; margin-left: 0;" id="agregar_renglon_partida_monto" class="btn btn-danger">+ Agrega rengl&oacute;n</button>
            <button type="button" id="cierraPartidaMontoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            <button type="button" id="aceptaPartidaMontoModal" class="btn btn-primary">Acepta Montos</button>
        </div>
    </div>
  </div>
</div>
