@php
    $esPdf = ! empty($es_pdf);
    $primer = $bloques[0] ?? null;
    $liqId = $liq->id ?? ($primer['recibo']->liquidacion_id ?? null);
    $reciboId = $recibo->id ?? ($primer['recibo']->id ?? null);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $primer['legajo'] ?? '' }} — Anexo III{{ !empty($multiempresa) ? ' multiempresa' : '' }}</title>
    <style>@include('sueldos.liquidacion.partials.recibo_anexo_iii_estilos')</style>
</head>
<body>
@if (! $esPdf && $liqId && $reciboId)
<div class="preview-bar">
    Vista previa recibo Anexo III
    @if (!empty($multiempresa))
        <strong>· Multiempresa</strong> ({{ count($bloques) }} empresa{{ count($bloques) === 1 ? '' : 's' }})
    @endif
    <a href="{{ route('pdf_recibo_liquidacion_sueldos', ['id' => $liqId, 'reciboId' => $reciboId, 'multiempresa' => !empty($multiempresa) ? 1 : 0]) }}" target="_blank" rel="noopener">PDF</a>
    <a href="{{ route('preview_recibo_liquidacion_sueldos', ['id' => $liqId, 'reciboId' => $reciboId, 'multiempresa' => empty($multiempresa) ? 1 : 0]) }}">
        {{ !empty($multiempresa) ? 'Ver solo esta empresa' : 'Incluir otras empresas' }}
    </a>
    <a href="{{ route('resultado_liquidacion_sueldos', ['id' => $liqId]) }}" id="btn-cerrar-preview-recibo">Cerrar y volver</a>
</div>
<script>
(function () {
    var btn = document.getElementById('btn-cerrar-preview-recibo');
    if (!btn) return;
    btn.addEventListener('click', function (ev) {
        try {
            if (window.opener && !window.opener.closed) {
                ev.preventDefault();
                window.opener.focus();
                window.close();
            }
        } catch (err) {
            // same-origin fallido: deja navegar al resultado
        }
    });
})();
</script>
@endif

@foreach ($bloques as $idx => $d)
    @include('sueldos.liquidacion.partials.recibo_anexo_iii_cuerpo', ['d' => $d])
    @if (! $loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
