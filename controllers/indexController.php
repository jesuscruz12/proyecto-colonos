<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class indexController extends Controller
{
     //private $_administradores;
     private $_usuarios;
     private $_wlempresas;
     private $_wl_usuarioscrm;

     public function __construct()
     {
          parent::__construct();
          //$this->_administradores = $this->loadModel('administradores');
          $this->_usuarios = $this->loadModel('usuarios');
          $this->_wlempresas = $this->loadModel('wlempresas');
          $this->_wl_usuarioscrm = $this->loadModel('wl_usuarioscrm');
     }

     public function index()
     {

          if (Session::get('rol') == ADMINISTRADOR) {
               $this->redireccionar('admin');
          }

          if ($this->getInt('enviar') == 1) {
               $this->_view->datos = $_POST;
               $this->_view->datos['usuario'] = $usuario;

               $usuario = $this->getPostParam('usuario');
               $contrasena = $this->getPostParam('password');

               //validar password			
               if (!$this->getPostParam('password')) {
                    $this->_view->_error = 'Debes ingresar tu contraseña';
                    $vistas = array('index');
                    $this->_view->renderizar($vistas, "ajax");
                    exit;
               }

               //validar correo
               if (!$this->validarEmail($usuario)) {
                    $this->_view->_error = 'La dirección de correo electrónico es inválida';
                    $vistas = array('index');
                    $this->_view->renderizar($vistas, "ajax");
                    exit;

                    // $data = 0;
                    // $password = Hash::getHash('sha1', $this->getPostParam('password'), HASH_KEY);
                    // $row1 = $this->_wlempresas->verificar_usuario(
                    //      $encoded
                    // );
                    // $ca = count($row1);
                    // if ($ca > 0) {
                    //      $data = 1;
                    // }
                    // if ($data == 0) {
                    //      $this->_view->_error = 'Nombre de usuario y / o contraseña incorrectos';
                    //      $vistas = array('index');
                    //      $this->_view->renderizar($vistas, "ajax");
                    //      exit;
                    // }

                    // Session::set('autenticado', true);
                    // // Session::set('tipo_usuario', $row1[0]['tipo_usuario']);
                    // // Session::set('cv_usuario', $row1[0]['cv_usuario']);
                    // // Session::set('nombre_usuario', $row1[0]['nombre_usuario']);
                    // Session::set('tiempo', time());
                    // $op_extra = new CORE;
                    // $tokenrf = $op_extra->cadena_aleatoria(15);
                    // Session::set('tokencsrf', $tokenrf);

                    // // if ($row1[0]['tipo_usuario'] == ADMINISTRADOR) {
                    // //      $this->redireccionar("admin");
                    // // }
                    // $this->redireccionar("admin");
               } else {
                    $data = 0;
                    $password = Hash::getHash('sha1', $contrasena, HASH_KEY);
                    $row1 = $this->_wl_usuarioscrm->verificar_usuario(
                         $usuario,
                         $password
                    );
                    // $this->_view->_error = json_encode($row1);
                    // $vistas = array('index');
                    // $this->_view->renderizar($vistas, "ajax");
                    // exit;
                    $ca = count($row1 ?? []);
                    if ($ca > 0) {
                         $data = 1;
                    }
                    if ($data == 0) {
                         $this->_view->_error = 'Nombre de usuario y / o contraseña incorrectos';
                         $vistas = array('index');
                         $this->_view->renderizar($vistas, "ajax");
                         exit;
                    }
                    Session::set('autenticado', true);
                    Session::set('rol', $row1['rol']);
                    Session::set('id_usuario', $row1['id_usuario']);
                    Session::set('token_likeapi', $row1['token_likeapi']);
                    Session::set('tiempo', time());
                    $op_extra = new CORE;
                    $tokenrf = $op_extra->cadena_aleatoria(15);
                    Session::set('tokencsrf', $tokenrf);

                    if ($row1['rol'] == ADMINISTRADOR) {
                         $this->redireccionar("admin");
                    }
               }
          }
          $vistas = array('index');
          $this->_view->renderizar($vistas, "ajax");
     }

     public function cerrar()
     {
          Session::destroy();
          $this->redireccionar("");
     }
}
