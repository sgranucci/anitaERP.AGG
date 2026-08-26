<div class="form-group row">
	<label for="file" class="col-lg-3 control-label text-right pr-2 requerido">Archivo</label>
	<div class="col-lg-6">
		<input type="file" name="file" id="file" class="form-control" accept=".csv,.txt,.zip,text/csv,text/plain,application/zip" required />
	</div>
	<div class="col-lg-3">
		<button type="button" id="btn-preanalizar-padron-mipyme" class="btn btn-outline-primary btn-sm" disabled>
			<i class="fa fa-search"></i> Analizar archivo
		</button>
	</div>
</div>

<div class="form-group row">
	<div class="col-lg-3"></div>
	<div class="col-lg-8">
		<p class="text-muted small mb-0">
			CSV/TXT del padrón AFIP (separador <code>;</code>):
			CUIT;NOMBRE;ACTIVIDAD;FECHA_INICIO. También acepta el <strong>ZIP</strong> oficial:
			el preanálisis lo detecta (por extensión o por contenido) y lo descomprime antes de importar.
			Reemplaza el padrón completo y actualiza el modo de facturación de los clientes.
		</p>
	</div>
</div>

<div id="panel-preanalisis-padron-mipyme" class="card card-outline card-info mb-3" style="display:none;">
	<div class="card-header py-2 d-flex justify-content-between align-items-center">
		<strong><i class="fa fa-search"></i> Preanálisis del archivo</strong>
		<span id="preanalisis-padron-mipyme-estado" class="badge badge-secondary">—</span>
	</div>
	<div class="card-body p-2" id="preanalisis-padron-mipyme-contenido">
		<p class="text-muted small mb-0">Seleccione un archivo para detectar ZIP, columnas y una muestra de filas.</p>
	</div>
</div>
