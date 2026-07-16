<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Models\Compras\Proveedor;
use App\Support\Compras\ProveedorExclusionAnitaSupport;

/**
 * Resuelve nombre, CUIT y condición IVA desde proveedor ERP (tabla proveedor).
 * El código Anita (retmov/retimov) se mapea con {@see ProveedorExclusionAnitaSupport::codigoErpDesdeAnita}.
 */
final class SicoreProveedorErpSupport
{
    /** @var array<string, array{nombre: string, cuit: string, cod_condicion: int}> */
    private array $cache = [];

    /** @var array<string, bool> */
    private array $existeAnita = [];

    /**
     * Códigos Anita (subd_emisor) que existen en el maestro ERP de proveedores.
     *
     * @param  list<string>  $codigosAnita
     * @return array<string, true>  Clave = código Anita normalizado (bridge)
     */
    public function indicesExistentes(array $codigosAnita): array
    {
        $this->precargar($codigosAnita);

        $out = [];
        foreach ($codigosAnita as $codigo) {
            $anita = $this->normalizarCodigoAnita($codigo);
            if ($anita !== '' && ! empty($this->existeAnita[$anita])) {
                $out[$anita] = true;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigosProveedor  Códigos Anita (6 dígitos) desde retmov/retimov / subd_emisor
     */
    public function precargar(array $codigosProveedor): void
    {
        $pendientes = [];
        /** @var array<string, string> $erpPorAnita */
        $erpPorAnita = [];

        foreach ($codigosProveedor as $codigo) {
            $anita = $this->normalizarCodigoAnita($codigo);
            if ($anita === '' || array_key_exists($anita, $this->cache)) {
                continue;
            }
            $pendientes[] = $anita;
            $erpPorAnita[$anita] = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita($anita);
        }

        if ($pendientes === []) {
            return;
        }

        $codigosErp = array_values(array_unique(array_values($erpPorAnita)));

        /** @var array<string, Proveedor> $porCodigoErp */
        $porCodigoErp = [];
        foreach (Proveedor::query()
            ->with('condicionivas')
            ->whereIn('codigo', $codigosErp)
            ->get(['id', 'codigo', 'nombre', 'fantasia', 'nroinscripcion', 'condicioniva_id']) as $proveedor) {
            $porCodigoErp[(string) $proveedor->codigo] = $proveedor;
        }

        foreach ($pendientes as $anita) {
            $erpCod = $erpPorAnita[$anita];
            $proveedor = $porCodigoErp[$erpCod]
                ?? $porCodigoErp[str_pad($erpCod, 6, '0', STR_PAD_LEFT)]
                ?? null;

            $this->existeAnita[$anita] = $proveedor !== null;
            $this->cache[$anita] = $this->datosDesdeProveedor($proveedor);
        }

        foreach ($pendientes as $anita) {
            $this->cache[$anita] ??= [
                'nombre' => '',
                'cuit' => '',
                'cod_condicion' => 1,
            ];
            $this->existeAnita[$anita] ??= false;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{codigo_proveedor: string, nombre: string, cuit: string, cod_condicion: int}
     */
    public function resolverDesdeFila(
        array $row,
        string $campoProveedor,
        string $campoNombre,
        string $campoCuit,
    ): array {
        $codigoAnita = $this->normalizarCodigoAnita((string) ($row[$campoProveedor] ?? ''));
        $nombreMov = trim((string) ($row[$campoNombre] ?? ''));
        $cuitMov = trim((string) ($row[$campoCuit] ?? ''));

        $datos = $this->cache[$codigoAnita] ?? null;
        if ($datos === null && $codigoAnita !== '') {
            $this->precargar([$codigoAnita]);
            $datos = $this->cache[$codigoAnita] ?? [
                'nombre' => '',
                'cuit' => '',
                'cod_condicion' => 1,
            ];
        }

        if ($datos === null) {
            $datos = ['nombre' => '', 'cuit' => '', 'cod_condicion' => 1];
        }

        return [
            'codigo_proveedor' => $codigoAnita,
            'nombre' => $datos['nombre'] !== '' ? $datos['nombre'] : $nombreMov,
            'cuit' => $datos['cuit'] !== '' ? $datos['cuit'] : $cuitMov,
            'cod_condicion' => (int) ($datos['cod_condicion'] ?? 1),
        ];
    }

    /**
     * @return array{nombre: string, cuit: string, cod_condicion: int}
     */
    private function datosDesdeProveedor(?Proveedor $proveedor): array
    {
        if ($proveedor === null) {
            return [
                'nombre' => '',
                'cuit' => '',
                'cod_condicion' => 1,
            ];
        }

        $nombre = trim((string) $proveedor->nombre);
        if ($nombre === '') {
            $nombre = trim((string) ($proveedor->fantasia ?? ''));
        }

        return [
            'nombre' => $nombre,
            'cuit' => trim((string) ($proveedor->nroinscripcion ?? '')),
            'cod_condicion' => SicoreCodigoCondicionSupport::desdeCondicionIvaCliente(
                $proveedor->condicionivas?->nombre,
            ),
        ];
    }

    private function normalizarCodigoAnita(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        return ProveedorExclusionAnitaSupport::codigoAnitaParaBridge($codigo);
    }
}
