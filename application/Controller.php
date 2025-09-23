<?php
/*
 * nota  protected, sólo desde la misma clase, desde las clases que hereden de ella y desde las clases parent.
 */
abstract class Controller
{
    protected $_view;
    protected $_request;
    private $_registry;

    //el objeto view ya lo tenemos disponible en el controlador pricipal
    public function __construct()
    {
        //si no esta alamacenada la instancia
        $this->_registry = Registry::getInstancia();
        $this->_view = new View(new Request);
        $this->_request = $this->_registry->_request;
    }

    //no podra ser instanseada
    //obliga que todas las clases que hereden de controller implementen un metodo index por obligacion
    abstract public function index();
    //metodo que se asigne por defecto cuando no se  envie nada o por error
    //cargar modelos al controlador principal
    protected function loadModel($modelo, $modulo = false)
    {
        $modelo = $modelo . 'Model';
        $rutaModelo = ROOT . 'models' . DS . $modelo . '.php';
        //si 1= usara cada modulo sus modelos 2= general
        if (MODELOS == 1) {
            //sino se envia un modulo
            if (!$modulo) {
                $modulo = $this->_request->getModulo();
            }
            if ($modulo) {
                if ($modulo != 'default') {
                    $rutaModelo = ROOT . 'modules' . DS . $modulo . DS . 'models' . DS . $modelo . '.php';
                }
            }
        }
        if (is_readable($rutaModelo)) {
            require_once $rutaModelo;
            $modelo = new $modelo;
            return $modelo;
        } else {
            throw new Exception('Error de modelo');
        }
    }
    //cargador de librerias
    protected function getLibrary($libreria)
    {
        $rutaLibreria = ROOT . 'libs' . DS . $libreria . '.php';
        if (is_readable($rutaLibreria)) {
            require_once $rutaLibreria;
        } else {
            throw new Exception('Error de libreria');
        }
    }

    //Filtrar texto Metodo POST
    protected function getTexto($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = htmlspecialchars($_POST[$clave], ENT_QUOTES);
            return $_POST[$clave];
        }

        return '';
    }

    //validar numeros enteros
    protected function getInt($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = filter_input(INPUT_POST, $clave, FILTER_VALIDATE_INT);
            return $_POST[$clave];
        }
        return 0;
    }
    //funcion Redireccionar
    protected function redireccionar($ruta = false)
    {
        if ($ruta) {
            header('location:' . BASE_URL . $ruta);
            exit;
        } else {
            header('location:' . BASE_URL);
            exit;
        }
    }

    //FILTRAR ENTERO 
    protected function filtrarInt($int)
    {
        $int = (int) $int;
        if (is_int($int)) {
            return $int;
        } else {
            return 0;
        }
    }

    //obtener POST sin filtros
    protected function getPostParam($clave)
    {
        if (isset($_POST[$clave])) {
            return $_POST[$clave];
        }
    }


    //Evitar injecciones SQL
    protected function getSql($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = strip_tags($_POST[$clave]);

            if (!get_magic_quotes_gpc()) {
                //remplazar esto en futuras versiones de php->php7 
                $_POST[$clave] = mysql_escape_string($_POST[$clave]);
            }
            return trim($_POST[$clave]);
        }
    }
    //validar cadena alfanumerico
    protected function getAlphaNum($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = (string) preg_replace('/[^A-Z0-9_]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
    }
    //usuario en alfanumerico
    protected function getAlphaUser($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = (string) preg_replace('/[^.A-Z0-9_]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
    }
    //obtener cadena sin caracteres que permitan un ataque xss o sql injection
    protected function getCadena($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = (string) preg_replace('/[^A-Z0-9_áéíóúÁÉÍÓÚÑñ\s\-\.\%\,]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
    }
    //funcion obtener coordenadas. (sin uso)
    protected function getGps_coordenadas($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = (string) preg_replace('/[^A-Z0-9._áéíóúÁÉÍÓÚÑñ\-]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
    }
    //validar numero de tipo double
    protected function getDouble($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = (string) preg_replace('/[^0-9\.]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
    }
    //validar hora
    protected function getHora($clave)
    {
        if (isset($_POST[$clave]) && !empty($_POST[$clave])) {
            $_POST[$clave] = (string) preg_replace('/[\:][^A-Z0-9_áéíóúÁÉÍÓÚÑñ\s]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
    }

    //validar correo electronico
    public function validarEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }


    //validar token
    public function tokencsrf()
    {
        //si el token no es igual al del sistema eliminar
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (Session::get('tokencsrf') != $_SERVER['HTTP_X_CSRF_TOKEN']) {
                exit;
            } else {
            }
        }
    }
}
