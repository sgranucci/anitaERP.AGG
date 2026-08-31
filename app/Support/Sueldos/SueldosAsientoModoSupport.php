<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Configuracion_Asiento_Sueldos;

/**
 * Cómo arma el asiento de sueldos cada empresa.
 * ERP (default): un asiento por corrida, CC en todas las líneas, tipo SUEL.
 * Anita: un asiento por CC, pasivos 2xx en CC 0, tipo PER + ctamov sistema P.
 */
final class SueldosAsientoModoSupport
{
    public const ERP = 'erp';

    public const ANITA = 'anita';

    public static function resolver(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return self::ERP;
        }

        $modo = Configuracion_Asiento_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->value('modo');

        return self::normalizar((string) $modo);
    }

    public static function guardar(int $empresaId, string $modo): void
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida para el modo de asiento.');
        }

        Configuracion_Asiento_Sueldos::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            ['modo' => self::normalizar($modo)]
        );
    }

    public static function normalizar(string $modo): string
    {
        return $modo === self::ANITA ? self::ANITA : self::ERP;
    }

    public static function esAnita(int $empresaId): bool
    {
        return self::resolver($empresaId) === self::ANITA;
    }

    public static function abrevTipoasiento(string $modo): string
    {
        return self::normalizar($modo) === self::ANITA
            ? SueldosAsientoSupport::ABREV_TIPOASIENTO_ANITA
            : SueldosAsientoSupport::ABREV_TIPOASIENTO;
    }

    /**
     * @return array<string, array{label: string, ayuda: string}>
     */
    public static function opciones(): array
    {
        return [
            self::ERP => [
                'label' => 'ERP (recomendado)',
                'ayuda' => 'Un asiento por corrida. El centro de costo va en todas las líneas, incluido sueldos a pagar. Tipo SUEL.',
            ],
            self::ANITA => [
                'label' => 'Anita (histórico)',
                'ayuda' => 'Un asiento por centro de costo. Los pasivos (cuentas 2xx) van en CC 0. Tipo PER y sistema P en ctamov.',
            ],
        ];
    }

    public static function etiqueta(string $modo): string
    {
        $modo = self::normalizar($modo);

        return self::opciones()[$modo]['label'];
    }

    public static function esCuentaPasivo(string $codigo): bool
    {
        $digitos = preg_replace('/\D+/', '', $codigo) ?? '';

        return $digitos !== '' && $digitos[0] === '2';
    }
}
