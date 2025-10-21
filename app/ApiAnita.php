<?php

namespace App;
use Illuminate\Support\Facades\File;

class ApiAnita {
    public function __construct()    {
        $this->fecha = date("YmdHisu")."_".random_int(0, 9999);

        $this->servidorAnita = env('ANITA_IP'); 
    }


    public function apiCallHttp($data){
        if (isset($data['servidor']))
            $this->servidorAnita = env($data['servidor']);

        if (isset($data['ifx_server']))
            $data["IFX_SERVER"]	 = env($data['ifx_server']);
        else
            $data["IFX_SERVER"]	 = env('IFX_SERVER');

        $data["DB_NAME"]	 = env('ANITA_BDD');
        $data["IFX_DB_PATH"] = env('ANITA_BDD_PATH')."/".env('ANITA_BDD');
        //dd($data);
        $curl = curl_init();        
        $url = "http://".$this->servidorAnita."/apiERP.php";
        //dd("url", $url);
        $fl = false;
        $data = json_encode($data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $data
        ));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-Type: application/json' )   );
        $response =  curl_exec($curl);
        if (curl_errno($curl)) {     
            $error_msg = curl_error($curl); 
            echo $error_msg; 
         } 
        curl_close($curl);
        //dd($response);
        return $response;
    }

    public function apiCall($data){
	//dd('anita.bridge'.env('ANITA_BRIDGE_TYPE'));
	//dd(env('DB_CONNECTION'));

        if (env('ANITA_BRIDGE_TYPE') == "HTTP") { return $this->apiCallHttp($data); }
        $portSSH = (env('ANITA_PUERTO_SSH') == null ? "" : "-p ".env('ANITA_PUERTO_SSH'));
        $portSCP = (env('ANITA_PUERTO_SSH') == null ? "" : "-P ".env('ANITA_PUERTO_SSH'));
        $sql = $this->armarSql($data);
        $nomArch = $this->fecha.".sql";
        $pathArch = base_path() . '/storage/logs/'.$nomArch;
        File::put(base_path() . '/storage/logs/'.$this->fecha.".sql", $sql);
        
        shell_exec('scp '.$portSCP.' '.$pathArch.' sergio@'.$this->servidorAnita.':/home/sergio/tmp/'.$nomArch.' > /dev/null');
        shell_exec('ssh '.$portSSH.' sergio@'.$this->servidorAnita.' "cd /usr2/www/htdocs; ./apiERP.php '.env('ANITA_BDD').' /home/sergio/tmp/'.$nomArch.' '.$this->fecha.' > /dev/null"');         
        shell_exec("ssh ".$portSSH." sergio@".$this->servidorAnita." \"rm /home/sergio/tmp/".$nomArch." > /dev/null\"");
        
        if($data['acc'] == "list" || $data['acc'] == "customSql"){
            shell_exec('scp '.$portSCP.' sergio@'.$this->servidorAnita.':'.env('ANITA_BDD_PATH').'/'.env('ANITA_BDD').'/'.$this->fecha.'.csv '.base_path().'/storage/logs/'.$this->fecha.'.csv > /dev/null');    
            shell_exec("ssh ".$portSSH." sergio@".$this->servidorAnita." \"cd ".env('ANITA_BDD_PATH')."/".env('ANITA_BDD')."; rm ".$this->fecha.".csv > /dev/null\"");

            $dataArr = array();
            $archivo = fopen(base_path() . '/storage/logs/'.$this->fecha.'.csv','r');
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

            unlink(base_path() . '/storage/logs/'.$this->fecha.'.csv');
            unlink(base_path() . '/storage/logs/'.$this->fecha.".sql");
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
                $sql = "UNLOAD TO '".$this->fecha.".csv' DELIMITER '|' SELECT ".$data['campos']." FROM ".$data['tabla']." ".$data['whereArmado']." ".$data['groupBy']." ".$data['orderBy'];
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
        $numerador = json_decode($this->apiCall($data))[0];
        if($numerador->id != ""){
            $id = $numerador->id + 1;
        } 
        return $id;
    }
}
