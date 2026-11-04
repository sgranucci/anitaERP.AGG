@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
<div class="modal fade" id="modalFacturaNcSinFactura" tabindex="-1" role="dialog" aria-labelledby="modalFacturaNcSinFacturaLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header py-2">
				<h5 class="modal-title" id="modalFacturaNcSinFacturaLabel">Devoluci&oacute;n NC: combinaci&oacute;n y talles</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="small text-muted mb-2" id="factura_nc_sin_factura_articulo_txt">Eleg&iacute; primero el art&iacute;culo en el rengl&oacute;n.</p>
				<div class="form-group row mb-2">
					<label class="col-lg-3 control-label text-right pr-2">Combinaci&oacute;n</label>
					<div class="col-lg-9">
						<div class="tm-combinacion-campo input-group input-group-sm">
							<input type="hidden" class="combinacion_id" id="factura_nc_sf_combinacion_id" value="">
							<input type="hidden" class="ot-combinacion-todas" value="0">
							<input type="text" class="form-control codigocombinacion" id="factura_nc_sf_combinacion_codigo" placeholder="C&oacute;d." autocomplete="off" title="F1 consulta combinaciones">
							<div class="input-group-append">
								<button type="button" class="btn btn-outline-secondary consultacombinacion" title="Consulta combinaciones (F1)">
									<i class="fa fa-search"></i>
								</button>
							</div>
							<input type="text" class="form-control descripcioncombinacion" id="factura_nc_sf_combinacion_nombre" readonly placeholder="Descripci&oacute;n" tabindex="-1">
						</div>
					</div>
				</div>
				<div class="form-group row mb-2">
					<label class="col-lg-3 control-label text-right pr-2" for="factura_nc_sf_lote">Lote</label>
					<div class="col-lg-4">
						<input type="text" class="form-control form-control-sm" id="factura_nc_sf_lote" placeholder="0 = sin lote" autocomplete="off">
					</div>
					<div class="col-lg-5">
						<small class="text-muted">Vac&iacute;o o 0 = lote 0. M&oacute;dulo abierto (99). La combinaci&oacute;n incluye colores inactivos: el stock sube con ese color y con los talles cargados.</small>
					</div>
				</div>
				<div class="table-responsive mb-2">
					<table class="table table-sm table-bordered mb-0" id="factura_nc_sf_tabla_talles">
						<thead style="background:#85C1E9;color:#17202A;">
							<tr id="factura_nc_sf_thead_talles">
								<th class="text-muted">Talles…</th>
							</tr>
						</thead>
						<tbody>
							<tr id="factura_nc_sf_row_cantidades">
								<td class="text-muted">Cargando…</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="d-flex align-items-center" style="gap:.75rem;">
					<label class="mb-0 font-weight-bold" for="factura_nc_sf_tot_pares">Total pares</label>
					<input type="text" id="factura_nc_sf_tot_pares" class="form-control form-control-sm text-right font-weight-bold" style="width:5rem;" readonly value="0">
					<small class="text-muted" id="factura_nc_sf_aviso_precio"></small>
				</div>
			</div>
			<div class="modal-footer py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary btn-sm" id="factura_nc_sf_confirmar">Confirmar</button>
			</div>
		</div>
	</div>
</div>
@endif
