@php
    $params = array_filter($queryparams ?? [], fn ($v) => $v !== null && $v !== '');
    $suffix = count($params) ? '?'.http_build_query($params) : '';
@endphp
<a href="{{ route($ruta, ['formato' => 'PDF']).$suffix }}" class="btn btn-app bg-danger">
    <i class="fas fa-file-pdf"></i> Pdf
</a>
<a href="{{ route($ruta, ['formato' => 'EXCEL']).$suffix }}" class="btn btn-app bg-success">
    <i class="fas fa-file-excel"></i> Excel
</a>
<a href="{{ route($ruta, ['formato' => 'CSV']).$suffix }}" class="btn btn-app bg-warning">
    <i class="fas fa-file-csv"></i> Csv
</a>
