<?php

namespace App\Support\Stock;

/**
 * Constantes del batch legacy l-stkmovint -b (cron El Bierzo lun–vie 21:10).
 * Lee stkmov Villafranca (local) + Bierzo (far) y manda saldos por artículo G1–G6.
 */
final class StkmovIntMailSupport
{
    public const MODULO = 'stock';

    public const CODIGO_AVISO = 'stkmov_int_diario';

    public const HORA_CRON = '21:10';

    /** Lun–vie (1=lun … 7=dom). */
    public const DIAS_CRON = '1,2,3,4,5';

    /** @var list<string> Códigos ERP (Anita 00G1 → G1 al sincronizar). */
    public const CATEGORIAS_ERP = ['G1', 'G2', 'G3', 'G4', 'G5', 'G6'];

    /** @var list<string> */
    public const CATEGORIAS_ANITA = ['00G1', '00G2', '00G3', '00G4', '00G5', '00G6'];

    /** @var list<string> */
    public const EMAILS_DEFAULT = [
        'GrupoSocios@elbierzo.com.ar',
    ];

    public const NOMBRE_AVISO = 'Movimientos de stock interno (l-stkmovint)';

    public const DESCRIPCION_AVISO = 'Batch lun–vie 21:10: saldos del día por artículo (grupos G1–G6). '
        .'Suma stock Villafranca + Bierzo. Reemplaza l-stkmovint -b del cron Linux.';

    public static function pathVillafranca(): string
    {
        return rtrim((string) config('cliente_anita.villafranca.path_sistema', '/usr2/villafranca'), '/');
    }

    public static function pathBierzo(): string
    {
        return rtrim((string) config('anita.bdd_path', '/usr2/bierzo'), '/');
    }

    public static function normalizarAgrupacion(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return '';
        }
        if (preg_match('/^0*G([0-9])$/', $codigo, $m)) {
            return 'G'.$m[1];
        }

        return $codigo;
    }

    public static function skuCorto(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }
        $norm = ltrim($sku, '0');

        return $norm !== '' ? $norm : '0';
    }
}
