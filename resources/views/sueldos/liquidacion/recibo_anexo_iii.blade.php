@php
    $d = $datos;
    $esPdf = ! empty($es_pdf);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $d['legajo'] ?? '' }} — Anexo III</title>
    <style>@include('sueldos.liquidacion.partials.recibo_anexo_iii_estilos')</style>
</head>
<body>
@if (! empty($d['modo_preview']) && ! $esPdf)
<div class="preview-bar">
    Vista previa recibo Anexo III (Dto. 407)
    <a href="{{ route('pdf_recibo_liquidacion_sueldos', ['id' => $d['recibo']->liquidacion_id, 'reciboId' => $d['recibo']->id] + request()->only('multiempresa')) }}" target="_blank" rel="noopener">PDF</a>
    <a href="{{ route('resultado_liquidacion_sueldos', ['id' => $d['recibo']->liquidacion_id]) }}">Volver al resultado</a>
</div>
@endif
@include('sueldos.liquidacion.partials.recibo_anexo_iii_cuerpo', ['d' => $d])
</body>
</html>
