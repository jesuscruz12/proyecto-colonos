<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class Model extends Illuminate\Database\Eloquent\Model
{
   public function __construct()
   {
      $capsule = new Capsule;
      //Indicamos en el siguiente array los datos de configuración de la BD
      $capsule->addConnection([
         'driver' => 'mysql',
         'host' => DB_HOST,
         'database' => DB_NAME,
         'username' => DB_USER,
         'password' => DB_PASS,
         'charset' => 'utf8',
         'collation' => 'utf8_unicode_ci',
         'prefix' => '',
         'strict' => false,
      ]);
      $capsule->setAsGlobal();
      //Y finalmente, iniciamos Eloquent
      $capsule->bootEloquent();
   }
}
