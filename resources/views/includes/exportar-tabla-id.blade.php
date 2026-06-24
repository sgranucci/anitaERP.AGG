@php
    $paramsExport = array_merge(
        ['formato' => 'PDF', 'busqueda' => $busqueda ?? '', 'id' => $id],
        $queryExtra ?? [],
    );
    $paramsExcel = array_merge($paramsExport, ['formato' => 'EXCEL']);
    $paramsCsv = array_merge($paramsExport, ['formato' => 'CSV']);
@endphp
<a href="{{ route($ruta, $paramsExport) }}" class="btn btn-app bg-danger">
    <i class="fas fa-file-pdf"></i> Pdf
</a>
<a href="{{ route($ruta, $paramsExcel) }}" class="btn btn-app bg-success">
    <i class="fas fa-file-excel"></i> Excel
</a>
<a href="{{ route($ruta, $paramsCsv) }}" class="btn btn-app bg-warning">
    <i class="fas fa-file-csv"></i> Csv
</a>
