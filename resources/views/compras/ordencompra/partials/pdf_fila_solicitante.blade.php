@php
    $ocSolicitantePdf = '';
    if (\App\Support\Compras\OrdencompraUiConfigSupport::solicitanteEditable() && isset($data) && $data) {
        $ocSolicitantePdf = trim((string) (optional($data->solicitantes)->nombre ?? ''));
        $ocAltaPdf = trim((string) (optional($data->usuarios)->nombre ?? ''));
        if ($ocSolicitantePdf !== '' && strcasecmp($ocSolicitantePdf, $ocAltaPdf) === 0) {
            $ocSolicitantePdf = '';
        }
    }
@endphp
@if ($ocSolicitantePdf !== '')
    <tr>
        <td class="lbl">Solicitante</td>
        <td colspan="3">{{ $ocSolicitantePdf }}</td>
    </tr>
@endif
