<?php
/* =========================================================================
 * Stripe MRR Dashboard — Password Protected + Streaming Preloader
 * Developed by weezly.com
 * Login: username = weezly, password = rocks
 * - Session cookie lifetime: 2 months
 * - Settings (gear): set Goal MRR + toggle Show Trials (persist in session)
 * - Streaming pre-loader with logo: sent BEFORE heavy Stripe/FX work
 * ========================================================================= */

/////////////////////////////
// AUTH CONFIG + SESSION
/////////////////////////////
$AUTH_USER = 'weezly';
$AUTH_PASS = 'rocks';

// 2 months cookie lifetime
$lifetime = 60 * 60 * 24 * 60; // 60 days
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
  'lifetime' => $lifetime,
  'path'     => $cookieParams['path'] ?? '/',
  'domain'   => $cookieParams['domain'] ?? '',
  'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_name('mrrdash');
session_start();

// Initialize settings defaults (persisted in session)
if (!isset($_SESSION['show_trials'])) $_SESSION['show_trials'] = true;
if (!isset($_SESSION['goal_mrr']))    $_SESSION['goal_mrr']    = 150000.0; // default SEK goal

// Quick helpers for reading settings
$SHOW_TRIALS = (bool)($_SESSION['show_trials'] ?? true);
$GOAL_MRR    = (float)($_SESSION['goal_mrr'] ?? 150000.0);

