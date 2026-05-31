<?php

namespace App\Services\Uif;

use App\Models\Uif\Juego_Uif;
use Illuminate\Support\Collection;

/**
 * Resuelve juego_uif_id a partir de cdescpremio (Anita) al sincronizar premios UIF.
 */
class JuegoUifDesdeAnitaResolver
{
    /** @var array<string, int>|null */
    private static ?array $juegosPorNombre = null;

    /** @var array<string, string> Valores Anita / alias → nombre canónico en juego_uif (tabla local) */
    private const ALIAS_ANITA = [
        'BINGO' => 'BINGO',
        'RULETA' => 'RULETA',
        'SLOT' => 'SLOTS',
        'SLOTS' => 'SLOTS',
        'OTRO' => 'SLOTS',
        'POS ELECTRONICA' => 'POSICION ELECTRONICA',
        'POSICION ELECT' => 'POSICION ELECTRONICA',
        'P' => 'SLOTS',
    ];

    /** @var array<string, string> Fragmentos (más específicos primero) → nombre canónico en juego_uif */
    private const FRAGMENTOS = [
        'POSICION ELECTRONICA' => 'POSICION ELECTRONICA',
        'COMPRA TARJETA' => 'COMPRA TARJETA',
        'POS ELECTRONICA' => 'POSICION ELECTRONICA',
        'POSICION ELECT' => 'POSICION ELECTRONICA',
        'BINGO' => 'BINGO',
        'RULETA' => 'RULETA',
        'SLOTS' => 'SLOTS',
        'SLOT' => 'SLOTS',
        'OTRO' => 'SLOTS',
    ];

    public static function resolveJuegoUifId(?string $cdescpremio): int
    {
        $texto = self::normalizar($cdescpremio);
        if ($texto === '') {
            return self::idPorDefecto();
        }

        $juegos = self::juegosPorNombre();

        if (isset($juegos[$texto])) {
            return $juegos[$texto];
        }

        if (isset(self::ALIAS_ANITA[$texto])) {
            return self::idDesdeNombreCanonico(self::ALIAS_ANITA[$texto], $juegos);
        }

        foreach (self::FRAGMENTOS as $fragmento => $nombreCanonico) {
            if (str_contains($texto, self::normalizar($fragmento))) {
                return self::idDesdeNombreCanonico($nombreCanonico, $juegos);
            }
        }

        foreach ($juegos as $nombre => $id) {
            if (str_contains($texto, $nombre)) {
                return $id;
            }
        }

        foreach ($juegos as $nombre => $id) {
            if (strlen($texto) >= 3 && str_contains($nombre, $texto)) {
                return $id;
            }
        }

        return self::idPorDefecto();
    }

    /**
     * @return array<string, int>
     */
    private static function juegosPorNombre(): array
    {
        if (self::$juegosPorNombre !== null) {
            return self::$juegosPorNombre;
        }

        self::$juegosPorNombre = self::cargarJuegosPorNombre();

        return self::$juegosPorNombre;
    }

    /**
     * @return array<string, int>
     */
    private static function cargarJuegosPorNombre(): array
    {
        /** @var Collection<int, Juego_Uif> $registros */
        $registros = Juego_Uif::query()->orderBy('id')->get(['id', 'nombre']);

        $mapa = [];
        foreach ($registros as $juego) {
            $mapa[self::normalizar($juego->nombre)] = (int) $juego->id;
        }

        if (isset($mapa['SLOTS']) && ! isset($mapa['SLOT'])) {
            $mapa['SLOT'] = $mapa['SLOTS'];
        }

        return $mapa;
    }

    /**
     * @param  array<string, int>  $juegos
     */
    private static function idDesdeNombreCanonico(string $nombreCanonico, array $juegos): int
    {
        $clave = self::normalizar($nombreCanonico);

        if (isset($juegos[$clave])) {
            return $juegos[$clave];
        }

        return self::idPorDefecto();
    }

    private static function idPorDefecto(): int
    {
        $juegos = self::juegosPorNombre();

        if (isset($juegos['SLOTS'])) {
            return $juegos['SLOTS'];
        }

        if (isset($juegos['SLOT'])) {
            return $juegos['SLOT'];
        }

        $primerId = reset($juegos);

        return $primerId !== false ? (int) $primerId : 1;
    }

    private static function normalizar(?string $valor): string
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return '';
        }

        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /** Solo para pruebas: limpia caché en memoria entre invocaciones. */
    public static function resetCacheForTesting(): void
    {
        self::$juegosPorNombre = null;
    }
}
