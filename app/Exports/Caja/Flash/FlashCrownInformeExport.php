<?php

namespace App\Exports\Caja\Flash;

use App\Support\Caja\Flash\FlashCrownInformeSupport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FlashCrownInformeExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        private readonly array $informe,
        private readonly string $formato,
    ) {}

    public function sheets(): array
    {
        $formato = FlashCrownInformeSupport::normalizarFormato($this->formato);
        $titulo = FlashCrownInformeHojaExport::tituloInforme($this->informe, $formato);

        if ($formato === FlashCrownInformeSupport::FORMATO_DAILY) {
            $sheets = [];
            $usados = [];
            foreach ($this->informe['secciones'] ?? [] as $seccion) {
                $codigo = trim((string) ($seccion['codigo'] ?? ''));
                $nombreBase = $codigo !== ''
                    ? $codigo
                    : trim((string) ($seccion['titulo'] ?? 'Sala'));
                if (strcasecmp($nombreBase, 'RESUMEN') === 0) {
                    $nombreBase = 'Resumen';
                }
                $nombre = FlashCrownInformeHojaExport::sanitizarNombreHoja($nombreBase);
                $i = 2;
                while (isset($usados[$nombre])) {
                    $suffix = ' ('.$i.')';
                    $nombre = FlashCrownInformeHojaExport::sanitizarNombreHoja(
                        mb_substr($nombreBase, 0, max(1, 31 - mb_strlen($suffix))).$suffix
                    );
                    $i++;
                }
                $usados[$nombre] = true;

                $sheets[] = new FlashCrownInformeHojaExport(
                    $this->informe,
                    $formato,
                    $titulo.' — '.(string) ($seccion['titulo'] ?? $nombre),
                    $nombre,
                    $seccion,
                );
            }

            return $sheets !== []
                ? $sheets
                : [new FlashCrownInformeHojaExport($this->informe, $formato, $titulo, 'Daily')];
        }

        return [
            new FlashCrownInformeHojaExport(
                $this->informe,
                $formato,
                $titulo,
                $formato === FlashCrownInformeSupport::FORMATO_GAMING ? 'Gaming' : 'Flash',
            ),
        ];
    }
}
