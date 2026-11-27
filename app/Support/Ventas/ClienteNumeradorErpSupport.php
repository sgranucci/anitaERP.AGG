<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\SistemaNumerador;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numerador de clientes en el ERP (tabla sistema_numerador).
 * Crown: una sola serie. Arranca en el último código ya creado (ERP o climae, el mayor)
 * y cada alta lo avanza. No escribe el numerador de Anita.
 */
final class ClienteNumeradorErpSupport
{
    public const CODIGO = 'ventas.cliente';

    public static function habilitada(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    /**
     * Deja el numerador en el último código existente si estaba atrás o no existía.
     * No lo baja si ya avanzó por altas posteriores.
     */
    public static function asegurarDesdeUltimoCreado(): SistemaNumerador
    {
        $piso = self::ultimoCodigoCreado();
        $empresaId = self::empresaAnclaId();

        $fila = SistemaNumerador::query()
            ->where('codigo', self::CODIGO)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($fila === null) {
            return SistemaNumerador::query()->create([
                'codigo' => self::CODIGO,
                'nombre' => 'Clientes',
                'empresa_id' => $empresaId,
                'modulo' => 'ventas',
                'ultimo_numero' => $piso,
                'numera_en_erp' => true,
                'anita_sistema' => null,
                'anita_fuente' => null,
                'anita_clave' => null,
                'activo' => true,
                'observacion' => 'Serie única de clientes. Sigue desde el último código creado. No mueve el numerador CLI de Anita.',
            ]);
        }

        if ($piso > (int) $fila->ultimo_numero || ! $fila->activo || ! $fila->numera_en_erp) {
            $fila->ultimo_numero = max((int) $fila->ultimo_numero, $piso);
            $fila->activo = true;
            $fila->numera_en_erp = true;
            $fila->anita_sistema = null;
            $fila->anita_fuente = null;
            $fila->anita_clave = null;
            $fila->save();
        }

        return $fila;
    }

    public static function proximoVisible(): string
    {
        if (! self::habilitada()) {
            return '';
        }

        $ultimo = (int) SistemaNumerador::query()
            ->where('codigo', self::CODIGO)
            ->where('empresa_id', self::empresaAnclaId())
            ->value('ultimo_numero');
        if ($ultimo <= 0) {
            $ultimo = self::ultimoCodigoCreado();
        }

        $numero = ClienteAnitaColisionSupport::primerCodigoDisponible($ultimo + 1);

        return self::formatear($numero);
    }

    public static function reservarSiguiente(): string
    {
        if (! self::habilitada()) {
            throw new \RuntimeException('El numerador de clientes del ERP no está habilitado.');
        }

        $asignado = (int) DB::transaction(function (): int {
            $empresaId = self::empresaAnclaId();
            $fila = SistemaNumerador::query()
                ->where('codigo', self::CODIGO)
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();

            if ($fila === null) {
                self::asegurarDesdeUltimoCreado();
                $fila = SistemaNumerador::query()
                    ->where('codigo', self::CODIGO)
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $piso = max((int) $fila->ultimo_numero, self::ultimoCodigoCreado());
            $numero = ClienteAnitaColisionSupport::primerCodigoDisponible($piso + 1);

            $fila->ultimo_numero = $numero;
            $fila->numera_en_erp = true;
            $fila->activo = true;
            $fila->save();

            Log::info('Cliente numerador ERP: código reservado', [
                'numerador' => self::CODIGO,
                'empresa_id' => $empresaId,
                'piso' => $piso,
                'asignado' => $numero,
            ]);

            return $numero;
        });

        return self::formatear($asignado);
    }

    /**
     * Mayor código numérico ya usado. En Crown el último cargado es el de 6 dígitos (001019).
     */
    public static function ultimoCodigoCreado(): int
    {
        return max(
            ClienteAnitaColisionSupport::maxCodigoClienteErp(),
            ClienteAnitaColisionSupport::maxCodigoClimae()
        );
    }

    public static function formatear(int $numero): string
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de cliente ERP inválido.');
        }

        return str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    private static function empresaAnclaId(): int
    {
        $id = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        if ($id > 0 && Empresa::query()->whereKey($id)->exists()) {
            return $id;
        }

        return (int) (Empresa::query()->orderBy('id')->value('id') ?: 1);
    }
}
