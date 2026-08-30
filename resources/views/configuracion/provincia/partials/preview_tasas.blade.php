@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filas = $filas ?? collect();
    $resumen = $resumen ?? ['provincias' => 0, 'alicuotas' => 0];
    foreach ($filas as $row) {
        if (! isset($row->nombreempresa) || $row->nombreempresa === '') {
            $row->nombreempresa = (string) config('app.empresa');
        }
    }
    $logosVista = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
@endphp
<div class="mb-2">
    <span class="badge badge-info mr-1">Provincias: {{ (int) ($resumen['provincias'] ?? 0) }}</span>
    <span class="badge badge-secondary">Al&iacute;cuotas: {{ (int) ($resumen['alicuotas'] ?? 0) }}</span>
</div>

<div class="mb-3">
    @include('includes.exportar-tabla-queryparams', [
        'ruta' => 'lista_provincia',
        'queryparams' => [],
    ])
</div>

<div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
    @foreach ($logosVista as $logo)
        <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
    @endforeach
    <div class="ml-auto text-muted small">
        Vista previa — columnas alineadas al reporte exportable
    </div>
</div>

<style>
    #tabla-reporte-tasas-iibb thead tr { background-color: #85C1E9; color: #17202A; }
    #tabla-reporte-tasas-iibb thead th { font-weight: 600; border-color: #7fb3d5; }
    #tabla-reporte-tasas-iibb .num { text-align: right; white-space: nowrap; }
</style>
<div class="table-responsive">
    @include('configuracion.provincia.partials.tabla_tasas', [
        'filas' => $filas,
        'tablaId' => 'tabla-reporte-tasas-iibb',
        'tablaClass' => 'table table-bordered table-hover table-sm mb-0',
        'mostrarColgroup' => false,
    ])
</div>
