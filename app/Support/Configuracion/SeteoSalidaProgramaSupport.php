<?php

namespace App\Support\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Claves estables para seteo de impresora por pantalla (seteosalida.programa).
 * Evita derivar la clave del HTTP Referer, que varía por host, query string y entorno.
 */
class SeteoSalidaProgramaSupport
{
    public const VENTAS_PEDIDO = 'ventas_pedido';

    public const VENTAS_REPEMISIONOT = 'ventas_repemisionot';

    public const STOCK_ARTICULO = 'stock_articulo';

    public const UIF_EXPORTA_OPERACION = 'uif_exportaoperacion';

    public const VENTAS_REPETIQUETAOT = 'ventas_repetiquetaot';

    /** @var array<string, string> */
    private const ETIQUETAS = [
        self::VENTAS_PEDIDO => 'Pedidos de clientes',
        self::VENTAS_REPEMISIONOT => 'Emisión de OT',
        self::STOCK_ARTICULO => 'Artículos (etiquetas)',
        self::UIF_EXPORTA_OPERACION => 'Exportación UIF',
        self::VENTAS_REPETIQUETAOT => 'Etiquetas de OT',
    ];

    public static function resolver(?string $opcion, ?Request $request = null): string
    {
        $opcion = self::normalizarEntrada($opcion);

        if ($opcion !== null && self::esCodigoExplicito($opcion)) {
            return self::slug($opcion);
        }

        if ($opcion !== null && ! self::esCodigoExplicito($opcion)) {
            return $opcion;
        }

        return self::legacyDesdeReferer(
            $request?->server('HTTP_REFERER') ?? request()->header('referer')
        );
    }

    public static function esCodigoExplicito(?string $opcion): bool
    {
        $opcion = self::normalizarEntrada($opcion);

        if ($opcion === null) {
            return false;
        }

        return ! Str::startsWith(strtolower($opcion), 'http');
    }

    /**
     * Claves históricas (referer / IP fija) para encontrar seteos grabados antes del refactor.
     *
     * @return list<string>
     */
    public static function clavesLegacy(?string $opcion): array
    {
        $canonical = self::resolver($opcion);
        $claves = [];

        if (in_array($canonical, [self::VENTAS_PEDIDO, self::VENTAS_REPEMISIONOT], true)) {
            $claves[] = str_replace('/', '_', 'http://160.132.0.209/anitaERP/public/ventas/repemisionot');
        }

        $referer = request()->header('referer') ?? request()->server('HTTP_REFERER') ?? '';
        if ($referer !== '') {
            // Seteos históricos grabados solo con referer (opción vacía en buscaSeteo).
            $legacySinSufijo = self::legacyDesdeReferer($referer, null);
            if ($legacySinSufijo !== $canonical) {
                $claves[] = $legacySinSufijo;
            }

            $opcionNorm = self::normalizarEntrada($opcion);
            if ($opcionNorm !== null) {
                $legacyConSufijo = self::legacyDesdeReferer($referer, $opcionNorm);
                if ($legacyConSufijo !== $canonical && $legacyConSufijo !== $legacySinSufijo) {
                    $claves[] = $legacyConSufijo;
                }
            }
        }

        return array_values(array_unique(array_filter($claves)));
    }

    /**
     * @return list<string>
     */
    public static function codigosPrograma(): array
    {
        return [
            self::VENTAS_PEDIDO,
            self::VENTAS_REPEMISIONOT,
            self::STOCK_ARTICULO,
            self::UIF_EXPORTA_OPERACION,
            self::VENTAS_REPETIQUETAOT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function opcionesParaFormulario(): array
    {
        $opciones = [];

        foreach (self::codigosPrograma() as $codigo) {
            $opciones[$codigo] = self::etiqueta($codigo);
        }

        return $opciones;
    }

    /**
     * @param  list<string>  $codigos
     */
    public static function etiquetasProgramas(array $codigos): string
    {
        $etiquetas = [];

        foreach ($codigos as $codigo) {
            if ($codigo === '') {
                continue;
            }
            $etiquetas[] = self::etiqueta($codigo);
        }

        if ($etiquetas === []) {
            return 'Todos los programas';
        }

        return implode(', ', $etiquetas);
    }

    public static function etiqueta(?string $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return 'General';
        }

        if (isset(self::ETIQUETAS[$codigo])) {
            return self::ETIQUETAS[$codigo];
        }

        if (Str::startsWith($codigo, self::VENTAS_REPETIQUETAOT.'_')) {
            return self::ETIQUETAS[self::VENTAS_REPETIQUETAOT].' ('.Str::after($codigo, self::VENTAS_REPETIQUETAOT.'_').')';
        }

        return $codigo;
    }

    public static function codigoRepetiquetaOt(?string $tipoEtiqueta): string
    {
        $tipo = self::slug((string) $tipoEtiqueta);

        return $tipo === ''
            ? self::VENTAS_REPETIQUETAOT
            : self::VENTAS_REPETIQUETAOT.'_'.$tipo;
    }

    private static function normalizarEntrada(?string $opcion): ?string
    {
        if ($opcion === null) {
            return null;
        }

        $opcion = trim(urldecode($opcion));

        if ($opcion === '' || $opcion === 'xx') {
            return null;
        }

        return $opcion;
    }

    private static function slug(string $valor): string
    {
        return Str::slug($valor, '_');
    }

    private static function legacyDesdeReferer(?string $referer, ?string $opcion = null): string
    {
        $programa = (string) $referer;

        if (Str::contains($programa, 'pedido')) {
            $programa = 'http://160.132.0.209/anitaERP/public/ventas/repemisionot';
        }

        if (Str::contains($programa, 'ordenestrabajo')) {
            $programa = 'http://160.132.0.209/anitaERP/public/ventas/repemisionot';
        }

        $urlCompleta = str_replace('/', '_', $programa);

        return $urlCompleta.($opcion ? '_'.self::slug($opcion) : '');
    }
}
