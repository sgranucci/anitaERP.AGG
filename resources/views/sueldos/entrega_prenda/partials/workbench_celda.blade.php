@php
    $alineado = in_array($key, ['cantidad', 'legajo', 'entrega_id', 'anio'], true);
    $valorTexto = \App\Support\Sueldos\EntregaPrendaListadoColumnas::valorCelda($data, $key);
    $venceVencido = false;
    if ($key === 'vence_el' && ! empty($data->vence_el)) {
        try {
            $venceVencido = \Carbon\Carbon::parse($data->vence_el)->lt(\Carbon\Carbon::today());
        } catch (\Throwable $e) {
            $venceVencido = false;
        }
    }

    $qsConsulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
    $href = null;

    if ($valorTexto !== '' && $valorTexto !== null) {
        if (in_array($key, ['legajo', 'empleado'], true)
            && ! empty($puedeVerEmpleado)
            && ! empty($data->empleado_id)) {
            $href = route('editar_empleado_sueldos', array_merge(['id' => $data->empleado_id], $qsConsulta));
        } elseif (in_array($key, ['sku'], true)
            && ! empty($puedeVerArticulo)
            && ! empty($data->articulo_id)) {
            $href = route('editar_articulo', array_merge(['id' => $data->articulo_id], $qsConsulta));
        } elseif (in_array($key, ['prenda_codigo', 'prenda'], true)
            && ! empty($puedeVerPrenda)
            && ! empty($data->prenda_id)) {
            $href = route('editar_prenda_sueldos', array_merge(['id' => $data->prenda_id], $qsConsulta));
        } elseif ($key === 'deposito'
            && ! empty($puedeVerDeposito)
            && ! empty($data->deposito_id)) {
            $href = route('editar_depmae', array_merge(['id' => $data->deposito_id], $qsConsulta));
        } elseif ($key === 'color'
            && ! empty($puedeVerColor)
            && ! empty($data->color_id)) {
            $href = route('editar_color', array_merge(['id' => $data->color_id], $qsConsulta));
        } elseif ($key === 'talle'
            && ! empty($puedeVerTalle)
            && ! empty($data->talle_id)) {
            $href = route('editar_talle', array_merge(['id' => $data->talle_id], $qsConsulta));
        } elseif ($key === 'entrega_id'
            && ! empty($puedeEditarEntrega)
            && ! empty($data->entrega_id)) {
            $href = route('editar_entrega_prenda', [
                'entrega' => $data->entrega_id,
                'retorno' => request()->fullUrl(),
            ]);
        }
    }
@endphp
<td class="{{ $alineado ? 'text-right text-nowrap' : '' }}{{ $venceVencido ? ' text-danger font-weight-bold' : '' }}">
    @if ($href)
        <a class="text-primary" target="_blank" rel="noopener" href="{{ $href }}">{{ $valorTexto }}</a>
    @else
        {{ $valorTexto }}
    @endif
</td>
