<div class="col-md-12">
    <div class="form-group row">
        <label for="observacion" class="col-lg-1 col-form-label">Observaciones</label>
        <div class="col-lg-6">
            <textarea name="observacion" name="observacion" class="form-control required" rows="4" required placeholder="Observaciones ...">{{old('observacion', $observacion ?? '')}}</textarea>
        </div>
    </div>
    <input type="hidden" name="tipocomprobante" class="form-control" value="{{$tipocomprobante}}">
    <input type="hidden" name="comprobante_id" class="form-control" value="{{$comprobante_id}}">
    <input type="hidden" name="aprobacion_id" class="form-control" value="{{$aprobacion_id}}">
    <input type="hidden" name="usuario_id" class="form-control" value="{{$usuario_id}}">
</div>

