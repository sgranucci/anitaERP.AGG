<?php

namespace App\Services\Uif;

use App\Models\Uif\Juego_Uif;
use App\Support\Uif\UifMaquinaRuletaBienUsoSupport;
use Illuminate\Support\Collection;

/**
 * Resuelve juego_uif_id a partir de cdescpremio (Anita) al sincronizar premios UIF.
 * Si la posición coincide con una ruleta electrónica en bien_uso, fuerza RULETA
 * (salvo BINGO / COMPRA TARJETA).
 */
class JuegoUifDesdeAnitaResolver
{
    /** @var array<string, int>|null */
    private static ?array $juegosPorNombre = null;

    /** Juegos que no se sobrescriben aunque la posición sea ruleta electrónica. */
    private const PRESERVAR_SI_RULETA_UID = [
        'BINGO',
        'COMPRA TARJETA',
        'RULETA',
    ];

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

    /**
     * @param  bool  $aplicarOverrideRuleta  Solo altas nuevas / forward; no usar en re-sync de existentes.
     */
    public static function resolveJuegoUifId(
        ?string $cdescpremio,
        ?string $posicion = null,
        ?int $salaOEmpresaId = null,
        bool $aplicarOverrideRuleta = false,
    ): int {
        $texto = self::normalizar($cdescpremio);
        if ($texto === '') {
            $juegoId = self::idPorDefecto();
        } else {
            $juegos = self::juegosPorNombre();

            if (isset($juegos[$texto])) {
                $juegoId = $juegos[$texto];
            } elseif (isset(self::ALIAS_ANITA[$texto])) {
                $juegoId = self::idDesdeNombreCanonico(self::ALIAS_ANITA[$texto], $juegos);
            } else {
                $juegoId = null;
                foreach (self::FRAGMENTOS as $fragmento => $nombreCanonico) {
                    if (str_contains($texto, self::normalizar($fragmento))) {
                        $juegoId = self::idDesdeNombreCanonico($nombreCanonico, $juegos);
                        break;
                    }
                }

                if ($juegoId === null) {
                    foreach ($juegos as $nombre => $id) {
                        if (str_contains($texto, $nombre)) {
                            $juegoId = $id;
                            break;
                        }
                    }
                }

                if ($juegoId === null) {
                    foreach ($juegos as $nombre => $id) {
                        if (strlen($texto) >= 3 && str_contains($nombre, $texto)) {
                            $juegoId = $id;
                            break;
                        }
                    }
                }

                $juegoId ??= self::idPorDefecto();
            }
        }

        if ($aplicarOverrideRuleta) {
            return self::aplicarOverrideRuletaSiCorresponde($juegoId, $posicion, $salaOEmpresaId);
        }

        return $juegoId;
    }

    /**
     * Si la posición es UID/código de ruleta en bien_uso → RULETA (salvo juegos a preservar).
     */
    public static function aplicarOverrideRuletaSiCorresponde(
        int $juegoUifId,
        ?string $posicion,
        ?int $salaOEmpresaId,
    ): int {
        $empresaId = UifMaquinaRuletaBienUsoSupport::empresaIdDesdeSalaUif($salaOEmpresaId);
        if ($empresaId <= 0 || ! UifMaquinaRuletaBienUsoSupport::esRuletaElectronica($posicion, $empresaId)) {
            return $juegoUifId;
        }

        if (self::debePreservarJuego($juegoUifId)) {
            return $juegoUifId;
        }

        return self::idDesdeNombreCanonico('RULETA', self::juegosPorNombre());
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

    private static function debePreservarJuego(int $juegoUifId): bool
    {
        foreach (self::juegosPorNombre() as $nombre => $id) {
            if ((int) $id === $juegoUifId) {
                return in_array($nombre, self::PRESERVAR_SI_RULETA_UID, true);
            }
        }

        return false;
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
