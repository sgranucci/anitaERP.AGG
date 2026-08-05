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
            Santa Fe = código <code>21</code> (jurisdicción 921). CABA = <code>1</code>. Buenos Aires / ARBA = <code>2</code>.
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
            <strong>CABA (AGIP):</strong> TXT <code>ARDJU….TXT</code>.
            <br>
            <strong>ARBA:</strong> ZIP <code>PadronRGS….zip</code> o TXT Per/Ret.
            <br>
            Archivos muy grandes (~70&nbsp;MB+) conviene usar la ruta en servidor.
        </small>
    </div>
</div>

<div class="form-group row">
    <label for="ruta_servidor" class="col-lg-3 control-label text-right pr-2">Ruta en servidor</label>
    <div class="col-lg-8">
        <input type="text" name="ruta_servidor" id="ruta_servidor" class="form-control"
            placeholder="/home/sergio/padronsantafe/PARP_202608.csv.zip"
            value="{{ old('ruta_servidor') }}">
        <small class="form-text text-muted">
            Absoluta, bajo <code>/home/sergio/padronsantafe</code>, <code>/home/sergio/padroncaba</code>,
            <code>/home/sergio/padronarba</code> o <code>storage/app</code>.
            Si completa este campo, no hace falta subir el archivo.
        </small>
    </div>
</div>
