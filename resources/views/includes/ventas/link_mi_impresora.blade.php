@php
    $programaMiImpresora = $programaMiImpresora
        ?? \App\Support\Configuracion\SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES;
    $claseBtnMiImpresora = $claseBtnMiImpresora ?? 'btn btn-outline-secondary btn-sm';
    $urlMiImpresora = route('configurar_salida', [
        'programa' => $programaMiImpresora,
        'origen' => 'modal_consulta',
        'vista' => 'consulta',
    ]);
@endphp
<a href="{{ $urlMiImpresora }}"
   class="{{ $claseBtnMiImpresora }}"
   target="_blank"
   rel="noopener"
   onclick="window.open(this.href, '_blank'); return false;"
   title="Elegí la impresora de papel de la sesión de comprobantes">
    <i class="fa fa-fw fa-print"></i> Mi impresora
</a>
