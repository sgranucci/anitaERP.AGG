<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2 requerido">Provincia</label>
    <div class="col-lg-8">
        <div class="input-group">
            <input type="hidden" id="provincia_id_previa" name="provincia_id_previa" value="">
            <input type="hidden" id="desc_provincia" name="desc_provincia" value="">
            <input type="hidden" class="provincia_id" id="provincia_id" name="provincia_id" value="{{ old('provincia_id') }}">
            <input type="text" class="form-control col-lg-2 codigoprovincia" id="codigoprovincia" name="codigoprovincia"
                   value="{{ old('codigoprovincia') }}" placeholder="Cód." title="Código provincia" maxlength="5">
            <input type="text" class="form-control nombreprovincia" id="nombreprovincia" name="nombreprovincia"
                   value="{{ old('nombreprovincia') }}" readonly placeholder="Provincia" title="Descripción">
            <div class="input-group-append">
                <button type="button" title="Consulta provincias" class="btn btn-outline-secondary consultaprovincia tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
            </div>
        </div>
        <small class="form-text text-muted">
            CABA = <code>1</code> &middot; Buenos Aires / ARBA = <code>2</code> &middot; C&oacute;rdoba = <code>4</code> &middot;
            Entre R&iacute;os = <code>8</code> &middot; Misiones = <code>14</code> &middot; Santa Fe = <code>21</code> &middot;
            Tucum&aacute;n = <code>24</code>.
        </small>
    </div>
</div>

<div class="form-group row" id="tipopadron" style="display:none;">
    <label for="tipopadron_sel" class="col-lg-3 control-label text-right pr-2 requerido">Tipo de padrón</label>
    <div class="col-lg-4">
        <select name="tipopadron" id="tipopadron_sel" class="form-control">
            <option value="">-- Elija tipo de padrón --</option>
            @foreach ($tipopadron_enum as $value => $tipopadron)
                <option value="{{ $value }}" @if(old('tipopadron') == $value) selected @endif>{{ $tipopadron }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Solo Tucumán: tasas o coeficientes.</small>
    </div>
</div>

<div class="form-group row">
    <label for="file" class="col-lg-3 control-label text-right pr-2">Archivo (upload)</label>
    <div class="col-lg-8">
        <input type="file" name="file" id="file" class="form-control-file">
        <small class="form-text text-muted">
            <strong>Santa Fe:</strong> ZIP <code>PARP_YYYYMM.csv.zip</code> o CSV <code>PARP_YYYYMM.csv</code>.
            <br>
            <strong>CABA (AGIP):</strong> TXT <code>ARDJU&hellip;.TXT</code>.
            <br>
            <strong>ARBA:</strong> ZIP <code>PadronRGS&hellip;.zip</code> o TXT Per/Ret.
            <br>
            <strong>Misiones:</strong> CSV <code>;</code> con cabecera <code>Periodo_fiscal;r&eacute;gimen;cuit;&hellip;</code>.
            <br>
            <strong>C&oacute;rdoba:</strong> CSV <code>;</code> con l&iacute;neas P y R. <strong>Entre R&iacute;os:</strong> CSV <code>;</code> con vigencia y al&iacute;cuotas.
            <br>
            <strong>Tucum&aacute;n:</strong> archivo de ancho fijo (elegir tasas o coeficientes).
            <br>
            Se acepta el archivo comprimido en ZIP. Para archivos muy grandes (~70&nbsp;MB+) conviene usar la ruta en servidor.
        </small>
    </div>
</div>

<div class="form-group row">
    <label for="ruta_servidor" class="col-lg-3 control-label text-right pr-2">Ruta en servidor</label>
    <div class="col-lg-8">
        <input type="text" name="ruta_servidor" id="ruta_servidor" class="form-control"
            placeholder="{{ storage_path('app/padrones') }}/PARP_202608.csv.zip"
            value="{{ old('ruta_servidor') }}">
        <small class="form-text text-muted">
            Ruta absoluta dentro de alguno de los directorios habilitados:
            @foreach (\App\Support\Configuracion\PadronIibbArchivoRutaSupport::directoriosPermitidos() as $directorio)
                <code>{{ $directorio }}</code>@if (! $loop->last), @endif
            @endforeach.
            Si completa este campo, no hace falta subir el archivo.
            <br>
            Deje el archivo en <code>{{ storage_path('app/padrones') }}</code>: los directorios personales
            (<code>/home/&hellip;</code>) no son legibles por el proceso que importa en background.
        </small>
    </div>
</div>
