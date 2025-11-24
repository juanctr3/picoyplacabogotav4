<?php
/**
 * generar_sitemap.php
 * Script de generación de sitemap inteligente (Rolling 30 días).
 * Genera URLs 100% amigables: /pico-y-placa/ciudad-dia-mes-anio/tipo
 * Este script DEBE ser ejecutado por un CRON JOB una vez al día.
 */

// 1. Configuración
date_default_timezone_set('America/Bogota');

// Cargar configuración de ciudades
require_once 'config-ciudades.php';

// Limpieza de configuración interna para no iterar sobre ella
if(isset($ciudades['rotaciones_base'])) {
    unset($ciudades['rotaciones_base']);
}

// Configuración del Sitemap
$BASE_URL = 'https://picoyplacabogota.com.co'; 
$DIAS_A_GENERAR = 30; // Ventana de 30 días
$SITEMAP_FILE = __DIR__ . '/sitemap.xml';

// Arrays de fechas en español
$MESES = [
    '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
    '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
    '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
];

// 2. Generación de URLs
$urls = [];
$now = new DateTime();

// Bucle para los próximos 30 días
for ($i = 0; $i < $DIAS_A_GENERAR; $i++) {
    $currentDate = clone $now;
    $currentDate->modify("+$i day");
    
    // Datos de fecha
    $dia = (int)$currentDate->format('d'); // Sin ceros iniciales (ej: 1, 2... 31)
    $mes_num = $currentDate->format('m');
    $ano = $currentDate->format('Y');
    $mes_nombre = $MESES[$mes_num];
    
    // Slug de fecha: 22-de-noviembre-de-2025
    $dateSlug = sprintf('%d-de-%s-de-%s', $dia, $mes_nombre, $ano);

    // Iterar por cada ciudad y tipo de vehículo
    foreach ($ciudades as $ciudad_slug => $ciudad_data) {
        foreach ($ciudad_data['tipos'] as $tipo_slug => $tipo_data) {
            
            // Construcción de URL Amigable Completa
            // Ejemplo: https://seo1a.one/pico-y-placa/bogota-22-de-noviembre-de-2025/particulares
            $loc = sprintf(
                '/pico-y-placa/%s-%s/%s',
                $ciudad_slug,
                $dateSlug,
                $tipo_slug
            );
            
            // Prioridad descendente (Hoy es más importante que dentro de 30 días)
            $priority = max(0.5, 1.0 - ($i / ($DIAS_A_GENERAR * 2)));

            $urls[] = [
                'loc' => $BASE_URL . $loc,
                'lastmod' => $now->format('Y-m-d'), // La fecha de modificación es hoy (cuando se regenera el sitemap)
                'changefreq' => 'daily',
                'priority' => number_format($priority, 2, '.', '')
            ];
        }
    }
}

// 3. Construcción del XML
$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xmlContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// A. URL Principal (Home)
$xmlContent .= "  <url>\n";
$xmlContent .= "    <loc>{$BASE_URL}/</loc>\n";
$xmlContent .= "    <lastmod>" . $now->format('Y-m-d') . "</lastmod>\n";
$xmlContent .= "    <changefreq>daily</changefreq>\n";
$xmlContent .= "    <priority>1.0</priority>\n";
$xmlContent .= "  </url>\n";

// B. URLs Dinámicas
foreach ($urls as $url) {
    $xmlContent .= "  <url>\n";
    $xmlContent .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
    $xmlContent .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
    $xmlContent .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
    $xmlContent .= "    <priority>" . $url['priority'] . "</priority>\n";
    $xmlContent .= "  </url>\n";
}

$xmlContent .= '</urlset>';

// 4. Guardar Archivo
if (file_put_contents($SITEMAP_FILE, $xmlContent) === false) {
    // Mensaje para logs de error del servidor
    error_log("ERROR SITEMAP: No se pudo escribir en $SITEMAP_FILE. Verifica permisos.");
    // Mensaje visible si se ejecuta manualmente
    echo "ERROR: No se pudo escribir el archivo sitemap.xml. Verifica los permisos de escritura en la carpeta.\n";
} else {
    echo "Sitemap generado correctamente el " . date('Y-m-d H:i:s') . ".\n";
    echo "Total URLs generadas: " . (count($urls) + 1) . "\n";
}
?>