<?php

namespace App\Support\Stock;

use App\Models\Stock\Tipoarticulo;

/**
 * Mapeo stkmae → articulo para INTERFORMING.
 *
 * stkm_tipo_articulo (Anita): I/P/T/R/B → tipoarticulo por nombre en ERP.
 */
final class InterformingArticuloAnitaMapperSupport
{
    /** @var array<string, string> Letra Anita → nombre tipoarticulo ERP */
    private const TIPO_ARTICULO_POR_LETRA = [
        'I' => 'Insumo',
        'P' => 'Producto en proceso',
        'T' => 'Producto Terminado',
        'R' => 'Reventa',
        'B' => 'Bien económico',
    ];

    /** @var array<string, int>|null */
    private static ?array $tipoarticuloPorNombre = null;

    /**
     * @param  array<string, mixed>  $comunes  Campos ya resueltos (categoría, cuentas, U.M., etc.)
     * @return array<string, mixed>
     */
    public static function mapearArrayCampos(object $data, array $comunes): array
    {
        [$estado, $noFactura] = self::resolverEstadoYNoFactura($data);

        $detalle = trim((string) ($data->stkm_desc_completa ?? ''));
        if ($detalle === '') {
            $detalle = trim((string) ($data->stkm_desc ?? ''));
        }

        return array_merge($comunes, [
            'detalle' => $detalle,
            'nofactura' => $noFactura,
            'estado' => $estado,
            'tipoarticulo_id' => self::resolverTipoarticuloId($data->stkm_tipo_articulo ?? null),
            'subrubro' => self::normalizarCampoVarchar($data->stkm_subrubro ?? null),
            'lineamaterial' => self::normalizarCampoVarchar($data->stkm_lineamaterial ?? null),
            'grupoproducto' => self::normalizarCampoVarchar($data->stkm_grupoproducto ?? null),
            'leyenda' => '',
            'coeficienteconversion' => $data->stkm_peso_aprox ?? null,
        ]);
    }

    public static function resolverTipoarticuloId(mixed $letraAnita): ?int
    {
        $letra = strtoupper(trim((string) $letraAnita));
        if ($letra === '') {
            return null;
        }

        $nombre = self::TIPO_ARTICULO_POR_LETRA[$letra] ?? null;
        if ($nombre === null) {
            return null;
        }

        $mapa = self::tipoarticuloPorNombre();
        $id = $mapa[mb_strtolower($nombre)] ?? null;

        return $id !== null ? (int) $id : null;
    }

    public static function normalizarCampoVarchar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '' || $texto === '0') {
            return null;
        }

        return mb_substr($texto, 0, 50);
    }

    /**
     * @return array{0: string, 1: string} estado, nofactura
     */
    private static function resolverEstadoYNoFactura(object $data): array
    {
        $estado = 'ACTIVO';
        $noFactura = '0';

        switch ((string) ($data->stkm_fl_no_factura ?? '0')) {
            case '1':
            case 'N':
                $noFactura = '1';
                $estado = 'ACTIVO';
                break;
            case 'I':
                $noFactura = '1';
                $estado = 'INACTIVO';
                break;
        }

        return [$estado, $noFactura];
    }

    /**
     * @return array<string, int> clave nombre normalizado → id
     */
    private static function tipoarticuloPorNombre(): array
    {
        if (self::$tipoarticuloPorNombre !== null) {
            return self::$tipoarticuloPorNombre;
        }

        $mapa = [];
        foreach (Tipoarticulo::query()->get(['id', 'nombre']) as $tipo) {
            $nombre = mb_strtolower(trim((string) $tipo->nombre));
            if ($nombre !== '') {
                $mapa[$nombre] = (int) $tipo->id;
            }
        }

        self::$tipoarticuloPorNombre = $mapa;

        return $mapa;
    }
}
