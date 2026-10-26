<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\SistemaNumerador;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\PedidoInterforming;
use Illuminate\Support\Facades\DB;

/**
 * Numeradores PED (venta local) y PEX (exportación) de pedidos Interforming.
 * El tipo sale de la condición de IVA del cliente: letra E → PEX; el resto → PED.
 */
final class PedidoInterformingNumeracionSupport
{
    public const TIPO_VENTA = 'PED';

    public const TIPO_EXPORTACION = 'PEX';

    public const SUCURSAL = 1;

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int, codigo: string}
     */
    public static function reservar(?Cliente $cliente): array
    {
        $tipo = self::tipoDesdeCliente($cliente);
        $letra = 'X';
        $empresaId = self::empresaId();

        return DB::transaction(function () use ($tipo, $letra, $empresaId): array {
            $row = self::asegurarFila($tipo, $empresaId);
            /** @var SistemaNumerador $row */
            $row = SistemaNumerador::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            if (! $row->activo) {
                throw new \RuntimeException('El numerador '.$row->codigo.' está inactivo.');
            }

            $siguiente = max((int) $row->ultimo_numero, self::maxNumeroExistente($tipo));
            $saltos = 0;
            do {
                $siguiente++;
                $saltos++;
                if ($saltos > 50) {
                    throw new \RuntimeException('No hay número libre en el numerador '.$tipo.'.');
                }
                $codigo = self::armarCodigo($tipo, $letra, self::SUCURSAL, $siguiente);
            } while (PedidoInterforming::query()->where('codigo', $codigo)->exists());

            $row->ultimo_numero = $siguiente;
            $row->save();

            return [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => self::SUCURSAL,
                'numero' => $siguiente,
                'codigo' => $codigo,
            ];
        });
    }

    public static function tipoDesdeCliente(?object $cliente): string
    {
        return FacturacionCircuitoAfipSupport::letraClienteDesdeModelo($cliente) === 'E'
            ? self::TIPO_EXPORTACION
            : self::TIPO_VENTA;
    }

    public static function codigoNumerador(string $tipo): string
    {
        return 'ventas.'.($tipo === self::TIPO_EXPORTACION ? self::TIPO_EXPORTACION : self::TIPO_VENTA);
    }

    public static function armarCodigo(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        $tipo = $tipo === self::TIPO_EXPORTACION ? self::TIPO_EXPORTACION : self::TIPO_VENTA;
        $letra = strtoupper(substr(trim($letra), 0, 1)) ?: 'X';

        return $tipo.'-'.$letra.'-'
            .str_pad((string) max(0, $sucursal), 5, '0', STR_PAD_LEFT).'-'
            .str_pad((string) max(0, $numero), 8, '0', STR_PAD_LEFT);
    }

    private static function asegurarFila(string $tipo, int $empresaId): SistemaNumerador
    {
        $codigo = self::codigoNumerador($tipo);
        $existente = SistemaNumerador::query()
            ->where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->first();
        if ($existente !== null) {
            return $existente;
        }

        $nombre = $tipo === self::TIPO_EXPORTACION
            ? 'Pedidos de exportación (PEX)'
            : 'Pedidos de venta (PED)';

        return SistemaNumerador::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'empresa_id' => $empresaId,
            'modulo' => 'ventas',
            'ultimo_numero' => self::maxNumeroExistente($tipo),
            'numera_en_erp' => true,
            'anita_sistema' => null,
            'anita_fuente' => null,
            'anita_clave' => null,
            'activo' => true,
            'observacion' => 'Secuencia ERP de pedidos Interforming. Letra E del cliente usa PEX.',
        ]);
    }

    private static function maxNumeroExistente(string $tipo): int
    {
        return (int) (PedidoInterforming::query()
            ->where('tipo_comprobante', $tipo)
            ->max('numero_comprobante') ?? 0);
    }

    private static function empresaId(): int
    {
        $session = (int) session('empresa_id');
        if ($session > 0 && Empresa::query()->whereKey($session)->exists()) {
            return $session;
        }

        $id = (int) (Empresa::query()->orderBy('id')->value('id') ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('No hay empresa para el numerador de pedidos.');
        }

        return $id;
    }
}
