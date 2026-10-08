<?php
namespace App\Controllers;

class HomeController {
    public function index() {
        $title = "Annasetu - SmartHarvest AI";
        
        // Path to the specific view for this route
        $contentView = __DIR__ . '/../../views/home/index.php';
        
        // Include the master layout, which will include the $contentView
        require_once __DIR__ . '/../../views/layouts/main.php';
    }
}
