<?php

namespace App\Support\Configuracion;

/**
 * Canal de aviso del árbol de aprobación.
 *
 * Hoy cada paso de cada nivel dispara tres canales a la vez para el mismo firmante: correo,
 * notificación in-app y entrada en Mis aprobaciones, más un recordatorio diario por cada
 * movimiento que siga pendiente. El modo «solo bandeja» apaga el correo inmediato y deja el
 * resto: sirve para empresas que trabajan dentro del sistema y no quieren el mail por paso.
 *
 * Se puede apagar globalmente o por tipo de árbol (RE, OC, SU, …), porque no todos los circuitos
 * tienen la misma urgencia. Por default está desactivado: el comportamiento no cambia.
 */
final class ArbolAprobacionCanalSupport
{
    public static function soloBandeja(string $tipoArbol): bool
    {
        $tipo = strtoupper(trim($tipoArbol));

        $porTipo = config('arbolaprobacion.solo_bandeja_por_tipo', []);
        if (is_array($porTipo) && $tipo !== '' && array_key_exists($tipo, $porTipo)) {
            return filter_var($porTipo[$tipo], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('arbolaprobacion.solo_bandeja', false);
    }
}
