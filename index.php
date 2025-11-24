<?php
/**
 * index.php
 * Versión Estabilizada:
 * - Fix Bucle de Recarga: Manejo seguro de expiración de tiempo.
 * - Reloj Sincronizado: Uso de Timestamps absolutos (PHP -> JS).
 * - PWA: Botón de instalación mejorado.
 */

// 1. Configuración inicial
date_default_timezone_set('America/Bogota');
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0); 

require_once 'config-ciudades.php';
require_once 'clases/PicoYPlaca.php';

$picoYPlaca = new PicoYPlaca();
if(isset($ciudades['rotaciones_base'])) unset($ciudades['rotaciones_base']);

// Datos Globales
$HOY = date('Y-m-d'); 
$DEFAULT_CIUDAD_URL = 'bogota';
$DEFAULT_TIPO_URL = 'particulares';
$MULTA_VALOR = '1.400.000';
$BASE_URL = 'https://picoyplacabogota.com.co';

$MESES = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
$MESES_CORTOS = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
$DIAS_SEMANA = [1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado',7=>'domingo'];

// 2. Lógica de Búsqueda
$es_busqueda = false;
$ciudad_busqueda = $DEFAULT_CIUDAD_URL;
$fecha_busqueda = $HOY;
$tipo_busqueda = $DEFAULT_TIPO_URL;

// Canonical URL
$request_uri_clean = explode('?', $_SERVER['REQUEST_URI'])[0];
$canonical_url = trim($BASE_URL . $request_uri_clean, '/');

if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/pico-y-placa/') === 0) {
    $slug = explode('/', trim($request_uri_clean, '/'))[1] ?? '';
    if (preg_match('/^([a-z-]+)-(\d{1,2})-de-([a-z]+)-de-(\d{4})$/', $slug, $m)) {
        $mes_num = array_search($m[3], $MESES);
        if ($mes_num) {
            $es_busqueda = true;
            $ciudad_busqueda = array_key_exists($m[1], $ciudades) ? $m[1] : $DEFAULT_CIUDAD_URL;
            $fecha_busqueda = $m[4].'-'.$mes_num.'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $tipo_busqueda = $_GET['tipo'] ?? $DEFAULT_TIPO_URL;
            if (!isset($ciudades[$ciudad_busqueda]['tipos'][$tipo_busqueda])) {
                $tipo_busqueda = key($ciudades[$ciudad_busqueda]['tipos']);
            }
        }
    }
}

// Consulta
$resultados = $picoYPlaca->obtenerRestriccion($ciudad_busqueda, $fecha_busqueda, $tipo_busqueda);
$nombre_festivo = $resultados['festivo'] ?? null;

// Datos Vista
$nombre_ciudad = $ciudades[$ciudad_busqueda]['nombre'];
$nombre_tipo = $ciudades[$ciudad_busqueda]['tipos'][$tipo_busqueda]['nombre_display'];
$dt = new DateTime($fecha_busqueda);
$fecha_texto = ucfirst($DIAS_SEMANA[$dt->format('N')]) . ', ' . (int)$dt->format('d') . ' de ' . ucfirst($MESES[$dt->format('m')]) . ' de ' . $dt->format('Y');
$fecha_corta = $DIAS_CORTOS[$dt->format('N')] . ' ' . (int)$dt->format('d') . ' ' . $MESES_CORTOS[$dt->format('m')];

// SEO
$titulo_h1 = "Pico y Placa " . ($es_busqueda ? "el {$fecha_texto}" : "hoy") . " en {$nombre_ciudad}";
$page_title = "Pico y Placa $nombre_ciudad hoy $fecha_corta | $nombre_tipo"; 
$estado_texto = $resultados['hay_pico'] ? "TIENE restricción" : "NO TIENE restricción";
$meta_description = "Consulta Pico y Placa en $nombre_ciudad para $nombre_tipo el $fecha_texto. Estado: $estado_texto. Evita multas.";

// Background
$body_class = $resultados['hay_pico'] ? 'bg-restriccion' : 'bg-libre';

