<div class="card form1">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="guia" class="col-lg-3 col-form-label">Rendido por</label>
                <input type="hidden" class="col-form-label guia_id" id="guia_id" name="guia_id" value="{{$data->guia_id ?? ''}}" >
                <input type="text" class="col-lg-2 codigoguia" id="codigoguia" name="codigoguia" value="{{$data->guias->codigo ?? ''}}" >
                <input type="text" class="col-lg-5 col-form-label nombreguia" id="nombreguia" name="nombreguia" value="{{$data->guias->nombre ?? ''}}" readonly>
                <button type="button" title="Consulta guías" style="padding:1;" class="btn-accion-tabla consultaguia tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="hidden" name="nombreguia" id="nombreguia" class="form-control" value="{{old('nombreguia', $data->guias->nombre ?? '')}}">
            </div>
            <div class="form-group row">
                <label for="movil" class="col-lg-3 col-form-label">Móvil</label>
                <input type="hidden" class="col-form-label movil_id" id="movil_id" name="movil_id" value="{{$data->movil_id ?? ''}}" >
                <input type="text" class="col-lg-2 codigomovil" id="codigomovil" name="codigomovil" value="{{$data->moviles->codigo ?? ''}}" >
                <input type="text" class="col-lg-5 col-form-label nombremovil" id="nombremovil" name="nombremovil" value="{{$data->moviles->nombre ?? ''}}" readonly>
                <button type="button" title="Consulta móviles" style="padding:1;" class="btn-accion-tabla consultamovil tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="hidden" name="nombremovil" id="nombremovil" class="form-control" value="{{old('nombremovil', $data->moviles->nombre ?? '')}}">
            </div>
            <div class="form-group row">
                <label for="desdekm" class="col-lg-3 col-form-label">Desde Km</label>
                <div class="col-lg-3">
                    <input type="number" name="desdekm" id="desdekm" class="form-control" value="{{old('desdekm', $data->desdekm ?? '')}}">
                </div>
                <label for="hastakm" class="col-lg-3 col-form-label">Hasta Km</label>
                <div class="col-lg-3">
                    <input type="number" name="hastakm" id="hastakm" class="form-control" value="{{old('hastakm', $data->hastakm ?? '')}}">
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="fecha" class="col-lg-3 col-form-label">Fecha</label>
                <div class="col-lg-3">
                    <input type="date" name="fecha" id="fecha" class="form-control required" value="{{old('fecha', $data->fecha ?? date('Y-m-d'))}}">
                </div>
            </div>
            <div class="form-group row">
                <label for="ordenservicio" class="col-lg-3 col-form-label">Orden de Servicio</label>
                <select id="ordenservicio_id" name="ordenservicio_id" class="col-lg-4 form-control" required>
                    <option value="">-- Elija orden de servicio --</option>
                    @foreach($ordenservicio_id_query as $ordenservicio)
                        @if ($ordenservicio == old('ordenservicio_id',$data->ordenservicio_id??''))
                            <option value="{{ $ordenservicio }}" selected>{{ $ordenservicio }}</option>    
                        @else
                            <option value="{{ $ordenservicio }}">{{ $ordenservicio }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="observacion" class="col-lg-3 col-form-label">Observaciones</label>
        <div class="col-lg-8">
            <input type="text" name="observacion" id="observacion" class="form-control" value="{{old('observacion', $data->observacion ?? '')}}">
        </div>
    </div>
    <input type="hidden" id="id" name="id" value="{{ $data->id ?? '' }}" />
    <input type="hidden" class="moneda_default_id" id="moneda_default_id" name="moneda_default_id" value="{{config('caja.ID_MONEDA_DEFAULT_VOUCHER') ?? ''}}" >
    <label for="Gastos anteriores" class="col-lg-3 col-form-label">Total gastos anteriores</label>
    <div class="form-group row totales-por-moneda-gastoanterior">
    </div>
    <label for="Vouchers" class="col-lg-3 col-form-label">Total vouchers</label>
    <div class="form-group row totales-por-moneda-voucher">
    </div>
    <label for="Gastos a compensar" class="col-lg-3 col-form-label">Total gastos a compensar</label>
    <div class="form-group row totales-por-moneda-gasto">
    </div>
    <label for="Comisiones" class="col-lg-3 col-form-label">Total comisiones</label>
    <div class="form-group row totales-por-moneda-comision">
    </div>
    <label for="Adelantos" class="col-lg-3 col-form-label">Total adelantos</label>
    <div class="form-group row totales-por-moneda-adelanto">
    </div>    
    <label for="Total rendicion" class="col-lg-3 col-form-label">Total a rendir</label>
    <div class="form-group row totales-por-moneda-rendicion">
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.receptivo.modalconsultaguia')
@include('includes.receptivo.modalconsultamovil')

