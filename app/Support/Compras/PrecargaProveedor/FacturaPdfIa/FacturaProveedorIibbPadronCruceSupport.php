<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Models\Configuracion\Empresa;
use App\Services\Configuracion\IIBBService;

/**
 * Cruza percepciones IIBB detectadas con padrón ARBA (y CABA si no cierra)
 * usando el CUIT de la empresa destinataria (Biyemas/Rebisco/Kandiko).
 */
final class FacturaProveedorIibbPadronCruceSupport
{
    public const JURISDICCION_CABA = 901;

    public const JURISDICCION_ARBA = 902;

    public const TOLERANCIA_TASA = 0.15;

    public function __construct(
        private IIBBService $iibbService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   padrones: array<string, mixed>
     * }
     */
    public function enriquecerLineas(
        array $lineas,
        int $empresaId,
        ?string $fechaFactura = null,
        ?float $netoGravado = null,
    ): array {
        $advertencias = [];
        $padrones = [];

        $cuitEmpresa = $this->cuitEmpresa($empresaId);
        if ($cuitEmpresa === '') {
            return ['lineas' => $lineas, 'advertencias' => $advertencias, 'padrones' => $padrones];
        }

        $neto = $netoGravado;
        if ($neto === null || $neto <= 0) {
            $neto = $this->inferirNetoDesdeLineas($lineas);
        }

        $arba = $this->leerTasa($cuitEmpresa, self::JURISDICCION_ARBA, $fechaFactura);
        $caba = $this->leerTasa($cuitEmpresa, self::JURISDICCION_CABA, $fechaFactura);
        $padrones = [
            'cuit_empresa' => $cuitEmpresa,
            'arba' => $arba,
            'caba' => $caba,
        ];

        foreach ($lineas as $i => $linea) {
            $tipo = strtolower((string) ($linea['tipo'] ?? ''));
            if (! str_contains($tipo, 'percepcion_iibb') && ! str_contains($tipo, 'iibb')) {
                continue;
            }
            if (str_contains($tipo, 'retencion')) {
                continue;
            }

            $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
            if ($importe <= 0 || $neto === null || $neto <= 0) {
                continue;
            }

            $tasaImplicita = round(($importe / $neto) * 100.0, 4);
            $lineas[$i]['tasa_iibb_implicita'] = $tasaImplicita;

            $match = $this->resolverJurisdiccion($tasaImplicita, $arba, $caba);
            if ($match === null) {
                $advertencias[] = sprintf(
                    'Percepción IIBB $%s (≈%s%% sobre neto) no coincide con padrón ARBA (%s) ni CABA (%s) para CUIT %s.',
                    number_format($importe, 2, ',', '.'),
                    number_format($tasaImplicita, 2, ',', '.'),
                    $this->etiquetaTasa($arba),
                    $this->etiquetaTasa($caba),
                    $this->formatearCuit($cuitEmpresa)
                );
                continue;
            }

            $lineas[$i]['jurisdiccion_iibb'] = $match['codigo'];
            $lineas[$i]['tasa_iibb_padron'] = $match['tasa'];
            $lineas[$i]['padron_iibb_origen'] = $match['origen'];

            if ($match['codigo'] === 'arba') {
                $advertencias[] = sprintf(
                    'Percepción IIBB $%s ≈ %s%%: coincide con padrón ARBA (%s%%) para %s.',
                    number_format($importe, 2, ',', '.'),
                    number_format($tasaImplicita, 2, ',', '.'),
                    number_format($match['tasa'], 2, ',', '.'),
                    $this->formatearCuit($cuitEmpresa)
                );
            } else {
                $advertencias[] = sprintf(
                    'Percepción IIBB $%s ≈ %s%%: coincide con padrón CABA/AGIP (%s%%) para %s.',
                    number_format($importe, 2, ',', '.'),
                    number_format($tasaImplicita, 2, ',', '.'),
                    number_format($match['tasa'], 2, ',', '.'),
                    $this->formatearCuit($cuitEmpresa)
                );
            }
        }

        return [
            'lineas' => $lineas,
            'advertencias' => $advertencias,
            'padrones' => $padrones,
        ];
    }

    /**
     * @return array{tasa: float, tipocontribuyente: ?string}|null
     */
    private function leerTasa(string $cuit, int $jurisdiccion, ?string $fecha): ?array
    {
        $reg = $this->iibbService->leeTasaPercepcion($cuit, $jurisdiccion, $fecha);
        if ($reg === null) {
            return null;
        }

        $tasa = $this->iibbService->tasaPercepcionDesdePadron($reg, $jurisdiccion);
        if ($tasa === null) {
            return null;
        }

        $tipo = is_array($reg)
            ? ($reg['tipocontribuyente'] ?? null)
            : ($reg->tipocontribuyente ?? null);

        return [
            'tasa' => $tasa,
            'tipocontribuyente' => $tipo,
        ];
    }

    /**
     * @param  array{tasa: float, tipocontribuyente: ?string}|null  $arba
     * @param  array{tasa: float, tipocontribuyente: ?string}|null  $caba
     * @return array{codigo: string, tasa: float, origen: string}|null
     */
    private function resolverJurisdiccion(float $tasaImplicita, ?array $arba, ?array $caba): ?array
    {
        $opciones = [];
        if ($arba !== null) {
            $opciones[] = [
                'codigo' => 'arba',
                'tasa' => (float) $arba['tasa'],
                'origen' => 'padron_iibb_arba',
                'diff' => abs($tasaImplicita - (float) $arba['tasa']),
            ];
        }
        if ($caba !== null) {
            $opciones[] = [
                'codigo' => 'caba',
                'tasa' => (float) $caba['tasa'],
                'origen' => 'padron_iibb_caba',
                'diff' => abs($tasaImplicita - (float) $caba['tasa']),
            ];
        }

        if ($opciones === []) {
            return null;
        }

        usort($opciones, static fn (array $a, array $b): int => $a['diff'] <=> $b['diff']);
        $mejor = $opciones[0];
        if ($mejor['diff'] > self::TOLERANCIA_TASA) {
            return null;
        }

        return [
            'codigo' => $mejor['codigo'],
            'tasa' => $mejor['tasa'],
            'origen' => $mejor['origen'],
        ];
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function inferirNetoDesdeLineas(array $lineas): ?float
    {
        $suma = 0.0;
        $hay = false;
        foreach ($lineas as $linea) {
            $tipo = strtolower((string) ($linea['tipo'] ?? ''));
            if (str_contains($tipo, 'neto') || str_contains($tipo, 'subtotal') || str_contains($tipo, 'gravado')) {
                $suma += abs((float) ($linea['importe'] ?? 0));
                $hay = true;
            }
        }

        return $hay ? round($suma, 2) : null;
    }

    private function cuitEmpresa(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return '';
        }
        $nro = Empresa::query()->whereKey($empresaId)->value('nroinscripcion');

        return preg_replace('/\D/', '', (string) $nro) ?? '';
    }

    private function formatearCuit(string $digitos): string
    {
        if (strlen($digitos) !== 11) {
            return $digitos;
        }

        return substr($digitos, 0, 2).'-'.substr($digitos, 2, 8).'-'.substr($digitos, 10, 1);
    }

    /** @param  array{tasa: float, tipocontribuyente: ?string}|null  $reg */
    private function etiquetaTasa(?array $reg): string
    {
        if ($reg === null) {
            return 'sin padrón';
        }

        return number_format((float) $reg['tasa'], 2, ',', '.').'%';
    }
}
