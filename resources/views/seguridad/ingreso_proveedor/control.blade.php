@extends("theme.$theme.layout")
@section('titulo')
    Control de ingreso
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/control.css') }}">
@endsection

@section('scripts')
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/seguridad/ingreso_proveedor/filtro.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/seguridad/ingreso_proveedor/control.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    use App\Support\Seguridad\IngresoProveedorListadoFiltros;
    $limpiarUrl = route('control_ingreso_proveedor', IngresoProveedorListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="porteria"
     data-url-buscar="{{ route('control_ingreso_buscar_dni') }}"
     data-url-entro="{{ route('control_ingreso_entro') }}"
     data-url-salio="{{ route('control_ingreso_salio') }}"
     data-empresa-id="{{ ($filtros['empresa_scope'] ?? '') === 'todas' ? '' : (int) ($filtros['empresa_id'] ?? 0) }}"
     data-empresa-todas="{{ ($filtros['empresa_scope'] ?? '') === 'todas' ? '1' : '0' }}"
     data-puede-registrar="{{ !empty($puedeRegistrarIngresoEgreso) ? '1' : '0' }}">

    <div class="porteria-hero">
        <div class="porteria-hero-copy">
            <p class="porteria-kicker">Seguridad &middot; Porter&iacute;a</p>
            <h1>Control de ingreso</h1>
            <p class="porteria-sub">Una persona. Un DNI. Dos decisiones: entr&oacute; o sali&oacute;. El resto queda para reportes y KPIs.</p>
        </div>
        <form id="porteria-form-dni" class="porteria-dni" autocomplete="off">
            @csrf
            <label for="porteria-dni">DNI / CUIL de quien llega</label>
            <div class="porteria-dni-row">
                <input type="text" id="porteria-dni" name="documento" inputmode="numeric" maxlength="20"
                       placeholder="30111222" autofocus>
                <button type="submit" class="porteria-btn-buscar">Buscar</button>
            </div>
            <p class="porteria-hint">Enter busca el ticket abierto en la empresa seleccionada.</p>
            <div id="porteria-alerta" class="porteria-alerta" hidden></div>
        </form>
    </div>

    <div id="porteria-ticket" class="porteria-ticket" hidden>
        <div class="porteria-ticket-head">
            <div>
                <p class="porteria-kicker" id="porteria-estado">Ticket</p>
                <h2 id="porteria-nombre">—</h2>
                <p class="porteria-dni-mostrar">DNI <strong id="porteria-doc">—</strong> &middot; <span id="porteria-empresa">—</span></p>
            </div>
            <div class="porteria-ticket-id">Ticket <span id="porteria-ticket-id">—</span></div>
        </div>
        <div class="porteria-grid-datos">
            <div><span>Fecha</span><strong id="porteria-fecha">—</strong></div>
            <div><span>Proveedor</span><strong id="porteria-proveedor">—</strong></div>
            <div><span>Motivo</span><strong id="porteria-motivo">—</strong></div>
            <div><span>Punto</span><strong id="porteria-punto">—</strong></div>
            <div><span>Sector</span><strong id="porteria-sector">—</strong></div>
            <div><span>&Aacute;rea</span><strong id="porteria-area">—</strong></div>
            <div><span>Patente</span><strong id="porteria-patente">—</strong></div>
            <div><span>T&iacute;tulo</span><strong id="porteria-titulo">—</strong></div>
        </div>
        <p class="porteria-comentario" id="porteria-comentario"></p>
        @if (!empty($puedeRegistrarIngresoEgreso))
        <div class="porteria-acciones">
            <input type="hidden" id="porteria-persona-id" value="">
            <button type="button" id="porteria-btn-entro" class="porteria-btn porteria-btn-entro">ENTRO</button>
            <button type="button" id="porteria-btn-salio" class="porteria-btn porteria-btn-salio">SALIO</button>
        </div>
        @else
            <input type="hidden" id="porteria-persona-id" value="">
            <p class="porteria-hint text-dark mt-3 mb-0">Solo Seguridad registra ENTRO / SALIO. Desde acá se consulta el ticket.</p>
        @endif
        <p class="porteria-reloj" id="porteria-reloj"></p>
    </div>

    <div class="card card-info porteria-grilla">
        <div class="card-header">
            <h3 class="card-title">Movimientos del d&iacute;a</h3>
            <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                <span class="badge badge-success mr-2" id="porteria-en-planta-count">0 en planta</span>
                @include('includes.listado.filtros_toolbar', [
                    'formId' => 'form-filtros-ingreso-proveedor',
                    'filtroValor' => $filtros['valor'] ?? '',
                    'tieneCriterios' => IngresoProveedorListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                    'limpiarUrl' => $limpiarUrl,
                    'placeholder' => 'Filtrar grilla…',
                    'toggleTarget' => '#panel-filtros-ingreso-proveedor',
                    'toggleId' => 'btn-toggle-filtros-ingreso-proveedor',
                    'inputId' => 'filtro_valor',
                ])
            </div>
        </div>
        <form method="get" action="{{ route('control_ingreso_proveedor') }}" id="form-filtros-ingreso-proveedor" class="mb-0">
            @include('seguridad.ingreso_proveedor.partials.filtros_listado', [
                'limpiarUrl' => $limpiarUrl,
            ])
        </form>
        @include('seguridad.ingreso_proveedor.partials.filtros_externos', [
            'rutaIndex' => 'control_ingreso_proveedor',
        ])
        <div class="card-body table-responsive p-0">
            <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Ticket</th>
                        <th>Empresa</th>
                        <th>DNI</th>
                        <th>Persona</th>
                        <th>Proveedor</th>
                        <th>Motivo</th>
                        <th>Punto</th>
                        <th>Estado</th>
                        <th>Entr&oacute;</th>
                        <th>Sali&oacute;</th>
                        <th>Min</th>
                    </tr>
                </thead>
                <tbody id="porteria-tbody">
                    @foreach ($filas as $fila)
                        @php $p = \App\Support\Seguridad\IngresoProveedorControlSupport::payloadPersona($fila); @endphp
                        <tr class="{{ !empty($p['en_planta']) ? 'porteria-fila-en-planta' : '' }}">
                            <td>{{ $p['ticket_id'] }}</td>
                            <td>{{ $p['empresa'] }}</td>
                            <td>{{ $p['documento'] }}</td>
                            <td>{{ $p['nombre'] }}</td>
                            <td>{{ $p['proveedor'] }}</td>
                            <td>{{ $p['motivo'] }}</td>
                            <td>{{ $p['punto'] }}</td>
                            <td>
                                <span class="badge badge-{{ IngresoProveedorEstados::badge((string) ($p['estado_codigo'] ?? '')) }}">{{ $p['estado'] }}</span>
                            </td>
                            <td>{{ $p['hora_ingreso'] }}</td>
                            <td>{{ $p['hora_egreso'] }}</td>
                            <td>{{ $p['minutos_en_planta'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
