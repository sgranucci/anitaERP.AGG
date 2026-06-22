<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Ordencompra;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraService;
use RuntimeException;

final class PrecargaProveedorResolucionSupport
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private ProveedorRepositoryInterface $proveedorRepository,
        private OrdencompraService $ordencompraService,
    ) {}

    /**
     * Biyemas / Rebisco / Kandiko: empresa operativa con codigo &lt; 5.
     *
     * @return array{empresa_id: int, codigo: string, nombre: string}
     */
    public function resolverEmpresaPorCuit(string $cuit): array
    {
        foreach ($this->variantesDocumento($cuit) as $documento) {
            $empresas = $this->empresaRepository->findPorDocumento($documento);
            if (! $empresas) {
                continue;
            }

            foreach ($empresas as $empresa) {
                if ((string) $empresa->codigo < '5') {
                    return [
                        'empresa_id' => (int) $empresa->id,
                        'codigo' => (string) $empresa->codigo,
                        'nombre' => (string) $empresa->nombre,
                    ];
                }
            }
        }

        throw new RuntimeException('No se encontró empresa destinatario (Biyemas/Rebisco/Kandiko) para CUIT «'.$cuit.'»');
    }

    /**
     * CUIT del proveedor desde Anita (prom_cuit) cuando el OCR/PDF no lo trae.
     */
    public function resolverCuitProveedorDesdeOc(string $numeroOc): string
    {
        $ordencompra = $this->ordencompraService->leeOrdenCompra($numeroOc);
        if ($ordencompra === 'OC inexistente') {
            throw new RuntimeException('OC inexistente');
        }

        $raw = trim((string) ($ordencompra['ordencompra']->prom_cuit ?? ''));
        if ($raw === '') {
            throw new RuntimeException('La OC no tiene CUIT de proveedor en Anita.');
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) !== 11) {
            throw new RuntimeException('CUIT de proveedor inválido en la OC.');
        }

        return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10, 1);
    }

    /**
     * Empresa operativa desde OC local cuando el PDF no trae CUIT destinatario.
     *
     * @return array{empresa_id: int, codigo: string, nombre: string}
     */
    public function resolverEmpresaPorOc(string $numeroOc): array
    {
        $oc = Ordencompra::query()
            ->where('numeroordencompra', (int) preg_replace('/\D/', '', $numeroOc))
            ->orderByDesc('id')
            ->first();

        if (! $oc || ! $oc->empresa_id) {
            throw new RuntimeException(
                'No se pudo determinar la empresa desde la OC «'.$numeroOc.'». Verifique que exista en el ERP.'
            );
        }

        $empresa = $this->empresaRepository->find((int) $oc->empresa_id);
        if (! $empresa) {
            throw new RuntimeException('Empresa de la OC no existe en el ERP.');
        }

        return [
            'empresa_id' => (int) $empresa->id,
            'codigo' => (string) $empresa->codigo,
            'nombre' => (string) $empresa->nombre,
        ];
    }

    /**
     * @return array{proveedor_id: int, codigo: string, nombre: string}
     */
    public function resolverProveedorPorOc(string $cuitProveedor, string $numeroOc): array
    {
        $ordencompra = $this->ordencompraService->leeOrdenCompra($numeroOc);
        if ($ordencompra === 'OC inexistente') {
            throw new RuntimeException('OC inexistente');
        }

        $datos = $ordencompra['ordencompra'];
        $cuitOc = str_replace('-', '', (string) ($datos->prom_cuit ?? ''));
        $cuit = str_replace('-', '', $cuitProveedor);
        if ($cuitOc !== $cuit) {
            throw new RuntimeException('El CUIT del proveedor no coincide con la OC '.$numeroOc);
        }

        $codigoProveedorOc = ltrim((string) ($datos->penmp_proveedor ?? ''), '0');
        if ($codigoProveedorOc === '') {
            throw new RuntimeException('La orden de compra no tiene proveedor asignado');
        }

        $proveedor = $this->proveedorRepository->findPorCodigo($codigoProveedorOc);
        if (! $proveedor) {
            throw new RuntimeException('Proveedor de la OC (código '.$codigoProveedorOc.') no existe en el ERP');
        }

        $this->assertProveedorHabilitado($proveedor);

        return [
            'proveedor_id' => (int) $proveedor->id,
            'codigo' => (string) $proveedor->codigo,
            'nombre' => (string) $proveedor->nombre,
        ];
    }

    /**
     * @return array{proveedor_id: int, codigo: string, nombre: string}
     */
    public function resolverProveedorPorCuit(string $cuit): array
    {
        foreach ($this->variantesDocumento($cuit) as $documento) {
            $proveedores = $this->proveedorRepository->findPorDocumento($documento);
            if (! $proveedores || $proveedores->isEmpty()) {
                continue;
            }

            foreach ($proveedores as $proveedor) {
                if ($this->proveedorEstaHabilitado($proveedor)) {
                    return [
                        'proveedor_id' => (int) $proveedor->id,
                        'codigo' => (string) $proveedor->codigo,
                        'nombre' => (string) $proveedor->nombre,
                    ];
                }
            }
        }

        throw new RuntimeException('No hay proveedor activo o regularizado para CUIT «'.$cuit.'»');
    }

    public function assertProveedorHabilitado(object $proveedor): void
    {
        if (! $this->proveedorEstaHabilitado($proveedor)) {
            throw new RuntimeException(
                'Proveedor «'.($proveedor->nombre ?? $proveedor->id).'» no está activo ni regularizado'
            );
        }
    }

    private function proveedorEstaHabilitado(object $proveedor): bool
    {
        return in_array((string) ($proveedor->estado ?? ''), ['0', '3'], true)
            || in_array($proveedor->estado ?? '', ['Activo', 'Regularizado'], true);
    }

    /** @return list<string> */
    private function variantesDocumento(string $documento): array
    {
        $documento = trim($documento);
        $sinGuiones = str_replace('-', '', $documento);
        $variantes = [$documento];
        if ($sinGuiones !== $documento) {
            $variantes[] = $sinGuiones;
        }
        if (strlen($sinGuiones) === 11 && ctype_digit($sinGuiones)) {
            $conGuiones = substr($sinGuiones, 0, 2).'-'.substr($sinGuiones, 2, 8).'-'.substr($sinGuiones, 10, 1);
            if (! in_array($conGuiones, $variantes, true)) {
                $variantes[] = $conGuiones;
            }
        }

        return array_values(array_unique($variantes));
    }
}
