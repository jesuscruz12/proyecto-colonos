<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class tabla_testModel extends Model
{
    protected $table = 'test_table';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function __construct()
    {
        parent::__construct();
    }
}
