<?php
/**
 * Word Occurrence Audit Routes
 * 
 * Handles word occurrence audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\WordOccurrenceAudit\WordOccurrenceController;

$controller = new WordOccurrenceController();
$controller->handle();
