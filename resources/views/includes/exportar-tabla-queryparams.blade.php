@php
    $params = array_filter($queryparams ?? [], fn ($v) => $v !== null && $v !== '');
    $suffix = count($params) ? '?'.http_build_query($params) : '';
    $variant = $variant ?? 'app';
@endphp
@if ($variant === 'compact')
    <span class="d-inline-flex align-items-center flex-nowrap ml-1" role="group" aria-label="Exportar listado">
        <a href="{{ route($ruta, ['formato' => 'PDF']).$suffix }}" class="btn btn-sm bg-danger mr-1" title="Exportar PDF">
            <i class="fas fa-file-pdf"></i> Pdf
        </a>
        <a href="{{ route($ruta, ['formato' => 'EXCEL']).$suffix }}" class="btn btn-sm bg-success mr-1" title="Exportar Excel">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="{{ route($ruta, ['formato' => 'CSV']).$suffix }}" class="btn btn-sm bg-warning" title="Exportar CSV">
            <i class="fas fa-file-csv"></i> Csv
        </a>
    </span>
@else
    <a href="{{ route($ruta, ['formato' => 'PDF']).$suffix }}" class="btn btn-app bg-danger">
        <i class="fas fa-file-pdf"></i> Pdf
    </a>
    <a href="{{ route($ruta, ['formato' => 'EXCEL']).$suffix }}" class="btn btn-app bg-success">
        <i class="fas fa-file-excel"></i> Excel
    </a>
    <a href="{{ route($ruta, ['formato' => 'CSV']).$suffix }}" class="btn btn-app bg-warning">
        <i class="fas fa-file-csv"></i> Csv
    </a>
@endif
