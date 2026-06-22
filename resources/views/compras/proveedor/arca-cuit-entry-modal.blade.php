{{-- Modal inicial: ingresar CUIT para consulta padrón ARCA (altas de proveedor y de cliente) --}}
<style>
	#arca-cuit-entry-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; align-items: center; justify-content: center; z-index: 2010; }
	#arca-cuit-entry-modal { background: #fff; width: min(420px, calc(100vw - 32px)); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
	#arca-cuit-entry-modal .hd { padding: 12px 16px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; justify-content: space-between; }
	#arca-cuit-entry-modal .bd { padding: 16px; }
	#arca-cuit-entry-modal .ft { padding: 12px 16px; border-top: 1px solid #e9ecef; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
	#arca-cuit-entry-loading {
		display: none;
		width: 100%;
		box-sizing: border-box;
		margin-top: 12px;
		padding: 10px 12px;
		background: #e7f3ff;
		border: 1px solid #b8daff;
		border-radius: 6px;
		color: #004085;
		font-size: 0.88em;
		align-items: center;
		justify-content: center;
		gap: 8px;
		text-align: center;
	}
	#arca-cuit-entry-loading.is-visible { display: flex; }
</style>
<div id="arca-cuit-entry-overlay" role="dialog" aria-modal="true" aria-labelledby="arca-cuit-entry-title">
	<div id="arca-cuit-entry-modal">
		<div class="hd">
			<div id="arca-cuit-entry-title" style="font-weight: 600;">Consulta padrón ARCA</div>
			<button type="button" class="btn btn-light btn-sm" id="arca-cuit-entry-close" aria-label="Cerrar">×</button>
		</div>
		<div class="bd">
			<label for="arca-cuit-entry-input" class="d-block small text-muted mb-1">C.U.I.T.</label>
			<input type="text" id="arca-cuit-entry-input" class="form-control" placeholder="XX-XXXXXXXX-X" maxlength="13" autocomplete="off" oninput="typeof formatarCUIT === 'function' && formatarCUIT(this)" />
			<p class="small text-muted mt-2 mb-0">Ingresá el CUIT y pulsá Consultar. Podés previsualizar los datos del padrón y aplicarlos al formulario.</p>
			<div id="arca-cuit-entry-loading" role="status" aria-live="polite">
				<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
				<span>Consultando el web service de ARCA… Aguardá un momento.</span>
			</div>
		</div>
		<div class="ft">
			<button type="button" class="btn btn-light mr-auto" id="arca-cuit-entry-cancel">Cancelar</button>
			<button type="button" class="btn btn-primary" id="arca-cuit-entry-consultar"><i class="fa fa-search"></i> Consultar</button>
		</div>
	</div>
</div>
