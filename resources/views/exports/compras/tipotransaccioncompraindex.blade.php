<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="12" style="height: 52px;">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="12"><strong>Tipos de comprobante de compras</strong></td>
    </tr>
    <tr>
        <td colspan="12">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Operación</th>
            <th>Abreviatura</th>
            <th>Tipo AFIP</th>
            <th>Signo</th>
            <th>Subdiario IVA</th>
            <th>Asiento</th>
            <th>Estado</th>
            <th>Retiene IVA</th>
            <th>Retiene ganancias</th>
            <th>Retiene IIBB</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->nombre }}</td>
                <td>{{ $data->desc_operacion }}</td>
                <td>{{ $data->abreviatura }}</td>
                <td>{{ $data->codigoafip }}</td>
                <td>{{ $data->desc_signo }}</td>
                <td>{{ $data->desc_subdiario }}</td>
                <td>{{ $data->desc_asientocontable }}</td>
                <td>{{ $data->desc_estado }}</td>
                <td>{{ $data->desc_retieneiva }}</td>
                <td>{{ $data->desc_retieneganancia }}</td>
                <td>{{ $data->desc_retieneiibb }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