// --- RELOJ PREDICTIVO (Cálculo en PHP para precisión) ---
$reloj_titulo = "⏳ TIEMPO RESTANTE";
$next_event_ts = 0; // Timestamp en milisegundos
$reloj_mode = 'none'; // 'ending', 'starting', 'next_day'

// Solo calcular reloj si es HOY
if ($fecha_busqueda === $HOY) {
    $now_ts = time(); // Timestamp actual servidor
    $rangos_hoy = $ciudades[$ciudad_busqueda]['tipos'][$tipo_busqueda]['rangos_horarios_php'] ?? [];
    
    if ($resultados['hay_pico'] && !empty($rangos_hoy)) {
        // Buscar si estamos DENTRO o ANTES de un rango
        foreach ($rangos_hoy as $r) {
            $inicio_ts = strtotime("$HOY " . $r['inicio']);
            $fin_ts = strtotime("$HOY " . $r['fin']);
            
            // Fix para rangos que cruzan medianoche (fin < inicio)
            if ($fin_ts < $inicio_ts) $fin_ts += 86400; // +24 horas
            
            if ($now_ts >= $inicio_ts && $now_ts < $fin_ts) {
                // Estamos DENTRO: Contar para FIN
                $next_event_ts = $fin_ts * 1000;
                $reloj_mode = 'ending';
                break;
            } elseif ($now_ts < $inicio_ts) {
                // Estamos ANTES: Contar para INICIO
                $next_event_ts = $inicio_ts * 1000;
                $reloj_mode = 'starting';
                break;
            }
        }
    }
    
    // Si no encontramos evento hoy (o no hay pico), buscar PRÓXIMO DÍA
    if ($next_event_ts == 0) {
        for ($i = 1; $i <= 15; $i++) { 
            $nd = date('Y-m-d', strtotime("$HOY +$i days"));
            $nr = $picoYPlaca->obtenerRestriccion($ciudad_busqueda, $nd, $tipo_busqueda);
            if ($nr['hay_pico']) {
                $rangos_next = $ciudades[$ciudad_busqueda]['tipos'][$tipo_busqueda]['rangos_horarios_php'] ?? [];
                if (!empty($rangos_next)) {
                    $inicio_ts = strtotime("$nd " . $rangos_next[0]['inicio']);
                    $next_event_ts = $inicio_ts * 1000;
                    $reloj_mode = 'next_day';
                    
                    $ndt = new DateTime($nd);
                    $d_nombre = $DIAS_SEMANA[$ndt->format('N')];
                    $d_num = $ndt->format('d');
                    $m_nom = $MESES[$ndt->format('m')];
                    $reloj_titulo = "📅 PRÓXIMA RESTRICCIÓN: " . mb_strtoupper("$d_nombre $d_num DE $m_nom");
                }
                break; 
            }
        }
    }
}

// --- CALENDARIO 30 DÍAS ---
$calendario = [];
$placa_usuario = isset($_GET['placa']) && is_numeric($_GET['placa']) ? (string)$_GET['placa'] : null;
$estado_usuario = null;

if ($placa_usuario !== null && $resultados['hay_pico']) {
    $estado_usuario = in_array($placa_usuario, $resultados['restricciones']);
} elseif ($placa_usuario !== null && !$resultados['hay_pico']) {
    $estado_usuario = false; 
}

