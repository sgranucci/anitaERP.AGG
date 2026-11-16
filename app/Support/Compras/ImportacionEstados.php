<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Máquina de estados de la carpeta de importación.
 */
final class ImportacionEstados
{
    public const BORRADOR = 'borrador';

    public const ABIERTA = 'abierta';

    public const EN_TRANSITO = 'en_transito';

    public const EN_PUERTO = 'en_puerto';

    public const NACIONALIZADA = 'nacionalizada';

    public const COSTOS_CERRADOS = 'costos_cerrados';

    public const ANULADA = 'anulada';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::BORRADOR => 'Borrador',
        self::ABIERTA => 'Abierta',
        self::EN_TRANSITO => 'En tránsito',
        self::EN_PUERTO => 'En puerto',
        self::NACIONALIZADA => 'Nacionalizada',
        self::COSTOS_CERRADOS => 'Costos cerrados',
        self::ANULADA => 'Anulada',
    ];

    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        self::BORRADOR => [self::ABIERTA, self::ANULADA],
        self::ABIERTA => [self::EN_TRANSITO, self::EN_PUERTO, self::ANULADA],
        self::EN_TRANSITO => [self::EN_PUERTO, self::NACIONALIZADA, self::ANULADA],
        self::EN_PUERTO => [self::NACIONALIZADA, self::ANULADA],
        self::NACIONALIZADA => [self::COSTOS_CERRADOS],
        self::COSTOS_CERRADOS => [],
        self::ANULADA => [],
    ];

    public static function etiqueta(string $estado): string
    {
        return self::ETIQUETAS[$estado] ?? $estado;
    }

    /** @return list<array{id: string, nombre: string}> */
    public static function paraSelect(): array
    {
        $out = [];
        foreach (self::ETIQUETAS as $id => $nombre) {
            $out[] = ['id' => $id, 'nombre' => $nombre];
        }

        return $out;
    }

    public static function puedeTransicionar(string $desde, string $hacia): bool
    {
        return in_array($hacia, self::TRANSICIONES[$desde] ?? [], true);
    }

    public static function esEditable(string $estado): bool
    {
        return ! in_array($estado, [self::COSTOS_CERRADOS, self::ANULADA], true);
    }

    public static function aceptaGastos(string $estado): bool
    {
        return ! in_array($estado, [self::COSTOS_CERRADOS, self::ANULADA], true);
    }

    public static function badgeClass(string $estado): string
    {
        return match ($estado) {
            self::BORRADOR => 'secondary',
            self::ABIERTA => 'info',
            self::EN_TRANSITO => 'primary',
            self::EN_PUERTO => 'warning',
            self::NACIONALIZADA => 'success',
            self::COSTOS_CERRADOS => 'dark',
            self::ANULADA => 'danger',
            default => 'secondary',
        };
    }
}
