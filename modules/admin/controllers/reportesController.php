<?php
// C:\xampp\htdocs\aqacrmseguridad\modules\admin\controllers\reportesController.php

class reportesController extends adminController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ficha imprimible del accidente.
     * URL: /admin/reportes/accidente_ficha?id=68
     *
     * Entrega HTML listo para "Imprimir" o "Guardar como PDF" desde el navegador.
     */
    public function accidente_ficha()
    {
        // Mostrar errores sólo cuando ?debug=1
        if (!empty($_GET['debug'])) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 0);
        }

        // ID del accidente
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            echo "ID de accidente inválido";
            return;
        }

        // Cargamos el modelo que arma TODO el expediente
        require_once ROOT . "/models/reportesaccidenteModel.php";

        $model = new reportesaccidenteModel();
        $exp   = $model->getAccidenteExpediente($id);

        if (!$exp) {
            echo "Accidente no encontrado";
            return;
        }

        // Variables que usará la vista
        $accidente    = $exp['accidente'];
        $oficial      = $exp['oficial'];
        $vehiculos    = $exp['vehiculos'];
        $personas     = $exp['personas'];
        $conductores  = $exp['conductores'];
        $peatones     = $exp['peatones'];
        $ocupantes    = $exp['ocupantes'];
        $croquis      = $exp['croquis'];
        $evidencias   = $exp['evidencias'];
        $anexos       = $exp['anexos'];

        // Render de la vista (PDF HTML)
        ob_start();
        include ROOT . "/modules/admin/views/reportes/accidente_ficha_pdf.phtml";
        $html = ob_get_clean();

        // Siempre respondemos HTML para imprimir / guardar como PDF
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
