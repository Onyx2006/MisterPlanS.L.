<?php
declare(strict_types=1);

// Front controller: todo lo que no es un fichero estático entra por aqui
require dirname(__DIR__) . '/vendor/autoload.php';

(new App\App(dirname(__DIR__)))->ejecutar();