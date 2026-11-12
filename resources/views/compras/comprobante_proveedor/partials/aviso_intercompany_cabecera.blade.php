@php
    $titulo = trim((string) ($titulo ?? ''));
    if ($titulo === '') {
        $titulo = 'Misma factura en otra empresa';
    }
    $mensaje = $mensaje ?? '';
    $comprobantes = is_array($comprobantes ?? null) ? $comprobantes : [];
@endphp
@if ($mensaje !== '')
<div class="alert alert-warning" id="cp-aviso-intercompany-cabecera">
    <h5 class="mb-2"><i class="fa fa-exclamation-triangle"></i> {{ $titulo }}</h5>
    <p class="mb-2">{{ $mensaje }}</p>
    @if ($comprobantes !== [])
        <ul class="mb-0 pl-3">
            @foreach ($comprobantes as $otro)
                @php
                    $otroId = (int) ($otro['id'] ?? 0);
                    $empresa = trim((string) ($otro['empresa'] ?? ''));
                @endphp
                @if ($otroId > 0)
                    <li>
                        {{ $empresa !== '' ? $empresa.' — ' : '' }}
                        <a href="{{ route('editar_comprobante_proveedor', [
                            'id' => $otroId,
                            'origen' => 'modal_consulta',
                            'vista' => 'consulta',
                        ]) }}"
                           class="text-primary"
                           target="_blank"
                           rel="noopener">
                            Abrir comprobante #{{ $otroId }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</div>
@endif
