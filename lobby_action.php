<?php
session_start();
require_once __DIR__ . '/class/config.php';

header('Content-Type: application/json');

$pdo = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);

$steamid = $_SESSION['steamid'] ?? null;

// GET state
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'state') {
    $team1 = $pdo->query("SELECT * FROM lobby_players WHERE team=1 ORDER BY joined_at")->fetchAll(PDO::FETCH_ASSOC);
    $team2 = $pdo->query("SELECT * FROM lobby_players WHERE team=2 ORDER BY joined_at")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['team1' => $team1, 'team2' => $team2]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if (!$steamid && in_array($action, ['join','leave','start'])) {
    echo json_encode(['success' => false, 'error' => 'No iniciaste sesión']);
    exit;
}

switch ($action) {

    case 'join':
        $team = (int)($data['team'] ?? 0);
        if ($team < 1 || $team > 2) { echo json_encode(['success'=>false,'error'=>'Equipo inválido']); exit; }
        $count = $pdo->query("SELECT COUNT(*) FROM lobby_players WHERE team=$team")->fetchColumn();
        if ($count >= 5) { echo json_encode(['success'=>false,'error'=>'El equipo está lleno']); exit; }
        $stmt = $pdo->prepare("UPDATE lobby_players SET team=? WHERE steamid=?");
        $stmt->execute([$team, $steamid]);
        echo json_encode(['success'=>true]);
        break;

    case 'leave':
        $stmt = $pdo->prepare("UPDATE lobby_players SET team=0 WHERE steamid=?");
        $stmt->execute([$steamid]);
        echo json_encode(['success'=>true]);
        break;

    case 'config':
        $bo = (int)($data['bo'] ?? 1);
        $maplist = $data['maplist'] ?? 'de_dust2,de_mirage,de_inferno,de_nuke,de_ancient,de_anubis,de_vertigo';
        $veto_first = in_array($data['veto_first'] ?? '', ['team1','team2']) ? $data['veto_first'] : 'team1';
        $stmt = $pdo->prepare("UPDATE lobby_state SET bo=?, maplist=?, veto_first=? WHERE id=1");
        $stmt->execute([$bo, $maplist, $veto_first]);
        echo json_encode(['success'=>true]);
        break;

    case 'names':
        $t1 = substr($data['team1_name'] ?? 'Team 1', 0, 64);
        $t2 = substr($data['team2_name'] ?? 'Team 2', 0, 64);
        $stmt = $pdo->prepare("UPDATE lobby_state SET team1_name=?, team2_name=? WHERE id=1");
        $stmt->execute([$t1, $t2]);
        echo json_encode(['success'=>true]);
        break;

    case 'start':
        $t1count = $pdo->query("SELECT COUNT(*) FROM lobby_players WHERE team=1")->fetchColumn();
        $t2count = $pdo->query("SELECT COUNT(*) FROM lobby_players WHERE team=2")->fetchColumn();
        if ($t1count < 5 || $t2count < 5) {
            echo json_encode(['success'=>false,'error'=>'Faltan jugadores']);
            exit;
        }

        $state = $pdo->query("SELECT * FROM lobby_state WHERE id=1")->fetch(PDO::FETCH_ASSOC);
        $team1players = $pdo->query("SELECT * FROM lobby_players WHERE team=1")->fetchAll(PDO::FETCH_ASSOC);
        $team2players = $pdo->query("SELECT * FROM lobby_players WHERE team=2")->fetchAll(PDO::FETCH_ASSOC);

        $t1obj = new stdClass();
        foreach ($team1players as $p) $t1obj->{$p['steamid']} = $p['name'];
        $t2obj = new stdClass();
        foreach ($team2players as $p) $t2obj->{$p['steamid']} = $p['name'];

        $matchConfig = [
            'matchid' => '1',
            'num_maps' => (int)$state['bo'],
            'maplist' => explode(',', $state['maplist']),
            'skip_veto' => false,
            'veto_first' => $state['veto_first'],
            'team1' => ['name' => $state['team1_name'], 'tag' => 'T1', 'players' => $t1obj],
            'team2' => ['name' => $state['team2_name'], 'tag' => 'T2', 'players' => $t2obj],
        ];

        file_put_contents(__DIR__ . '/match.json', json_encode($matchConfig, JSON_PRETTY_PRINT));

        // Send RCON command
        $rconHost = getenv('RCON_HOST') ?: '45.235.98.222';
        $rconPort = (int)(getenv('RCON_PORT') ?: 27287);
        $rconPass = getenv('RCON_PASS') ?: '';
        $webUrl = 'https://hoy-se-juega.onrender.com/match.json';

        $rconResult = sendRcon($rconHost, $rconPort, $rconPass, "matchzy_loadmatch_url \"$webUrl\"");

        if ($rconResult !== false) {
            // Clear lobby
            $pdo->exec("UPDATE lobby_players SET team=0");
            echo json_encode(['success'=>true, 'rcon' => $rconResult]);
        } else {
            echo json_encode(['success'=>false, 'error'=>'Error al conectar via RCON. Verificá la contraseña y el puerto.']);
        }
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Acción desconocida']);
}

function sendRcon($host, $port, $password, $command) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$socket) return false;

    stream_set_timeout($socket, 5);

    function rconPacket($id, $type, $body) {
        $body .= "\x00\x00";
        $size = strlen($body) + 8;
        return pack('VVV', $size, $id, $type) . $body;
    }

    // Auth
    fwrite($socket, rconPacket(1, 3, $password));
    $authResp = fread($socket, 4096);
    if (!$authResp) { fclose($socket); return false; }

    // Command
    fwrite($socket, rconPacket(2, 2, $command));
    $resp = fread($socket, 4096);
    fclose($socket);

    if (!$resp || strlen($resp) < 12) return '';
    return substr($resp, 12, -2);
}
?>
