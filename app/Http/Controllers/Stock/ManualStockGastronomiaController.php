<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Manuales\ManualDocumentoController;
use App\Services\Stock\ManualStockGastronomiaService;

class ManualStockGastronomiaController extends ManualDocumentoController
{
    protected string $directorio = 'manual-stock-gastronomia';

    protected string $baseName = 'Manual_Usuario_AnitaERP_Stock_Gastronomia';

    protected string $configKey = 'manual_stock_gastronomia';

    protected string $imgPublicPrefix = 'docs/manual-stock-gastronomia/img';

    protected string $etiquetaModulo = 'Stock gastronómico';

    protected array $atajos = [
        ['label' => 'Fórmulas', 'route' => 'consultar_formula_articulo'],
        ['label' => 'Config PV', 'route' => 'consultar_configuracion_puntoventa_gastronomia'],
    ];

    public function __construct(ManualStockGastronomiaService $manual)
    {
        parent::__construct($manual);
    }

    protected static function rutaPdf(): string
    {
        return 'manual_stock_gastronomia_pdf';
    }

    protected static function rutaWord(): string
    {
        return 'manual_stock_gastronomia_word';
    }
}
