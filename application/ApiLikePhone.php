<?php
//clase que se encarga de generar y/o validar token API LikePhone  
class ApiLikePhone extends Controller
{
    private $_wlempresas;
    private $_url;
    private $_tokenLP;

    public function __construct()
    {
        $this->_wlempresas = $this->loadModel('wlempresas');
        $this->_url = URL_API_LP;
        $this->_tokenLP = "";
    }

    public function index()
    {

    }
    public function Login($clave)
    {
        $tokenLP = "";

         $data = $this
            ->_wlempresas
            ->where('cv_wl', $clave)
            ->first();

        if (!$data) {
            return $tokenLP;
        }

        $hash_string = $data["token_likeapi"];

        // $sessionToken = Session::get('tokenLP');
        // if ($sessionToken){

        //     // si no a caducado no es necesario renovar
        //     if ($this->validaTimeToken()) {
        //         return;
        //     }
        // }

        if($hash_string !== ""){
            // Peticion LP
            $payload = array('grant-type' => 'client_credentials');

            $headersx = array(
                'Authorization: Basic '.$hash_string,
                'Content-Type: application/json'
            ); 

            $ch = curl_init ();
            curl_setopt ($ch, CURLOPT_URL, $this->_url. LOGIN_LP);       
            curl_setopt ($ch, CURLOPT_HTTPHEADER, $headersx);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt ($ch, CURLOPT_HEADER, FALSE);
            curl_setopt ($ch, CURLOPT_POST, TRUE);
            curl_setopt ($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            // hacer debug debe de ir antes del curl_exec
            // curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            // curl_setopt($ch, CURLOPT_VERBOSE, true);

            $respuesta = curl_exec ($ch);

            // hacer debug esta porcion de codigo debe de ir despues de cur_exec
            // if ($respuesta === false) {
            //     echo 'Error cURL: ' . curl_error($ch);
            // } else {
            //     echo "Respuesta cruda: " . $respuesta;
            //     $info = curl_getinfo($ch);
            //     echo "\n\nRequest Info:\n";
            //     print_r($info);
            // }

            curl_close ($ch);			
              
            $obj = json_decode($respuesta, TRUE);
            $tokenLP=$obj['accessToken'];

            $this->_tokenLP = $tokenLP;
            
            // Guardar el token cifrado por 1 hra
            // Session::set('tokenLP', [
            //     'token' => HashToken::encryptHashToken($tokenLP),
            //     'expira' => time() + 3600
            // ]);

            // echo HashToken::encryptHashToken($tokenLP);
            // echo "<br/>";
            // echo HashToken::decryptHashToken($sessionToken['token']),
            // exit;

        }
        
    }

    public function ActivaSIM($cv_plan, $iccid, $canal = 'NORMAL') //body.cv_plan, body.iccid, channel_of_sale
    {
        // Peticion LP
            $payload = array(
                'cv_plan' => $cv_plan,
                'iccid' => $iccid,
                'canal_venta' => $canal
            );
            
            $headersx = array(
                'Authorization: Bearer '.$this->_tokenLP,
                'Content-Type: application/json'
            ); 

            $ch = curl_init ();
            curl_setopt ($ch, CURLOPT_URL, $this->_url. ACTIVA_SIM);       
            curl_setopt ($ch, CURLOPT_HTTPHEADER, $headersx);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt ($ch, CURLOPT_HEADER, FALSE);
            curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt ($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            $respuesta = curl_exec ($ch);

            // Obtener status code
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($respuesta === false) {
                return [
                    'status'  => 500,
                    'message' => 'Error en la conexion',
                ];
            }
        
            curl_close ($ch);		
            	
            $obj = json_decode($respuesta, TRUE);
            return [
                'status'  => $httpCode,
                'message' => $obj['status']
            ];

    }

    public function RecargaSIM($cv_plan, $msisdn, $canal = 'NORMAL'){
        // Peticion LP
            $payload = array(
                'cv_plan' => $cv_plan,
                'msisdn' => $msisdn,
                'canal_venta' => $canal
            );
            
            $headersx = array(
                'Authorization: Bearer '.$this->_tokenLP,
                'Content-Type: application/json'
            ); 

            $ch = curl_init ();
            curl_setopt ($ch, CURLOPT_URL, $this->_url. RECARGA_SIM);       
            curl_setopt ($ch, CURLOPT_HTTPHEADER, $headersx);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt ($ch, CURLOPT_HEADER, FALSE);
            curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt ($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            $respuesta = curl_exec ($ch);

            // Obtener status code
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($respuesta === false) {
                return [
                    'status'  => 500,
                    'message' => 'Error en la conexion',
                ];
            }
        
            curl_close ($ch);		
            	
            $obj = json_decode($respuesta, TRUE);
            return [
                'status'  => $httpCode,
                'message' => $obj['status']
            ];


        

    }
    public function validaTimeToken()
    {
        $valido = false;

        // si no a caducado no es necesario renovar
            if (time() <= Session::get(['tokenLP']['expira'])) {
                $valido = true;
            }

        return $valido;
    }
}
