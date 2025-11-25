<?php
// guardar_puntos.php
header('Content-Type: application/json');

// 1. Recibir y limpiar datos
$nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
$contacto = filter_input(INPUT_POST, 'contacto', FILTER_SANITIZE_STRING); // Nuevo
$puntos = filter_input(INPUT_POST, 'puntos', FILTER_VALIDATE_INT);

if (!$nombre || !$puntos) {
    echo json_encode(['status' => 'error']);
    exit;
}

// 2. Leer archivo actual
$archivo = 'puntajes.json';
$datos = [];

if (file_exists($archivo)) {
    $contenido = file_get_contents($archivo);
    $datos = json_decode($contenido, true) ?? [];
}

// 3. Agregar nuevo récord con contacto
$datos[] = [
    'nombre' => substr($nombre, 0, 15), // Máximo 15 letras
    'contacto' => substr($contacto, 0, 50), // Guardamos el contacto
    'puntos' => $puntos,
    'fecha' => date('Y-m-d H:i')
];

// 4. Ordenar (Mayor a menor)
usort($datos, function($a, $b) {
    return $b['puntos'] - $a['puntos'];
});

// 5. Mantener solo el Top 50
$datos = array_slice($datos, 0, 50);

// 6. Guardar
file_put_contents($archivo, json_encode($datos, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
?>