@php
    $titulos = [
        'sku' => 'SKU',
        'codigobarra' => 'Cód. barra',
        'descripcion' => 'Descripción',
        'unidadmedida' => 'Unidad de Medida',
        'categoria' => 'Categoría',
        'tipoarticulo' => 'Tipo de Artículo',
        'usoarticulo' => 'Uso',
        'canal' => 'Canal',
        'empresa' => 'Empresa',
        'numeroparte' => 'Nro.Parte',
        'ubicacionparte' => 'Ubic.Parte',
        'saldo' => 'Saldo dep.',
        'nofactura' => 'Facturable',
        'estado' => 'Estado',
        'fecha_alta' => 'Fecha de alta',
        'fecha_modificacion' => 'Fecha de modificación',
    ];
    $titulo = $tituloColArticulo($key, $titulos[$key] ?? $key);
    $ordenables = ['sku', 'codigobarra', 'descripcion'];
@endphp
<th class="{{ $key === 'saldo' ? 'text-right' : '' }}" @if ($key === 'saldo') title="Saldo en Anita (stkdep) para artículos LAB con depósito de entrega" @endif>
    @if (in_array($key, $ordenables, true))
        <a href="{{ $urlOrdenArticulo($key) }}" class="text-dark">{{ $titulo }}{{ $marcaOrdenArticulo($key) }}</a>
    @else
        {{ $titulo }}
    @endif
</th>
