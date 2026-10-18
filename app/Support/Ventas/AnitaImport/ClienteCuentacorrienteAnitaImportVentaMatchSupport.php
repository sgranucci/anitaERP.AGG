<?php

namespace App\Support\Ventas\AnitaImport;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Empareja climov (tipo/letra/sucursal/nro) con filas `venta` del ERP.
 */
final class ClienteCuentacorrienteAnitaImportVentaMatchSupport
{
    /**
     * Índice claveDocumento => listado de ventas (puede haber colisiones raras).
     *
     * @param  list<array{tipo:string,letra:string,sucursal:int,numero:int}>  $claves
     * @return array<string, list<object>>
     */
    public static function indexarVentasPorClaves(array $claves): array
    {
        if ($claves === [] || ! Venta::query()->exists()) {
            return [];
        }

        $porTipoNro = [];
        foreach ($claves as $c) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($c['tipo']);
            $nro = (int) $c['numero'];
            if ($tipo === '' || $nro <= 0) {
                continue;
            }
            $porTipoNro[$tipo][$nro] = true;
        }
        if ($porTipoNro === []) {
            return [];
        }

        // MySQL ~65535 placeholders: no armar un solo WHERE con todos los nros.
        $lotes = [];
        $loteActual = [];
        $placeholders = 0;
        foreach ($porTipoNro as $tipo => $nrosMap) {
            $nros = array_map('intval', array_keys($nrosMap));
            foreach (array_chunk($nros, 400) as $chunk) {
                $costo = 1 + count($chunk);
                if ($loteActual !== [] && ($placeholders + $costo) > 5000) {
                    $lotes[] = $loteActual;
                    $loteActual = [];
                    $placeholders = 0;
                }
                $loteActual[] = ['tipo' => $tipo, 'nros' => $chunk];
                $placeholders += $costo;
            }
        }
        if ($loteActual !== []) {
            $lotes[] = $loteActual;
        }

        $out = [];
        foreach ($lotes as $lote) {
            $query = Venta::query()
                ->from('venta as v')
                ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
                ->join('tipotransaccion as t', 't.id', '=', 'v.tipotransaccion_id')
                ->select([
                    'v.id',
                    'v.codigo',
                    'v.numerocomprobante',
                    'v.cliente_id',
                    'v.fecha',
                    'v.moneda_id',
                    'v.cotizacion',
                    'v.puntoventa_id',
                    'v.tipotransaccion_id',
                    'pv.codigo as puntoventa_codigo',
                    'pv.empresa_id',
                    't.abreviatura',
                    't.signo',
                ]);

            $query->where(function ($q) use ($lote) {
                foreach ($lote as $parte) {
                    $q->orWhere(function ($qq) use ($parte) {
                        $qq->where('t.abreviatura', $parte['tipo'])
                            ->whereIn('v.numerocomprobante', $parte['nros']);
                    });
                }
            });

            foreach ($query->get() as $venta) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) $venta->abreviatura);
                $letra = self::letraDesdeCodigoVenta((string) $venta->codigo);
                $suc = (int) $venta->puntoventa_codigo;
                $nro = (int) $venta->numerocomprobante;
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($tipo, $letra, $suc, $nro);
                $out[$clave][] = $venta;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $climov
     * @param  array<string, list<object>>  $indice
     */
    public static function resolver(array $climov, array $indice): ?object
    {
        $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
        $candidatos = $indice[$clave] ?? [];
        if ($candidatos === []) {
            // Fallback flexible: mismo tipo+nro+importe no; solo tipo+nro+letra ignorando padding sucursal ya normalizado
            return null;
        }

        if (count($candidatos) === 1) {
            return $candidatos[0];
        }

        $clienteAnita = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp(
            (string) ($climov['cliv_cliente'] ?? '')
        );
        foreach ($candidatos as $venta) {
            $codigoCliente = self::codigoClienteErp((int) $venta->cliente_id);
            if ($codigoCliente !== null && $codigoCliente === $clienteAnita) {
                return $venta;
            }
        }

        return $candidatos[0];
    }

    public static function letraDesdeCodigoVenta(string $codigo): string
    {
        // "FAC A-00012-00083016"
        if (preg_match('/^[A-Z0-9]{1,3}\s+([A-Z0-9])-/i', trim($codigo), $m)) {
            return ClienteCuentacorrienteAnitaImportClaveSupport::letra($m[1]);
        }
        if (preg_match('/\s([A-Z0-9])-/i', $codigo, $m)) {
            return ClienteCuentacorrienteAnitaImportClaveSupport::letra($m[1]);
        }

        return ' ';
    }

    private static function codigoClienteErp(int $clienteId): ?string
    {
        static $cache = [];
        if (array_key_exists($clienteId, $cache)) {
            return $cache[$clienteId];
        }
        $cod = DB::table('cliente')->where('id', $clienteId)->value('codigo');
        if ($cod === null) {
            return $cache[$clienteId] = null;
        }

        return $cache[$clienteId] = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp((string) $cod);
    }
}
