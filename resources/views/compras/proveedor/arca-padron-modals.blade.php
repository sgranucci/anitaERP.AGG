<style>
	/* Preview ARCA (simple, sin depender de librerías) */
	#arca-preview-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index: 2000; }
	#arca-preview-modal { background: #fff; width: min(720px, calc(100vw - 32px)); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
	#arca-preview-modal .hd { padding: 12px 16px; border-bottom: 1px solid #e9ecef; display:flex; align-items:center; justify-content:space-between; }
	#arca-preview-modal .bd { padding: 14px 16px; }
	#arca-preview-modal .ft { padding: 12px 16px; border-top: 1px solid #e9ecef; display:flex; flex-wrap: wrap; gap:8px; align-items:center; justify-content:flex-start; }
	#arca-preview-modal .grid { display:grid; grid-template-columns: 160px 1fr; gap: 8px 12px; }
	#arca-preview-modal .k { color:#6c757d; }
	#arca-preview-modal .warn { margin-top: 10px; color:#856404; background:#fff3cd; border: 1px solid #ffeeba; padding: 8px 10px; border-radius: 6px; display:none; }
	#arca-preview-expand-full { margin-right: auto; }
	/* Visor respuesta completa WS */
	#arca-full-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index: 2100; }
	#arca-full-modal { background: #fff; width: min(960px, calc(100vw - 32px)); max-height: min(90vh, 900px); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.25); display: flex; flex-direction: column; }
	#arca-full-modal .hd { padding: 12px 16px; border-bottom: 1px solid #e9ecef; display:flex; align-items:center; justify-content:space-between; flex-shrink: 0; }
	#arca-full-modal .bd { padding: 12px 16px; overflow: auto; flex: 1; min-height: 0; }
	#arca-full-modal .ft { padding: 12px 16px; border-top: 1px solid #e9ecef; display:flex; justify-content:flex-end; flex-shrink: 0; }
	#arca-full-tree { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; line-height: 1.45; }
	.arca-tree-summary { cursor: pointer; font-weight: 600; color: #1a365d; list-style-position: outside; padding: 2px 0; }
	.arca-tree-line { padding: 2px 0; word-break: break-word; }
	.arca-tree-k { color: #6c757d; margin-right: 8px; }
	.arca-tree-v { color: #212529; }
	.arca-tree-null { font-style: italic; color: #868e96; }
	#arca-full-tree details { margin: 2px 0; border-left: 1px solid #e9ecef; padding-left: 6px; }
	.arca-soap-block { margin-top: 16px; border-top: 1px solid #e9ecef; padding-top: 12px; }
	.arca-soap-block h6 { font-size: 13px; font-weight: 600; margin: 0 0 8px; }
	.arca-soap-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
	.arca-soap-pre { margin: 0; padding: 10px 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 11px; line-height: 1.4; max-height: 220px; overflow: auto; white-space: pre-wrap; word-break: break-word; }
	.arca-soap-empty { color: #868e96; font-size: 12px; font-style: italic; }
	.arca-soap-warn { margin-bottom: 12px; color: #856404; background: #fff3cd; border: 1px solid #ffeeba; padding: 8px 10px; border-radius: 6px; font-size: 12px; }
</style>
<div id="arca-preview-overlay" role="dialog" aria-modal="true" aria-labelledby="arca-preview-title">
	<div id="arca-preview-modal">
		<div class="hd">
			<div id="arca-preview-title" style="font-weight:600;">Datos encontrados en padrón ARCA</div>
			<button type="button" class="btn btn-light btn-sm" id="arca-preview-close" aria-label="Cerrar">×</button>
		</div>
		<div class="bd">
			<div class="grid">
				<div class="k">CUIT</div><div id="arca-prev-cuit"></div>
				<div class="k">Nombre/Razón social</div><div id="arca-prev-nombre"></div>
				<div class="k">Domicilio fiscal</div><div id="arca-prev-domicilio"></div>
				<div class="k">Código postal</div><div id="arca-prev-cp"></div>
				<div class="k">Provincia</div><div id="arca-prev-prov"></div>
				<div class="k">Localidad</div><div id="arca-prev-loc"></div>
			</div>
			<div id="arca-prev-warn" class="warn"></div>
		</div>
		<div class="ft">
			<button type="button" class="btn btn-outline-secondary btn-sm" id="arca-preview-expand-full" title="Datos normalizados, raw del WS y XML SOAP request/response">Ver respuesta completa / SOAP</button>
			<button type="button" class="btn btn-light" id="arca-preview-cancel">Cancelar</button>
			<button type="button" class="btn btn-primary" id="arca-preview-apply">Aplicar al formulario</button>
		</div>
	</div>
</div>

<div id="arca-full-overlay" role="dialog" aria-modal="true" aria-labelledby="arca-full-title">
	<div id="arca-full-modal">
		<div class="hd">
			<div>
				<div id="arca-full-title" style="font-weight:600;">Respuesta completa del padrón ARCA</div>
				<div id="arca-full-subtitle" class="text-muted" style="font-size:12px; margin-top:4px; font-weight:normal;"></div>
			</div>
			<button type="button" class="btn btn-light btn-sm" id="arca-full-close" aria-label="Cerrar">×</button>
		</div>
		<div class="bd">
			<p class="text-muted small" style="margin-bottom:10px;">Podés expandir y contraer cada grupo. El bloque <strong>raw</strong> replica la estructura devuelta por el web service.</p>
			<div id="arca-full-tree"></div>
			<div id="arca-soap-section" class="arca-soap-block" style="display:none;">
				<div id="arca-soap-warn" class="arca-soap-warn">
					El XML incluye <strong>token</strong> y <strong>sign</strong> de acceso. Usalo solo para soporte ARCA o diagnóstico interno; no lo reenvíes por canales públicos.
				</div>
				<div class="arca-soap-block">
					<h6>SOAP request (última llamada a personaServiceA5)</h6>
					<div class="arca-soap-toolbar">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="arca-soap-copy-request">Copiar request</button>
					</div>
					<pre id="arca-soap-request" class="arca-soap-pre"></pre>
					<div id="arca-soap-request-empty" class="arca-soap-empty" style="display:none;">Sin request capturado (la consulta no llegó al web service).</div>
				</div>
				<div class="arca-soap-block">
					<h6>SOAP response</h6>
					<div class="arca-soap-toolbar">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="arca-soap-copy-response">Copiar response</button>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="arca-soap-copy-both">Copiar request + response</button>
					</div>
					<pre id="arca-soap-response" class="arca-soap-pre"></pre>
					<div id="arca-soap-response-empty" class="arca-soap-empty" style="display:none;">Sin response capturado.</div>
				</div>
			</div>
		</div>
		<div class="ft">
			<button type="button" class="btn btn-primary" id="arca-full-close-foot">Cerrar</button>
		</div>
	</div>
</div>
