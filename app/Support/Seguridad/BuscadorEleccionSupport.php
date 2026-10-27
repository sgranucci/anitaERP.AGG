<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\BuscadorEleccion;

/**
 * Cuenta qué fila elige un rol para un texto. La búsqueda siguiente la sube.
 */
class BuscadorEleccionSupport
{
    /** @var list<string> */
    public const TIPOS = ['pantalla', 'ayuda', 'registro', 'comprobante', 'accion', 'frase'];

    /**
     * @return array<string, int> clave => veces
     */
    public function refuerzos(string $consulta): array
    {
        $q = BuscadorDiccionarioSupport::normalizar($consulta);
        $rolId = (int) session('rol_id');
        if ($q === '' || $rolId <= 0) {
            return [];
        }

        $filas = BuscadorEleccion::query()
            ->where('rol_id', $rolId)
            ->where('consulta', $q)
            ->get(['clave', 'veces']);

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(string) $fila->clave] = (int) $fila->veces;
        }

        return $mapa;
    }

    public function registrar(string $consulta, string $tipo, string $clave): void
    {
        $q = BuscadorDiccionarioSupport::normalizar($consulta);
        $rolId = (int) session('rol_id');
        $tipo = trim($tipo);
        $clave = trim($clave);
        if ($q === '' || mb_strlen($q) < BuscadorGlobalSupport::MIN_CARACTERES || $rolId <= 0) {
            return;
        }
        if (! in_array($tipo, self::TIPOS, true) || ! preg_match('/^[A-Za-z0-9:\/_.\-]{1,190}$/', $clave)) {
            return;
        }

        $fila = BuscadorEleccion::query()->firstOrCreate(
            [
                'rol_id' => $rolId,
                'consulta' => mb_substr($q, 0, 80),
                'clave' => $clave,
            ],
            [
                'tipo' => $tipo,
                'veces' => 0,
            ]
        );

        if ((int) $fila->veces >= 100000) {
            return;
        }

        $fila->increment('veces');
    }
}
