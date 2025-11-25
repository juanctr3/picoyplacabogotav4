<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🎮 Escapa del Comparendo</title>
    
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2L2EV10ZWW"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-2L2EV10ZWW');
    </script>

    <style>
        body { margin: 0; padding: 0; background: #2d3436; font-family: 'Poppins', sans-serif; overflow: hidden; touch-action: none; }
        #game-container { position: relative; width: 100%; max-width: 480px; height: 100vh; margin: 0 auto; background: #636e72; overflow: hidden; border-left: 5px solid #fff; border-right: 5px solid #fff; }
        canvas { display: block; width: 100%; height: 100%; }
        
        /* UI Overlay */
        #ui-layer { position: absolute; top: 10px; left: 10px; right: 10px; display: flex; justify-content: space-between; color: white; font-weight: bold; text-shadow: 1px 1px 2px black; z-index: 10; pointer-events: none; }
        .stat-box { background: rgba(0,0,0,0.5); padding: 5px 10px; border-radius: 10px; }
        
        /* Barra de Combustible */
        #fuel-container { position: absolute; top: 50px; left: 10px; width: 150px; height: 20px; background: #000; border: 2px solid #fff; border-radius: 10px; z-index: 10; }
        #fuel-bar { width: 100%; height: 100%; background: linear-gradient(90deg, #ff7675, #fab1a0); border-radius: 8px; transition: width 0.2s; }
        
        /* Alerta de Gasolina Baja (Parpadeante) */
        #low-fuel-alert {
            position: absolute; top: 80px; left: 10px; width: 150px; text-align: center;
            color: #ff0000; font-weight: 900; font-size: 0.9em; text-shadow: 1px 1px 0 #fff;
            display: none; animation: blink 0.5s infinite; z-index: 15;
        }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }

        /* Pantalla Game Over */
        #game-over-screen { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); display: none; flex-direction: column; align-items: center; justify-content: center; color: white; z-index: 20; text-align: center; padding: 20px; box-sizing: border-box; }
        .btn-start { background: #00b894; color: white; border: none; padding: 15px 30px; font-size: 1.2em; border-radius: 50px; cursor: pointer; margin-top: 20px; box-shadow: 0 4px 0 #008f72; width: 100%; max-width: 300px; }
        .btn-start:active { transform: translateY(4px); box-shadow: none; }
        
        .form-group { width: 100%; max-width: 300px; margin-bottom: 10px; text-align: left; }
        .form-group label { font-size: 0.85em; color: #dfe6e9; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="email"], input[type="tel"] { padding: 12px; border-radius: 8px; border: none; width: 100%; font-size: 1em; background: rgba(255,255,255,0.9); }
        
        .legal-text { font-size: 0.65em; color: #b2bec3; margin-top: 15px; line-height: 1.4; max-width: 350px; }
        
        /* Tabla de Líderes */
        #leaderboard { background: white; color: #333; padding: 15px; border-radius: 10px; margin-top: 20px; width: 100%; max-width: 350px; max-height: 25vh; overflow-y: auto; }
        .leader-row { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 5px 0; font-size: 0.9em; }
        .rank-1 { color: #d63031; font-weight: bold; }
    </style>
</head>
<body>

<div id="game-container">
    <div id="ui-layer">
        <div class="stat-box">🏁 Metros: <span id="score">0</span></div>
        <div class="stat-box" id="level-box" style="display:none; background: #e17055;">🔥 Nivel <span id="level">1</span></div>
    </div>
    
    <div id="fuel-container">
        <div id="fuel-bar"></div>
        <span style="position:absolute; top:0; left:0; width:100%; text-align:center; color:white; font-size:0.8em; line-height:20px; font-weight:bold; text-shadow:1px 1px 1px #000;">⛽ GASOLINA</span>
    </div>
    <div id="low-fuel-alert">⚠️ ¡POCA GASOLINA!</div>

    <canvas id="gameCanvas"></canvas>

    <div id="game-over-screen" style="display:flex;">
        <h1 id="go-title">🚘 ¡EVITA EL PICO Y PLACA!</h1>
        <p id="go-msg" style="margin-bottom: 20px;">Esquiva policías y cámaras.<br>¡Recoge gasolina o te varas!</p>
        
        <div id="input-area" style="display:none; width:100%; flex-direction:column; align-items:center;">
            <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 15px; width: 100%; max-width: 350px;">
                <p style="margin:0 0 10px 0; font-weight:bold; color:#ffeaa7;">🏆 ¡Nuevo Récord! Regístrate para ganar:</p>
                
                <div class="form-group">
                    <label>Tu Nombre o Apodo:</label>
                    <input type="text" id="player-name" placeholder="Ej: Juan El Rápido" maxlength="15">
                </div>
                
                <div class="form-group">
                    <label>WhatsApp o Email (Para notificarte):</label>
                    <input type="text" id="player-contact" placeholder="Ej: 3001234567 o juan@mail.com">
                </div>
                
                <p class="legal-text">
                    * Los 3 mejores puntajes de la semana serán contactados. Nos reservamos el derecho de anular puntajes sospechosos o fraudulentos.
                </p>
            </div>
        </div>

        <button class="btn-start" onclick="startGame()">▶ ARRANCAR</button>
        <button onclick="location.href='/'" style="background:transparent; border:1px solid white; color:white; padding:8px 15px; margin-top:15px; border-radius:20px; font-size:0.9em;">🏠 Volver al Inicio</button>
        
        <div id="leaderboard">
            <h4 style="margin:0 0 10px 0;">🏆 Ranking Semanal</h4>
            <div id="leader-list">Cargando...</div>
        </div>
    </div>
</div>

<script>
    const canvas = document.getElementById('gameCanvas');
    const ctx = canvas.getContext('2d');
    
    let w, h;
    function resize() {
        w = canvas.width = document.getElementById('game-container').offsetWidth;
        h = canvas.height = document.getElementById('game-container').offsetHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    // Variables
    let gameRunning = false;
    let score = 0; // Score en frames
    let meters = 0; // Metros reales mostrados
    let speed = 5;
    let fuel = 100;
    let car = { x: 0, y: 0, w: 40, h: 70 };
    let obstacles = []; 
    let bonuses = [];   
    
    // Dificultad
    let obstacleProbability = 0.02; // 2% probabilidad inicial
    let currentLevel = 1;

    // SPRITES
    const SPRITES = {
        car: '🚗',
        police: '🚓', 
        camera: '📸',
        gas: '⛽'
    };

    function startGame() {
        const nameInput = document.getElementById('player-name');
        const contactInput = document.getElementById('player-contact');
        
        if (document.getElementById('go-title').innerText === "GAME OVER") {
            if (nameInput.value.trim() !== "" && contactInput.value.trim() !== "") {
                saveScore(nameInput.value.trim(), contactInput.value.trim(), score);
            }
        }

        document.getElementById('game-over-screen').style.display = 'none';
        document.getElementById('input-area').style.display = 'none';
        document.getElementById('low-fuel-alert').style.display = 'none';
        document.getElementById('level-box').style.display = 'none';
        
        // Reiniciar variables
        gameRunning = true;
        score = 0;
        meters = 0;
        speed = 5;
        fuel = 100;
        obstacleProbability = 0.02;
        currentLevel = 1;
        
        // CORRECCIÓN 1: Subir el carro (h - 160 en vez de h - 120)
        car.x = w / 2 - 20;
        car.y = h - 160; 
        
        obstacles = [];
        bonuses = [];
        
        loop();
    }

    function gameOver(reason) {
        gameRunning = false;
        document.getElementById('game-over-screen').style.display = 'flex';
        document.getElementById('go-title').innerText = "GAME OVER";
        document.getElementById('go-msg').innerHTML = reason === 'crash' ? "🚓 ¡TE COGIÓ EL TRÁNSITO!" : "⛽ ¡TE QUEDASTE SIN GASOLINA!";
        document.getElementById('input-area').style.display = 'flex';
        document.getElementById('low-fuel-alert').style.display = 'none';
        loadLeaderboard();
    }

    function update() {
        if (!gameRunning) return;

        // Consumo
        fuel -= 0.15;
        if (fuel <= 0) { fuel = 0; gameOver('gas'); }
        
        const bar = document.getElementById('fuel-bar');
        bar.style.width = fuel + '%';
        
        // CORRECCIÓN 3: Alerta de Gasolina
        if (fuel < 25) {
            bar.style.background = '#ff0000';
            document.getElementById('low-fuel-alert').style.display = 'block';
        } else {
            bar.style.background = 'linear-gradient(90deg, #ff7675, #fab1a0)';
            document.getElementById('low-fuel-alert').style.display = 'none';
        }

        // Generar Obstáculos (Con probabilidad dinámica)
        if (Math.random() < obstacleProbability) {
            let type = Math.random() < 0.5 ? 'police' : 'camera';
            obstacles.push({ x: Math.random() * (w - 40), y: -50, type: type });
        }
        if (Math.random() < 0.01) {
            bonuses.push({ x: Math.random() * (w - 40), y: -50 });
        }

        obstacles.forEach((obs, i) => {
            obs.y += speed;
            if (obs.x < car.x + car.w - 10 && obs.x + 30 > car.x + 10 &&
                obs.y < car.y + car.h - 10 && obs.y + 30 > car.y + 10) {
                gameOver('crash');
            }
            if (obs.y > h) obstacles.splice(i, 1);
        });

        bonuses.forEach((b, i) => {
            b.y += speed;
            if (b.x < car.x + car.w && b.x + 30 > car.x &&
                b.y < car.y + car.h && b.y + 30 > car.y) {
                fuel = Math.min(100, fuel + 20); 
                score += 50; 
                bonuses.splice(i, 1);
            }
            if (b.y > h) bonuses.splice(i, 1);
        });

        score++;
        meters = Math.floor(score / 10);
        document.getElementById('score').innerText = meters;

        // CORRECCIÓN 2: Aumentar dificultad cada 500 metros
        if (meters > 0 && meters % 500 === 0 && (meters / 500) >= currentLevel) {
            currentLevel++;
            speed += 1; // Más rápido
            obstacleProbability += 0.005; // Más obstáculos (0.02 -> 0.025 -> 0.03...)
            
            // Mostrar aviso de nivel
            const lvlBox = document.getElementById('level-box');
            document.getElementById('level').innerText = currentLevel;
            lvlBox.style.display = 'block';
            setTimeout(() => { lvlBox.style.display = 'none'; }, 2000);
        }
    }

    function drawRotatedSprite(sprite, x, y, angle) {
        ctx.save(); 
        ctx.translate(x + 20, y + 35); 
        ctx.rotate(angle); 
        ctx.fillText(sprite, -20, 15); 
        ctx.restore(); 
    }

    function draw() {
        ctx.clearRect(0, 0, w, h);
        
        ctx.fillStyle = '#b2bec3';
        for(let i=0; i<h; i+=40) ctx.fillRect(w/2 - 2, i + (score % 40), 4, 20); 

        ctx.font = '40px Arial';
        
        // JUGADOR: Mira hacia ARRIBA (-90 grados)
        drawRotatedSprite(SPRITES.car, car.x, car.y, Math.PI / 2);

        // OBSTÁCULOS
        obstacles.forEach(obs => {
            if(obs.type === 'police') {
                // POLICÍA: Mira hacia ABAJO (+90 grados) -> Viene de frente
                 drawRotatedSprite(SPRITES[obs.type], obs.x, obs.y, Math.PI / 2);
            } else {
                ctx.fillText(SPRITES[obs.type], obs.x, obs.y + 30);
            }
        });

        bonuses.forEach(b => ctx.fillText(SPRITES.gas, b.x, b.y + 30));
    }

    function loop() {
        if (!gameRunning) return;
        update();
        draw();
        requestAnimationFrame(loop);
    }

    window.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft' && car.x > 0) car.x -= 20;
        if (e.key === 'ArrowRight' && car.x < w - 40) car.x += 20;
    });

    canvas.addEventListener('touchmove', e => {
        e.preventDefault();
        let touch = e.touches[0];
        let rect = canvas.getBoundingClientRect();
        car.x = touch.clientX - rect.left - 20; 
        if(car.x < 0) car.x = 0;
        if(car.x > w - 40) car.x = w - 40;
    }, { passive: false });

    async function saveScore(name, contact, points) {
        const formData = new FormData();
        formData.append('nombre', name);
        formData.append('contacto', contact);
        formData.append('puntos', Math.floor(points/10));

        await fetch('guardar_puntos.php', { method: 'POST', body: formData });
        loadLeaderboard();
        document.getElementById('player-name').value = "";
        document.getElementById('player-contact').value = "";
    }

    async function loadLeaderboard() {
        const res = await fetch('puntajes.json?v=' + Date.now());
        const data = await res.json();
        const list = document.getElementById('leader-list');
        list.innerHTML = '';
        
        data.slice(0, 10).forEach((p, i) => {
            let medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : (i+1)+'.'));
            let cls = i === 0 ? 'rank-1' : '';
            list.innerHTML += `<div class="leader-row ${cls}"><span>${medal} ${p.nombre}</span><span>${p.puntos} m</span></div>`;
        });
    }

    loadLeaderboard();
</script>
</body>
</html>