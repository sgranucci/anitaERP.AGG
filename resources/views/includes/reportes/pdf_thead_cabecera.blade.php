{{--
  Filas de thead que DomPDF repite en cada hoja (display: table-header-group).
  Uso: dentro de <thead> de table.data, ANTES de la fila de columnas.

  @var string $titulo
  @var string|null $subtitulo
  @var int $colspan
  @var iterable|null $logosCabecera  (EmpresaLogoArchivo::logosCabeceraDesdeColeccion)
  @var int|null $totalFilas
--}}
@php
    $colspanCab = (int) ($colspan ?? 1);
    $tituloCab = $titulo ?? 'Listado';
    $subtituloCab = $subtitulo ?? '';
    $logosCab = $logosCabecera ?? [];
    $totalCab = (int) ($totalFilas ?? 0);
@endphp
<tr class="pdf-cabecera-repetida">
    <th colspan="{{ $colspanCab }}" style="background:#ffffff;border:none;padding:4px 2px 6px 2px;font-weight:normal;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:28%;border:none;vertical-align:middle;padding:0;">
                    @foreach ($logosCab as $logo)
                        <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] ?? '' }}" style="max-height:40px;max-width:140px;margin-right:6px;vertical-align:middle;">
                    @endforeach
                </td>
                <td style="width:48%;border:none;text-align:center;vertical-align:middle;padding:0;">
                    <div style="font-size:13px;font-weight:bold;color:#17202A;">{{ $tituloCab }}</div>
                    <div style="font-size:7px;color:#444;margin-top:2px;">Generado {{ date('d/m/Y H:i') }}</div>
                    @if ($subtituloCab !== '')
                        <div style="font-size:7px;color:#444;margin-top:1px;">{{ $subtituloCab }}</div>
                    @endif
                </td>
                <td style="width:24%;border:none;text-align:right;vertical-align:middle;padding:0;font-size:7px;color:#444;">
                    @if ($totalCab > 0)
                        Registros: {{ $totalCab }}
                    @endif
                </td>
            </tr>
        </table>
    </th>
</tr>
