<?php

namespace App\Support\Ventas;

use App\Models\Ventas\MozoGastronomia;

/**
 * Tipo de mozo Crown / Anita (vend_aplicacion en a-mozo.c).
 * M = mozo normal (colaborador), E = encargado, A = adicionista.
 */
final class MozoGastronomiaAplicacionSupport
{
    public const MOZO = 'M';

    public const ENCARGADO = 'E';

    public const ADICIONISTA = 'A';

    public const SESSION_KEY = 'gastro_crown_mozo';

    /** @var array<string, string> */
    public const LABELS = [
        self::MOZO => 'Colaborador (mozo normal)',
        self::ENCARGADO => 'Encargado',
        self::ADICIONISTA => 'Adicionista',
    ];

    public static function normalizar(?string $codigo): string
    {
        $c = strtoupper(trim((string) $codigo));

        return isset(self::LABELS[$c]) ? $c : self::MOZO;
    }

    public static function etiqueta(?string $codigo): string
    {
        $c = self::normalizar($codigo);

        return self::LABELS[$c];
    }

    public static function etiquetaCorta(?string $codigo): string
    {
        return match (self::normalizar($codigo)) {
            self::ENCARGADO => 'Encargado',
            self::ADICIONISTA => 'Adicionista',
            default => 'Colaborador',
        };
    }

    public static function esEncargado(?string $codigo): bool
    {
        return self::normalizar($codigo) === self::ENCARGADO;
    }

    public static function esAdicionista(?string $codigo): bool
    {
        return self::normalizar($codigo) === self::ADICIONISTA;
    }

    /** Mesa ajena / transferir ítems: Encargado o Adicionista (a-comandera). */
    public static function puedeAbrirMesaAjena(?string $codigo): bool
    {
        $c = self::normalizar($codigo);

        return $c === self::ENCARGADO || $c === self::ADICIONISTA;
    }

    public static function puedeTransferir(?string $codigo): bool
    {
        return self::puedeAbrirMesaAjena($codigo);
    }

    /** F10 dto directo: bloqueado para mozo normal. F8 con autorizante sí. */
    public static function puedeDescuentoDirecto(?string $codigo): bool
    {
        return self::normalizar($codigo) !== self::MOZO;
    }

    /** Adicionista no carga artículos de menú (a-comandera). */
    public static function puedeAgregarArticulos(?string $codigo): bool
    {
        return ! self::esAdicionista($codigo);
    }

    /** Borrar / anular mesa: no mozo normal. */
    public static function puedeAnularCuenta(?string $codigo): bool
    {
        return self::normalizar($codigo) !== self::MOZO;
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,aplicacion:string,aplicacion_label:string}|null
     */
    public static function sesionActual(): ?array
    {
        $raw = session(self::SESSION_KEY);
        if (! is_array($raw) || (int) ($raw['id'] ?? 0) <= 0) {
            return null;
        }

        return [
            'id' => (int) $raw['id'],
            'codigo' => (string) ($raw['codigo'] ?? ''),
            'nombre' => (string) ($raw['nombre'] ?? ''),
            'aplicacion' => self::normalizar($raw['aplicacion'] ?? null),
            'aplicacion_label' => self::etiquetaCorta($raw['aplicacion'] ?? null),
        ];
    }

    public static function guardarSesion(MozoGastronomia $mozo): void
    {
        session([
            self::SESSION_KEY => [
                'id' => (int) $mozo->id,
                'codigo' => (string) $mozo->codigo,
                'nombre' => (string) $mozo->nombre,
                'aplicacion' => self::normalizar($mozo->aplicacion ?? null),
                'empresa_id' => (int) $mozo->empresa_id,
            ],
        ]);
    }

    public static function limpiarSesion(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function mozoDeSesion(?int $empresaId = null): ?MozoGastronomia
    {
        $sesion = self::sesionActual();
        if ($sesion === null) {
            return null;
        }
        $q = MozoGastronomia::query()->whereKey($sesion['id']);
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        return $q->first();
    }
}
