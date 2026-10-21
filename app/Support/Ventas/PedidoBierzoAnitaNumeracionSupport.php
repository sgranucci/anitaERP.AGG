<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Pedido;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Numeración de pedidos El Bierzo: t_comp PED → numerador Anita (num_clave = tcomp_refer).
 * El código ERP queda PED-X-00001-{nro} para que el import diario reconozca el mismo pedido.
 */
final class PedidoBierzoAnitaNumeracionSupport
{
    public const TIPO = 'PED';

    public const LETRA = 'X';

    public const SUCURSAL = 1;

    public static function reservarSiguiente(): int
    {
        self::assertElBierzo();

        $ultimo = self::leerUltimoNumero();
        $maxAnita = self::maxNumeroPendmae();
        $candidato = max($ultimo, $maxAnita) + 1;

        for ($paso = 0; $paso < 30; $paso++) {
            if (! self::numeroOcupado($candidato)) {
                self::actualizarNumerador($candidato);

                return $candidato;
            }
            $candidato++;
        }

        throw new RuntimeException('No hay un número de pedido libre en Anita.');
    }

    public static function formatearCodigo(int $nro): string
    {
        if ($nro <= 0) {
            throw new \InvalidArgumentException('Número de pedido Anita inválido.');
        }

        return sprintf(
            '%s-%s-%05d-%08d',
            self::TIPO,
            self::LETRA,
            self::SUCURSAL,
            $nro
        );
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}|null
     */
    public static function parsearCodigo(?string $codigo): ?array
    {
        $codigo = trim((string) $codigo);
        if (! preg_match('/^PED-([A-Za-z])-(\d+)-(\d+)$/', $codigo, $m)) {
            return null;
        }

        $nro = (int) $m[3];
        if ($nro <= 0) {
            return null;
        }

        return [
            'tipo' => self::TIPO,
            'letra' => strtoupper($m[1]),
            'sucursal' => (int) $m[2] > 0 ? (int) $m[2] : self::SUCURSAL,
            'nro' => $nro,
        ];
    }

    public static function whereCabecera(array $clave): string
    {
        return " WHERE penm_tipo='".self::esc($clave['tipo'])."' AND penm_letra='".self::esc($clave['letra'])
            ."' AND penm_sucursal=".(int) $clave['sucursal'].' AND penm_nro='.(int) $clave['nro'].' ';
    }

    public static function whereLineas(array $clave): string
    {
        return " WHERE penv_tipo='".self::esc($clave['tipo'])."' AND penv_letra='".self::esc($clave['letra'])
            ."' AND penv_sucursal=".(int) $clave['sucursal'].' AND penv_nro='.(int) $clave['nro'].' ';
    }

    private static function leerUltimoNumero(): int
    {
        $claveNumerador = self::esc(self::resolverClaveNumerador());
        $api = new ApiAnita();
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave = '".$claveNumerador."'",
        ], 'pedido numerador PED lectura');

        $fila = ApiAnita::primeraFilaLista($raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new RuntimeException('Numerador Anita inexistente para pedidos (t_comp PED).');
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    private static function actualizarNumerador(int $numero): void
    {
        $claveNumerador = self::esc(self::resolverClaveNumerador());
        $api = new ApiAnita();
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => " WHERE num_clave = '".$claveNumerador."'",
        ], 'pedido numerador PED update', 'anita_bridge.fallo', true);
    }

    private static function resolverClaveNumerador(): string
    {
        $api = new ApiAnita();
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 't_comp',
            'campos' => 'tcomp_refer',
            'whereArmado' => " WHERE tcomp_clave = 'PED'",
        ], 'pedido t_comp PED');

        $fila = ApiAnita::primeraFilaLista($raw);
        $refer = trim((string) ($fila->tcomp_refer ?? ''));
        if ($refer === '') {
            throw new RuntimeException('t_comp sin tcomp_refer para la clave PED en Anita.');
        }

        return $refer;
    }

    private static function maxNumeroPendmae(): int
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => 'max(penm_nro) as penm_nro',
            'whereArmado' => " WHERE penm_tipo='".self::TIPO."' AND penm_letra='".self::LETRA
                ."' AND penm_sucursal=".self::SUCURSAL.' ',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista((string) $raw);
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException('No se pudo leer el último pedido de Anita: '.$parsed['error_lectura']);
        }

        $fila = $parsed['filas'][0] ?? null;

        return max(0, (int) ($fila->penm_nro ?? 0));
    }

    private static function numeroOcupado(int $nro): bool
    {
        if (Pedido::query()->where('codigo', self::formatearCodigo($nro))->exists()) {
            return true;
        }

        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => 'penm_nro',
            'whereArmado' => self::whereCabecera([
                'tipo' => self::TIPO,
                'letra' => self::LETRA,
                'sucursal' => self::SUCURSAL,
                'nro' => $nro,
            ]),
        ]);
        $parsed = ApiAnita::parsearRespuestaLista((string) $raw);
        if (($parsed['error_lectura'] ?? null) !== null) {
            throw new RuntimeException('No se pudo verificar el pedido '.$nro.' en Anita: '.$parsed['error_lectura']);
        }

        return ($parsed['filas'] ?? []) !== [];
    }

    private static function assertElBierzo(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            throw new RuntimeException('La numeración de pedidos Anita solo aplica a El Bierzo.');
        }
    }

    private static function esc(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}
