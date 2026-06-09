<?php

namespace App;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ApiAnita {
    protected string $fecha;

    protected string $servidorAnita;

    public function __construct()
    {
        $this->fecha = date('YmdHisu').'_'.random_int(0, 9999);
        $this->servidorAnita = (string) config('anita.ip', '');
    }

    /**
     * Resuelve host del bridge desde clave .env (ej. LOCAL_IP) o valor directo host:puerto.
     */
    public static function resolverHost(?string $claveServidor = null): string
    {
        if ($claveServidor !== null && trim($claveServidor) !== '') {
            $clave = strtoupper(trim($claveServidor));
            $mapa = config('anita.servidores', []);
            if (isset($mapa[$clave]) && trim((string) $mapa[$clave]) !== '') {
                return trim((string) $mapa[$clave]);
            }
            if (preg_match('#^[\w.\-]+(:\d+)?$#', trim($claveServidor))) {
                return trim($claveServidor);
            }
        }

        return trim((string) config('anita.ip', ''));
    }

    /**
     * URL del bridge HTTP (apiERP.php).
     */
    public static function urlBridge(?string $host = null): string
    {
        $host = trim((string) ($host ?? self::resolverHost()));
        if ($host === '') {
            throw new \RuntimeException(
                'ANITA_IP no está configurado. Defínalo en .env y ejecute php artisan config:cache (o config:clear).'
            );
        }

        $script = ltrim((string) config('anita.api_script', 'apiERP.php'), '/');

        if (preg_match('#^https?://#i', $host)) {
            $base = rtrim($host, '/');
            if (preg_match('#\.php(\?|$)#i', $base)) {
                return $base;
            }

            return $base.'/'.$script;
        }

        return 'http://'.$host.'/'.$script;
    }

    private static function resolverIfxServer(?string $claveIfx = null): string
    {
        if ($claveIfx !== null && trim($claveIfx) !== '') {
            $clave = strtoupper(trim($claveIfx));
            $mapa = config('anita.ifx_servers', []);
            if (isset($mapa[$clave]) && trim((string) $mapa[$clave]) !== '') {
                return trim((string) $mapa[$clave]);
            }
            if (preg_match('#^[\w.\-]+$#', trim($claveIfx))) {
                return trim($claveIfx);
            }
        }

        return trim((string) config('anita.ifx_server', ''));
    }

    public function apiCallHttp($data){
        $acc = (string) ($data['acc'] ?? '');

        if (isset($data['servidor'])) {
            $this->servidorAnita = self::resolverHost((string) $data['servidor']);
        }

        if (isset($data['ifx_server'])) {
            $data['IFX_SERVER'] = self::resolverIfxServer((string) $data['ifx_server']);
        } else {
            $data['IFX_SERVER'] = self::resolverIfxServer();
        }

        $bdd = (string) config('anita.bdd', 'ventas');
        $bddPath = rtrim((string) ($data['path_sistema'] ?? config('anita.bdd_path', '')), '/');
        $sistema = (isset($data['sistema']) && trim((string) $data['sistema']) !== '')
            ? trim((string) $data['sistema'])
            : $bdd;
        $data['sistema'] = $sistema;
        $data['DB_NAME'] = $sistema;
        $data['IFX_DB_PATH'] = ($bddPath !== '' ? $bddPath.'/' : '').$sistema;
        if ($bddPath !== '') {
            $data['path_sistema'] = $bddPath;
        }

        try {
            $url = self::urlBridge($this->servidorAnita);
        } catch (\RuntimeException $e) {
            return json_encode(['Error' => 'Bridge HTTP Anita: '.$e->getMessage()]);
        }

        $curl = curl_init();
        $payload = json_encode($data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $payload
        ));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-Type: application/json' )   );
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $errorMsg = curl_error($curl);
            curl_close($curl);

