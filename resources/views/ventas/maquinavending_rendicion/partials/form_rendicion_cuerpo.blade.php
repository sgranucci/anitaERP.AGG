                    <div id="panel-rendicion-contenido" class="d-none">
                        <div class="row mv-columnas-principales">
                            <div class="col-xl-5">
                                <div class="card card-outline card-success mb-3 mv-card-articulos">
                                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                        <span><i class="fa fa-cube"></i> Art&iacute;culos por rulo</span>
                                        <strong id="mv-total-ventas" class="text-success">$0,00</strong>
                                    </div>
                                    <div class="card-body py-2 p-0">
                                        <div class="table-responsive mv-panel-articulos">
                                            <table class="table table-sm table-bordered mb-0" id="tabla-articulos-rendicion">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th style="width:8%;">Rulo</th>
                                                        <th style="width:14%;">SKU</th>
                                                        <th>Descripci&oacute;n</th>
                                                        <th style="width:14%;" class="text-right">P. lista</th>
                                                        <th style="width:12%;" class="text-right">Cant.</th>
                                                        <th style="width:14%;" class="text-right">Importe</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbody-articulos-rendicion">
                                                    <tr><td colspan="6" class="text-muted text-center p-3">Seleccione una m&aacute;quina.</td></tr>
                                                </tbody>
                                                <tfoot class="thead-light">
                                                    <tr class="font-weight-bold">
                                                        <td colspan="5" class="text-right">Total a rendir</td>
                                                        <td class="text-right" id="mv-total-ventas-foot">$0,00</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-7">
                                <div class="card card-outline card-dark mb-3 mv-card-cobranza">
                                    <div class="card-header py-2">
                                        <span><i class="fa fa-money"></i> Medios de pago</span>
                                    </div>
                                    <div class="card-body py-2 d-flex flex-column" style="min-height: 420px;">
                                        <div id="panel-cobranza-compacta" class="small flex-grow-1 d-flex flex-column">
                                            <input type="hidden" id="mv-empresa-id" value="{{ $empresaInicial ?? 0 }}">
                                            <input type="hidden" id="empresa_id_mirror" value="{{ $empresaInicial ?? 0 }}">
                                            <p class="text-muted mb-1" style="font-size:11px;">Debe cuadrar con el total de art&iacute;culos vendidos.</p>
                                            <div class="table-responsive mv-cobranza-scroll flex-grow-1">
                                                <table class="table table-sm table-bordered mb-0 bg-white" id="mv-cuenta-table">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 42%;">Cuenta de caja</th>
                                                            <th style="width: 8%;">Mon.</th>
                                                            <th style="width: 18%;">Monto</th>
                                                            <th style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tbody-mv-cuenta-table">
                                                        <tr><td colspan="4" class="text-muted text-center p-2">Cargando medios…</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-1 mv-cobranza-acciones d-flex flex-wrap align-items-start" style="gap:0.35rem;">
                                                <button type="button" class="btn btn-sm btn-danger" id="mv-agrega-renglon-cuenta">+ Agregar rengl&oacute;n</button>
                                                <div id="mv-medios-rapidos" class="d-none" role="group" aria-label="Medios de pago r&aacute;pidos"></div>
                                                <div id="mv-totales-cobranza" class="mv-totales-resumen ml-auto"></div>
                                            </div>
                                            <div id="mv-alert-diferencias" class="alert alert-danger py-1 px-2 small mt-2 mb-0 d-none"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="aviso-seleccion-maquina" class="alert alert-warning mb-0">
                        Seleccione empresa y m&aacute;quina para cargar los rulos y medios de pago.
                    </div>
