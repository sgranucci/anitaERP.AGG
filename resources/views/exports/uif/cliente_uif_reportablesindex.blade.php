@php
    use App\Support\Uif\ClienteUifInformeReportablesSupport;
@endphp
<table>
    <tbody>
        <tr>
            <td colspan="29">{{ $titulo }}</td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th># Id</th>
            <th>Nombre</th>
            <th>Tipo de Documento</th>
            <th>Nro. de documento</th>
            <th>Cuit</th>
            <th>Fecha nac.</th>
            <th>Localidad de nacimiento</th>
            <th>Pais de nacimiento</th>
            <th>Sexo</th>
            <th>Estado civil</th>
            <th>Domicilio</th>
            <th>Localidad</th>
            <th>Codigo postal</th>
            <th>Telefono</th>
            <th>Email</th>
            <th>Profesion</th>
            <th>PEP</th>
            <th>Sujeto Obligado</th>
            <th>Residente exterior</th>
            <th>Residente paraiso fiscal</th>
            <th>Premio pagado</th>
            <th>Moneda</th>
            <th>Descripcion del premio</th>
            <th>Fecha de entrega</th>
            <th>Fecha de alta</th>
            <th>Hora de alta</th>
            <th>Usuario de alta</th>
            <th>Estado</th>
            <th>Posicion</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($premios as $data)
            <tr>
                <td>{{ ClienteUifInformeReportablesSupport::idClienteInforme($data) }}</td>
                <td>{{ $data->nombrecliente }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::tipoDocumentoInforme($data->abreviaturatipodocumento ?? '') }}</td>
                <td>{{ $data->numerodocumento }}</td>
                <td>{{ $data->cuit }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::fechaInforme($data->fechanacimiento ?? null) }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::textoInforme($data->nombrelocalidadnacimiento ?? '') }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::paisInforme($data->nombrepaisnacimiento ?? '') }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::sexoInforme($data->sexo ?? '') }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::textoInforme($data->nombreestadocivil ?? '') }}</td>
                <td>{{ $data->domicilio }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::textoInforme($data->nombrelocalidad ?? '') }}</td>
                <td>{{ $data->codigopostal }}</td>
                <td>{{ $data->telefono }}</td>
                <td>{{ $data->email }}</td>
                <td>{{ $data->actividad_uif_id }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::pepInforme($data->nombrepep ?? '') }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::soInforme($data->nombreso ?? '') }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::resideInforme($data->resideexterior ?? '') }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::resideInforme($data->resideparaisofiscal ?? '') }}</td>
                <td>{{ (float) ($data->monto ?? 0) }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::monedaInforme($data->nombremoneda ?? '') }}</td>
                <td>{{ $data->nombrejuego }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::fechaInforme($data->fechaentrega ?? null) }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::fechaInforme($data->fechaalta ?? null) }}</td>
                <td>{{ ClienteUifInformeReportablesSupport::horaInforme($data->fechaalta ?? null) }}</td>
                <td>{{ trim($data->nombreusuarioalta ?? '') }}</td>
                <td>{{ $data->estado }}</td>
                <td>{{ $data->posicion }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
