<a href="{{route($ruta, ['formato' => 'PDF', 'busqueda' => $busqueda, 'id' => $id])}}" class="btn btn-app bg-danger">
    <i class="fas fa-file-pdf"></i> Pdf
</a>
<a href="{{route($ruta, ['formato' => 'EXCEL', 'busqueda' => $busqueda, 'id' => $id])}}" class="btn btn-app bg-success">
    <i class="fas fa-file-excel"></i> Excel
</a>
<a href="{{route($ruta, ['formato' => 'CSV', 'busqueda' => $busqueda, 'id' => $id])}}" class="btn btn-app bg-warning">
    <i class="fas fa-file-csv"></i> Csv
</a>