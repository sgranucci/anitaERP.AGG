<?php

namespace App\Models\Configuracion;

use App\ApiAnita;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Pais extends Model
{
    /** null = aún no consultado; true/false según exista pais_cod_afip en Informix ventas. */
    private static ?bool $informixTieneCodigoAfip = null;

    protected $fillable = ['nombre', 'codigo', 'codigo_afip'];

    protected $table = 'pais';

    protected $keyField = 'id';

    protected $keyFieldAnita = 'pais_pais';

    /**
     * Código Informix (pais_pais) para clim_pais y FKs Anita.
     */
    public function codigoAnita(): ?string
    {
        $codigo = trim((string) ($this->codigo ?? ''));

        return $codigo !== '' ? $codigo : null;
    }

    public function sincronizarConAnita(): void
    {
        $this->sincronizarCodigosDesdeAnita();
    }

    /**
     * Trae / actualiza países desde Informix (pais_pais, pais_desc, pais_cod_afip).
     *
     * @return array{insertados: int, actualizados: int, omitidos: int}
     */
    public function sincronizarCodigosDesdeAnita(): array
    {
        $stats = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        $filas = $this->listarFilasDesdeAnita();
        if ($filas === []) {
            Log::warning('pais.sincronizar_anita.sin_datos', [
                'mensaje' => 'No se obtuvieron países desde Anita (revise bridge y columna pais_cod_afip en Informix).',
            ]);

            return $stats;
        }

        foreach ($filas as $row) {
            $codigoAnita = trim((string) ($row->pais_pais ?? ''));
            $nombre = trim((string) ($row->pais_desc ?? ''));
            $codigoAfip = trim((string) ($row->pais_cod_afip ?? ''));

            if ($codigoAnita === '' || $nombre === '') {
                $stats['omitidos']++;

                continue;
            }

            $pais = self::query()
                ->where('codigo', $codigoAnita)
                ->orWhereRaw('TRIM(nombre) = ?', [$nombre])
                ->first();

            $payload = [
                'nombre' => $nombre,
                'codigo' => $codigoAnita,
                'codigo_afip' => $codigoAfip !== '' ? $codigoAfip : null,
            ];

            if ($pais) {
                $pais->update($payload);
                $stats['actualizados']++;
            } else {
                self::create($payload);
                $stats['insertados']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{insertados: int, actualizados: int, omitidos: int}
     */
    public function resincronizarConAnita(): array
    {
        return $this->sincronizarCodigosDesdeAnita();
    }

    public function traerRegistroDeAnita($key): void
    {
        $filas = $this->listarFilasDesdeAnita(' WHERE '.$this->keyFieldAnita." = '".$key."' ");
        if ($filas === []) {
            return;
        }

        $row = $filas[0];
        $codigoAnita = trim((string) ($row->pais_pais ?? $key));
        $nombre = trim((string) ($row->pais_desc ?? ''));
        $codigoAfip = trim((string) ($row->pais_cod_afip ?? ''));

        $pais = self::query()
            ->where('codigo', $codigoAnita)
            ->orWhere('id', $key)
            ->first();

        $payload = [
            'nombre' => $nombre,
            'codigo' => $codigoAnita,
            'codigo_afip' => $codigoAfip !== '' ? $codigoAfip : null,
        ];

        if ($pais) {
            $pais->update($payload);
        } else {
            self::create($payload);
        }
    }

    /**
     * @return list<object>
     */
    private function listarFilasDesdeAnita(string $whereArmado = ''): array
    {
        $apiAnita = new ApiAnita();
        $base = [
            'acc' => 'list',
            'tabla' => $this->table,
            'sistema' => (string) config('anita.bdd', 'ventas'),
            'orderBy' => 'pais_pais',
            'whereArmado' => $whereArmado,
        ];

        foreach (['pais_pais, pais_desc, pais_cod_afip', 'pais_pais, pais_desc'] as $campos) {
            $filas = ApiAnita::decodificarListaFilas((string) $apiAnita->apiCall(array_merge($base, ['campos' => $campos])));
            if ($filas !== []) {
                if (self::$informixTieneCodigoAfip === null) {
                    self::$informixTieneCodigoAfip = str_contains($campos, 'pais_cod_afip');
                }

                return $filas;
            }
        }

        if (self::$informixTieneCodigoAfip === null) {
            self::$informixTieneCodigoAfip = false;
        }

        return [];
    }

    private function informixSoportaCodigoAfip(): bool
    {
        if (self::$informixTieneCodigoAfip === null) {
            $this->listarFilasDesdeAnita(" WHERE {$this->keyFieldAnita} = '1' ");
        }

        return self::$informixTieneCodigoAfip === true;
    }

    public function guardarAnita($request, $id): void
    {
        $apiAnita = new ApiAnita();
        $codigoAnita = trim((string) ($request->codigo ?? $id));
        $codigoAfip = trim((string) ($request->codigo_afip ?? ''));

        $campos = 'pais_pais, pais_desc';
        $valores = " '".$codigoAnita."', '".$request->nombre."' ";
        if ($this->informixSoportaCodigoAfip()) {
            $campos .= ', pais_cod_afip';
            $valores .= ", '".$codigoAfip."' ";
        }

        $data = [
            'tabla' => $this->table,
            'sistema' => (string) config('anita.bdd', 'ventas'),
            'acc' => 'insert',
            'campos' => $campos,
            'valores' => $valores,
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function actualizarAnita($request, $id): void
    {
        $apiAnita = new ApiAnita();
        $codigoAnita = trim((string) ($request->codigo ?? $id));
        $codigoAfip = trim((string) ($request->codigo_afip ?? ''));

        $valores = " pais_desc = '".$request->nombre."' ";
        if ($this->informixSoportaCodigoAfip()) {
            $valores .= ", pais_cod_afip = '".$codigoAfip."' ";
        }

        $data = [
            'acc' => 'update',
            'tabla' => $this->table,
            'sistema' => (string) config('anita.bdd', 'ventas'),
            'valores' => $valores,
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$codigoAnita."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function eliminarAnita(string|int $codigoAnita): void
    {
        $codigoAnita = trim((string) $codigoAnita);

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'tabla' => $this->table,
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$codigoAnita."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }
}
