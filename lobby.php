<?php
session_start();
require_once __DIR__ . '/class/config.php';
if (isset($_SESSION['steamid'])) {
    require_once __DIR__ . '/steamauth/userInfo.php';
}

$pdo = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);

// Create lobby table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS lobby_players (
    steamid VARCHAR(20) PRIMARY KEY,
    name VARCHAR(64),
    avatar VARCHAR(256),
    team TINYINT DEFAULT 0,
    ready TINYINT DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS lobby_state (
    id INT PRIMARY KEY DEFAULT 1,
    status VARCHAR(20) DEFAULT 'waiting',
    bo TINYINT DEFAULT 1,
    maplist TEXT,
    veto_first VARCHAR(10) DEFAULT 'team1',
    team1_name VARCHAR(64) DEFAULT 'Team 1',
    team2_name VARCHAR(64) DEFAULT 'Team 2',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$pdo->exec("INSERT IGNORE INTO lobby_state (id, maplist) VALUES (1, 'de_dust2,de_mirage,de_inferno,de_nuke,de_ancient,de_anubis,de_vertigo')");

$loggedIn = isset($_SESSION['steamid']);
$steamid = $loggedIn ? $_SESSION['steamid'] : null;
$playerName = $loggedIn ? $_SESSION['username'] : null;
$playerAvatar = $loggedIn ? $_SESSION['avatar'] : null;

if ($loggedIn) {
    $stmt = $pdo->prepare("INSERT INTO lobby_players (steamid, name, avatar) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), avatar=VALUES(avatar)");
    $stmt->execute([$steamid, $playerName, $playerAvatar]);
}

$state = $pdo->query("SELECT * FROM lobby_state WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$players = $pdo->query("SELECT * FROM lobby_players ORDER BY team, joined_at")->fetchAll(PDO::FETCH_ASSOC);
$team1 = array_filter($players, fn($p) => $p['team'] == 1);
$team2 = array_filter($players, fn($p) => $p['team'] == 2);
$unassigned = array_filter($players, fn($p) => $p['team'] == 0);
$totalPlayers = count($team1) + count($team2);
$myPlayer = $loggedIn ? array_filter($players, fn($p) => $p['steamid'] == $steamid) : [];
$myPlayer = !empty($myPlayer) ? array_values($myPlayer)[0] : null;
$myTeam = $myPlayer ? $myPlayer['team'] : 0;
$maps = explode(',', $state['maplist']);
$MAPS_ALL = ['de_dust2','de_mirage','de_inferno','de_nuke','de_ancient','de_anubis','de_vertigo','de_overpass'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lobby - Hoy Se Juega</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #0a0a0f;
  --surface: #12121a;
  --surface2: #1a1a26;
  --border: #2a2a3a;
  --accent: #e8ff47;
  --accent2: #47b8ff;
  --t1: #ff6b47;
  --t2: #47b8ff;
  --text: #eeeef4;
  --muted: #6b6b8a;
  --success: #47ff8a;
  --radius: 8px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
body::before { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at 20% 50%, rgba(232,255,71,0.03) 0%, transparent 60%), radial-gradient(ellipse at 80% 20%, rgba(71,184,255,0.04) 0%, transparent 50%); pointer-events: none; }

header { border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
.logo { font-family: 'Rajdhani', sans-serif; font-size: 1.4rem; font-weight: 700; letter-spacing: 2px; color: var(--accent); text-transform: uppercase; }
.user-info { display: flex; align-items: center; gap: 10px; font-size: 14px; }
.user-info img { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--border); }
.btn-steam { background: #1b2838; border: 1px solid #2a475e; color: #c7d5e0; padding: 8px 16px; border-radius: var(--radius); cursor: pointer; font-size: 13px; font-family: 'Inter', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-steam:hover { background: #2a3f55; }
.btn-logout { background: transparent; border: 1px solid var(--border); color: var(--muted); padding: 6px 12px; border-radius: var(--radius); cursor: pointer; font-size: 12px; font-family: 'Inter', sans-serif; }
.btn-logout:hover { border-color: var(--text); color: var(--text); }

main { max-width: 1100px; margin: 0 auto; padding: 2rem; }

.status-bar { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.status-label { font-family: 'Rajdhani', sans-serif; font-size: 1.1rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
.player-count { font-size: 13px; color: var(--muted); }
.player-count span { color: var(--accent); font-weight: 500; }
.progress-bar { height: 3px; background: var(--border); border-radius: 2px; margin-top: 8px; overflow: hidden; }
.progress-fill { height: 100%; background: var(--accent); border-radius: 2px; transition: width 0.3s; }

.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }

.team-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.team-header { padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); }
.team-name { font-family: 'Rajdhani', sans-serif; font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.team1 .team-name { color: var(--t1); }
.team2 .team-name { color: var(--t2); }
.team-count { font-size: 12px; color: var(--muted); }
.team-body { padding: 0.75rem; min-height: 180px; }
.player-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 6px; margin-bottom: 4px; }
.player-row:hover { background: var(--surface2); }
.player-row img { width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--border); }
.player-row .pname { font-size: 13px; flex: 1; }
.player-row .me-tag { font-size: 10px; color: var(--accent); font-weight: 600; letter-spacing: 1px; }
.empty-slot { padding: 8px 10px; border: 1px dashed var(--border); border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; }
.empty-slot span { font-size: 12px; color: var(--muted); }

.join-btn { width: 100%; padding: 10px; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; cursor: pointer; border: 1px solid; font-weight: 500; margin-top: 8px; transition: all 0.15s; }
.join-t1 { background: rgba(255,107,71,0.1); border-color: var(--t1); color: var(--t1); }
.join-t1:hover { background: rgba(255,107,71,0.2); }
.join-t2 { background: rgba(71,184,255,0.1); border-color: var(--t2); color: var(--t2); }
.join-t2:hover { background: rgba(71,184,255,0.2); }
.leave-btn { width: 100%; padding: 10px; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; cursor: pointer; border: 1px solid var(--border); color: var(--muted); background: transparent; margin-top: 8px; }
.leave-btn:hover { border-color: var(--text); color: var(--text); }

.config-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1rem; }
.config-title { font-family: 'Rajdhani', sans-serif; font-size: 0.85rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); margin-bottom: 1rem; }
.btn-group { display: flex; gap: 6px; margin-bottom: 1rem; }
.pill-btn { flex: 1; padding: 8px; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; cursor: pointer; border: 1px solid var(--border); color: var(--muted); background: transparent; transition: all 0.15s; }
.pill-btn.active { background: rgba(232,255,71,0.1); border-color: var(--accent); color: var(--accent); }
.pill-btn:hover:not(.active) { border-color: var(--text); color: var(--text); }
.map-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.map-pill { padding: 6px 8px; border-radius: 6px; font-size: 12px; font-family: 'Inter', sans-serif; cursor: pointer; border: 1px solid var(--border); color: var(--muted); background: transparent; text-align: center; transition: all 0.15s; }
.map-pill.active { background: rgba(232,255,71,0.08); border-color: var(--accent); color: var(--accent); }
.name-input { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; color: var(--text); font-size: 13px; font-family: 'Inter', sans-serif; margin-bottom: 8px; }
.name-input:focus { outline: none; border-color: var(--accent); }