$fecha_iter = new DateTime($HOY);
for ($i = 0; $i < 30; $i++) {
    $f_str = $fecha_iter->format('Y-m-d');
    $res = $picoYPlaca->obtenerRestriccion($ciudad_busqueda, $f_str, $tipo_busqueda);
    
    $estado_dia = 'libre'; $mensaje_dia = 'Sin restricción';
    
    if ($res['hay_pico']) {
        if ($placa_usuario !== null) {
            if (in_array($placa_usuario, $res['restricciones'])) {
                $estado_dia = 'restriccion_usuario'; $mensaje_dia = "🚫 Tu placa ($placa_usuario) tiene Pico y Placa";
            } else {
                $estado_dia = 'libre_usuario'; $mensaje_dia = "✅ Tu placa ($placa_usuario) circula libremente";
            }
        } else {
            $estado_dia = 'restriccion_general'; $mensaje_dia = "🚫 Restringe: " . implode('-', $res['restricciones']);
        }
    }
    if ($res['festivo']) {
        $mensaje_dia .= "<br><span class='festivo-label'>🎉 Es Festivo: {$res['festivo']}</span>";
        if (!$res['hay_pico']) $estado_dia = 'libre';
    }

    $calendario[] = [
        'd' => $fecha_iter->format('d'),
        'm' => substr(ucfirst($MESES[$fecha_iter->format('m')]), 0, 3),
        'dia' => ucfirst($DIAS_SEMANA[$fecha_iter->format('N')]),
        'estado' => $estado_dia,
        'mensaje' => $mensaje_dia
    ];
    $fecha_iter->modify('+1 day');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">
    <meta name="theme-color" content="<?= $resultados['hay_pico'] ? '#ff7675' : '#55efc4' ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" sizes="57x57" href="/favicons/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="/favicons/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="/favicons/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="/favicons/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="/favicons/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="/favicons/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-icon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192"  href="/favicons/android-icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="manifest" href="/favicons/manifest.json">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="/favicons/ms-icon-144x144.png">
<meta name="theme-color" content="#ffffff">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/styles.css?v=6.1">
    
    <style>
        body.bg-libre { background-image: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
        body.bg-restriccion { background-image: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%); }
        .festivo-label { color: #e67e22; font-weight: 700; font-size: 0.9em; }
        
        #install-wrapper { position: fixed; bottom: 20px; right: 20px; z-index: 2000; display: none; }
        .btn-install-app { background: #2d3436; color: white; border: none; padding: 12px 20px; border-radius: 50px; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer; display: flex; align-items: center; gap: 10px; animation: bounce 2s infinite; }
        .ios-tooltip { background: #fff; padding: 10px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); margin-bottom: 10px; font-size: 0.9em; position: relative; }
        .ios-tooltip:after { content:''; position:absolute; bottom:-5px; right:20px; width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:5px solid #fff; }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% {transform: translateY(0);} 40% {transform: translateY(-10px);} 60% {transform: translateY(-5px);} }
    </style>
    </script>
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-2L2EV10ZWW"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-2L2EV10ZWW');
</script>
    <script>
        const DATA_CIUDADES = <?= json_encode($ciudades) ?>;
        const HOY_STR = '<?= $HOY ?>';
        const FECHA_BUSQUEDA = '<?= $fecha_busqueda ?>';
        const TIPO_ACTUAL = '<?= $tipo_busqueda ?>';
        const CIUDAD_ACTUAL = '<?= $ciudad_busqueda ?>';
        const DEFAULT_TIPO = '<?= $DEFAULT_TIPO_URL ?>';
        
        // DATOS DEL RELOJ (Calculados en PHP)
        const NEXT_EVENT_TS = <?= $next_event_ts ?>; // Timestamp Milisegundos
        const RELOJ_MODE = '<?= $reloj_mode ?>';
        const RELOJ_TITULO = '<?= $reloj_titulo ?>';

        // Sincronización hora servidor
        const SERVER_TIME_MS = <?= time() * 1000 ?>;
        const CLIENT_OFFSET = new Date().getTime() - SERVER_TIME_MS;

        document.addEventListener('DOMContentLoaded', () => {
            initReloj();
            initFormulario();
            initPWA();
        });
        
        function initPWA() {
            const wrapper = document.getElementById('install-wrapper');
            const btn = document.getElementById('install-btn');
            const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
            
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                wrapper.style.display = 'block';
                btn.style.display = 'flex';
            });
            
            btn.addEventListener('click', () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(() => { deferredPrompt = null; wrapper.style.display = 'none'; });
                }
            });

            if (isIos && !window.navigator.standalone) {
                wrapper.style.display = 'block';
                btn.style.display = 'none'; 
                document.getElementById('ios-install-msg').style.display = 'block';
                setTimeout(() => { wrapper.style.display = 'none'; }, 12000);
            }
        }

        function toggleCalendario() {
            const grid = document.getElementById('calendario-grid');
            const btn = document.getElementById('btn-toggle-cal');
            if (grid.style.display === 'none') {
                grid.style.display = 'grid'; btn.textContent = 'Ocultar calendario';
            } else {
                grid.style.display = 'none'; btn.textContent = 'Ver próximos 30 días';
                document.getElementById('calendario').scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        }

        function initReloj() {
            const section = document.getElementById('countdown-section');
            // Ocultar si no es hoy o no hay evento futuro calculado
            if (FECHA_BUSQUEDA !== HOY_STR || NEXT_EVENT_TS === 0) {
                if(FECHA_BUSQUEDA === HOY_STR) {
                    // Si es hoy pero next_event_ts es 0, significa libre todo el día
                   section.innerHTML = `<h3 style="color:var(--color-libre); margin:0; text-align:center;">🎉 ¡SIN RESTRICCIÓN!</h3>`;
                } else {
                   section.style.display = 'none'; 
                }
                return;
            }

            const updateTimer = () => {
                const now = new Date().getTime() - CLIENT_OFFSET;
                const diff = NEXT_EVENT_TS - now;

                // FIX BUCLE: Si el tiempo se acabó, recargar UNA VEZ
                if (diff < 0) {
                    if (!sessionStorage.getItem('reloaded_flag')) {
                        sessionStorage.setItem('reloaded_flag', '1');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        sessionStorage.removeItem('reloaded_flag');
                        document.getElementById('lbl-reloj').innerHTML = "Actualizando...";
                    }
                    return; // Detener ejecución
                }
                sessionStorage.removeItem('reloaded_flag');

                let hours = Math.floor(diff / (1000 * 60 * 60));
                let minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                let seconds = Math.floor((diff % (1000 * 60)) / 1000);

                document.getElementById('cd-h').textContent = hours < 10 ? '0'+hours : hours;
                document.getElementById('cd-m').textContent = minutes < 10 ? '0'+minutes : minutes;
                document.getElementById('cd-s').textContent = seconds < 10 ? '0'+seconds : seconds;

                const label = document.getElementById('lbl-reloj');
                const blocks = document.querySelectorAll('.countdown-block');
                const seps = document.querySelectorAll('.separator');
                let col = 'var(--color-libre)'; 

                if (RELOJ_MODE === 'ending') { label.innerHTML = '🚨 TERMINA EN:'; col = 'var(--color-pico)'; }
                else if (RELOJ_MODE === 'next_day') { label.innerHTML = RELOJ_TITULO; }
                else { label.innerHTML = '⏳ INICIA EN:'; }

                blocks.forEach(b => {
                    b.style.backgroundColor = col;
                    RELOJ_MODE==='ending' ? b.classList.add('ending') : b.classList.remove('ending');
                });
                seps.forEach(s => s.style.color = col);
            };
            
            setInterval(updateTimer, 1000);
            updateTimer();
        }

        function initFormulario() {
            const selC = document.getElementById('sel-ciudad');
            const selT = document.getElementById('sel-tipo');
            const selM = document.getElementById('ciudad-selector-mobile'); 

            const upd = () => {
                const c = selC.value;
                const t = DATA_CIUDADES[c]?.tipos || {};
                const prev = selT.value;
                selT.innerHTML = '';
                for(let k in t) {
                    let o = document.createElement('option');
                    o.value = k; o.textContent = t[k].nombre_display; selT.appendChild(o);
                }
                if(t[TIPO_ACTUAL] && c === CIUDAD_ACTUAL) selT.value = TIPO_ACTUAL;
                else if(t[prev]) selT.value = prev;
            };
            selC.addEventListener('change', upd);
            upd();

            if(selM) {
                selM.value = CIUDAD_ACTUAL;
                selM.addEventListener('change', (e) => {
                    const c = e.target.value;
                    if(c) {
                        const d = '<?= date('j', strtotime($HOY)) ?>';
                        const m = '<?= $MESES[date('m', strtotime($HOY))] ?>';
                        const a = '<?= date('Y', strtotime($HOY)) ?>';
                        window.location.href = `/pico-y-placa/${c}-${d}-de-${m}-de-${a}/${DEFAULT_TIPO}`;
                    }
                });
            }
        }
    </script>
</head>
<body class="<?= $body_class ?>">

    <div id="install-wrapper">
        <div id="ios-install-msg" class="ios-tooltip" style="display:none">
            📲 Para instalar: toca el botón <strong>Compartir</strong> y selecciona <strong>"Agregar a Inicio"</strong>.
        </div>
        <button id="install-btn" class="btn-install-app" style="display:none">
            <span>📲</span> Instalar App
        </button>
    </div>

    <header>
        <div class="container">
            <span class="header-icon">🚗</span>
            <h1 class="titulo-principal"><?= $titulo_h1 ?></h1>
            <p class="seo-lead-text-header">
                Mantente informado sobre el <strong>Pico y Placa en <?= $nombre_ciudad ?></strong>. 
                Evita multas de hasta <span class="precio-multa">$<?= $MULTA_VALOR ?></span> y planifica tu viaje.
            </p>
        </div>
    </header>

    <main class="container">
        
        <section class="card">
            <h2>🗓️ Buscar</h2>
            <form action="/buscar.php" method="POST" class="busqueda-form-inline">
                <div style="display:flex; gap:10px; width:100%; flex-wrap:wrap;">
                    <div class="form-group-inline" style="flex:1; min-width:150px;">
                        <input type="date" name="fecha" value="<?= $fecha_busqueda ?>" required min="2020-01-01" max="2030-12-31">
                    </div>
                    <div class="form-group-inline" style="flex:1; min-width:150px;">
                        <select name="ciudad" id="sel-ciudad">
                            <?php 
                            $ciudades_ord = $ciudades;
                            uasort($ciudades_ord, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
                            foreach($ciudades_ord as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $k===$ciudad_busqueda?'selected':'' ?>><?= $v['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-inline" style="flex:1; min-width:150px;">
                        <select name="tipo" id="sel-tipo"></select>
                    </div>
                    <div class="form-group-inline" style="flex:0.5; min-width:80px;">
                        <input type="number" name="placa" placeholder="Placa" value="<?= $placa_usuario ?>" min="0" max="9">
                    </div>
                    <div class="form-group-inline" style="flex:0;">
                        <button type="submit" class="btn-primary-inline">Buscar</button>
                    </div>
                </div>
            </form>
        </section>

        <section id="countdown-section" class="countdown-section">
            <div id="lbl-reloj" class="countdown-title"></div>
            <div class="countdown-timer">
                <div class="countdown-block"><span id="cd-h" class="countdown-num"></span><small>Horas</small></div>
                <div class="separator">:</div>
                <div class="countdown-block"><span id="cd-m" class="countdown-num"></span><small>Min</small></div>
                <div class="separator">:</div>
                <div class="countdown-block"><span id="cd-s" class="countdown-num"></span><small>Seg</small></div>
            </div>
        </section>

        <div class="layout-grid">
            
            <div class="col-izq">
                <section class="resultado-detalle-card">
                    <div class="resultado-fecha-header">🗓️ <?= $fecha_texto ?></div>
                    <div class="resultado-subtitulo">🚗 <?= $nombre_ciudad ?> (<?= $nombre_tipo ?>)</div>
                    
                    <div class="info-panel">
                        <div class="info-row">📅 <strong>Día:</strong> <?= ucfirst($DIAS_SEMANA[$dt->format('N')]) ?></div>
                        
                        <?php if($nombre_festivo): ?>
                            <div class="info-row" style="color:#e67e22; background:#fff8e1; padding:10px; border-radius:8px;">
                                🎉 <strong>El día <?= $fecha_texto ?> es festivo y se celebra: <?= $nombre_festivo ?></strong>. 
                                <br>Generalmente no aplica el pico y placa (Verifica decretos locales).
                            </div>
                        <?php endif; ?>

                        <div class="info-row">🕒 <strong>Horario:</strong> <?= $resultados['horario'] ?></div>
                        <div class="info-row">📊 
                            <?php 
                            if ($placa_usuario !== null) {
                                if ($estado_usuario === true) echo '<span class="estado-badge restriccion">🚫 TIENES RESTRICCIÓN</span>';
                                else echo '<span class="estado-badge libre">✅ PUEDES CIRCULAR</span>';
                            } else {
                                if($resultados['hay_pico']) echo '<span class="estado-badge restriccion">⚠️ Hay restricción</span>';
                                else echo '<span class="estado-badge libre">✅ Sin restricción</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="seccion-placas">
                        <div class="titulo-placas"><?= $resultados['hay_pico'] ? '🚫 Restringe:' : '✅ Habilitadas:' ?></div>
                        <div class="placas-list">
                            <?php 
                            $lista = $resultados['hay_pico'] ? $resultados['restricciones'] : $resultados['permitidas'];
                            $cls = $resultados['hay_pico'] ? 'rojo' : 'verde';
                            foreach($lista as $p) echo "<div class='badge-placa $cls'>$p</div>";
                            ?>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-der">
                <section class="card">
                    <h2>📍 Tu ciudad</h2>
                    <div class="mobile-only" style="margin-bottom:10px;">
                        <select id="ciudad-selector-mobile" class="form-group-inline" style="width:100%; padding:10px;">
                            <option value="">Seleccionar otra ciudad...</option>
                            <?php foreach($ciudades_ord as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid-ciudades desktop-only">
                        <?php foreach($ciudades_ord as $k => $v): 
                            $d_hoy = date('j'); $m_hoy = $MESES[date('m')]; $a_hoy = date('Y');
                            $url = "/pico-y-placa/$k-$d_hoy-de-$m_hoy-de-$a_hoy/$DEFAULT_TIPO_URL";
                        ?>
                            <a href="<?= $url ?>" class="btn-ciudad <?= $k===$ciudad_busqueda?'active':'' ?>"><?= $v['nombre'] ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
                
                <section class="card">
                    <h2>ℹ️ Resumen HOY</h2>
                    <div style="font-size:0.9em; line-height:1.8;">
                        <h4 style="color:var(--color-primary); margin-bottom:5px;"><?= $nombre_ciudad ?></h4>
                        <div><?= $resultados['hay_pico'] ? '🚫 Restringe: '.implode(', ', $resultados['restricciones']) : '✅ ¡Sin Restricción!' ?></div>
                        <div style="margin-top:15px; padding-top:10px; border-top:1px solid #eee;">
                            <span style="font-size:1.2em;">🗓️</span> <strong>Planea tu mes</strong>
                            <a href="javascript:void(0)" onclick="toggleCalendario()" style="display:block; margin-top:5px; color:var(--color-primary); text-decoration:none; font-weight:600; font-size:0.95em;">Ver calendario completo →</a>
                        </div>
                    </div>
                </section>
            </div>

        </div>

        <section class="card calendario-section" id="calendario">
            <h2 class="calendario-titulo">
                🗓️ Próximos 30 días: <?= $nombre_ciudad ?> (<?= $nombre_tipo ?>)
                <?php if($placa_usuario): ?> <small>Placa terminada en <?= $placa_usuario ?></small> <?php endif; ?>
            </h2>
            
            <div style="text-align:center; margin-bottom:20px;">
                <button id="btn-toggle-cal" class="btn-primary-inline" style="width:auto; min-width:250px;" onclick="toggleCalendario()">Ver próximos 30 días</button>
            </div>

            <div id="calendario-grid" class="calendario-grid" style="display:none;">
                <?php foreach($calendario as $dia): 
                    $clase_dia = ($dia['estado'] == 'libre' || $dia['estado'] == 'libre_usuario') ? 'dia-libre' : 'dia-restriccion';
                ?>
                    <div class="calendario-item <?= $clase_dia ?>">
                        <div class="cal-fecha">
                            <span class="cal-dia-num"><?= $dia['d'] ?></span>
                            <span class="cal-mes"><?= $dia['m'] ?></span>
                        </div>
                        <div class="cal-info">
                            <div class="cal-dia-semana"><?= $dia['dia'] ?></div>
                            <div class="cal-mensaje"><?= $dia['mensaje'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> Pico y PL - Colombia | Versión 2.9</p>
    </footer>

</body>
</html>