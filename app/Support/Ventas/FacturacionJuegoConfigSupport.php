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
                'tipo_gan_online' => (string) config('facturacion_juego.tipos.gan_online', 'FOL'),
                'tipo_per_online' => (string) config('facturacion_juego.tipos.per_online', 'COL'),
                'sucursal_del_rio' => (int) config('caja_anita.sucursal_del_rio_online', 67),
                'sucursal_betsur' => (int) config('caja_anita.sucursal_betsur_online', 68),
                'sucursal_apuestas' => 0,
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
            ->with([
                'sala',
                'cuentacaja',
                'cuentacontableMesa',
                'cuentacontableSlot',
                'cuentacontableRuletaEl',
                'cuentacontableDelRio',
                'cuentacontableBetsur',
                'cuentacontableApuestas',
                'centrocosto',
            ])
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
            ->with([
                'sala',
                'cuentacaja',
                'cuentacontableMesa',
                'cuentacontableSlot',
                'cuentacontableRuletaEl',
                'cuentacontableDelRio',
                'cuentacontableBetsur',
                'cuentacontableApuestas',
                'centrocosto',
            ])
            ->where('activo', true)
            ->orderBy('sala_id')
            ->get();
    }

    /**
     * Config de sala a usar para emitir un rubro online global (1ª activa con cuenta).
     */
    public static function salaParaRubroOnline(string $rubro): ?FacturacionJuegoSala
    {
        foreach (self::salasActivas() as $cfg) {
            if (self::faltantesSala($cfg, $rubro) === []) {
                return $cfg;
            }
        }

        return self::salasActivas()->first();
    }

    /**
     * @return list<string>
     */
    public static function faltantesSala(FacturacionJuegoSala $cfg, string $rubro): array
    {
        $faltan = [];
        $sucursal = FacturacionJuegoTipoSupport::sucursalAnitaParaRubro(
            $rubro,
            (int) $cfg->sucursal_anita,
        );
        if ($sucursal <= 0) {
            $faltan[] = FacturacionJuegoTipoSupport::esOnline($rubro)
                ? 'Punto de venta '.FacturacionJuegoTipoSupport::nombreCliente($rubro)
                : 'Sucursal / PV Anita';
        }
        if ((int) ($cfg->cuentacaja_id ?? 0) <= 0) {
            $faltan[] = 'Cuenta de caja (tesorería)';
        }
        $cuentaId = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => (int) ($cfg->cuentacontable_mesa_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_SLOT => (int) ($cfg->cuentacontable_slot_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => (int) ($cfg->cuentacontable_ruleta_el_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_DEL_RIO => (int) ($cfg->cuentacontable_del_rio_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_BETSUR => (int) ($cfg->cuentacontable_betsur_id ?? 0),
            FacturacionJuegoTipoSupport::RUBRO_APUESTAS => (int) ($cfg->cuentacontable_apuestas_id ?? 0),
            default => 0,
        };
        if ($cuentaId <= 0) {
            $faltan[] = 'Cuenta contable de '.FacturacionJuegoTipoSupport::nombreCliente($rubro);
        }

        return $faltan;
    }
}
