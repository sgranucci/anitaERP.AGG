
        <!-- MODAL para ordenar datos de articulos -->
        <div class="modal fade" tabindex="-1" role="dialog" id='advanced_filter_modal'>
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button class="close" aria-label="Close" type="button" data-dismiss="modal">
                            <span aria-hidden="true">.</span></button>
                        <h4 class="modal-title"><i class='fa fa-filter'></i> Filtros y b&uacute;quedas avanzadas</h4>
                    </div>
                    <form method='get' action=''>
                        <div class="modal-body">

                            <div class='form-group'>

                                <div class='row-filter-combo row'>

                                    <div class="col-sm-2">
                                        <strong>Estado Pedidos</strong>
                                    </div>

                                    <div class='col-sm-3'>
                                        <select name='filter_column[0][type]' data-type='varchar'
                                                class="filter-combo form-control">
                                            <option value=''>** Operador</option>
                                            <option selected typeallow='all'
                                                     value='='>= (Igual a)</option>
                                            <option  value='empty'>Vacio (o Null) </option>
                                        </select>
                                    </div><!--END COL_SM_4-->

                                    <div class='col-sm-5'>
										<select id="estado" name="filter_column[0][value]" class="filter-value form-control">
                        					<option value="">-- Selecciona estado --</option>
                                			<option value="P">Pendientes</option>    
                                			<option value="F">Facturados</option>    
                                			<option value="E">En produccion</option>  
                                            <option value="A">Anulados</option>  
                    					</select>
										<!-- 
 										<input type='text' class='filter-value form-control' style="display:none"
                                               disabled name='filter_column[t][value]'
                                               value=''> -->

                                        <div class='row between-group' style="display:none">
                                            <div class='col-sm-6'>
                                                <div class='input-group '>
                                                    <span class="input-group-addon">Desde:</span>
                                                    <input
                                                            disabled
                                                            type='text'
                                                            class='filter-value-between form-control timepicker'
                                                            readonly placeholder='Acci¢n Desde'
                                                            name='filter_column[0][value][]' value=''>
                                                </div>
                                            </div>
                                            <div class='col-sm-6'>
                                                <div class='input-group '>
                                                    <span class="input-group-addon">Hasta:</span>
                                                    <input
                                                            disabled
                                                            type='text'
                                                            class='filter-value-between form-control timepicker'
                                                            readonly placeholder='Acci¢n Hasta'
                                                            name='filter_column[0][value][]' value=''>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!--END COL_SM_6-->

                                    <div class='col-sm-2'>
                                        <select class='form-control' name='filter_column[0][sorting]'>
                                            <option  value='asc' selected>- - -</option>
                                        </select>
                                    </div><!--END_COL_SM_2-->
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" align="right">
                            <button class="btn btn-default" type="button" data-dismiss="modal">Cerrar</button>
                            <button class="btn btn-default btn-reset" type="reset"
                                    onclick='location.href=""'>Resetear</button>
                            <button class="btn btn-primary btn-submit" type="submit">Enviar</button>
                        </div>

                        <input type="hidden" name="filter_column[0][column]" id="estado" value="estado">
                        <input type="hidden" name="lasturl" value="{{route('pedido')}}">
                    </form>
                </div>
                <!-- /.modal-content -->
            </div>
        </div>
