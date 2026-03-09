<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;

Route::get('/subsidio-empleo', function () {
    $pathToFile = public_path('formatos/subsidio-empleo.pdf');

    if (!file_exists($pathToFile)) {
        abort(404, 'El archivo solicitado no se encuentra en el servidor.');
    }
    return response()->download($pathToFile, 'Formato_Subsidio_para_el_desempleo_2026.pdf');
});