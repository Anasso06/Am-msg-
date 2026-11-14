<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const STORAGE_DIR = __DIR__ . '/storage';
const USERS_FILE = STORAGE_DIR . '/users.json';
const CHATS_DIR = STORAGE_DIR . '/conversations';

function respond(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function ensure_storage(): void {
    if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0700, true) && !is_dir(STORAGE_DIR)) respond(['ok'=>false,'error'=>'Impossible de créer le stockage.'],500);
    if (!is_dir(CHATS_DIR) && !mkdir(CHATS_DIR, 0700, true) && !is_dir(CHATS_DIR)) respond(['ok'=>false,'error'=>'Impossible de créer le dossier des conversations.'],500);
    if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, "{}");
}
function read_json(string $path, array $fallback=[]): array {
    if (!is_file($path)) return $fallback;
    $raw = file_get_contents($path);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $fallback;
}
function write_json(string $path, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $fp = fopen($path, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX); ftruncate($fp, 0); rewind($fp);
    $ok = fwrite($fp, $json) !== false; fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return $ok;
}
function username(string $value): string {
    return strtolower(trim($value));
}
function valid_user(string $name): bool {
    return (bool)preg_match('/^[a-z0-9_]{3,30}$/', $name);
}
function require_user(): string {
    if (empty($_SESSION['user'])) respond(['ok'=>false,'error'=>'Connecte-toi pour continuer.'],401);
    return (string)$_SESSION['user'];
}
function chat_key(string $a,string $b): string {
    $names=[$a,$b]; sort($names,SORT_STRING);
    return hash('sha256',$names[0].'|'.$names[1]);
}
ensure_storage();
$input=json_decode(file_get_contents('php://input') ?: '{}',true);
if (!is_array($input)) $input=[];
$action=$_GET['action'] ?? '';

if ($action==='status') respond(['ok'=>true,'message'=>'AM MSG API opérationnelle']);
if ($action==='register') {
    $name=username((string)($input['username']??'')); $password=(string)($input['password']??'');
    if (!valid_user($name)) respond(['ok'=>false,'error'=>'Nom : 3 à 30 caractères, lettres sans accent, chiffres ou _.'],422);
    if (strlen($password)<8) respond(['ok'=>false,'error'=>'Le mot de passe doit contenir au moins 8 caractères.'],422);
    $users=read_json(USERS_FILE);
    if (isset($users[$name])) respond(['ok'=>false,'error'=>'Ce nom est déjà utilisé.'],409);
    $users[$name]=['password'=>password_hash($password,PASSWORD_DEFAULT),'created_at'=>date('c')];
    if (!write_json(USERS_FILE,$users)) respond(['ok'=>false,'error'=>'Impossible d’enregistrer le compte.'],500);
    session_regenerate_id(true); $_SESSION['user']=$name; respond(['ok'=>true,'user'=>$name]);
}
if ($action==='login') {
    $name=username((string)($input['username']??'')); $password=(string)($input['password']??'');
    $users=read_json(USERS_FILE);
    if (!isset($users[$name]) || !password_verify($password,$users[$name]['password']??'')) respond(['ok'=>false,'error'=>'Identifiants incorrects.'],401);
    session_regenerate_id(true); $_SESSION['user']=$name; respond(['ok'=>true,'user'=>$name]);
}
if ($action==='me') respond(['ok'=>true,'user'=>require_user()]);
if ($action==='logout') { $_SESSION=[]; session_destroy(); respond(['ok'=>true]); }

$me=require_user();
if ($action==='chats') {
    $result=[];
    foreach (glob(CHATS_DIR.'/*.json') ?: [] as $file) {
        $data=read_json($file);
        if (in_array($me,$data['participants']??[],true)) {
            foreach ($data['participants'] as $person) if ($person!==$me) $result[]=$person;
        }
    }
    $result=array_values(array_unique($result)); sort($result);
    respond(['ok'=>true,'chats'=>$result]);
}
if ($action==='start_chat' || $action==='messages' || $action==='send') {
    $peer=username((string)($input['peer']??''));
    if (!valid_user($peer) || $peer===$me) respond(['ok'=>false,'error'=>'Nom d’utilisateur invalide.'],422);
    $users=read_json(USERS_FILE);
    if (!isset($users[$peer])) respond(['ok'=>false,'error'=>'Cet utilisateur n’existe pas encore.'],404);
    $path=CHATS_DIR.'/'.chat_key($me,$peer).'.json';
    $chat=read_json($path,['participants'=>[$me,$peer],'messages'=>[]]);
    if ($action==='start_chat') {
        if (!write_json($path,$chat)) respond(['ok'=>false,'error'=>'Erreur de stockage.'],500);
        respond(['ok'=>true]);
    }
    if ($action==='messages') respond(['ok'=>true,'messages'=>$chat['messages']??[]]);
    $message=trim((string)($input['text']??''));
    if ($message==='' || mb_strlen($message)>4000) respond(['ok'=>false,'error'=>'Message vide ou trop long (4 000 caractères maximum).'],422);
    $chat['messages'][]=['from'=>$me,'to'=>$peer,'text'=>$message,'time'=>date('Y-m-d H:i')];
    if (!write_json($path,$chat)) respond(['ok'=>false,'error'=>'Impossible d’enregistrer le message.'],500);
    respond(['ok'=>true]);
}
respond(['ok'=>false,'error'=>'Action inconnue.'],404);
