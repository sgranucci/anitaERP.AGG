@php
    use App\Support\Sueldos\ComparativoConceptosListadoFiltros as F;
    $coleccion = $filas instanceof \Illuminate\Support\Collection || $filas instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $filas
        : collect($filas);
    $columnas = $resultado['columnas'] ?? [];
    $formato = $resultado['formato'] ?? F::FORMATO_IMPORTE;
    $muestraDif = ! empty($resultado['muestra_diferencia']);
    $mostrarCant = in_array($formato, [F::FORMATO_CANTIDAD, F::FORMATO_AMBOS], true);
    $mostrarImp = in_array($formato, [F::FORMATO_IMPORTE, F::FORMATO_AMBOS], true);
    $esPantalla = $esPantalla ?? true;
    $qs = ['origen' => 'modal_consulta', 'vista' => 'consulta'];

    $verEmpleado = $esPantalla && ! empty($puede_ver_empleado);
    $verConcepto = $esPantalla && ! empty($puede_ver_concepto);
    $verCcosto = $esPantalla && ! empty($puede_ver_centrocosto);
    $verCategoria = $esPantalla && ! empty($puede_ver_categoria);
    $verSindicato = $esPantalla && ! empty($puede_ver_sindicato);
    $verLiquidacion = $esPantalla && ! empty($puede_ver_liquidacion);

    $fmtN = function ($n, bool $force = false) {
        $n = (float) $n;
        if (! $force && abs($n) < 0.00001) {
            return '';
        }

        return number_format($n, 2, ',', '.');
    };

    $urlGrupo = function (array $f) use ($qs, $verCcosto, $verCategoria, $verSindicato) {
        $id = (int) ($f['grupo_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $tipo = (string) ($f['grupo_tipo'] ?? '');
        if ($tipo === 'ccosto' && $verCcosto) {
            return route('editar_centrocosto', array_merge(['id' => $id], $qs));
        }
        if ($tipo === 'categoria' && $verCategoria) {
            return route('editar_categoria_sueldos', array_merge(['id' => $id], $qs));
        }
        if ($tipo === 'sindicato' && $verSindicato) {
            return route('editar_sindicato_sueldos', array_merge(['id' => $id], $qs));
        }

        return null;
    };
@endphp

@if ($esPantalla)
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}"></script>
<div class="tabla-ancha-grilla" data-tabla-ancha>
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
@endif

<table class="table table-sm table-bordered table-striped mb-0 {{ $esPantalla ? 'tabla-ancha' : '' }}" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>N.Leg.</th>
            <th>Nombre</th>
            <th>F.Ingr.</th>
            <th>C.Costo</th>
            <th>Categor&iacute;a</th>
            <th>Sindicato</th>
            <th>N.Con.</th>
            <th>Concepto</th>
            @foreach ($columnas as $col)
                @php
                    $liqId = (int) ($col['liquidacion_id'] ?? 0);
                    $urlLiq = ($verLiquidacion && $liqId > 0)
                        ? route('resultado_liquidacion_sueldos', array_merge(['id' => $liqId], $qs))
                        : null;
                @endphp
                @if ($formato === F::FORMATO_AMBOS)
                    <th class="text-right" colspan="2">
                        @if ($urlLiq)
                            <a href="{{ $urlLiq }}" class="text-primary" target="_blank" rel="noopener">{{ $col['etiqueta'] }}</a>
                        @else
                            {{ $col['etiqueta'] }}
                        @endif
                        <br><small>{{ $col['periodo'] }}</small>
                    </th>
                @else
                    <th class="text-right">
                        @if ($urlLiq)
                            <a href="{{ $urlLiq }}" class="text-primary" target="_blank" rel="noopener">{{ $col['etiqueta'] }}</a>
                        @else
                            {{ $col['etiqueta'] }}
                        @endif
                        <br><small>{{ $col['periodo'] }}</small>
                    </th>
                @endif
            @endforeach
            @if ($muestraDif)
                @if ($formato === F::FORMATO_AMBOS)
                    <th class="text-right" colspan="2">Diferencia</th>
                @else
                    <th class="text-right">Diferencia</th>
                @endif
            @endif
            @if ($formato === F::FORMATO_AMBOS)
                <th class="text-right" colspan="2">Total concepto</th>
            @else
                <th class="text-right">Total concepto</th>
            @endif
        </tr>
        @if ($formato === F::FORMATO_AMBOS)
            <tr>
                <th colspan="8"></th>
                @foreach ($columnas as $col)
                    <th class="text-right">Cant</th>
                    <th class="text-right">Importe</th>
                @endforeach
                @if ($muestraDif)
                    <th class="text-right">Cant</th>
                    <th class="text-right">Importe</th>
                @endif
                <th class="text-right">Cant</th>
                <th class="text-right">Importe</th>
            </tr>
        @endif
    </thead>
    <tbody>
        @forelse ($coleccion as $f)
            @if (($f['tipo'] ?? '') === 'grupo')
                @php $ug = $urlGrupo($f); @endphp
                <tr class="table-info font-weight-bold">
                    <td colspan="{{ 8 + count($columnas) * ($formato === F::FORMATO_AMBOS ? 2 : 1) + ($muestraDif ? ($formato === F::FORMATO_AMBOS ? 2 : 1) : 0) + ($formato === F::FORMATO_AMBOS ? 2 : 1) }}">
                        @if ($ug)
                            <a href="{{ $ug }}" class="text-primary" target="_blank" rel="noopener">{{ $f['grupo_label'] ?? '' }}</a>
                        @else
                            {{ $f['grupo_label'] ?? '' }}
                        @endif
                    </td>
                </tr>
            @elseif (($f['tipo'] ?? '') === 'total_grupo' || !empty($f['es_total']))
                <tr class="font-weight-bold table-secondary">
                    <td colspan="8">{{ $f['nombre'] ?? 'Total' }}</td>
                    @foreach ($columnas as $col)
                        @php $v = $f['valores'][$col['clave']] ?? ['importe' => 0, 'cantidad' => 0]; @endphp
                        @if ($mostrarCant)
                            <td class="text-right">{{ $fmtN($v['cantidad'] ?? 0, true) }}</td>
                        @endif
                        @if ($mostrarImp)
                            <td class="text-right">{{ $fmtN($v['importe'] ?? 0, true) }}</td>
                        @endif
                    @endforeach
                    @if ($muestraDif)
                        @if ($mostrarCant)
                            <td class="text-right">{{ $fmtN($f['diferencia_cantidad'] ?? 0, true) }}</td>
                        @endif
                        @if ($mostrarImp)
                            <td class="text-right">{{ $fmtN($f['diferencia_importe'] ?? 0, true) }}</td>
                        @endif
                    @endif
                    @if ($mostrarCant)
                        <td class="text-right">{{ $fmtN($f['total_cantidad'] ?? 0, true) }}</td>
                    @endif
                    @if ($mostrarImp)
                        <td class="text-right">{{ $fmtN($f['total_importe'] ?? 0, true) }}</td>
                    @endif
                </tr>
            @else
                @php
                    $empId = (int) ($f['empleado_id'] ?? 0);
                    $urlEmp = ($verEmpleado && $empId > 0)
                        ? route('editar_empleado_sueldos', array_merge(['id' => $empId], $qs))
                        : null;
                    $concId = (int) ($f['concepto_id'] ?? 0);
                    $urlConc = ($verConcepto && $concId > 0)
                        ? route('editar_concepto_sueldos', array_merge(['id' => $concId], $qs))
                        : null;
                    $ccId = (int) ($f['centrocosto_id'] ?? 0);
                    $urlCc = ($verCcosto && $ccId > 0)
                        ? route('editar_centrocosto', array_merge(['id' => $ccId], $qs))
                        : null;
                    $catId = (int) ($f['categoria_id'] ?? 0);
                    $urlCat = ($verCategoria && $catId > 0)
                        ? route('editar_categoria_sueldos', array_merge(['id' => $catId], $qs))
                        : null;
                    $sinId = (int) ($f['sindicato_id'] ?? 0);
                    $urlSin = ($verSindicato && $sinId > 0)
                        ? route('editar_sindicato_sueldos', array_merge(['id' => $sinId], $qs))
                        : null;
                @endphp
                <tr>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlEmp,
                            'url' => $urlEmp,
                            'texto' => $f['legajo'] ?? '',
                        ])
                    </td>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlEmp,
                            'url' => $urlEmp,
                            'texto' => $f['nombre'] ?? '',
                        ])
                    </td>
                    <td>{{ $f['fecha_ingreso'] ?? '' }}</td>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlCc,
                            'url' => $urlCc,
                            'texto' => $f['centrocosto'] ?? '',
                        ])
                    </td>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlCat,
                            'url' => $urlCat,
                            'texto' => $f['categoria'] ?? '',
                        ])
                    </td>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlSin,
                            'url' => $urlSin,
                            'texto' => $f['sindicato'] ?? '',
                        ])
                    </td>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlConc,
                            'url' => $urlConc,
                            'texto' => $f['concepto_codigo'] ?? '',
                        ])
                    </td>
                    <td>
                        @include('sueldos.comparativo_conceptos.partials.link_consulta', [
                            'habilitado' => (bool) $urlConc,
                            'url' => $urlConc,
                            'texto' => $f['concepto_descripcion'] ?? '',
                        ])
                    </td>
                    @foreach ($columnas as $col)
                        @php $v = $f['valores'][$col['clave']] ?? ['importe' => 0, 'cantidad' => 0]; @endphp
                        @if ($mostrarCant)
                            <td class="text-right">{{ $fmtN($v['cantidad'] ?? 0) }}</td>
                        @endif
                        @if ($mostrarImp)
                            <td class="text-right">{{ $fmtN($v['importe'] ?? 0) }}</td>
                        @endif
                    @endforeach
                    @if ($muestraDif)
                        @if ($mostrarCant)
                            <td class="text-right">{{ $fmtN($f['diferencia_cantidad'] ?? 0) }}</td>
                        @endif
                        @if ($mostrarImp)
                            <td class="text-right">{{ $fmtN($f['diferencia_importe'] ?? 0) }}</td>
                        @endif
                    @endif
                    @if ($mostrarCant)
                        <td class="text-right">{{ $fmtN($f['total_cantidad'] ?? 0) }}</td>
                    @endif
                    @if ($mostrarImp)
                        <td class="text-right">{{ $fmtN($f['total_importe'] ?? 0) }}</td>
                    @endif
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="12" class="text-center text-muted py-3">Sin conceptos liquidados en el rango.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($esPantalla)
    </div>
</div>
@endif
