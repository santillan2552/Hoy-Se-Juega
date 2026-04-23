<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        file_put_contents(__DIR__ . '/match.json', json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generador de Partida - Hoy Se Juega</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #111; color: #eee; padding: 2rem; }
h1 { font-size: 1.4rem; margin-bottom: 1.5rem; color: #fff; }
h2 { font-size: 1rem; margin-bottom: 0.75rem; color: #aaa; font-weight: normal; }
.section { margin-bottom: 1.5rem; }
.card { background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
input[type="text"] { background: #222; border: 1px solid #444; border-radius: 6px; padding: 8px 12px; color: #eee; font-size: 14px; width: 100%; margin-bottom: 8px; }
input[type="text"]:focus { outline: none; border-color: #5b8ee6; }
.row { display: flex; gap: 8px; }
.row input { margin-bottom: 0; }
.btn-group { display: flex; gap: 8px; }
.btn { flex: 1; padding: 10px; border-radius: 6px; background: #222; border: 1px solid #444; color: #eee; font-size: 14px; cursor: pointer; text-align: center; }
.btn.selected { background: #1a3a6b; border-color: #5b8ee6; color: #7eb3ff; font-weight: bold; }
.btn:hover { background: #2a2a2a; }
.map-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; }
.map-btn { background: #222; border: 1px solid #444; border-radius: 6px; padding: 8px 12px; font-size: 13px; cursor: pointer; color: #eee; text-align: left; }
.map-btn.selected { background: #1a3a6b; border-color: #5b8ee6; color: #7eb3ff; }
.warn { font-size: 12px; color: #e6a817; margin-top: 6px; min-height: 16px; }
.save-btn { width: 100%; padding: 12px; border-radius: 8px; background: #1a3a6b; border: 1px solid #5b8ee6; color: #7eb3ff; font-size: 15px; cursor: pointer; margin-top: 0.5rem; font-weight: bold; }
.save-btn:hover { background: #1e4a8a; }
.output { background: #0d0d0d; border-radius: 6px; padding: 1rem; font-family: monospace; font-size: 12px; color: #7eb3ff; white-space: pre; overflow-x: auto; margin-top: 1rem; display: none; }
.status { margin-top: 0.75rem; font-size: 13px; text-align: center; min-height: 20px; }
.status.ok { color: #4caf50; }
.status.err { color: #e55; }
.command { background: #0d0d0d; border-radius: 6px; padding: 0.75rem 1rem; font-family: monospace; font-size: 13px; color: #aaa; margin-top: 1rem; display: none; }
.command span { color: #7eb3ff; }
</style>
</head>
<body>

<h1>Generador de Partida</h1>

<div class="section">
  <h2>Formato</h2>
  <div class="btn-group">
    <button class="btn selected" onclick="setBo(1, this)">BO1</button>
    <button class="btn" onclick="setBo(3, this)">BO3</button>
  </div>
</div>

<div class="section">
  <h2>Mapas para el veto</h2>
  <div class="map-grid" id="map-grid"></div>
  <p class="warn" id="map-warn"></p>
</div>

<div class="section">
  <h2>Equipo 1</h2>
  <div class="card">
    <div class="row">
      <input type="text" id="t1name" placeholder="Nombre" value="Team 1">
      <input type="text" id="t1tag" placeholder="Tag" value="T1" style="max-width:100px">
    </div>
  </div>
</div>

<div class="section">
  <h2>Equipo 2</h2>
  <div class="card">
    <div class="row">
      <input type="text" id="t2name" placeholder="Nombre" value="Team 2">
      <input type="text" id="t2tag" placeholder="Tag" value="T2" style="max-width:100px">
    </div>
  </div>
</div>

<div class="section">
  <h2>Quién veta primero</h2>
  <div class="btn-group">
    <button class="btn selected" id="vf1" onclick="setVetoFirst('team1')">Equipo 1</button>
    <button class="btn" id="vf2" onclick="setVetoFirst('team2')">Equipo 2</button>
  </div>
</div>

<button class="save-btn" onclick="save()">Guardar match.json en el servidor</button>
<div class="status" id="status"></div>
<div class="output" id="output"></div>
<div class="command" id="command">
  Comando para el servidor:<br><br>
  <span>matchzy_loadmatch_url https://hoy-se-juega.onrender.com/match.json</span>
</div>

<script>
const MAPS = ['de_dust2','de_mirage','de_inferno','de_nuke','de_ancient','de_anubis','de_vertigo','de_overpass'];
let selectedMaps = new Set(['de_dust2','de_mirage','de_inferno','de_nuke','de_ancient','de_anubis','de_vertigo']);
let bo = 1;
let vetoFirst = 'team1';

const grid = document.getElementById('map-grid');
MAPS.forEach(m => {
  const btn = document.createElement('button');
  btn.className = 'map-btn' + (selectedMaps.has(m) ? ' selected' : '');
  btn.textContent = m.replace('de_', '');
  btn.onclick = () => { selectedMaps.has(m) ? selectedMaps.delete(m) : selectedMaps.add(m); btn.classList.toggle('selected'); checkWarn(); };
  grid.appendChild(btn);
});

function checkWarn() {
  document.getElementById('map-warn').textContent = selectedMaps.size < 7 ? `Se recomiendan al menos 7 mapas. Tenés ${selectedMaps.size}.` : '';
}

function setBo(n, el) {
  bo = n;
  el.closest('.btn-group').querySelectorAll('.btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  checkWarn();
}

function setVetoFirst(team) {
  vetoFirst = team;
  document.getElementById('vf1').classList.toggle('selected', team === 'team1');
  document.getElementById('vf2').classList.toggle('selected', team === 'team2');
}

function save() {
  const config = {
    matchid: Date.now().toString(),
    num_maps: bo,
    maplist: Array.from(selectedMaps),
    skip_veto: false,
    veto_first: vetoFirst,
    team1: { name: document.getElementById('t1name').value || 'Team 1', tag: document.getElementById('t1tag').value || 'T1', players: {} },
    team2: { name: document.getElementById('t2name').value || 'Team 2', tag: document.getElementById('t2tag').value || 'T2', players: {} }
  };

  const status = document.getElementById('status');
  status.textContent = 'Guardando...';
  status.className = 'status';

  fetch('match.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(config)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      status.textContent = 'match.json guardado correctamente!';
      status.className = 'status ok';
      const out = document.getElementById('output');
      out.textContent = JSON.stringify(config, null, 2);
      out.style.display = 'block';
      document.getElementById('command').style.display = 'block';
    } else {
      status.textContent = 'Error: ' + (data.error || 'desconocido');
      status.className = 'status err';
    }
  })
  .catch(() => {
    status.textContent = 'Error al guardar. Intentá de nuevo.';
    status.className = 'status err';
  });
}
</script>
</body>
</html>
