<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use App\Models\Stock\Recepcion_Proveedor;

/**
 * Permite ingresar mercadería en depósitos de cualquier empresa aunque la
 * recepción (y su orden de compra) pertenezcan a otra empresa.
 */
final class RecepcionProveedorIntercompanySupport
{
    public const PERMISO = 'deposito-intercompany-recepcion-proveedor';

    public static function puedeUsar(): bool
    {
        return can(self::PERMISO, false);
    }

    /**
     * Con permiso intercompany, el depósito solo debe estar autorizado para el usuario
     * (sin exigir que pertenezca a la empresa de la recepción). Sin permiso, mantiene la
     * restricción por empresa.
     */
    public static function depositoAutorizado(int $depmaeId, int $empresaIdRecepcion): bool
    {
        if ($depmaeId <= 0) {
            return false;
        }

        if (self::puedeUsar()) {
            return Depmae::autorizadoParaUsuario($depmaeId);
        }

        return Depmae::autorizadoParaUsuarioYEmpresa($depmaeId, $empresaIdRecepcion);
    }

    /** ¿El depósito pertenece a una empresa distinta a la de la recepción? (para documentar en el formulario) */
    public static function esIngresoIntercompany(int $empresaDeposito, int $empresaRecepcion): bool
    {
        return $empresaDeposito > 0 && $empresaRecepcion > 0 && $empresaDeposito !== $empresaRecepcion;
    }

    /**
     * Expresión SQL (0/1) para marcar en listados si la recepción ingresó mercadería
     * en un depósito general de entrada (cabecera, elegido por el usuario) de una empresa
     * distinta a la de la recepción.
     *
     * Solo se evalúa la cabecera (recepcion_proveedor.deposito_id): es el único depósito
     * que el usuario elige en el formulario. Los depósitos por defecto de artículo a nivel
     * línea NO se consideran (datos legacy con códigos compartidos entre empresas apuntan a
     * otra empresa sin ser un ingreso intercompany real).
     */
    public static function selectEsIntercompanySql(string $alias = 'recepcion_proveedor'): string
    {
        return '(CASE WHEN EXISTS ('
            .'SELECT 1 FROM depmae dc_ic '
            .'WHERE dc_ic.id = '.$alias.'.deposito_id '
            .'AND dc_ic.empresa_id IS NOT NULL AND dc_ic.empresa_id <> '.$alias.'.empresa_id'
            .') THEN 1 ELSE 0 END)';
    }

    /**
     * Detalle para documentar el ingreso intercompany en el comprobante PDF.
     * Solo considera el depósito general de entrada (cabecera). Requiere la relación
     * depositos.empresas cargada.
     *
     * @return array{es_intercompany: bool, empresas_deposito: list<string>}
     */
    public static function detalleIntercompanyPdf(Recepcion_Proveedor $recepcion): array
    {
        $empresaRecepcion = (int) ($recepcion->empresa_id ?? 0);
        $deposito = $recepcion->depositos;

        if ($empresaRecepcion <= 0 || $deposito === null) {
            return ['es_intercompany' => false, 'empresas_deposito' => []];
        }

        $empresaDeposito = (int) ($deposito->empresa_id ?? 0);
        if ($empresaDeposito <= 0 || $empresaDeposito === $empresaRecepcion) {
            return ['es_intercompany' => false, 'empresas_deposito' => []];
        }

        $nombre = trim((string) (optional($deposito->empresas)->nombre ?? ''));
        $etiqueta = $nombre !== '' ? $nombre : ('empresa '.$empresaDeposito);
        $codigo = trim((string) ($deposito->codigo ?? ''));
        $nombreDep = trim((string) ($deposito->nombre ?? ''));
        $detalleDep = trim($codigo.($nombreDep !== '' ? ' '.$nombreDep : ''));

        return [
            'es_intercompany' => true,
            'empresas_deposito' => [$etiqueta.($detalleDep !== '' ? ' (dep. '.$detalleDep.')' : '')],
        ];
    }
}
