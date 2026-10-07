@php
    use App\Support\Logistica\SolicitudLogisticaListadoColumnas;
    $alineado = in_array($key, ['total', 'items', 'numero'], true) ? 'text-right' : '';
@endphp
<td class="{{ $alineado }}">
    @if ($key === 'numero')
        <a href="{{ route('ver_logistica_solicitud', $data->id) }}" class="text-primary">{{ SolicitudLogisticaListadoColumnas::numeroVisible($data) }}</a>
    @elseif ($key === 'compromiso')
        {{ SolicitudLogisticaListadoColumnas::valorCelda($data, $key) }}
        @if (\App\Support\Logistica\LogisticaPlazoSupport::vencida($data))
            <span class="badge badge-danger">Vencida</span>
        @endif
    @elseif ($key === 'estado')
        @php
            $estado = (string) ($data->estado ?? '');
            $badge = match ($estado) {
                'pendiente_aprobacion' => 'badge-warning',
                'aprobada' => 'badge-primary',
                'en_preparacion' => 'badge-info',
                'entregada', 'cerrada' => 'badge-success',
                'rechazada' => 'badge-danger',
                default => 'badge-secondary',
            };
        @endphp
        <span class="badge {{ $badge }}">{{ SolicitudLogisticaListadoColumnas::etiquetaEstado($estado) }}</span>
    @else
        {{ SolicitudLogisticaListadoColumnas::valorCelda($data, $key) }}
    @endif
</td>
