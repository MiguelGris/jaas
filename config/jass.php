<?php

return [
    'name' => 'JASS Pucará',
    'full_name' => 'Junta Administradora de Agua y Saneamiento',
    'register_pdf_memory_mb' => (int) env('JASS_REGISTER_PDF_MEMORY_MB', 512),
    'training_mode' => env('JASS_TRAINING_MODE', false),
];
