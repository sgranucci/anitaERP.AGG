@extends("theme.$theme.layout")
@section('titulo')
    Configuración de tickets
@endsection

@section('styles')
<style>
    .ticket-cfg-page .ticket-cfg-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .ticket-cfg-page .ticket-cfg-hero h2 {
        font-size: 1.35rem;
        font-weight: 600;
        margin: 0 0 .35rem;
        color: #1f2a37;
    }
    .ticket-cfg-page .ticket-cfg-hero p {
        margin: 0;
        max-width: 46rem;
        color: #6b7280;
        font-size: .925rem;
        line-height: 1.45;
    }
    .ticket-cfg-page .ticket-cfg-stats {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
    }
    .ticket-cfg-page .ticket-cfg-stat {
        min-width: 7.5rem;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: .5rem;
        padding: .65rem .9rem;
        text-align: center;
    }
    .ticket-cfg-page .ticket-cfg-stat strong {
        display: block;
        font-size: 1.25rem;
        line-height: 1.2;
        color: #111827;
    }
    .ticket-cfg-page .ticket-cfg-stat span {
        font-size: .75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .ticket-cfg-page .card-ticket-cfg {
        border: 1px solid #e5e7eb;
        border-radius: .65rem;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .ticket-cfg-page .card-ticket-cfg > .card-header {
        background: linear-gradient(180deg, #f8fafc 0%, #f3f4f6 100%);
        border-bottom: 1px solid #e5e7eb;
        color: #111827;
        padding: .9rem 1.1rem;
    }
    .ticket-cfg-page .card-ticket-cfg > .card-header .card-title {
        font-size: 1.05rem;
        font-weight: 600;
        margin: 0;
    }
    .ticket-cfg-page .card-ticket-cfg > .card-header .card-subtitle {
        display: block;
        margin-top: .25rem;
        font-size: .82rem;
        font-weight: 400;
        color: #6b7280;
    }
    .ticket-cfg-page .ticket-cfg-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .85rem;
    }
    .ticket-cfg-page .ticket-cfg-search {
        position: relative;
        flex: 1 1 16rem;
        max-width: 22rem;
    }
    .ticket-cfg-page .ticket-cfg-search i {
        position: absolute;
        left: .75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: .85rem;
    }
    .ticket-cfg-page .ticket-cfg-search input {
        padding-left: 2.1rem;
        border-radius: .45rem;
        border-color: #d1d5db;
    }
    .ticket-cfg-page .ticket-cfg-filter .btn {
        border-radius: .4rem;
        font-size: .8rem;
        padding: .3rem .7rem;
    }
    .ticket-cfg-page .table-ticket-cfg thead th {
        background: #eef6fb;
        color: #1f2937;
        border-bottom-width: 1px;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        white-space: nowrap;
        vertical-align: middle;
    }
    .ticket-cfg-page .table-ticket-cfg tbody td {
        vertical-align: middle;
        font-size: .925rem;
    }
    .ticket-cfg-page .cc-codigo {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: .85rem;
        color: #374151;
        background: #f3f4f6;
        border-radius: .3rem;
        padding: .15rem .45rem;
    }
    .ticket-cfg-page .cc-usuarios {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        color: #4b5563;
        font-size: .875rem;
    }
    .ticket-cfg-page .custom-switch.ticket-cfg-switch {
        padding-left: 2.75rem;
        margin: 0;
    }
    .ticket-cfg-page .custom-switch.ticket-cfg-switch .custom-control-label {
        cursor: pointer;
        font-size: .8rem;
        color: #6b7280;
        padding-top: .1rem;
    }
    .ticket-cfg-page .custom-switch.ticket-cfg-switch .custom-control-label::before {
        height: 1.35rem;
        width: 2.35rem;
        border-radius: 1rem;
        left: -2.75rem;
        top: .1rem;
    }
    .ticket-cfg-page .custom-switch.ticket-cfg-switch .custom-control-label::after {
        width: calc(1.35rem - 4px);
        height: calc(1.35rem - 4px);
        border-radius: 50%;
        left: calc(-2.75rem + 2px);
        top: calc(.1rem + 2px);
    }
    .ticket-cfg-page .custom-switch.ticket-cfg-switch .custom-control-input:checked ~ .custom-control-label::after {
        transform: translateX(1rem);
    }
    .ticket-cfg-page .custom-switch.ticket-cfg-switch .custom-control-input:checked ~ .custom-control-label {
        color: #047857;
        font-weight: 600;
    }
    .ticket-cfg-page tr.ticket-cfg-row-on {
        background: #f0fdf4;
    }
    .ticket-cfg-page tr.ticket-cfg-row-claim {
        background: #fff7ed;
    }
    .ticket-cfg-page tr.ticket-cfg-row-hidden {
        display: none;
    }
    .ticket-cfg-page .ticket-cfg-empty {
        text-align: center;
        color: #6b7280;
        padding: 1.5rem;
    }
    .ticket-cfg-page .card-footer {
        background: #fafafa;
        border-top: 1px solid #e5e7eb;
    }
    .ticket-cfg-page .modo-select {
        max-width: 18rem;
        margin: 0 auto;
        font-size: .875rem;
    }
    .ticket-cfg-page .badge-modo-claim {
        background: #ea580c;
    }
    .ticket-cfg-page .badge-modo-dispatch {
        background: #64748b;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ticket/configuracion/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ticket/configuracion/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $puedeActualizar = can('actualizar-configuracion-ticket', false);
    $totalFilas = $filas->count();
    $totalAreas = $filasArea->count();
@endphp
<div class="row ticket-cfg-page">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="ticket-cfg-hero">
            <div>
                <h2><i class="fa fa-cog text-muted mr-1"></i> Configuración de tickets</h2>
                <p>
                    Ajustes del módulo por área y por centro de costo. Sin configuración, el comportamiento
                    se mantiene como hasta ahora (asignación por administrador, mail solo al creador).
                    Pensado para ir sumando logística, laboratorio, etc.
                </p>
            </div>
            <div class="ticket-cfg-stats">
                <div class="ticket-cfg-stat">
                    <strong>{{ $totalAreas }}</strong>
                    <span>Áreas</span>
                </div>
                <div class="ticket-cfg-stat">
                    <strong id="ticket-cfg-stat-claim">{{ $areasClaim }}</strong>
                    <span>Modo cola</span>
                </div>
                <div class="ticket-cfg-stat">
                    <strong id="ticket-cfg-stat-activos">{{ $activos }}</strong>
                    <span>Aviso CC</span>
                </div>
            </div>
        </div>

        {{-- Modo de operación por área --}}
        <div class="card card-ticket-cfg">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-sitemap mr-1"></i> Modo de operación por área
                </h3>
                <span class="card-subtitle">
                    <strong>Asignación por administrador</strong> (Sistemas): un encargado asigna técnicos.
                    <strong>Cola / el técnico toma</strong> (Mantenimiento): el ticket queda sin asignar y el técnico lo toma de la bandeja.
                </span>
            </div>

            <form action="{{ route('actualiza_configuracion_ticket_areadestino') }}" method="POST" id="form-ticket-configuracion-area" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered mb-0 table-ticket-cfg" id="tabla-ticket-configuracion-area">
                            <thead>
                                <tr>
                                    <th style="width:8%">ID</th>
                                    <th>Área destino</th>
                                    <th style="width:12%" class="text-center">Técnicos</th>
                                    <th style="width:42%" class="text-center">Modo de operación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filasArea as $fila)
                                    @php
                                        $esClaim = $fila->modo_operacion === \App\Support\Ticket\TicketModoOperacionSupport::MODO_CLAIM;
                                        $selectId = 'modo_area_'.$fila->areadestino_id;
                                    @endphp
                                    <tr class="{{ $esClaim ? 'ticket-cfg-row-claim' : '' }}"
                                        data-modo="{{ $fila->modo_operacion }}">
                                        <td>
                                            <span class="cc-codigo">{{ $fila->areadestino_id }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $fila->nombre }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="cc-usuarios">
                                                <i class="fa fa-wrench"></i> {{ $fila->tecnicos }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($puedeActualizar)
                                                <select class="form-control form-control-sm modo-select ticket-cfg-modo"
                                                        id="{{ $selectId }}"
                                                        name="modo_operacion[{{ $fila->areadestino_id }}]">
                                                    @foreach ($modosOperacion as $valor => $etiqueta)
                                                        <option value="{{ $valor }}" @selected($fila->modo_operacion === $valor)>
                                                            {{ $etiqueta }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                @if ($esClaim)
                                                    <span class="badge badge-modo-claim">Cola / toma el técnico</span>
                                                @else
                                                    <span class="badge badge-modo-dispatch">Asignación admin</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="ticket-cfg-empty">No hay áreas destino cargadas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($puedeActualizar && $totalAreas > 0)
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Guardar modos de área
                        </button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Notificaciones por CC --}}
        <div class="card card-ticket-cfg">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-envelope-o mr-1"></i> Notificaciones por centro de costo
                </h3>
                <span class="card-subtitle">
                    Cuando un técnico responde un ticket, el mail va al usuario que lo originó.
                    Con el flag activo, se agrega en copia (CC) a los demás usuarios activos de ese
                    centro de costo <strong>y la misma empresa de origen</strong> del ticket
                    (<code>ticket.empresa_id</code> / <code>usuario_empresa</code>). Tope blando:
                    {{ (int) config('ticket.notificacion_cc_max_destinatarios', 100) }} destinatarios en CC
                    (Office 365 suele admitir hasta ~500 entre To+CC+Bcc).
                </span>
            </div>

            <form action="{{ route('actualiza_configuracion_ticket') }}" method="POST" id="form-ticket-configuracion" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="card-body">
                    <div class="ticket-cfg-toolbar">
                        <div class="ticket-cfg-search">
                            <i class="fa fa-search"></i>
                            <input type="search" id="ticket-cfg-filtro" class="form-control form-control-sm"
                                   placeholder="Buscar por código o nombre…" aria-label="Buscar centro de costo">
                        </div>
                        <div class="ticket-cfg-filter btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
                            <button type="button" class="btn btn-outline-secondary active" data-filtro="todos">Todos</button>
                            <button type="button" class="btn btn-outline-secondary" data-filtro="activos">Solo activos</button>
                            <button type="button" class="btn btn-outline-secondary" data-filtro="inactivos">Sin configurar</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered mb-0 table-ticket-cfg" id="tabla-ticket-configuracion">
                            <thead>
                                <tr>
                                    <th style="width:7%">Código</th>
                                    <th>Centro de costo</th>
                                    <th style="width:10%">Abrev.</th>
                                    <th style="width:12%" class="text-center">Usuarios</th>
                                    <th style="width:28%" class="text-center">CC en respuesta del técnico</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    @php
                                        $activo = (bool) $fila->notificar_comentario_a_cc;
                                        $switchId = 'notif_cc_'.$fila->centrocosto_id;
                                    @endphp
                                    <tr class="{{ $activo ? 'ticket-cfg-row-on' : '' }}"
                                        data-nombre="{{ mb_strtolower($fila->nombre.' '.$fila->codigo.' '.($fila->abreviatura ?? '')) }}"
                                        data-activo="{{ $activo ? '1' : '0' }}">
                                        <td>
                                            <span class="cc-codigo">{{ $fila->codigo ?: '—' }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $fila->nombre }}</strong>
                                        </td>
                                        <td class="text-muted">{{ $fila->abreviatura ?: '—' }}</td>
                                        <td class="text-center">
                                            <span class="cc-usuarios" title="Usuarios activos del centro de costo">
                                                <i class="fa fa-users"></i> {{ $fila->usuarios_activos }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($puedeActualizar)
                                                <div class="custom-control custom-switch ticket-cfg-switch d-inline-block text-left">
                                                    <input type="checkbox"
                                                           class="custom-control-input ticket-cfg-toggle"
                                                           id="{{ $switchId }}"
                                                           name="notificar_comentario_a_cc[{{ $fila->centrocosto_id }}]"
                                                           value="1"
                                                           @checked($activo)>
                                                    <label class="custom-control-label" for="{{ $switchId }}">
                                                        <span class="ticket-cfg-toggle-label">{{ $activo ? 'Activo' : 'Como ahora' }}</span>
                                                    </label>
                                                </div>
                                            @else
                                                @if ($activo)
                                                    <span class="badge badge-success">Activo</span>
                                                @else
                                                    <span class="badge badge-secondary">Como ahora</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="ticket-cfg-empty">No hay centros de costo cargados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-3 mb-0" id="ticket-cfg-sin-resultados" style="display:none;">
                        Ningún centro de costo coincide con el filtro.
                    </p>
                </div>

                @if ($puedeActualizar && $totalFilas > 0)
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Guardar notificaciones
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
