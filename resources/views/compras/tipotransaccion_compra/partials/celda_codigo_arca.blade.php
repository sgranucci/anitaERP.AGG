@php
    $descArca = \App\Services\Arca\ArcaTiposComprobanteCatalogoService::descripcionCodigo((string) ($data->codigoafip ?? ''));
@endphp
{{ $data->codigoafip }}
@if ($descArca !== '')
    — {{ $descArca }}
@endif
