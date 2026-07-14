@extends("theme.$theme.layout")
@section('titulo')
    Generar certificado sanitario
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Generar solicitud WEB SENASA</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_certificado_sanitario')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form method="GET" action="{{route('crear_certificado_sanitario')}}" class="form-horizontal" id="form-consulta-certsan">
                <div class="card-body">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label requerido">Fecha entrega</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" class="form-control" value="{{ old('fecha', $filtros['fecha'] ?? '') }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Transporte</label>
                        <div class="col-lg-5">
                            <select name="transporte_id" class="form-control">
                                <option value="">-- Todos --</option>
                                @foreach($transportes as $t)
                                <option value="{{$t->id}}" @selected((string)old('transporte_id', $filtros['transporte_id'] ?? '') === (string)$t->id)>
                                    {{$t->codigo}} - {{$t->nombre}}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Zona de venta</label>
                        <div class="col-lg-5">
                            <select name="zonavta_id" class="form-control">
                                <option value="">-- Todas --</option>
                                @foreach($zonas as $z)
                                <option value="{{$z->id}}" @selected((string)old('zonavta_id', $filtros['zonavta_id'] ?? '') === (string)$z->id)>
                                    {{$z->codigo}} - {{$z->nombre}}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Fallback Anita</label>
                        <div class="col-lg-5">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fallback_anita" value="1" id="fallback_anita"
                                    @checked(old('fallback_anita', $filtros['fallback_anita'] ?? true))>
                                <label class="form-check-label" for="fallback_anita">
                                    Si el pedido no está en ERP, leerlo de Anita
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fa fa-search"></i> Consultar pedidos
                    </button>
                </div>
            </form>
        </div>

        @if (!is_null($preview))
        <div class="card card-info mt-3">
            <div class="card-header">
                <h3 class="card-title">Preview ({{ $preview->count() }} líneas)</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Origen</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Transporte</th>
                            <th>SKU</th>
                            <th>Kilos</th>
                            <th>Cajas</th>
                            <th>Frio</th>
                            <th>Registro SENASA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($preview as $l)
                        <tr>
                            <td>{{ strtoupper($l->origen) }}</td>
                            <td>{{ $l->codigoPedido }}</td>
                            <td>{{ $l->codigoCliente }} {{ $l->clienteNombre }}</td>
                            <td>{{ $l->codigoTransporte }}</td>
                            <td>{{ $l->sku }}</td>
                            <td class="text-right">{{ number_format($l->kilos, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($l->cajas, 2, ',', '.') }}</td>
                            <td>{{ $l->llevafrio }}</td>
                            <td>{{ $l->registroSenasa }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9">Sin líneas SENASA para los filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($preview->count() > 0)
        <div class="card card-danger mt-3">
            <div class="card-header">
                <h3 class="card-title">Datos del certificado y generación WEB</h3>
            </div>
            <form method="POST" action="{{route('guardar_certificado_sanitario')}}" class="form-horizontal" id="form-generar-certsan">
                @csrf
                <input type="hidden" name="fecha" value="{{ $filtros['fecha'] }}">
                <input type="hidden" name="transporte_id" value="{{ $filtros['transporte_id'] }}">
                <input type="hidden" name="zonavta_id" value="{{ $filtros['zonavta_id'] }}">
                <input type="hidden" name="fallback_anita" value="{{ !empty($filtros['fallback_anita']) ? 1 : 0 }}">
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label requerido">Camión</label>
                        <div class="col-lg-5">
                            <select name="camion_id" class="form-control" required>
                                <option value="">-- Seleccionar --</option>
                                @foreach($camiones as $c)
                                <option value="{{$c->id}}" @selected((string)old('camion_id') === (string)$c->id)>
                                    {{$c->codigo}} · {{$c->dominio}} · {{$c->habilitacion}}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Temperatura</label>
                        <div class="col-lg-2">
                            <input type="number" step="0.1" name="temperatura" class="form-control" value="{{ old('temperatura', 7) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Nro. remito</label>
                        <div class="col-lg-2">
                            <input type="number" name="nro_remito" class="form-control" value="{{ old('nro_remito') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Cant. precintos</label>
                        <div class="col-lg-2">
                            <input type="number" name="cantidad_precinto" class="form-control" min="0" max="99" value="{{ old('cantidad_precinto', 0) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Precintos</label>
                        <div class="col-lg-3">
                            <input type="text" name="precinto" maxlength="15" class="form-control" value="{{ old('precinto') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Establ. destino</label>
                        <div class="col-lg-2">
                            <input type="number" name="establecimiento_destino" class="form-control" min="0" max="9999" value="{{ old('establecimiento_destino') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Apertura</label>
                        <div class="col-lg-5">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="abre_por_localidad" value="1" id="abre_por_localidad" @checked(old('abre_por_localidad'))>
                                <label class="form-check-label" for="abre_por_localidad">Abrir por localidad (un certificado por zona)</label>
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="genera_web" value="1" id="genera_web" @checked(old('genera_web', true))>
                                <label class="form-check-label" for="genera_web">Generar archivo WEB (XML SENASA)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('¿Generar certificado(s) WEB?');">
                        <i class="fa fa-save"></i> Generar certificado(s)
                    </button>
                </div>
            </form>
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
