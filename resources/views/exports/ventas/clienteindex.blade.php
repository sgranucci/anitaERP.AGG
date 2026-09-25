<h2>Clientes</h2>
<table class="table table-striped table-bordered table-hover">
    @include('ventas.cliente.partials.tabla_listado_export', [
        'clientes' => $clientes,
        'columnasVisibles' => $columnasVisibles ?? null,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
</table>
