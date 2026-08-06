<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

/**
 * Una línea ya normalizada de un padrón IIBB provincial, lista para insertar
 * en padron_iibb + padron_iibb_tasa.
 */
final class PadronIibbLinea
{
    /** Línea que trae percepción y retención juntas. */
    public const LADO_AMBAS = 'A';

    /** Línea que solo aporta la alícuota de percepción. */
    public const LADO_PERCEPCION = 'P';

    /** Línea que solo aporta la alícuota de retención. */
    public const LADO_RETENCION = 'R';

    public function __construct(
        public readonly string $cuit,
        public readonly string $desdefecha,
        public readonly string $hastafecha,
        public readonly ?string $nombre = null,
        public readonly ?float $tasapercepcion = null,
        public readonly ?float $tasaretencion = null,
        public readonly ?float $coeficiente = null,
        public readonly ?string $tipocontribuyente = null,
        public readonly ?string $riesgofiscal = null,
        public readonly ?string $excluido = null,
        public readonly string $lado = self::LADO_AMBAS,
    ) {}

    public function periodo(): string
    {
        return $this->desdefecha . '|' . $this->hastafecha;
    }
}
