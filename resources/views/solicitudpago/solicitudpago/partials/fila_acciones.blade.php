@php
    $retornoListadoQuery = $retornoListadoQuery ?? [];
    $esHija = (int) ($data->solicitudpago_madre_id ?? 0) > 0;
    $cuotasTotal = (int) ($data->cuotas_total_count ?? 0);
@endphp
@if (can('listar-solicitud-pago', false) || can('editar-solicitud-pago', false))
    <a href="{{ route('imprimir_pdf_solicitudpago', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Emitir comprobante PDF" target="_blank" rel="noopener noreferrer">
        <i class="fas fa-print"></i>
    </a>
@endif
@if ($cuotasTotal > 0 || $esHija)
    <button type="button" class="btn-accion-tabla tooltipsC btn-sp-ver-plan" title="Ver plan / cuotas vinculadas"
            data-url="{{ route('familia_vinculos_solicitudpago', $data->id) }}" data-codigo="{{ $data->codigo }}">
        <i class="fa fa-sitemap text-info"></i>
    </button>
@endif
@if (can('editar-solicitud-pago', false))
    <a href="{{ route('editar_solicitudpago', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
        <i class="fa fa-edit"></i>
    </a>
@endif
@if (
    can('actualizar-solicitud-pago', false)
    && can('crear-ingresos-egresos-caja', false)
    && ($data->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::AUTORIZADA
)
    <a href="{{ route('ir_a_pago_solicitudpago', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Pagar (IE)">
        <i class="fa fa-money text-success"></i>
    </a>
@endif
@if (
    ($data->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::PAGADA
    || ($data->cajaMovimientosPago ?? collect())->isNotEmpty()
)
    @php $pagosFila = $data->cajaMovimientosPago ?? collect(); @endphp
    @if ($pagosFila->isNotEmpty() && (can('listar-ingresos-egresos-caja', false) || can('editar-ingresos-egresos-caja', false)))
        @foreach ($pagosFila->take(3) as $pagoFila)
            <a href="{{ route('editar_ingresoegreso', ['id' => $pagoFila->id, 'origen' => 'solicitudpago']) }}"
               class="btn-accion-tabla tooltipsC text-primary"
               title="Abrir pago {{ $pagoFila->tipotransaccioncajas->abreviatura ?? 'OP' }} {{ $pagoFila->numerotransaccion }}"
               target="_blank" rel="noopener">
                <i class="fa fa-file-invoice-dollar"></i>
            </a>
        @endforeach
        <a href="{{ route('ingresoegreso', ['solicitudpago_id' => $data->id, 'empresa_todas' => 1]) }}"
           class="btn-accion-tabla tooltipsC" title="Listar pagos de esta SP en caja" target="_blank" rel="noopener">
            <i class="fa fa-list text-info"></i>
        </a>
    @endif
@endif
@if (can('anular-pago-solicitud-pago', false) && ($data->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::PAGADA)
    <form action="{{ route('anular_pago_solicitudpago', $data->id) }}" class="d-inline form-anular-pago-sp" method="POST">
        @csrf
        <button type="submit" class="btn-accion-tabla tooltipsC" title="Anular pago físicamente (borra OP)">
            <i class="fa fa-ban text-danger"></i>
        </button>
    </form>
@endif
@if (can('revertir-pago-solicitud-pago', false) && ($data->estado ?? '') === \App\Support\Solicitudpago\SolicitudpagoEstados::PAGADA)
    <form action="{{ route('revertir_pago_solicitudpago', $data->id) }}" class="d-inline form-revertir-pago-sp" method="POST">
        @csrf
        <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir pago (compensatorio)">
            <i class="fa fa-undo text-warning"></i>
        </button>
    </form>
@endif
@if (can('borrar-solicitud-pago', false) && \App\Support\Solicitudpago\SolicitudpagoEstados::esAdministradorSesion())
    <form action="{{ route('eliminar_solicitudpago', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
        @csrf @method("delete")
        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar (solo administrador)">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </form>
@endif
