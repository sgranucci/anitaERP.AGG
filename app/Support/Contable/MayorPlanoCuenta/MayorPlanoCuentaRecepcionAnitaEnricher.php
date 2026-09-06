<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza líneas Anita tipo COM con recepcion_proveedor y resuelve la OC real.
 *
 * El nro del COM no es la OC (choca con OCs homónimas de otro proveedor).
 * La OC Anita es el PEP en recepmae: recm_tipo_fac=PEP + recm_nro_fac
 * (ver RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera).
 *
 * Las filas de pantalla a veces solo traen comprobante formateado (X0001-00159903)
 * sin letra/sucursal/nro sueltos: hay que parsearlos del texto.
 */
class MayorPlanoCuentaRecepcionAnitaEnricher
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        if ($filas === []) {
            return $filas;
        }

        $partesPorIdx = [];
        $clavesRecepcion = [];
        $nrosParaPep = [];

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $partes = $this->partesComAnita($fila);
            if ($partes === null) {
                continue;
            }
            $partesPorIdx[$idx] = $partes;
            $clave = $this->claveDePartes($partes);
            if ((int) ($fila['recepcionproveedor_id'] ?? 0) <= 0) {
                $clavesRecepcion[$clave] = true;
            }
            $nrosParaPep[$partes['nro']] = $partes['nro'];
        }

        if ($partesPorIdx === []) {
            return $filas;
        }

        $mapaRecepcion = [];
        if ($clavesRecepcion !== [] && Schema::hasTable('recepcion_proveedor')
            && Schema::hasColumn('recepcion_proveedor', 'anita_nro')) {
            $mapaRecepcion = $this->cargarRecepciones(array_keys($clavesRecepcion));
        }

        // 1) Enlazar recepción + limpiar nro_oc = nro COM (homónima falsa).
        foreach ($partesPorIdx as $idx => $partes) {
            $fila = $filas[$idx];
            $clave = $this->claveDePartes($partes);
            $nroCom = $partes['nro'];

            $filas[$idx]['letra'] = $partes['letra'];
            $filas[$idx]['sucursal'] = $partes['sucursal'];
            $filas[$idx]['nro'] = $partes['nro'];

            if ((int) ($filas[$idx]['recepcionproveedor_id'] ?? 0) <= 0 && isset($mapaRecepcion[$clave])) {
                $filas[$idx]['recepcionproveedor_id'] = (int) $mapaRecepcion[$clave]['id'];
            }

            $nroOcActual = (int) ($fila['nro_oc'] ?? 0);
            if ($nroCom > 0 && $nroOcActual === $nroCom) {
                $filas[$idx]['nro_oc'] = 0;
                $filas[$idx]['ordencompra_id'] = 0;
            }
        }

        // 2) OC desde recepción ERP ya vinculada.
        $ocIdsRecepcion = [];
        foreach ($mapaRecepcion as $rec) {
            $ocId = (int) ($rec['ordencompra_id'] ?? 0);
            if ($ocId > 0) {
                $ocIdsRecepcion[$ocId] = $ocId;
            }
        }
        // También recepciones ya presentes en la fila.
        $recIdsExistentes = [];
        foreach ($partesPorIdx as $idx => $_) {
            $recId = (int) ($filas[$idx]['recepcionproveedor_id'] ?? 0);
            if ($recId > 0) {
                $recIdsExistentes[$recId] = $recId;
            }
        }
        $ocPorRecepcionId = [];
        if ($recIdsExistentes !== [] && Schema::hasTable('recepcion_proveedor')) {
            $rows = DB::table('recepcion_proveedor')
                ->whereIn('id', array_values($recIdsExistentes))
                ->where('ordencompra_id', '>', 0)
                ->get(['id', 'ordencompra_id']);
            foreach ($rows as $row) {
                $ocPorRecepcionId[(int) $row->id] = (int) $row->ordencompra_id;
                $ocIdsRecepcion[(int) $row->ordencompra_id] = (int) $row->ordencompra_id;
            }
        }
        foreach ($mapaRecepcion as $rec) {
            $ocId = (int) ($rec['ordencompra_id'] ?? 0);
            if ($ocId > 0) {
                $ocPorRecepcionId[(int) $rec['id']] = $ocId;
            }
        }

        $nroPorOcId = $this->nrosOrdencompra(array_values($ocIdsRecepcion));

        foreach ($partesPorIdx as $idx => $_) {
            $recId = (int) ($filas[$idx]['recepcionproveedor_id'] ?? 0);
            $ocId = (int) ($ocPorRecepcionId[$recId] ?? 0);
            if ($ocId <= 0) {
                continue;
            }
            $filas[$idx]['ordencompra_id'] = $ocId;
            $nro = (int) ($nroPorOcId[$ocId] ?? 0);
            if ($nro > 0) {
                $filas[$idx]['nro_oc'] = $nro;
            }
        }

        // 3) Sin OC aún → PEP en recepmae (Anita).
        $nrosFaltantes = [];
        foreach ($partesPorIdx as $idx => $partes) {
            if ((int) ($filas[$idx]['ordencompra_id'] ?? 0) > 0 && (int) ($filas[$idx]['nro_oc'] ?? 0) > 0) {
                continue;
            }
            $nrosFaltantes[$partes['nro']] = $partes['nro'];
        }

        if ($nrosFaltantes === []) {
            return $filas;
        }

        $pepPorClaveCom = $this->cargarPepDesdeRecepmae(array_values($nrosFaltantes));
        if ($pepPorClaveCom === []) {
            return $filas;
        }

        $numerosOc = array_values(array_unique(array_filter($pepPorClaveCom)));
        $ocPorNumeroYEmpresa = $this->cargarOrdenesPorNumero($numerosOc);

        foreach ($partesPorIdx as $idx => $partes) {
            if ((int) ($filas[$idx]['ordencompra_id'] ?? 0) > 0 && (int) ($filas[$idx]['nro_oc'] ?? 0) > 0) {
                continue;
            }
            $claveCom = $partes['letra'].'|'.$partes['sucursal'].'|'.$partes['nro'];
            $nroPep = (int) ($pepPorClaveCom[$claveCom] ?? 0);
            if ($nroPep <= 0) {
                continue;
            }

            $filas[$idx]['nro_oc'] = $nroPep;
            $empresaId = $partes['empresa_id'];
            $ocId = (int) ($ocPorNumeroYEmpresa[$empresaId.'|'.$nroPep] ?? 0);
            if ($ocId <= 0) {
                $ocId = (int) ($ocPorNumeroYEmpresa['0|'.$nroPep] ?? 0);
            }
            if ($ocId > 0) {
                $filas[$idx]['ordencompra_id'] = $ocId;
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $nrosCom
     * @return array<string, int> clave letra|sucursal|nro => nro PEP/OC
     */
    private function cargarPepDesdeRecepmae(array $nrosCom): array
    {
        try {
            $cabs = RecepcionProveedorAnitaImportSupport::listarRecepmaePorNros($nrosCom);
        } catch (\Throwable $e) {
            Log::warning('MayorPlanoCuentaRecepcionAnitaEnricher: recepmae PEP', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $mapa = [];
        foreach ($cabs as $cab) {
            $letra = trim((string) ($cab->recm_letra ?? ' '));
            if ($letra === '') {
                $letra = ' ';
            }
            $sucursal = (int) ($cab->recm_sucursal ?? 0);
            $nro = (int) ($cab->recm_nro ?? 0);
            $pep = RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab);
            if ($nro <= 0 || $pep <= 0) {
                continue;
            }
            $mapa[$letra.'|'.$sucursal.'|'.$nro] = $pep;
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $numerosOc
     * @return array<string, int> empresaId|numero => ordencompra_id (empresa 0 = cualquiera)
     */
    private function cargarOrdenesPorNumero(array $numerosOc): array
    {
        if ($numerosOc === [] || ! Schema::hasTable('ordencompra')) {
            return [];
        }

        $mapa = [];
        foreach (DB::table('ordencompra')
            ->whereIn('numeroordencompra', $numerosOc)
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'numeroordencompra']) as $row) {
            $nro = (int) $row->numeroordencompra;
            $emp = (int) ($row->empresa_id ?? 0);
            $id = (int) $row->id;
            $claveEmp = $emp.'|'.$nro;
            if (! isset($mapa[$claveEmp])) {
                $mapa[$claveEmp] = $id;
            }
            if (! isset($mapa['0|'.$nro])) {
                $mapa['0|'.$nro] = $id;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<int>  $ocIds
     * @return array<int, int>
     */
    private function nrosOrdencompra(array $ocIds): array
    {
        if ($ocIds === [] || ! Schema::hasTable('ordencompra')) {
            return [];
        }

        return DB::table('ordencompra')
            ->whereIn('id', $ocIds)
            ->pluck('numeroordencompra', 'id')
            ->all();
    }

    /**
     * @param  array{empresa_id:int,letra:string,sucursal:int,nro:int}  $partes
     */
    private function claveDePartes(array $partes): string
    {
        return $partes['empresa_id'].'|COM|'.$partes['letra'].'|'.$partes['sucursal'].'|'.$partes['nro'];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{empresa_id:int,letra:string,sucursal:int,nro:int}|null
     */
    private function partesComAnita(array $fila): ?array
    {
        $tipo = strtoupper(trim((string) ($fila['tipo_comp'] ?? '')));
        if ($tipo !== 'COM') {
            return null;
        }

        $letra = trim((string) ($fila['letra'] ?? ''));
        $sucursal = (int) ($fila['sucursal'] ?? 0);
        $nro = (int) ($fila['nro'] ?? 0);

        if ($letra === '' || $sucursal <= 0 || $nro <= 0) {
            $desdeTexto = $this->parsearComprobanteTexto((string) ($fila['comprobante'] ?? ''));
            if ($desdeTexto !== null) {
                if ($letra === '') {
                    $letra = $desdeTexto['letra'];
                }
                if ($sucursal <= 0) {
                    $sucursal = $desdeTexto['sucursal'];
                }
                if ($nro <= 0) {
                    $nro = $desdeTexto['nro'];
                }
            }
        }

        if ($nro <= 0) {
            return null;
        }
        if ($letra === '') {
            $letra = ' ';
        }

        return [
            'empresa_id' => (int) ($fila['empresa_id'] ?? 0),
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => $nro,
        ];
    }

    /**
     * Formato mayor: X0001-00159903 → letra X, sucursal 1, nro 159903.
     *
     * @return array{letra:string,sucursal:int,nro:int}|null
     */
    private function parsearComprobanteTexto(string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^([A-Za-z])\s*0*(\d+)\s*-\s*0*(\d+)\s*$/', $texto, $m) === 1) {
            return [
                'letra' => strtoupper($m[1]),
                'sucursal' => (int) $m[2],
                'nro' => (int) $m[3],
            ];
        }

        if (preg_match('/(\d+)\s*$/', $texto, $m) === 1) {
            return [
                'letra' => ' ',
                'sucursal' => 0,
                'nro' => (int) $m[1],
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, array{id:int,ordencompra_id:int,anita_nro:int}>
     */
    private function cargarRecepciones(array $claves): array
    {
        $porEmpresa = [];
        foreach ($claves as $clave) {
            [$empresaId, $tipo, $letra, $sucursal, $nro] = explode('|', $clave, 5);
            $porEmpresa[(int) $empresaId][] = [
                'letra' => $letra,
                'sucursal' => (int) $sucursal,
                'nro' => (int) $nro,
            ];
        }

        $mapa = [];
        foreach ($porEmpresa as $empresaId => $items) {
            $nros = array_values(array_unique(array_map(static fn (array $i) => $i['nro'], $items)));
            $query = DB::table('recepcion_proveedor')
                ->where('anita_tipo', 'COM')
                ->whereIn('anita_nro', $nros)
                ->select(['id', 'empresa_id', 'ordencompra_id', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro']);

            if ($empresaId > 0) {
                $query->where('empresa_id', $empresaId);
            }

            foreach ($query->get() as $row) {
                $letra = trim((string) ($row->anita_letra ?? ' '));
                if ($letra === '') {
                    $letra = ' ';
                }
                $clave = (int) $row->empresa_id.'|COM|'.$letra.'|'.(int) $row->anita_sucursal.'|'.(int) $row->anita_nro;
                if (! isset($mapa[$clave])) {
                    $mapa[$clave] = [
                        'id' => (int) $row->id,
                        'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
                        'anita_nro' => (int) $row->anita_nro,
                    ];
                }
            }
        }

        return $mapa;
    }
}
