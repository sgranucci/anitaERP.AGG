<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\FacturacionJuegoParametro;
use App\Models\Ventas\FacturacionJuegoSala;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class FacturacionJuegoConfigSupport
{
    private static ?FacturacionJuegoParametro $cacheParametro = null;

    public static function parametro(): FacturacionJuegoParametro
    {
        if (self::$cacheParametro !== null) {
            return self::$cacheParametro;
        }

        if (! Schema::hasTable('facturacion_juego_parametro')) {
            throw new RuntimeException('Falta tabla facturacion_juego_parametro. Ejecute migraciones Crown.');
        }

        $fila = FacturacionJuegoParametro::query()->orderBy('id')->first();
        if ($fila === null) {
            $fila = FacturacionJuegoParametro::query()->create([
                'tipo_gan_mesa' => (string) config('facturacion_juego.tipos.gan_mesa', 'FME'),
                'tipo_per_mesa' => (string) config('facturacion_juego.tipos.per_mesa', 'CME'),
                'tipo_gan_slot' => (string) config('facturacion_juego.tipos.gan_slot', 'FSL'),
                'tipo_per_slot' => (string) config('facturacion_juego.tipos.per_slot', 'CSL'),
                'tipo_gan_ruleta_el' => (string) config('facturacion_juego.tipos.gan_ruleta_el', 'FRE'),
                'tipo_per_ruleta_el' => (string) config('facturacion_juego.tipos.per_ruleta_el', 'CRE'),
                'letra' => (string) config('facturacion_juego.letra', 'B'),
                'tipoasiento_abreviatura' => (string) config('facturacion_juego.tipoasiento_abreviatura', 'VTA'),
            ]);
        }

        return self::$cacheParametro = $fila;
    }

    public static function forgetCache(): void
    {
        self::$cacheParametro = null;
    }

    public static function salaPorSalaId(int $salaId): ?FacturacionJuegoSala
    {
        if ($salaId <= 0 || ! Schema::hasTable('facturacion_juego_sala')) {
            return null;
        }

        return FacturacionJuegoSala::query()
            ->with(['sala', 'cuentacaja', 'cuentacontableMesa', 'cuentacontableSlot', 'cuentacontableRuletaEl', 'centrocosto'])
            ->where('sala_id', $salaId)
            ->first();
    }

    /**
     * @return Collection<int, FacturacionJuegoSala>
     */
    public static function salasActivas(): Collection
    {
        if (! Schema::hasTable('facturacion_juego_sala')) {
            return collect();
        }

        return FacturacionJuegoSala::query()
            ->with(['sala', 'cuentacaja', 'cuentacontableMesa', 'cuentacontableSlot', 'cuentacontableRuletaEl', 'centrocosto'])
            ->where('activo', true)
            ->orderBy('sala_id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function faltantesSala(FacturacionJuegoSala $cfg, string $rubro): array
    {
        $faltan = [];
        if ((int) $cfg->sucursal_anita <= 0) {
            $faltan[] = 'Sucursal / PV Anita';
        }
        if ((int) ($cfg->cuentacaja_id ?? 0) <= 0) {
            $faltan[] = 'Cuenta de caja (tesorería)';
        }
        $cuentaId = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => (int) ($cfg->cuentacontable_mesa_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_SLOT => (int) ($cfg->cuentacontable_slot_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => (int) ($cfg->cuentacontable_ruleta_el_id ?? 0),
            default => 0,
        };
        if ($cuentaId <= 0) {
            $faltan[] = 'Cuenta contable de '.$rubro;
        }

        return $faltan;
    }
}
