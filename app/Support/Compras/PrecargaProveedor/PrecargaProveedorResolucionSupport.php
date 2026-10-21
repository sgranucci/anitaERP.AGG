<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Ordencompra;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraService;
use App\Support\Configuracion\EntornoEmpresaSupport;
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
     * Si el CUIT OCR no matchea exacto, tolera 1 dígito erróneo (ej. 36-68240367-1 → 30-68240367-1).
     * Si tampoco, prueba cambiar ceros leídos por ochos (el 8 del escaneo sale como 0) y acepta
     * solo si una única empresa conocida coincide.
     *
     * @return array{
     *   empresa_id: int,
     *   codigo: string,
     *   nombre: string,
     *   cuit_corregido?: string,
     *   cuit_leido?: string
     * }
     */
    public function resolverEmpresaPorCuit(string $cuit): array
    {
        if (EntornoEmpresaSupport::esAgg()) {
            return $this->resolverEmpresaPorCuitAgg($cuit);
        }

        return $this->resolverEmpresaPorCuitInstalacion($cuit);
    }

    /**
     * AGG: Biyemas / Rebisco / Kandiko (codigo &lt; 5). No alterar este criterio.
     *
     * @return array{
     *   empresa_id: int,
     *   codigo: string,
     *   nombre: string,
     *   cuit_corregido?: string,
     *   cuit_leido?: string
     * }
     */
    private function resolverEmpresaPorCuitAgg(string $cuit): array
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

        $aproximada = $this->resolverEmpresaPorCuitOcrTolerante($cuit, true);
        if ($aproximada !== null) {
            return $aproximada;
        }

        throw new RuntimeException('No se encontró empresa destinatario (Biyemas/Rebisco/Kandiko) para CUIT «'.$cuit.'»');
    }

    /**
     * Ferli y el resto: cualquier empresa de la instalación, por CUIT.
     *
     * @return array{
     *   empresa_id: int,
     *   codigo: string,
     *   nombre: string,
     *   cuit_corregido?: string,
     *   cuit_leido?: string
     * }
     */
    private function resolverEmpresaPorCuitInstalacion(string $cuit): array
    {
        foreach ($this->variantesDocumento($cuit) as $documento) {
            $empresas = $this->empresaRepository->findPorDocumento($documento);
            if (! $empresas) {
                continue;
            }

            foreach ($empresas as $empresa) {
                return [
                    'empresa_id' => (int) $empresa->id,
                    'codigo' => (string) $empresa->codigo,
                    'nombre' => (string) $empresa->nombre,
                ];
            }
        }

        $aproximada = $this->resolverEmpresaPorCuitOcrTolerante($cuit, false);
        if ($aproximada !== null) {
            return $aproximada;
        }

        throw new RuntimeException('No se encontró empresa destinataria para CUIT «'.$cuit.'».');
    }

    /**
     * Corrige CUITs mal leídos por OCR contra las empresas operativas conocidas.
     * Primero un único candidato a distancia de Hamming 1 (11 dígitos).
     * Si no hay ninguno, prueba reemplazar ceros del leído por ochos: el 8 del escaneo
     * suele salir como 0 (ej. 30-70022205-9 → 30-70822285-9). Acepta solo un candidato.
     *
     * @return array{
     *   empresa_id: int,
     *   codigo: string,
     *   nombre: string,
     *   cuit_corregido: string,
     *   cuit_leido: string
     * }|null
     */
    private function resolverEmpresaPorCuitOcrTolerante(string $cuit, bool $soloCodigoMenorA5): ?array
    {
        $leido = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($leido) !== 11 || ! ctype_digit($leido)) {
            return null;
        }

        $empresas = $soloCodigoMenorA5
            ? $this->empresasOperativasConCuit()
            : $this->empresasConCuit();

        $candidatos = [];
        foreach ($empresas as $empresa) {
            $conocido = preg_replace('/\D/', '', (string) $empresa->nroinscripcion) ?? '';
            if (strlen($conocido) !== 11 || ! ctype_digit($conocido)) {
                continue;
            }

            if ($this->distanciaHammingDigitos($leido, $conocido) === 1) {
                $candidatos[] = [
                    'empresa' => $empresa,
                    'cuit' => $conocido,
                ];
            }
        }

        if (count($candidatos) === 1) {
            return $this->empaquetarEmpresaCuitCorregido($candidatos[0]['empresa'], $candidatos[0]['cuit'], $leido);
        }

        if ($candidatos !== []) {
            return null;
        }

        $porCeros = [];
        foreach ($empresas as $empresa) {
            $conocido = preg_replace('/\D/', '', (string) $empresa->nroinscripcion) ?? '';
            if (! $this->cuitConocidoSaleDeCerosComoOchos($leido, $conocido)) {
                continue;
            }

            $porCeros[] = [
                'empresa' => $empresa,
                'cuit' => $conocido,
            ];
        }

        if (count($porCeros) !== 1) {
            return null;
        }

        return $this->empaquetarEmpresaCuitCorregido($porCeros[0]['empresa'], $porCeros[0]['cuit'], $leido);
    }

    /**
     * El CUIT conocido se obtiene cambiando uno o más ceros del leído por ochos, sin otro dígito distinto.
     */
    private function cuitConocidoSaleDeCerosComoOchos(string $leido, string $conocido): bool
    {
        if (strlen($leido) !== 11 || strlen($conocido) !== 11 || ! ctype_digit($conocido)) {
            return false;
        }

        $cambios = 0;
        for ($i = 0; $i < 11; $i++) {
            if ($leido[$i] === $conocido[$i]) {
                continue;
            }

            if ($leido[$i] !== '0' || $conocido[$i] !== '8') {
                return false;
            }

            $cambios++;
        }

        return $cambios >= 1;
    }

    /**
     * @return array{
     *   empresa_id: int,
     *   codigo: string,
     *   nombre: string,
     *   cuit_corregido: string,
     *   cuit_leido: string
     * }
     */
    private function empaquetarEmpresaCuitCorregido(object $empresa, string $cuitConocido, string $cuitLeido): array
    {
        return [
            'empresa_id' => (int) $empresa->id,
            'codigo' => (string) $empresa->codigo,
            'nombre' => (string) $empresa->nombre,
            'cuit_corregido' => $this->formatearCuit11($cuitConocido),
            'cuit_leido' => $this->formatearCuit11($cuitLeido),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function empresasOperativasConCuit()
    {
        return collect($this->empresaRepository->all() ?? [])
            ->filter(function ($empresa) {
                return (string) ($empresa->codigo ?? '9') < '5'
                    && filled($empresa->nroinscripcion ?? null);
            })
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function empresasConCuit()
    {
        return collect($this->empresaRepository->all() ?? [])
            ->filter(fn ($empresa) => filled($empresa->nroinscripcion ?? null))
            ->values();
    }

    private function distanciaHammingDigitos(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b)) {
            return PHP_INT_MAX;
        }

        $dist = 0;
        $len = strlen($a);
        for ($i = 0; $i < $len; $i++) {
            if ($a[$i] !== $b[$i]) {
                $dist++;
            }
        }

        return $dist;
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
        if (! PrecargaProveedorCuitCoincidenciaSupport::coinciden($cuitOc, $cuit)) {
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

        $resultado = [
            'proveedor_id' => (int) $proveedor->id,
            'codigo' => (string) $proveedor->codigo,
            'nombre' => (string) $proveedor->nombre,
        ];

        $cuitMaestro = (string) ($proveedor->nroinscripcion ?? '');
        if ($cuitMaestro !== ''
            && ! PrecargaProveedorCuitCoincidenciaSupport::digitoVerificadorValido($cuitProveedor)
            && PrecargaProveedorCuitCoincidenciaSupport::coinciden($cuitMaestro, $cuitProveedor)
        ) {
            $resultado['cuit_corregido'] = PrecargaProveedorCuitCoincidenciaSupport::corregirDigitoVerificador($cuitProveedor)
                ?? $cuitMaestro;
            $resultado['cuit_leido'] = $this->formatearCuit11($cuitProveedor);
        }

        return $resultado;
    }

    /**
     * @return array{
     *   proveedor_id: int,
     *   codigo: string,
     *   nombre: string,
     *   cuit_corregido?: string,
     *   cuit_leido?: string
     * }
     */
    public function resolverProveedorPorCuit(string $cuit): array
    {
        $hallado = $this->buscarProveedorHabilitado($cuit);
        if ($hallado !== null) {
            return $hallado;
        }

        if (! PrecargaProveedorCuitCoincidenciaSupport::digitoVerificadorValido($cuit)) {
            $corregido = PrecargaProveedorCuitCoincidenciaSupport::corregirDigitoVerificador($cuit);
            if ($corregido !== null) {
                $hallado = $this->buscarProveedorHabilitado($corregido);
                if ($hallado !== null) {
                    $hallado['cuit_corregido'] = $corregido;
                    $hallado['cuit_leido'] = $this->formatearCuit11($cuit);

                    return $hallado;
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

    /**
     * @return array{proveedor_id: int, codigo: string, nombre: string}|null
     */
    private function buscarProveedorHabilitado(string $cuit): ?array
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

        return null;
    }

    private function formatearCuit11(string $cuit): string
    {
        $digitos = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($digitos) !== 11) {
            return $cuit;
        }

        return substr($digitos, 0, 2).'-'.substr($digitos, 2, 8).'-'.substr($digitos, 10, 1);
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
