@php
    $codigoTxt = trim((string) ($codigo ?? ''));
    $cuentacajaId = (int) ($cuentacajaId ?? 0);
    $nombreTxt = trim((string) ($nombre ?? ''));
@endphp
@if ($codigoTxt !== '' && $cuentacajaId > 0 && can('listar-cuentas-de-caja', false))
    <a href="{{ route('editar_cuentacaja', ['id' => $cuentacajaId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
       target="_blank"
       rel="noopener"
       class="text-primary font-weight-bold"
       title="Consultar cuenta de caja">{{ $codigoTxt }}</a>@if ($nombreTxt !== '') — {{ $nombreTxt }}@endif
@else
    {{ trim($codigoTxt.' '.$nombreTxt) !== '' ? trim($codigoTxt.' '.$nombreTxt) : ($cuenta ?? '—') }}
@endif
