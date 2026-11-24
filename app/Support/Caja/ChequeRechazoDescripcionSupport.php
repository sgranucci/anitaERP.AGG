<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;

/**
 * Texto estándar del rechazo de cheque (mayor Anita / asiento / leyenda ND).
 * Misma forma en todas las empresas: Rech.#nro int.interno BANCO.
 */
final class ChequeRechazoDescripcionSupport
{
    /** Límite habitual de ctav_desc_mov / subd_desc_mov en Anita. */
    public const LIMITE_MAYOR = 40;

    /**
     * Descripción corta para el mayor / asiento (todas las empresas).
     */
    public static function detalleMayor(Cheque $cheque): string
    {
        $nro = self::numeroChequeVisible($cheque);
        $interno = (int) ($cheque->nro_interno_anita ?? 0);
        $banco = self::bancoCorto($cheque, 18);

        $cabeza = 'Rech.';
        if ($nro !== '') {
            $cabeza = 'Rech.#'.$nro;
        } elseif ($interno > 0) {
            $cabeza = 'Rech.#'.$interno;
        }

        $partes = [$cabeza];
        if ($interno > 0 && $nro !== '' && (string) $interno !== $nro) {
            $partes[] = 'int.'.$interno;
        }
        if ($banco !== '') {
            $partes[] = $banco;
        }

        return self::recortar(implode(' ', $partes), self::LIMITE_MAYOR);
    }

    /**
     * Texto de renglón / leyenda de la ND (campo más largo).
     */
    public static function detalleDocumento(Cheque $cheque, string $prefijo = 'Cheque rechazado'): string
    {
        $detalle = self::detalleMayor($cheque);
        $texto = trim($prefijo) !== ''
            ? trim($prefijo).' — '.$detalle
            : $detalle;

        return self::recortar($texto, 255);
    }

    /**
     * Destinatario visible en la impresión de la ND: proveedor (si se entregó) o banco (depósito).
     *
     * @return array{etiqueta: string, valor: string}|null
     */
    public static function destinatarioImpresion(Cheque $cheque): ?array
    {
        $cheque->loadMissing(['proveedores', 'bancos']);
        $proveedor = trim((string) ($cheque->proveedores->nombre ?? ''));
        if ($proveedor !== '' && (int) ($cheque->proveedor_id ?? 0) > 0) {
            return ['etiqueta' => 'Proveedor', 'valor' => $proveedor];
        }

        $banco = trim((string) ($cheque->bancos->nombre ?? ''));
        if ($banco !== '') {
            return ['etiqueta' => 'Banco', 'valor' => $banco];
        }

        return null;
    }

    private static function numeroChequeVisible(Cheque $cheque): string
    {
        $raw = trim((string) ($cheque->numerocheque ?? ''));
        if ($raw === '') {
            return '';
        }
        $soloDigitos = preg_replace('/\D+/', '', $raw) ?? '';
        if ($soloDigitos !== '') {
            return ltrim($soloDigitos, '0') !== '' ? ltrim($soloDigitos, '0') : '0';
        }

        return $raw;
    }

    private static function bancoCorto(Cheque $cheque, int $max): string
    {
        $cheque->loadMissing('bancos');
        $nombre = trim((string) ($cheque->bancos->nombre ?? ''));
        if ($nombre === '') {
            return '';
        }

        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;

        return self::recortar(mb_strtoupper($nombre), $max);
    }

    private static function recortar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $max));
    }
}
