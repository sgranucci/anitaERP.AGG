<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\SistemaNumerador;
use App\Models\Ventas\Cliente;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * EL BIERZO: dos series de código de cliente.
 * Cliente sigue el numerador CLI de Anita (serie más baja).
 * Empleado sigue 14000–14500 en sistema_numerador, con el permiso elegir-numeracion-cliente-empleado.
 */
final class ClienteCodigoEmpleadoBierzoSupport
{
    public const CODIGO_NUMERADOR = 'ventas.cliente.empleado';

    public const CLIENTE = 'cliente';

    public const EMPLEADO = 'empleado';

    public const PERMISO = 'elegir-numeracion-cliente-empleado';

    public static function habilitada(): bool
    {
        return EntornoEmpresaSupport::esElBierzo()
            && self::desde() > 0
            && self::hasta() >= self::desde();
    }

    public static function desde(): int
    {
        return (int) config('cliente.SECUENCIA_EMPLEADO_DESDE', 0);
    }

    public static function hasta(): int
    {
        return (int) config('cliente.SECUENCIA_EMPLEADO_HASTA', 0);
    }

    public static function numeroEnRango(int $numero): bool
    {
        return self::habilitada()
            && $numero >= self::desde()
            && $numero <= self::hasta();
    }

    public static function esCodigoDeSerie(?string $codigo): bool
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return false;
        }

        return self::numeroEnRango((int) $codigo);
    }

    public static function usuarioPuedeElegir(): bool
    {
        if (! self::habilitada() || ! auth()->check()) {
            return false;
        }

        return can(self::PERMISO, false) === true;
    }

    public static function muestraSelector(bool $esAlta): bool
    {
        return $esAlta && self::usuarioPuedeElegir();
    }

    /**
     * Sin el permiso, el alta sigue la serie de clientes aunque el formulario pida empleado.
     */
    public static function esPedidoEmpleado(?string $secuencia): bool
    {
        return self::usuarioPuedeElegir()
            && trim((string) $secuencia) === self::EMPLEADO;
    }

    /**
     * Mayor código ya grabado dentro del rango. 0 si todavía no hay ninguno.
     */
    public static function ultimoCargadoEnRango(): int
    {
        if (! self::habilitada()) {
            return 0;
        }

        $desde = self::desde();
        $hasta = self::hasta();
        $maximo = 0;

        Cliente::withTrashed()
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->select(['id', 'codigo'])
            ->orderBy('id')
            ->chunkById(1000, function ($filas) use (&$maximo, $desde, $hasta): void {
                foreach ($filas as $fila) {
                    $codigo = trim((string) $fila->codigo);
                    if ($codigo === '' || ! ctype_digit($codigo)) {
                        continue;
                    }
                    $numero = (int) $codigo;
                    if ($numero >= $desde && $numero <= $hasta && $numero > $maximo) {
                        $maximo = $numero;
                    }
                }
            });

        return $maximo;
    }

    public static function proximoVisible(): string
    {
        if (! self::habilitada()) {
            return '';
        }

        $ultimo = (int) SistemaNumerador::query()
            ->where('codigo', self::CODIGO_NUMERADOR)
            ->where('empresa_id', self::empresaAnclaId())
            ->value('ultimo_numero');
        if ($ultimo <= 0) {
            $ultimo = self::ultimoCargadoEnRango();
        }

        $siguiente = max($ultimo, self::desde() - 1) + 1;
        while ($siguiente <= self::hasta() && self::ocupadoEnErp($siguiente)) {
            $siguiente++;
        }

        if ($siguiente > self::hasta()) {
            return '';
        }

        return (string) $siguiente;
    }

    public static function reservarSiguiente(): string
    {
        if (! self::habilitada()) {
            throw new RuntimeException('La secuencia de clientes empleado no está habilitada.');
        }

        $asignado = (int) DB::transaction(function (): int {
            $empresaId = self::empresaAnclaId();
            $fila = SistemaNumerador::query()
                ->where('codigo', self::CODIGO_NUMERADOR)
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();

            if ($fila === null) {
                self::asegurarFila(self::ultimoCargadoEnRango());
                $fila = SistemaNumerador::query()
                    ->where('codigo', self::CODIGO_NUMERADOR)
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $desde = self::desde();
            $hasta = self::hasta();
            $siguiente = max((int) $fila->ultimo_numero, $desde - 1) + 1;
            if ($siguiente < $desde) {
                $siguiente = $desde;
            }

            while ($siguiente <= $hasta && self::ocupado($siguiente)) {
                $siguiente++;
            }

            if ($siguiente > $hasta) {
                throw new RuntimeException(
                    'No hay códigos libres en la secuencia de empleados ('.$desde.'–'.$hasta.').'
                );
            }

            $fila->ultimo_numero = $siguiente;
            $fila->numera_en_erp = true;
            $fila->activo = true;
            $fila->save();

            Log::info('Cliente empleado Bierzo: código reservado', [
                'numerador' => self::CODIGO_NUMERADOR,
                'empresa_id' => $empresaId,
                'asignado' => $siguiente,
            ]);

            return $siguiente;
        });

        return (string) $asignado;
    }

    public static function asegurarFila(int $piso): SistemaNumerador
    {
        $empresaId = self::empresaAnclaId();
        $piso = max(0, $piso);

        $existente = SistemaNumerador::query()
            ->where('codigo', self::CODIGO_NUMERADOR)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($existente !== null) {
            $dirty = false;
            if (! $existente->activo) {
                $existente->activo = true;
                $dirty = true;
            }
            if (! $existente->numera_en_erp) {
                $existente->numera_en_erp = true;
                $dirty = true;
            }
            if ($piso > (int) $existente->ultimo_numero) {
                $existente->ultimo_numero = $piso;
                $dirty = true;
            }
            if ($existente->anita_sistema !== null
                || $existente->anita_fuente !== null
                || $existente->anita_clave !== null
            ) {
                $existente->anita_sistema = null;
                $existente->anita_fuente = null;
                $existente->anita_clave = null;
                $dirty = true;
            }
            if ($dirty) {
                $existente->save();
            }

            return $existente;
        }

        return SistemaNumerador::query()->create([
            'codigo' => self::CODIGO_NUMERADOR,
            'nombre' => 'Clientes empleado',
            'empresa_id' => $empresaId,
            'modulo' => 'ventas',
            'ultimo_numero' => $piso,
            'numera_en_erp' => true,
            'anita_sistema' => null,
            'anita_fuente' => null,
            'anita_clave' => null,
            'activo' => true,
            'observacion' => 'Serie global de empleados '.$piso.' en '.self::desde().'–'.self::hasta().'. No mueve el numerador CLI de clientes.',
        ]);
    }

    private static function empresaAnclaId(): int
    {
        $id = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        if ($id > 0 && Empresa::query()->whereKey($id)->exists()) {
            return $id;
        }

        return (int) (Empresa::query()->orderBy('id')->value('id') ?: 1);
    }

    private static function ocupado(int $numero): bool
    {
        return ClienteAnitaColisionSupport::codigoOcupadoParaNuevaAsignacion($numero);
    }

    private static function ocupadoEnErp(int $numero): bool
    {
        $variantes = ClienteAnitaColisionSupport::variantesCodigoNumerico($numero);
        if ($variantes === []) {
            return false;
        }

        return DB::table('cliente')->whereIn('codigo', $variantes)->exists();
    }
}
