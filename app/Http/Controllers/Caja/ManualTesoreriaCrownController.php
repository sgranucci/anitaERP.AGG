<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Caja\ManualTesoreriaCrownService;
use App\Support\Caja\TesoreriaCrownAcceso;

class ManualTesoreriaCrownController extends ManualDocumentoController
{
    protected string $directorio = 'manual-tesoreria-crown';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Tesoreria_Crown';

    protected string $configKey = 'manual_tesoreria_crown';

    protected string $imgPublicPrefix = 'docs/manual-tesoreria-crown/img';

    protected string $etiquetaModulo = 'Tesorería Crown';

    protected array $atajos = [
        ['label' => 'Jornada', 'route' => 'tesoreria_crown_jornada'],
        ['label' => 'Habilitaciones', 'route' => 'tesoreria_crown_movimiento'],
        ['label' => 'Bóveda', 'route' => 'tesoreria_crown_boveda'],
        ['label' => 'Anexos', 'route' => 'tesoreria_crown_anexo1'],
    ];

    public function __construct(ManualTesoreriaCrownService $manual)
    {
        parent::__construct($manual);
    }

    public function index()
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();

        return parent::index();
    }

    public function descargarPdf(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();

        return parent::descargarPdf();
    }

    public function descargarWord(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();

        return parent::descargarWord();
    }

    protected static function rutaPdf(): string
    {
        return 'manual_tesoreria_crown_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_tesoreria_crown_word';
    }
}
