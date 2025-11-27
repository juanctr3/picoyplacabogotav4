<?php
/**
 * admin/reports.php - Panel de Reportes de Publicidad
 * Muestra las estadísticas en tiempo real (Impresiones y Clicks) por banner.
 */

// Configuración de la Base de Datos
$dbHost = 'localhost';
$dbName = 'picoyplacabogota';
$dbUser = 'picoyplacabogota';
$dbPass = 'Q20BsIFHI9j8h2XoYNQm3RmQg';

$reportes = [];

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta SQL para agrupar y sumar los eventos de la tabla banner_events
    // Y hacer JOIN con la tabla banners para obtener el título y límites
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS banner_id,
            b.titulo,
            b.max_impresiones,
            b.max_clicks,
            COALESCE(SUM(CASE WHEN be.event_type = 'impresion' THEN 1 ELSE 0 END), 0) AS total_impresiones,
            COALESCE(SUM(CASE WHEN be.event_type = 'click' THEN 1 ELSE 0 END), 0) AS total_clicks
        FROM banners b
        LEFT JOIN banner_events be ON b.id = be.banner_id
        GROUP BY b.id, b.titulo, b.max_impresiones, b.max_clicks
        ORDER BY b.id ASC
    ");

    $stmt->execute();
    $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión o consulta: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Reportes de Banners</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f7f6; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #3498db; color: white; }
        .success { background-color: #e6f7e9; }
        .warning { background-color: #fff8e1; }
        .error { background-color: #fcebeb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reportes de Publicidad (Tiempo Real) 📈</h1>
        <p>Aquí puedes monitorear el rendimiento de tus campañas activas y el uso de los límites de Clicks/Impresiones.</p>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título del Banner</th>
                    <th>Impresiones (Vistas)</th>
                    <th>Clicks (Acciones)</th>
                    <th>Límite Máx.</th>
                    <th>Ratio Click (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportes as $banner): ?>
                <?php
                    $impresiones = (int)$banner['total_impresiones'];
                    $clicks = (int)$banner['total_clicks'];
                    $maxImp = (int)$banner['max_impresiones'];
                    $maxClick = (int)$banner['max_clicks'];
                    
                    // Cálculo de Ratio Click-Through (CTR)
                    $ctr = ($impresiones > 0) ? round(($clicks / $impresiones) * 100, 2) : 0;

                    // Clases para resaltar si los límites están cerca
                    $rowClass = '';
                    if ($clicks >= $maxClick * 0.9 || $impresiones >= $maxImp * 0.9) {
                        $rowClass = 'error'; // 90% o más
                    } elseif ($clicks >= $maxClick * 0.7 || $impresiones >= $maxImp * 0.7) {
                        $rowClass = 'warning'; // 70% o más
                    } else {
                        $rowClass = 'success';
                    }
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= $banner['banner_id'] ?></td>
                    <td><?= htmlspecialchars($banner['titulo']) ?></td>
                    <td><?= number_format($impresiones, 0, ',', '.') ?></td>
                    <td><?= number_format($clicks, 0, ',', '.') ?></td>
                    <td>
                        V: <?= number_format($maxImp, 0, ',', '.') ?><br>
                        C: <?= number_format($maxClick, 0, ',', '.') ?>
                    </td>
                    <td><?= $ctr ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>