            return json_encode(['Error' => 'Bridge HTTP Anita: '.$errorMsg]);
        }
        curl_close($curl);

        $trimResponse = trim((string) $response);
        if ($response === false || $trimResponse === '') {
            // acc=list sin filas: el bridge puede responder vacío; no es error.
            if ($acc === 'list') {
                return '[]';
            }
            // insert/update/delete exitosos en legacy devuelven []; el HTTP a veces body vacío.
            if (in_array($acc, ['insert', 'update', 'delete'], true)) {
                return '[]';
            }

            return json_encode(['Error' => 'Bridge HTTP Anita: respuesta vacía']);
        }

        // Quita warnings HTML del bridge y devuelve JSON limpio en consultas.
        if (in_array($acc, ['list', 'customSql'], true)) {
            return json_encode(self::decodificarListaFilas($trimResponse));
        }

        return $response;
    }

    /**
     * Quita advertencias PHP del body del bridge (insert/update) sin ocultar errores Informix.
     */
    public static function limpiarRespuestaBridgeEscritura(string $respuesta): string
    {
        $lineas = preg_split('/\R/', $respuesta) ?: [];
        $limpias = [];
        foreach ($lineas as $linea) {
            if (preg_match('/^\s*(?:Warning|Notice|Deprecated|Fatal error)\b/i', $linea)) {
                continue;
            }
            $limpias[] = $linea;
        }

        return trim(implode("\n", $limpias));
    }

    /**
     * Respuesta legacy del bridge en INSERT/UPDATE/DELETE exitoso.
     */
    public static function respuestaBridgeEscrituraExitosa(string $respuesta): bool
    {
        $limpia = self::limpiarRespuestaBridgeEscritura($respuesta);

        return $limpia !== ''
            && preg_match('/\d+\s+row\(s\)\s+(?:inserted|updated|deleted)\b/i', $limpia) === 1;
    }

    /**
     * Detecta error en la respuesta del bridge (HTTP o legacy).
     * [] es válido: lista sin filas (consulta) o OK en insert/update (bridge legacy).
     */
    public static function extraerMensajeError(?string $respuesta): ?string
    {
        if ($respuesta === null) {
            return 'Sin respuesta del bridge Anita';
        }

        $trim = trim($respuesta);
        if ($trim === '') {
            return 'Respuesta vacía del bridge Anita';
        }

        if ($trim === '[]' || $trim === '{}') {
            return null;
        }

        $decoded = json_decode($trim, true);
        if (is_array($decoded)) {
            if (isset($decoded['Error']) && (string) $decoded['Error'] !== '') {
                return (string) $decoded['Error'];
            }
            if (isset($decoded['error']) && (string) $decoded['error'] !== '') {
                return (string) $decoded['error'];
            }

            return null;
        }

        $limpia = self::limpiarRespuestaBridgeEscritura($trim);
        if (self::respuestaBridgeEscrituraExitosa($trim)) {
            return null;
        }

        if (strcasecmp($limpia, 'Error') === 0) {
            return 'Error en ejecución SQL Informix (revise el archivo .ret en el servidor Anita)';
        }

        if (stripos($trim, '<b>warning</b>') !== false || stripos($trim, '<b>fatal error</b>') !== false) {
            return strip_tags(html_entity_decode($trim));
        }

        if ($limpia === '' && preg_match('/\b(?:Warning|Notice|Fatal error)\b/i', $trim)) {
            return 'Advertencia PHP en bridge Anita (actualice apiERP.php en el servidor)';
        }

        if (stripos($limpia, 'error') !== false) {
            return $limpia;
        }

        return null;
    }

    /**
     * Ejecuta escritura en Informix vía bridge y falla si la respuesta indica error.
     */
    public function apiCallEscritura(array $payload, ?string $contexto = null, string $logEvento = 'anita_bridge.fallo'): string
    {
        if ($contexto === null || $contexto === '') {
            $tabla = $payload['tabla'] ?? 'sql';
            $acc = $payload['acc'] ?? '';
            $contexto = trim($tabla.' '.$acc);
        }

        $raw = (string) $this->apiCall($payload);
        $err = self::extraerMensajeError($raw === '' ? null : $raw);
        if ($err !== null) {
            Log::warning($logEvento, [
                'contexto' => $contexto,
                'tabla' => $payload['tabla'] ?? null,
                'acc' => $payload['acc'] ?? null,
                'mensaje' => $err,
            ]);
            throw new \RuntimeException('Error al grabar en Anita ('.$contexto.'): '.$err);
        }

        return $raw;
    }

    /**
     * Normaliza la respuesta JSON de acc=list del bridge a lista de filas (objetos).
     * El bridge HTTP puede devolver un array o un único objeto por fila; el acceso con [0]
     * sobre stdClass provoca "Cannot use object of type stdClass as array".
     *
     * @return list<object>
     */
    /**
     * @return array{filas: list<object>, error_lectura: ?string}
     */
    public static function parsearRespuestaLista(?string $respuesta): array
    {
        if ($respuesta === null) {
            return ['filas' => [], 'error_lectura' => 'Sin respuesta del bridge Anita'];
        }

        $trim = trim($respuesta);
        if ($trim === '' || $trim === '[]') {
            return ['filas' => [], 'error_lectura' => null];
        }

        $err = self::extraerMensajeError($trim);
        if ($err !== null) {
            return ['filas' => [], 'error_lectura' => $err];
        }

        if (preg_match('/(\[[\s\S]*\])\s*$/', $trim, $m)) {
            $trim = $m[1];
        }

        $decoded = json_decode($trim, false);
        if ($decoded === null) {
            return ['filas' => [], 'error_lectura' => 'Respuesta Anita no parseable como JSON'];
        }

        if (is_array($decoded)) {
            return ['filas' => array_values($decoded), 'error_lectura' => null];
        }

        if (is_object($decoded)) {
            if (isset($decoded->Error) || isset($decoded->error)) {
                $msg = trim((string) ($decoded->Error ?? $decoded->error ?? 'Error Anita'));

                return ['filas' => [], 'error_lectura' => $msg !== '' ? $msg : 'Error Anita'];
            }

            return ['filas' => [$decoded], 'error_lectura' => null];
        }

        return ['filas' => [], 'error_lectura' => 'Formato de respuesta Anita no reconocido'];
    }

    public static function decodificarListaFilas(?string $respuesta): array
    {
        return self::parsearRespuestaLista($respuesta)['filas'];
    }

    /**
     * Primera fila de una respuesta acc=list, o null si no hay datos.
     */
    public static function primeraFilaLista(?string $respuesta): ?object
    {
        $filas = self::decodificarListaFilas($respuesta);

        return $filas === [] ? null : $filas[0];
    }

    private function usaBridgeHttp(): bool
    {
        $tipo = strtoupper(trim((string) config('anita.bridge_type', config('gastronomia.anita_bridge_type', 'HTTP'))));

        return $tipo === 'HTTP';
    }

    public function apiCall($data){
	//dd('anita.bridge'.env('ANITA_BRIDGE_TYPE'));
	//dd(env('DB_CONNECTION'));

        if ($this->usaBridgeHttp()) {
            return $this->apiCallHttp($data);
        }
        $puertoSsh = config('anita.puerto_ssh');
        $portSSH = ($puertoSsh == null ? '' : '-p '.$puertoSsh);
        $portSCP = ($puertoSsh == null ? '' : '-P '.$puertoSsh);
        $sql = $this->armarSql($data);
        $nomArch = $this->fecha.".sql";
        $logsDir = storage_path('logs');
        if (! is_dir($logsDir)) {
            File::ensureDirectoryExists($logsDir);
        }
        $pathArch = $logsDir.'/'.$nomArch;
        File::put($logsDir.'/'.$this->fecha.'.sql', $sql);
        
        shell_exec('scp '.$portSCP.' '.$pathArch.' sergio@'.$this->servidorAnita.':/home/sergio/tmp/'.$nomArch.' > /dev/null');
        shell_exec('ssh '.$portSSH.' sergio@'.$this->servidorAnita.' "cd /usr2/www/htdocs; ./apiERP.php '.config('anita.bdd').' /home/sergio/tmp/'.$nomArch.' '.$this->fecha.' > /dev/null"');
        shell_exec("ssh ".$portSSH." sergio@".$this->servidorAnita." \"rm /home/sergio/tmp/".$nomArch." > /dev/null\"");
        
        if($data['acc'] == "list" || $data['acc'] == "customSql"){
            $csvPath = $logsDir.'/'.$this->fecha.'.csv';
            $bddPathSsh = rtrim((string) config('anita.bdd_path', ''), '/');
            $bddSsh = (string) config('anita.bdd', 'ventas');
            shell_exec('scp '.$portSCP.' sergio@'.$this->servidorAnita.':'.$bddPathSsh.'/'.$bddSsh.'/'.$this->fecha.'.csv '.$csvPath.' > /dev/null 2>&1');
            shell_exec("ssh ".$portSSH." sergio@".$this->servidorAnita." \"cd ".$bddPathSsh.'/'.$bddSsh."; rm ".$this->fecha.".csv > /dev/null\"");

            if (! is_readable($csvPath)) {
                if (is_file($logsDir.'/'.$this->fecha.'.sql')) {
                    @unlink($logsDir.'/'.$this->fecha.'.sql');
                }

                return json_encode([]);
            }

            $dataArr = array();
            $archivo = fopen($csvPath, 'r');
            $camposArr = explode(",", $data['campos']);
            while ($linea = fgets($archivo)) {
                $registroAssoc = array();
                $lineaArr = (explode("|", $linea));
                foreach ($camposArr as $key => $value) {
                    $nombreAux = explode(" as ", $value);
                    if (count($nombreAux) == 2) {
                        $value = $nombreAux[1];
                    }else{
                        $nombreAux = explode(" AS ", $value); 
                        if (count($nombreAux) == 2) {
                            $value = $nombreAux[1];
                        }
                    }
                    $registroAssoc[trim($value)] = $lineaArr[$key];
                }
                $dataArr[] = $registroAssoc; 
            }
            fclose($archivo);

            @unlink($csvPath);
            @unlink($logsDir.'/'.$this->fecha.'.sql');
            //dd($dataArr);
            return json_encode($dataArr);
        }
        return json_encode(array());
    }
    
    public function armarSql($data){
        $data['where'] 		 = (array_key_exists('where', $data) ? $data['where'] : "");
        $data['campos'] 	 = (array_key_exists('campos', $data) ? $data['campos'] : "");
        $data['tabla'] 		 = (array_key_exists('tabla', $data) ? $data['tabla'] : "");
        $data['whereArmado'] = (array_key_exists('whereArmado', $data) ? $data['whereArmado'] : "");
        $data['orderBy'] 	 = (array_key_exists('orderBy', $data) ? " ORDER BY ".$data['orderBy'] : "");
        $data['groupBy'] 	 = (array_key_exists('groupBy', $data) ? " GROUP BY ".$data['groupBy'] : "");
        $data['valores'] 	 = (array_key_exists('valores', $data) ? $data['valores'] : "");

        switch ($data['acc']){
            case 'list':
                $first = '';
                if (! empty($data['limit'])) {
                    $first = trim((string) $data['limit']).' ';
                }
                $sql = "UNLOAD TO '".$this->fecha.".csv' DELIMITER '|' SELECT ".$first.$data['campos']." FROM ".$data['tabla']." ".$data['whereArmado']." ".$data['groupBy']." ".$data['orderBy'];
            break;
            case 'insert':
                $sql = "INSERT INTO ".$data['tabla']." (".$data['campos'].") VALUES (".$data['valores'].")";
            break;
            case 'update':
                $sql = "UPDATE ".$data['tabla']." SET ".$data['valores']." ".$data['whereArmado'];
            break;
            case 'delete':
                $sql = "DELETE FROM ".$data['tabla']." ".$data['whereArmado'];
            break;
            case 'customSql':
                $sql = "UNLOAD TO '".$this->fecha.".csv' DELIMITER '|' ".$data['customSql'];
            break;
        }
        $sql = trim(preg_replace('/\s\s+/', ' ', $sql));
        return $sql;
    }

    public function obtenerSiguienteNumerador($tabla, $campoId = "id"){
        $id = 1;
        $data = array( 'acc' => 'list', 'campos' => 'MAX('.$campoId.') AS id', 'tabla' => $tabla );
        $fila = self::primeraFilaLista($this->apiCall($data));
        if ($fila !== null && isset($fila->id) && (string) $fila->id !== '') {
            $id = (int) $fila->id + 1;
        }
        return $id;
    }
}
