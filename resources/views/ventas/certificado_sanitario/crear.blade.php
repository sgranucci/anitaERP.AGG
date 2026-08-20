@extends("theme.$theme.layout")
@section('titulo')
    Generar certificado sanitario
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/transporte/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/transporte/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/zonavta/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/zonavta/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/camion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/camion/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/certificado_sanitario/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/certificado_sanitario/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script>
$(function () {
    if (typeof activa_eventos_consultatransporte === 'function') {
        activa_eventos_consultatransporte();
    }
    if (typeof activa_eventos_consultazonavta === 'function') {
        activa_eventos_consultazonavta();
    }
    if (typeof activa_eventos_consultacliente === 'function') {
        activa_eventos_consultacliente();
    }
    if (typeof activa_eventos_consultacamion === 'function') {
        activa_eventos_consultacamion();
    }
    var $reparto = $('#codigotransporte');
    if ($reparto.length) {
        $reparto.trigger('focus');
    }
});
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Generar solicitud WEB SENASA</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_certificado_sanitario')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>

            <form method="GET" action="{{route('crear_certificado_sanitario')}}" class="form-horizontal" id="form-consulta-certsan" autocomplete="off">
                <div class="card-body">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group row">
                        <label for="fecha" class="col-lg-3 control-label text-right pr-2 requerido">Fecha entrega</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', $filtros['fecha'] ?? '') }}" required>
                        </div>
                    </div>
                    <div class="form-group row tm-transporte-campo">
                        <label for="codigotransporte" class="col-lg-3 control-label text-right pr-2">Reparto</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="hidden" class="transporte_id" name="transporte_id" id="transporte_id"
                                    value="{{ old('transporte_id', $filtros['transporte_id'] ?? '') }}">
                                <input type="text" class="form-control codigotransporte" id="codigotransporte" name="codigotransporte"
                                    value="{{ old('codigotransporte', optional($transporteSeleccionado)->codigo ?? '') }}"
                                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="max-width:7rem;">
                                <input type="text" class="form-control nombretransporte" id="nombretransporte"
                                    value="{{ old('nombretransporte', optional($transporteSeleccionado)->nombre ?? '') }}"
                                    placeholder="Todos" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultatransporte" title="Consultar repartos (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row tm-zonavta-campo">
                        <label for="codigozonavta" class="col-lg-3 control-label text-right pr-2">Zona de venta</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="hidden" class="zonavta_id" name="zonavta_id" id="zonavta_id"
                                    value="{{ old('zonavta_id', $filtros['zonavta_id'] ?? '') }}">
                                <input type="text" class="form-control codigozonavta" id="codigozonavta" name="codigozonavta"
                                    value="{{ old('codigozonavta', optional($zonaSeleccionada)->codigo ?? '') }}"
                                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="max-width:7rem;">
                                <input type="text" class="form-control nombrezonavta" id="nombrezonavta" name="nombrezonavta"
                                    value="{{ old('nombrezonavta', optional($zonaSeleccionada)->nombre ?? '') }}"
                                    placeholder="Todas" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultazonavta" title="Consultar zonas (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row tm-cliente-campo">
                        <label for="codigocliente" class="col-lg-3 control-label text-right pr-2">Cliente</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="hidden" class="cliente_id" name="cliente_id" id="cliente_id"
                                    value="{{ old('cliente_id', $filtros['cliente_id'] ?? '') }}">
                                <input type="text" class="form-control codigocliente" id="codigocliente" name="codigocliente"
                                    value="{{ old('codigocliente', optional($clienteSeleccionado)->codigo ?? '') }}"
                                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="max-width:7rem;">
                                <input type="text" class="form-control nombrecliente" id="nombrecliente"
                                    value="{{ old('nombrecliente', optional($clienteSeleccionado)->nombre ?? '') }}"
                                    placeholder="Todos" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultacliente" title="Consultar clientes (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Fallback Anita</label>
                        <div class="col-lg-6">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="fallback_anita" value="1" id="fallback_anita"
                                    @checked(old('fallback_anita', $filtros['fallback_anita'] ?? true))>
                                <label class="form-check-label" for="fallback_anita">
                                    Si el pedido no est&aacute; en ERP, leerlo de Anita
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar pedidos
                    </button>
                </div>
            </form>
        </div>

        @if (!is_null($preview))
        @php
            $fmtN = static fn (float $n, int $dec = 2): string => number_format($n, $dec, ',', '.');
            $previewTotales = $previewTotales ?? ['kilos' => 0.0, 'cajas' => 0.0, 'lineas' => 0, 'pedidos' => 0];
            $previewFilas = $previewFilas ?? collect();
            $omitidosSinSenasa = $omitidosSinSenasa ?? collect();
            $bloquearGeneracion = $omitidosSinSenasa->isNotEmpty();
        @endphp
        @include('ventas.certificado_sanitario.partials.aviso_sin_senasa', [
            'omitidosSinSenasa' => $omitidosSinSenasa,
        ])
        <div class="card card-outline card-info mt-3">
            <div class="card-header">
                <h3 class="card-title">
                    Preview ({{ (int) $previewTotales['lineas'] }} l&iacute;neas
                    @if ((int) $previewTotales['pedidos'] > 0)
                        · {{ (int) $previewTotales['pedidos'] }} pedido{{ (int) $previewTotales['pedidos'] === 1 ? '' : 's' }}
                    @endif
                    )
                </h3>
                <div class="card-tools">
                    <span class="badge badge-info">
                        {{ $fmtN((float) $previewTotales['kilos']) }} kg
                        · {{ $fmtN((float) $previewTotales['cajas']) }} cajas
                    </span>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered mb-0" style="min-width: 1180px;">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Origen</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Transporte</th>
                            <th class="text-nowrap">SKU</th>
                            <th>Art&iacute;culo</th>
                            <th class="text-right">Kilos</th>
                            <th class="text-right">Cajas</th>
                            <th>Frio</th>
                            <th>Registro SENASA</th>
                            <th>Amparo origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($previewFilas as $fila)
                            @php $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
                            @if ($tipo === 'subtotal_pedido')
                                <tr class="font-weight-bold" style="background-color:#e9ecef;">
                                    <td colspan="6">Subtotal pedido {{ $fila['codigoPedido'] }}</td>
                                    <td class="text-right">{{ $fmtN((float) $fila['kilos']) }}</td>
                                    <td class="text-right">{{ $fmtN((float) $fila['cajas']) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                            @elseif ($tipo === 'total_final')
                                <tr class="font-weight-bold" style="background-color:#d6eaf8;">
                                    <td colspan="6">TOTAL FINAL</td>
                                    <td class="text-right">{{ $fmtN((float) $fila['kilos']) }}</td>
                                    <td class="text-right">{{ $fmtN((float) $fila['cajas']) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                            @else
                                @php $l = $fila['linea']; @endphp
                                <tr>
                                    <td>{{ strtoupper($l->origen) }}</td>
                                    <td class="text-nowrap">{{ $l->codigoPedido }}</td>
                                    <td>{{ $l->codigoCliente }} {{ $l->clienteNombre }}</td>
                                    <td>{{ $l->codigoTransporte }}</td>
                                    <td class="text-nowrap">{{ $l->sku }}</td>
                                    <td>{{ $l->articuloNombre }}</td>
                                    <td class="text-right text-nowrap">{{ $fmtN((float) $l->kilos) }}</td>
                                    <td class="text-right text-nowrap">{{ $fmtN((float) $l->cajas) }}</td>
                                    <td class="text-nowrap">{{ $l->llevafrio }}</td>
                                    <td class="text-nowrap">{{ $l->prefijoSenasa !== '' ? $l->prefijoSenasa.'-' : '' }}{{ $l->registroSenasa }}</td>
                                    <td class="text-nowrap">{{ $l->certificadoOrigen !== '' ? $l->certificadoOrigen : '—' }}</td>
                                </tr>
                            @endif
                        @empty
                        <tr><td colspan="11" class="text-center text-muted py-3">Sin l&iacute;neas SENASA para los filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($preview->count() > 0 && $bloquearGeneracion)
        <div class="alert alert-warning mt-3 mb-0">
            <i class="fa fa-ban"></i>
            La generaci&oacute;n qued&oacute; bloqueada hasta que esos art&iacute;culos tengan c&oacute;digo SENASA.
            C&aacute;rguelo en el ABM (links de arriba) y vuelva a consultar.
        </div>
        @endif

        @if ($preview->count() > 0 && ! $bloquearGeneracion)
        <div class="card card-primary mt-3">
            <div class="card-header">
                <h3 class="card-title">Datos del certificado y generaci&oacute;n WEB</h3>
                <div class="card-tools">
                    <span class="badge badge-light">
                        {{ number_format((float) ($previewTotales['kilos'] ?? 0), 2, ',', '.') }} kg
                        · {{ number_format((float) ($previewTotales['cajas'] ?? 0), 2, ',', '.') }} cajas
                    </span>
                </div>
            </div>
            <form method="POST" action="{{route('guardar_certificado_sanitario')}}" class="form-horizontal" id="form-generar-certsan" autocomplete="off">
                @csrf
                <input type="hidden" name="fecha" value="{{ $filtros['fecha'] }}">
                <input type="hidden" name="transporte_id" value="{{ $filtros['transporte_id'] }}">
                <input type="hidden" name="zonavta_id" value="{{ $filtros['zonavta_id'] }}">
                <input type="hidden" name="cliente_id" value="{{ $filtros['cliente_id'] }}">
                <input type="hidden" name="fallback_anita" value="{{ !empty($filtros['fallback_anita']) ? 1 : 0 }}">
                <div class="card-body">
                    @include('ventas.partials.campo_consulta_camion', [
                        'camionId' => old('camion_id', optional($camionSeleccionado)->id ?? ''),
                        'codigo' => old('camion_codigo', optional($camionSeleccionado)->codigo ?? ''),
                        'descripcion' => old('camion_descripcion', optional($camionSeleccionado)->descripcionConsulta() ?? ''),
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'focusSiguiente' => '#temperatura',
                    ])
                    <div class="form-group row">
                        <label for="temperatura" class="col-lg-3 control-label text-right pr-2">Temperatura</label>
                        <div class="col-lg-2">
                            <input type="number" step="0.1" name="temperatura" id="temperatura" class="form-control" value="{{ old('temperatura', 7) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nro_remito" class="col-lg-3 control-label text-right pr-2">Nro. remito</label>
                        <div class="col-lg-2">
                            <input type="number" name="nro_remito" id="nro_remito" class="form-control" value="{{ old('nro_remito') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="cantidad_precinto" class="col-lg-3 control-label text-right pr-2">Cant. precintos</label>
                        <div class="col-lg-2">
                            <input type="number" name="cantidad_precinto" id="cantidad_precinto" class="form-control" min="0" max="99" value="{{ old('cantidad_precinto', optional($camionSeleccionado)->cantidad_precinto ?? 0) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="precinto" class="col-lg-3 control-label text-right pr-2">Precintos</label>
                        <div class="col-lg-3">
                            <input type="text" name="precinto" id="precinto" maxlength="15" class="form-control" value="{{ old('precinto') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="establecimiento_destino" class="col-lg-3 control-label text-right pr-2">Establ. destino</label>
                        <div class="col-lg-2">
                            <input type="number" name="establecimiento_destino" id="establecimiento_destino" class="form-control" min="0" max="9999" value="{{ old('establecimiento_destino') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Apertura</label>
                        <div class="col-lg-6">
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
                <div class="card-footer text-right">
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
@include('includes.ventas.modalconsultatransporte')
@include('includes.ventas.modalconsultazonavta')
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultacamion')
@endsection