.start-btn { width: 100%; padding: 14px; border-radius: var(--radius); font-size: 15px; font-family: 'Rajdhani', sans-serif; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; cursor: pointer; border: none; background: var(--accent); color: #0a0a0f; transition: all 0.15s; }
.start-btn:hover { background: #d4eb3a; transform: translateY(-1px); }
.start-btn:disabled { background: var(--border); color: var(--muted); cursor: not-allowed; transform: none; }

.notification { position: fixed; top: 1rem; right: 1rem; background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 16px; font-size: 13px; display: none; z-index: 100; }
.notification.show { display: block; animation: fadeIn 0.2s; }
.notification.ok { border-color: var(--success); color: var(--success); }
.notification.err { border-color: #ff4747; color: #ff4747; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

.section-label { font-size: 11px; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 0.5rem; }
</style>
</head>
<body>

<header>
  <div class="logo">⚡ Hoy Se Juega</div>
  <div class="user-info">
    <?php if ($loggedIn): ?>
      <img src="<?= htmlspecialchars($playerAvatar) ?>" alt="">
      <span><?= htmlspecialchars($playerName) ?></span>
      <a href="logout.php" class="btn-logout">Salir</a>
    <?php else: ?>
      <a href="steamauth/steamauth.php" class="btn-steam">
        <svg width="16" height="16" viewBox="0 0 32 32" fill="#c7d5e0"><path d="M16 0C7.163 0 0 7.163 0 16c0 7.833 5.635 14.337 13.13 15.664L16.5 24h-.5a8 8 0 110-16 8 8 0 018 8h-8l-3.5 8.5C14.18 24.83 15.083 25 16 25c4.97 0 9-4.03 9-9s-4.03-9-9-9z"/></svg>
        Iniciar sesión con Steam
      </a>
    <?php endif; ?>
  </div>
</header>

<main>
  <div class="status-bar">
    <div>
      <div class="status-label" id="status-label">
        <?= $totalPlayers < 10 ? 'Esperando jugadores...' : '¡Listo para jugar!' ?>
      </div>
      <div class="player-count"><span id="player-count"><?= $totalPlayers ?></span>/10 jugadores</div>
      <div class="progress-bar"><div class="progress-fill" id="progress-fill" style="width: <?= ($totalPlayers/10)*100 ?>%"></div></div>
    </div>
  </div>

  <div class="grid">
    <div class="team-card team1">
      <div class="team-header">
        <div class="team-name" id="t1-display"><?= htmlspecialchars($state['team1_name']) ?></div>
        <div class="team-count" id="t1-count"><?= count($team1) ?>/5</div>
      </div>
      <div class="team-body" id="t1-body">
        <?php foreach($team1 as $p): ?>
        <div class="player-row">
          <img src="<?= htmlspecialchars($p['avatar']) ?>" alt="">
          <span class="pname"><?= htmlspecialchars($p['name']) ?></span>
          <?php if($p['steamid'] == $steamid): ?><span class="me-tag">TÚ</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php for($i=count($team1); $i<5; $i++): ?>
        <div class="empty-slot"><span>Esperando...</span></div>
        <?php endfor; ?>
      </div>
      <?php if($loggedIn && $myTeam != 1): ?>
      <div style="padding: 0 0.75rem 0.75rem">
        <button class="join-btn join-t1" onclick="joinTeam(1)">Unirse al Equipo 1</button>
      </div>
      <?php endif; ?>
    </div>

    <div class="team-card team2">
      <div class="team-header">
        <div class="team-name" id="t2-display"><?= htmlspecialchars($state['team2_name']) ?></div>
        <div class="team-count" id="t2-count"><?= count($team2) ?>/5</div>
      </div>
      <div class="team-body" id="t2-body">
        <?php foreach($team2 as $p): ?>
        <div class="player-row">
          <img src="<?= htmlspecialchars($p['avatar']) ?>" alt="">
          <span class="pname"><?= htmlspecialchars($p['name']) ?></span>
          <?php if($p['steamid'] == $steamid): ?><span class="me-tag">TÚ</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php for($i=count($team2); $i<5; $i++): ?>
        <div class="empty-slot"><span>Esperando...</span></div>
        <?php endfor; ?>
      </div>
      <?php if($loggedIn && $myTeam != 2): ?>
      <div style="padding: 0 0.75rem 0.75rem">
        <button class="join-btn join-t2" onclick="joinTeam(2)">Unirse al Equipo 2</button>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if($loggedIn && $myTeam > 0): ?>
  <div style="text-align:center; margin-bottom: 1rem;">
    <button class="leave-btn" style="max-width:200px" onclick="leaveTeam()">Abandonar equipo</button>
  </div>
  <?php endif; ?>

  <div class="config-card">
    <div class="config-title">Configuración de la partida</div>

    <div class="section-label">Nombres de equipo</div>
    <input type="text" class="name-input" id="t1name" placeholder="Nombre Equipo 1" value="<?= htmlspecialchars($state['team1_name']) ?>" oninput="saveNames()">
    <input type="text" class="name-input" id="t2name" placeholder="Nombre Equipo 2" value="<?= htmlspecialchars($state['team2_name']) ?>" oninput="saveNames()">

    <div class="section-label" style="margin-top:1rem">Formato</div>
    <div class="btn-group">
      <button class="pill-btn <?= $state['bo']==1?'active':'' ?>" onclick="setBo(1,this)">BO1</button>
      <button class="pill-btn <?= $state['bo']==3?'active':'' ?>" onclick="setBo(3,this)">BO3</button>
    </div>

    <div class="section-label">Mapas para el veto</div>
    <div class="map-grid">
      <?php foreach($MAPS_ALL as $m): ?>
      <button class="map-pill <?= in_array($m,$maps)?'active':'' ?>" onclick="toggleMap('<?= $m ?>',this)"><?= str_replace('de_','',$m) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="section-label" style="margin-top:1rem">Primer veto</div>
    <div class="btn-group">
      <button class="pill-btn <?= $state['veto_first']=='team1'?'active':'' ?>" onclick="setVeto('team1',this)">Equipo 1</button>
      <button class="pill-btn <?= $state['veto_first']=='team2'?'active':'' ?>" onclick="setVeto('team2',this)">Equipo 2</button>
    </div>
  </div>

  <button class="start-btn" id="start-btn" onclick="startMatch()" <?= $totalPlayers < 10 ? 'disabled' : '' ?>>
    <?= $totalPlayers < 10 ? 'Esperando jugadores (' . $totalPlayers . '/10)' : '¡Iniciar Partida!' ?>
  </button>
</main>

<div class="notification" id="notif"></div>

<script>
let selectedMaps = <?= json_encode($maps) ?>;
let bo = <?= $state['bo'] ?>;
let vetoFirst = '<?= $state['veto_first'] ?>';
let nameTimer = null;

function notify(msg, type='ok') {
  const n = document.getElementById('notif');
  n.textContent = msg;
  n.className = 'notification show ' + type;
  setTimeout(() => n.className = 'notification', 3000);
}

function api(action, data={}) {
  return fetch('lobby_action.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action, ...data})
  }).then(r => r.json());
}

function joinTeam(team) {
  api('join', {team}).then(r => {
    if(r.success) { notify('Te uniste al equipo ' + team); refresh(); }
    else notify(r.error || 'Error', 'err');
  });
}

function leaveTeam() {
  api('leave').then(r => { if(r.success) { notify('Abandonaste el equipo'); refresh(); } });
}

function setBo(n, el) {
  bo = n;
  document.querySelectorAll('.btn-group .pill-btn').forEach(b => { if(b.closest('.btn-group') === el.closest('.btn-group')) b.classList.remove('active'); });
  el.classList.add('active');
  api('config', {bo, maplist: selectedMaps.join(','), veto_first: vetoFirst});
}

function toggleMap(map, btn) {
  const idx = selectedMaps.indexOf(map);
  if(idx > -1) selectedMaps.splice(idx, 1); else selectedMaps.push(map);
  btn.classList.toggle('active');
  api('config', {bo, maplist: selectedMaps.join(','), veto_first: vetoFirst});
}

function setVeto(team, el) {
  vetoFirst = team;
  document.querySelectorAll('.btn-group .pill-btn').forEach(b => { if(b.closest('.btn-group') === el.closest('.btn-group')) b.classList.remove('active'); });
  el.classList.add('active');
  api('config', {bo, maplist: selectedMaps.join(','), veto_first: vetoFirst});
}

function saveNames() {
  clearTimeout(nameTimer);
  nameTimer = setTimeout(() => {
    api('names', {team1_name: document.getElementById('t1name').value, team2_name: document.getElementById('t2name').value}).then(() => {
      document.getElementById('t1-display').textContent = document.getElementById('t1name').value;
      document.getElementById('t2-display').textContent = document.getElementById('t2name').value;
    });
  }, 800);
}

function startMatch() {
  document.getElementById('start-btn').disabled = true;
  document.getElementById('start-btn').textContent = 'Iniciando...';
  api('start').then(r => {
    if(r.success) notify('¡Partida iniciada! Conectate al servidor.');
    else { notify(r.error || 'Error al iniciar', 'err'); document.getElementById('start-btn').disabled = false; document.getElementById('start-btn').textContent = '¡Iniciar Partida!'; }
  });
}

function refresh() {
  fetch('lobby_action.php?action=state').then(r => r.json()).then(data => {
    const total = data.team1.length + data.team2.length;
    document.getElementById('player-count').textContent = total;
    document.getElementById('progress-fill').style.width = (total/10*100) + '%';
    document.getElementById('status-label').textContent = total < 10 ? 'Esperando jugadores...' : '¡Listo para jugar!';
    document.getElementById('t1-count').textContent = data.team1.length + '/5';
    document.getElementById('t2-count').textContent = data.team2.length + '/5';

    const startBtn = document.getElementById('start-btn');
    startBtn.disabled = total < 10;
    startBtn.textContent = total < 10 ? 'Esperando jugadores (' + total + '/10)' : '¡Iniciar Partida!';

    renderTeam('t1-body', data.team1, '<?= $steamid ?>');
    renderTeam('t2-body', data.team2, '<?= $steamid ?>');
  });
}

function renderTeam(id, players, mySteamid) {
  const body = document.getElementById(id);
  let html = '';
  players.forEach(p => {
    html += '<div class="player-row"><img src="'+p.avatar+'" alt=""><span class="pname">'+p.name+'</span>'+(p.steamid==mySteamid?'<span class="me-tag">TÚ</span>':'')+'</div>';
  });
  for(let i=players.length; i<5; i++) html += '<div class="empty-slot"><span>Esperando...</span></div>';
  body.innerHTML = html;
}

setInterval(refresh, 4000);
</script>
</body>
</html>
