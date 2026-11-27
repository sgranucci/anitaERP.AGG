<div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
    <span class="small text-muted">
        L&iacute;neas de OC del per&iacute;odo con todos los filtros aplicados.
        Pdf, Excel y Csv salen con el mismo filtro (no solo la p&aacute;gina visible).
        Un Pdf de m&aacute;s de {{ number_format((int) config('compras.tablero_pdf.max_filas_sincrono'), 0, ',', '.') }} l&iacute;neas se arma en segundo plano y te llega por mail.
    </span>
    <div>
        @include('includes.exportar-tabla-queryparams', [
            'ruta' => 'listar_tablero_compras',
            'queryparams' => $filtrosQuery,
            'variant' => 'compact',
        ])
    </div>
</div>

<div class="tabla-ancha-grilla" data-tabla-ancha>
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
        <table class="table table-sm table-bordered table-hover mb-0 tabla-ancha" id="tabla-paginada">
            @include('compras.tablero_oc.partials.tabla_detalle', [
                'filas' => $detalle->items(),
                'pantalla' => true,
                'para_pdf' => false,
                'mostrar_cabecera' => true,
                'para_excel' => false,
                'puede_ver_ordencompra' => $puede_ver_ordencompra,
            ])
        </table>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between mt-2">
    <span class="small text-muted mb-2 mb-md-0">
        @if ($detalle->total() > 0)
            Mostrando {{ $detalle->firstItem() }}&ndash;{{ $detalle->lastItem() }} de {{ number_format($detalle->total(), 0, ',', '.') }} l&iacute;neas
        @else
            Sin l&iacute;neas para los filtros elegidos.
        @endif
    </span>
    {{ $detalle->appends($filtrosQuery + ['tab' => 'detalle'])->links() }}
</div>
