@php
    $avisos = $avisos ?? [];
@endphp
@if (!empty($avisos))
    @foreach ($avisos as $aviso)
        @php
            $cls = match ($aviso['estado'] ?? '') {
                'vencido', 'faltante' => 'alert-danger portal-aviso-doc-vencido',
                'proximo', 'sin_fecha' => 'alert-warning',
                default => 'alert-info',
            };
        @endphp
        <div class="alert {{ $cls }}">
            <strong>{{ $aviso['etiqueta'] ?? 'Documento' }}:</strong>
            {{ $aviso['mensaje'] ?? '' }}
            <a class="alert-link ml-2"
               href="{{ route('portal_proveedores_documentos', ['proveedor_id' => $proveedorId ?? null]) }}">
                Ir a Documentación
            </a>
        </div>
    @endforeach
@endif