// Handle logout
if (isset($_GET['logout'])) {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

// Handle login attempt
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
  $u = isset($_POST['username']) ? trim($_POST['username']) : '';
  $p = isset($_POST['password']) ? (string)$_POST['password'] : '';
  $ok = hash_equals($AUTH_USER, $u) && hash_equals($AUTH_PASS, $p);
  if ($ok) {
    $_SESSION['logged_in'] = true;
    $_SESSION['logged_at'] = time();
    setcookie(session_name(), session_id(), time() + $lifetime, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  } else {
    $login_error = 'Invalid username or password.';
  }
}

// Handle Settings save (gear)
if (!empty($_SESSION['logged_in']) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
  $_SESSION['show_trials'] = isset($_POST['show_trials']) && $_POST['show_trials'] === '1';
  if (isset($_POST['goal_mrr'])) {
    $val = preg_replace('/[^\d\.\,]/', '', (string)$_POST['goal_mrr']);
    $val = str_replace(',', '.', $val);
    $num = (float)$val;
    $_SESSION['goal_mrr'] = max(0.0, $num);
  }
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

// If not logged in, show login form and stop (no streaming needed)
if (empty($_SESSION['logged_in'])): ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>MRR Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root {--bg:#0b1220;--card:#0f172a;--muted:#94a3b8;--text:#e2e8f0;--accent:#22d3ee;--radius:16px;}
  *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font:500 16px/1.4 system-ui}
  .card{width:min(420px,92vw);background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius);padding:24px 22px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
  h1{margin:0 0 12px;font-size:20px;color:#cbd5e1}
  .muted{color:var(--muted);margin-bottom:18px}
  .row{display:grid;gap:8px;margin-bottom:14px}
  label{font-size:13px;color:#b6c2d3}
  input{width:100%;padding:12px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0f172a;color:#e2e8f0;outline:none}
  button{width:100%;padding:12px;border-radius:12px;border:0;background:linear-gradient(90deg,#22d3ee,#34d399);color:#0b1220;font-weight:800;cursor:pointer}
  .err{background:#2b1a1a;border:1px solid #ef4444;color:#fecaca;padding:10px 12px;border-radius:12px;margin-bottom:12px}
  .hint{color:#96a3b8;font-size:12px;margin-top:8px}
</style>
</head>
<body>
  <form class="card" method="post" action="">
    <h1>Sign in</h1>
    <div class="muted">Enter your credentials to access the dashboard.</div>
    <?php if ($login_error): ?><div class="err"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
    <input type="hidden" name="action" value="login" />
    <div class="row">
      <label for="username">Username</label>
      <input id="username" name="username" autocomplete="username" required />
    </div>
    <div class="row">
      <label for="password">Password</label>
      <input id="password" type="password" name="password" autocomplete="current-password" required />
    </div>
    <button type="submit">Login</button>
    <div class="hint">Session lasts 2 months or until you log out.</div>
  </form>
</body>
</html>
<?php
exit; // stop here if not logged in
endif;

/* ================== LOGGED IN: STREAM PRELOADER BEFORE HEAVY WORK ================== */

// Send minimal page shell + preloader immediately, then flush
if (!headers_sent()) {
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-transform'); // reduce proxy buffering
}
while (ob_get_level()) { @ob_end_flush(); }
@ob_implicit_flush(true);

// Minimal HTML shell with preloader (logo + spinner)
echo '<!doctype html><html lang="sv"><head><meta charset="utf-8" />'
   . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
   . '<title>Loading…</title>'
   . '<style>'
   . 'body{margin:0;background:#0b1220;color:#e2e8f0;font:500 16px/1.5 system-ui}'
   . '.loader{position:fixed;inset:0;background:rgba(11,18,32,.96);display:flex;align-items:center;justify-content:center;z-index:1000}'
   . '.loader-inner{display:flex;flex-direction:column;align-items:center;gap:16px;transform:translateY(-20px)}'
   . '.loader-logo{height:36px;width:auto;opacity:.95}'
   . '.spinner{width:56px;height:56px;border-radius:50%;border:6px solid rgba(255,255,255,.15);border-top-color:#22d3ee;animation:spin .9s linear infinite}'
   . '.loader-text{color:#cbd5e1;font-size:13px}'
   . '@keyframes spin{to{transform:rotate(360deg)}}'
   . '</style></head><body>'
   . '<div id="preloader" class="loader"><div class="loader-inner">'
   . '<img class="loader-logo" src="https://weezly.com/wp-content/uploads/2025/07/weezly_logo_white_new_new.png" alt="Weezly logo" />'
   . '<div class="spinner"></div><div class="loader-text">Loading dashboard…</div>'
   . '</div></div>';
// send enough bytes to trigger chunked transfer on some hosts
echo str_repeat(" ", 4096);
@flush();

/* ================== HEAVY WORK HAPPENS NOW (Stripe/FX) ================== */

$STRIPE_API_KEY = 'sk_live_code'; // <-- your full LIVE key
$DASH_CURRENCY  = 'sek'; // <-- set your currency here

// ---------- FX fetching with cache ----------
function fx_fetch_json($url, $timeout = 2){
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_USERAGENT      => 'mrr-dashboard/1.5'
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($resp === false || $code >= 400) return [null, $err ?: ('HTTP '.$code)];
  $json = json_decode($resp, true);
  if (!is_array($json)) return [null, 'Invalid JSON'];
  return [$json, null];
}
function fx_get_usd_eur_to_sek(&$fx_source_note){
  $cache_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mrr_fx_cache.json';
  $cache_ttl  = 6 * 3600;
  $now        = time();
  $usd = null; $eur = null;

  list($j1,) = fx_fetch_json('https://api.frankfurter.app/latest?from=USD&to=SEK', 2);
  if ($j1 && isset($j1['rates']['SEK'])) $usd = (float)$j1['rates']['SEK'];
  list($j2,) = fx_fetch_json('https://api.frankfurter.app/latest?from=EUR&to=SEK', 2);
  if ($j2 && isset($j2['rates']['SEK'])) $eur = (float)$j2['rates']['SEK'];

  if ($usd && $eur) {
    $fx_source_note = 'live: frankfurter.app';
    @file_put_contents($cache_file, json_encode(['ts'=>$now,'usd'=>$usd,'eur'=>$eur]));
    return ['sek'=>1.0, 'usd'=>$usd, 'eur'=>$eur];
  }

  if (is_file($cache_file)) {
    $raw = @file_get_contents($cache_file);
    $data = $raw ? json_decode($raw, true) : null;
    if (is_array($data) && isset($data['ts'],$data['usd'],$data['eur']) && ($now - (int)$data['ts']) <= $cache_ttl) {
      $fx_source_note = 'cache (≤6h)';
      return ['sek'=>1.0, 'usd'=>(float)$data['usd'], 'eur'=>(float)$data['eur']];
    }
  }

  $fx_source_note = 'fallback (hardcoded)';
  return ['sek'=>1.0, 'usd'=>11.20, 'eur'=>11.80];
}

// ---------- Stripe + compute helpers ----------
function key_mode($k){ if(strpos($k,'sk_live_')===0||strpos($k,'rk_live_')===0)return 'live'; if(strpos($k,'sk_test_')===0||strpos($k,'rk_test_')===0)return 'test'; return 'unknown'; }
function mask_key($k){ $n=strlen($k); return $n<=10?str_repeat('•',max(0,$n-4)).substr($k,-4):substr($k,0,10).'…'.substr($k,-4); }
function stripe_get($endpoint, $params = [], $expands = []) {
  global $STRIPE_API_KEY;
  $qs=[];
  foreach($params as $k=>$v){ $qs[] = urlencode($k).'='.urlencode(is_bool($v)?($v?'true':'false'):$v); }
  foreach($expands as $e){ $qs[]='expand[]='.urlencode($e); }
  $url='https://api.stripe.com/v1/'.ltrim($endpoint,'/');
  if($qs) $url.='?'.implode('&',$qs);
  $ch=curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL=>$url,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$STRIPE_API_KEY],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>45,
  ]);
  $raw=curl_exec($ch);
  if($raw===false){ $err=curl_error($ch); curl_close($ch); die('Stripe cURL error: '.$err); }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  $data=json_decode($raw,true);
  if($code>=400){ $msg=$data['error']['message']??('HTTP '.$code); die('Stripe API error: '.$msg); }
  return $data;
}
function zero_decimal_currencies(){ return ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf']; }
function decimals_for($code){ return in_array(strtolower($code), zero_decimal_currencies(), true) ? 0 : 2; }
function interval_to_month_factor($interval,$count){
  $c=max(1,(int)$count);
  switch($interval){
    case 'month': return 1.0/$c;
    case 'year' : return 1.0/(12.0*$c);
    case 'week' : return (52.0/12.0)/$c;
    case 'day'  : return (365.2425/12.0)/$c;
    default     : return 0.0;
  }
}
function get_price_full_cached($id){
  static $cache=[]; if(!$id) return null;
  if(isset($cache[$id])) return $cache[$id];
  $cache[$id] = stripe_get('prices/'.urlencode($id), [], ['tiers']);
  return $cache[$id];
}
function tier_amounts_from_row($t){
  $ua = isset($t['unit_amount_decimal']) && $t['unit_amount_decimal']!=='' ? (float)$t['unit_amount_decimal'] : (isset($t['unit_amount'])?(float)$t['unit_amount']:0.0);
  $fa = isset($t['flat_amount_decimal']) && $t['flat_amount_decimal']!=='' ? (float)$t['flat_amount_decimal'] : (isset($t['flat_amount'])?(float)$t['flat_amount']:0.0);
  return [$ua,$fa];
}
function compute_tiered_interval_minor($price, $qty){
  $qty = max(0,(int)$qty);
  $mode  = $price['tiers_mode'] ?? 'graduated';
  $tiers = $price['tiers'] ?? null;
  if(!$tiers){
    $full=get_price_full_cached($price['id'] ?? '');
    $tiers=$full['tiers'] ?? [];
    if(!$mode && $full) $mode=$full['tiers_mode'] ?? 'graduated';
  }
  if(!$tiers) return 0.0;
  $sum=0.0;
  if ($mode === 'volume') {
    $chosen=null;
    foreach($tiers as $t){
      $lim = ($t['up_to'] ?? null) === 'inf' ? PHP_INT_MAX : (int)($t['up_to'] ?? PHP_INT_MAX);
      if ($qty <= $lim){ $chosen=$t; break; }
    }
    if(!$chosen) $chosen=end($tiers);
    list($ua,$fa)=tier_amounts_from_row($chosen);
    $sum = $qty * $ua + ($qty>0 ? $fa : 0);
  } else {
    $prev=0;
    foreach($tiers as $t){
      $lim = ($t['up_to'] ?? null) === 'inf' ? PHP_INT_MAX : (int)($t['up_to'] ?? PHP_INT_MAX);
      $units = max(min($qty,$lim)-$prev,0);
      if($units>0){ list($ua,$fa)=tier_amounts_from_row($t); $sum += $units*$ua + ($fa>0?$fa:0); }
      $prev=$lim;
      if($prev >= $qty) break;
    }
  }
  return $sum;
}
function item_monthly_major_native($item){
  $price = $item['price'] ?? null;
  if(!$price || !isset($price['recurring'])) return 0.0;
  $qty = max(1,(int)($item['quantity'] ?? 1));
  $rec = $price['recurring'];
  $factor = interval_to_month_factor($rec['interval'] ?? 'month', $rec['interval_count'] ?? 1);
  if (($price['billing_scheme'] ?? 'per_unit') === 'tiered') {
    $minor = compute_tiered_interval_minor($price, $qty) * $factor;
  } else {
    if (isset($price['unit_amount_decimal']) && $price['unit_amount_decimal']!=='') { $ua = (float)$price['unit_amount_decimal']; }
    elseif (isset($price['unit_amount'])) { $ua = (float)$price['unit_amount']; }
    else { $ua = 0.0; }
    $minor = $ua * $qty * $factor;
  }
  $cur = strtolower($price['currency'] ?? 'usd');
  return $minor / pow(10, decimals_for($cur));
}
function to_sek($amountMajor, $currencyLower, $fxMap){
  $rate = $fxMap[strtolower($currencyLower)] ?? 0.0;
  return $rate > 0 ? $amountMajor * $rate : 0.0;
}
function compute_current_mrr_sek($fxMap){
  $totalSEK = 0.0; $starting_after = null;
  do {
    $params = ['status'=>'all','limit'=>100];
    $expands=['data.items.data.price'];
    if ($starting_after) $params['starting_after'] = $starting_after;
    $resp = stripe_get('subscriptions', $params, $expands);
    $subs = $resp['data'] ?? [];
    foreach ($subs as $sub) {
      if (!in_array($sub['status'], ['active','past_due'])) continue;
      foreach (($sub['items']['data'] ?? []) as $item) {
        $price = $item['price'] ?? [];
        if (!isset($price['recurring'])) continue;
        $nativeMajor = item_monthly_major_native($item);
        $cur = strtolower($price['currency'] ?? 'usd');
        $totalSEK += to_sek($nativeMajor, $cur, $fxMap);
      }
    }
    $starting_after = !empty($resp['has_more']) ? ($subs ? end($subs)['id'] : null) : null;
  } while ($starting_after);
  return $totalSEK;
}
function fetch_trialing_rows($fxMap){
  $rows = []; $starting_after = null;
  do {
    $params=['status'=>'trialing','limit'=>100];
    $expands=['data.customer','data.items.data.price'];
    if ($starting_after) $params['starting_after']=$starting_after;
    $resp = stripe_get('subscriptions',$params,$expands);
    $subs = $resp['data'] ?? [];
    foreach ($subs as $sub) {
      $cust = $sub['customer'] ?? [];
      $name = is_array($cust) ? ($cust['name'] ?? '') : '';
      $email= is_array($cust) ? ($cust['email'] ?? '') : '';
      $trialEndISO = isset($sub['trial_end']) && $sub['trial_end'] ? gmdate('Y-m-d',$sub['trial_end']) : '—';
      $sumSEK = 0.0;
      foreach (($sub['items']['data'] ?? []) as $item) {
        $price = $item['price'] ?? [];
        if (!isset($price['recurring'])) continue;
        $nativeMajor = item_monthly_major_native($item);
        $cur = strtolower($price['currency'] ?? 'usd');
        $sumSEK += to_sek($nativeMajor, $cur, $fxMap);
      }
      $rows[] = [
        'customer' => $name ?: ($email ?: '—'),
        'email'    => $email ?: '—',
        'trial_end'=> $trialEndISO,
        'mrr_sek'  => $sumSEK,
      ];
    }
    $starting_after = !empty($resp['has_more']) ? ($subs ? end($subs)['id'] : null) : null;
  } while ($starting_after);
  return $rows;
}
function fmt_money($amountMajor, $currency='SEK'){
  if (class_exists('NumberFormatter')) { $fmt=numfmt_create('sv_SE', NumberFormatter::CURRENCY); return numfmt_format_currency($fmt,$amountMajor,strtoupper($currency)); }
  return strtoupper($currency).' '.number_format($amountMajor,2,'.',',');
}

// ---------- MAIN COMPUTE ----------
$FX_SOURCE = '';
$FX = fx_get_usd_eur_to_sek($FX_SOURCE);
$MODE = key_mode($STRIPE_API_KEY);
$MASK = mask_key($STRIPE_API_KEY);

$acct = stripe_get('account');
$acct_id = $acct['id'] ?? 'unknown';

$current_mrr_sek = compute_current_mrr_sek($FX);
$current_arr_sek = $current_mrr_sek * 12;

if ($SHOW_TRIALS) {
  $trial_rows      = fetch_trialing_rows($FX);
  $trial_total_sek = array_sum(array_map(function($r){ return $r['mrr_sek']; }, $trial_rows));
} else {
  $trial_rows = [];
  $trial_total_sek = 0.0;
}

$left_to_goal            = max(0.0, $_SESSION['goal_mrr'] - $current_mrr_sek);
$potential_mrr_sek       = $current_mrr_sek + $trial_total_sek;
$potential_arr_sek       = $potential_mrr_sek * 12;
$potential_left_to_goal  = max(0.0, $left_to_goal - $trial_total_sek);

/* ================== OUTPUT THE REAL PAGE, REMOVE PRELOADER ================== */

// Full page styles and markup
?>
<style>
  :root {--bg:#0b1220;--card:#0f172a;--muted:#94a3b8;--text:#e2e8f0;--accent:#22d3ee;--accent-2:#34d399;--danger:#f87171;--shadow:0 10px 30px rgba(0,0,0,.25);--radius:16px;}
  *{box-sizing:border-box}
  body{padding:16px;background:var(--bg);color:var(--text);font:500 16px/1.5 system-ui}
  .grid{display:grid;gap:16px;<?php echo $SHOW_TRIALS ? 'grid-template-columns:minmax(280px,1.1fr) 1.9fr' : 'grid-template-columns:1fr'; ?>}
  @media (max-width: 980px){ .grid{grid-template-columns:1fr} }
  .card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,.08);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);backdrop-filter:blur(6px)}
  .banner{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
  .brand{display:flex;align-items:center;gap:10px}
  .brand img{height:28px;width:auto;border-radius:6px}
  .actions{display:flex;gap:10px;align-items:center}
  .iconbtn{display:inline-flex;align-items:center;justify-content:center;height:36px;width:36px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:#fff;text-decoration:none;cursor:pointer}
  .logout{padding:8px 12px;border-radius:10px;background:#ef4444;color:#fff;text-decoration:none;font-weight:700;white-space:nowrap}
  .title{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:10px}
  .title h2{margin:0;font-size:18px;color:#cbd5e1}.title span{color:var(--muted);font-size:13px}
  .kpis{display:grid;gap:12px}
  .kpi{display:grid;gap:6px;padding:12px 14px;border-radius:14px;background:rgba(15,23,42,.6);border:1px solid rgba(255,255,255,.06)}
  .kpi small{color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-size:11px}
  .kpi .value{font-size:20px;font-weight:800}
  .value.accent{color:var(--accent)}.value.success{color:#34d399}.value.warn{color:#f87171}
  .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.12);color:#94a3b8;font-size:12px}
  table{width:100%;border-collapse:collapse;font-size:14px;border:1px solid rgba(255,255,255,.08);border-radius:12px;overflow:hidden}
  .tablewrap{overflow:auto;-webkit-overflow-scrolling:touch}
  thead{background:rgba(148,163,184,.08)}th,td{padding:10px 8px;text-align:left;vertical-align:top}tbody tr{border-top:1px solid rgba(255,255,255,.06)}tbody tr:hover{background:rgba(255,255,255,.03)}
  /* Modal */
  .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:16px;z-index:50}
  .modal{width:min(520px,96vw);background:#0f172a;border:1px solid rgba(255,255,255,.12);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.5);padding:16px}
  .modal h3{margin:0 0 8px;color:#e2e8f0;font-size:18px}
  .modal .row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid rgba(255,255,255,.06)}
  .modal .row:first-of-type{border-top:none}
  .switch{position:relative;display:inline-block;width:48px;height:28px}
  .switch input{display:none}
  .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#6b7280;border-radius:999px;transition:.2s}
  .slider:before{position:absolute;content:"";height:22px;width:22px;left:3px;top:3px;background:white;border-radius:50%;transition:.2s}
  input:checked + .slider{background:#34d399}
  input:checked + .slider:before{transform:translateX(20px)}
  .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
  .btn{padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:#fff;cursor:pointer}
  .btn.primary{background:#22d3ee;color:#0b1220;border-color:transparent;font-weight:800}
  .field{display:flex;align-items:center;gap:10px}
  .field input[type="number"]{width:160px;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:#0f172a;color:#e2e8f0}
  @media (max-width: 560px){
    .kpi .value{font-size:18px}
    .actions .logout{padding:8px 10px}
    .iconbtn{height:34px;width:34px}
    .field input[type="number"]{width:140px}
  }
</style>

<div class="grid">
  <!-- LEFT -->
  <section class="card">
    <div class="banner">
      <div class="brand" title="FX source: <?= htmlspecialchars($FX_SOURCE) ?>">
        <img src="https://weezly.com/wp-content/uploads/2025/07/weezly_logo_white_new_new.png" alt="Weezly logo" />
      </div>
      <div class="actions">
        <!-- Gear (Settings) -->
        <button class="iconbtn" id="openSettings" aria-label="Settings" title="Settings">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.8"/>
            <path d="M19 12a7 7 0 0 0-.12-1.28l2.06-1.6-2-3.46-2.45.97A7.02 7.02 0 0 0 14.3 4l-.3-2h-4l-.3 2a7.02 7.02 0 0 0-2.19 1.63l-2.45-.97-2 3.46 2.06 1.6A7 7 0 0 0 5 12c0 .44.04.87.12 1.28l-2.06 1.6 2 3.46 2.45-.97c.64.65 1.39 1.2 2.19 1.63l.3 2h4l.3-2c.8-.43 1.55-.98 2.19-1.63l2.45.97 2-3.46-2.06-1.6c.08-.41.12-.84.12-1.28Z" stroke="currentColor" stroke-width="1.4"/>
          </svg>
        </button>
        <a class="logout" href="?logout=1">Logout</a>
      </div>
    </div>

    <div class="title"><h2>Overview</h2><span class="pill">SEK</span></div>
    <div class="kpis">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="kpi">
          <small>Current MRR</small>
          <div class="value accent"><?= fmt_money($current_mrr_sek,'SEK'); ?></div>
        </div>
        <div class="kpi">
          <small>Current ARR</small>
          <div class="value accent"><?= fmt_money($current_arr_sek,'SEK'); ?></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="kpi">
          <small>Goal MRR</small>
          <div class="value success"><?= fmt_money($GOAL_MRR,'SEK'); ?></div>
        </div>
        <div class="kpi">
          <small>Left to Earn MRR</small>
          <div class="value <?= $left_to_goal<=0?'success':'warn'; ?>"><?= fmt_money($left_to_goal,'SEK'); ?></div>
        </div>
      </div>

      <?php if ($SHOW_TRIALS): ?>
      <div class="kpi">
        <small>Trials (MRR, FX→SEK)</small>
        <div class="value"><?= fmt_money($trial_total_sek,'SEK'); ?></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
        <div class="kpi">
          <small>Potential MRR</small>
          <div class="value"><?= fmt_money($potential_mrr_sek,'SEK'); ?></div>
        </div>
        <div class="kpi">
          <small>Potential ARR</small>
          <div class="value"><?= fmt_money($potential_arr_sek,'SEK'); ?></div>
        </div>
        <div class="kpi">
          <small>Potential Left to Earn</small>
          <div class="value <?= $potential_left_to_goal<=0?'success':'warn'; ?>"><?= fmt_money($potential_left_to_goal,'SEK'); ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div style="margin-top:10px;color:#94a3b8;font-size:12px;">Account: <strong><?= htmlspecialchars($acct_id); ?></strong></div>
  </section>

  <?php if ($SHOW_TRIALS): ?>
  <!-- RIGHT -->
  <section class="card">
    <div class="title"><h2>Trialing Subscriptions</h2><span class="pill"><?= count($trial_rows); ?> total</span></div>
    <div class="tablewrap">
      <table>
        <thead>
          <tr><th>Customer</th><th>Email</th><th>Trial ends</th><th style="text-align:right;">Est. MRR (SEK)</th></tr>
        </thead>
        <tbody>
        <?php if (!$trial_rows): ?>
          <tr><td colspan="4">No active trials.</td></tr>
        <?php else: foreach ($trial_rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['customer']); ?></td>
            <td><?= htmlspecialchars($r['email']); ?></td>
            <td><?= htmlspecialchars($r['trial_end']); ?></td>
            <td style="text-align:right;"><?= fmt_money($r['mrr_sek'],'SEK'); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</div>

<!-- Settings Modal -->
<div class="modal-backdrop" id="settingsModal" aria-hidden="true">
  <form class="modal" method="post" action="">
    <input type="hidden" name="action" value="save_settings" />
    <h3>Settings</h3>

    <div class="row">
      <div class="field">
        <div style="font-weight:700;">Goal MRR (SEK)</div>
      </div>
      <div class="field">
        <input type="number" name="goal_mrr" step="1" min="0" value="<?= htmlspecialchars((string)$GOAL_MRR); ?>" />
      </div>
    </div>

    <div class="row">
      <div>
        <div style="font-weight:700;">Show trialing subscriptions</div>
        <div style="color:#94a3b8;font-size:13px">Toggle the trials panel and related KPIs.</div>
      </div>
      <label class="switch" title="Show trials">
        <input type="checkbox" name="show_trials" value="1" <?= $SHOW_TRIALS ? 'checked' : ''; ?> />
        <span class="slider"></span>
      </label>
    </div>

    <div class="modal-actions">
      <button type="button" class="btn" id="cancelSettings">Cancel</button>
      <button type="submit" class="btn primary">Save</button>
    </div>
  </form>
</div>

<script>
  // Remove preloader now that content is rendered
  (function(){ const p = document.getElementById('preloader'); if (p) p.remove(); })();

  // Settings modal
  const openBtn = document.getElementById('openSettings');
  const modal = document.getElementById('settingsModal');
  const cancelBtn = document.getElementById('cancelSettings');

  function openModal(){ modal.style.display = 'flex'; modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); }

  openBtn?.addEventListener('click', openModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e)=>{ if(e.target===modal) closeModal(); });

  // ESC to close
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && modal.getAttribute('aria-hidden')==='false') closeModal(); });
</script>

</body></html>
