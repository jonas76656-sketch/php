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
        if (date('Y-m-d') > $all_keys[$input_key]['expiry']) {
            $error = "Key Expired!";
        } elseif ($all_keys[$input_key]['credits'] < 5) {
            $error = "Insufficient Credits!";
        } elseif (!empty($all_keys[$input_key]['session_id']) && $all_keys[$input_key]['session_id'] !== session_id()) {
            $error = "Key in use on another device!";
        } else {
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HEYOz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #080a0f; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; overflow: hidden; }
        body::before { content: ""; position: absolute; width: 300px; height: 300px; background: #58a6ff; filter: blur(150px); border-radius: 50%; top: 10%; left: 10%; opacity: 0.2; }
        body::after { content: ""; position: absolute; width: 300px; height: 300px; background: #f85149; filter: blur(150px); border-radius: 50%; bottom: 10%; right: 10%; opacity: 0.1; }
        .login-card { background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 350px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); z-index: 10; transform: translateY(0); transition: 0.5s; }
        .login-card:hover { transform: translateY(-10px); border-color: #58a6ff; }
        .logo-icon { font-size: 50px; color: #58a6ff; margin-bottom: 20px; text-shadow: 0 0 20px rgba(88, 166, 255, 0.5); }
        h2 { color: #fff; margin-bottom: 10px; font-weight: 600; letter-spacing: 1px; }
        p { color: #8b949e; font-size: 13px; margin-bottom: 30px; }
        input { width: 100%; padding: 15px; margin-bottom: 20px; background: rgba(1, 4, 9, 0.5); border: 1px solid #30363d; color: #58a6ff; border-radius: 12px; box-sizing: border-box; text-align: center; font-size: 16px; outline: none; transition: 0.3s; }
        input:focus { border-color: #58a6ff; box-shadow: 0 0 15px rgba(88,166,255,0.2); }
        button { width: 100%; padding: 15px; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 16px; transition: 0.3s; box-shadow: 0 10px 20px rgba(35, 134, 54, 0.3); }
        button:hover { transform: scale(1.02); box-shadow: 0 15px 25px rgba(35, 134, 54, 0.4); }
        .err { background: rgba(248, 81, 73, 0.1); color: #f85149; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid rgba(248, 81, 73, 0.2); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-icon"><i class="fa-solid fa-bolt"></i></div>
        <h2>HEYOz LOGIN</h2>
        <p>Enter your premium key to access</p>
        <?php if($error) echo "<div class='err'><i class='fa-solid fa-circle-exclamation'></i> $error</div>"; ?>
        <form method="POST">
            <input type="text" name="key" placeholder="•••• •••• •••• ••••" required>
            <button type="submit" name="login_key">ACTIVATE SYSTEM</button>
        </form>
    </div>
</body>
</html>
<?php exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HEYOz DASHBOARD</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --accent: #58a6ff; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; margin: 0; padding: 15px; display: flex; justify-content: center; }
        .wrapper { width: 100%; max-width: 850px; animation: fadeIn 0.8s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .header-box { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(90deg, #161b22, #0d1117); padding: 15px 25px; border-radius: 15px; border: 1px solid var(--border); margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .header-box h1 { font-size: 1.4rem; color: var(--accent); margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .credit-badge { background: rgba(88, 166, 255, 0.1); color: var(--accent); padding: 8px 15px; border-radius: 10px; border: 1px solid rgba(88, 166, 255, 0.3); font-weight: bold; font-size: 14px; }
        
        #status-display { background: #010409; border: 1px solid var(--accent); padding: 15px; border-radius: 12px; text-align: center; margin-bottom: 20px; font-family: monospace; color: var(--accent); font-weight: bold; box-shadow: inset 0 0 15px rgba(88, 166, 255, 0.1); }
        
        .gate-select { width: 100%; background: var(--card); color: var(--accent); border: 1px solid var(--border); padding: 12px; border-radius: 10px; margin-bottom: 15px; font-weight: 600; outline: none; cursor: pointer; transition: 0.3s; }
        .gate-select:focus { border-color: var(--accent); }
        
        textarea { width: 100%; height: 160px; background: #010409; color: var(--accent); border: 1px solid var(--border); padding: 15px; border-radius: 12px; font-family: monospace; resize: none; outline: none; transition: 0.3s; box-sizing: border-box; }
        textarea:focus { border-color: var(--accent); box-shadow: 0 0 15px rgba(88, 166, 255, 0.1); }
        
        .btn-group { display: flex; gap: 10px; margin: 20px 0; }
        #btn { flex: 2; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 16px; transition: 0.3s; }
        #stopBtn { flex: 1; background: #da3633; color: white; border: none; padding: 15px; border-radius: 12px; cursor: pointer; font-weight: bold; display: none; }
        #btn:hover { transform: scale(1.02); }

        .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 12px; border-radius: 12px; text-align: center; transition: 0.3s; }
        .stat-box:hover { border-color: var(--accent); background: #1c2128; }
        .stat-box small { color: #8b949e; font-size: 10px; text-transform: uppercase; }
        .stat-box span { font-size: 22px; font-weight: bold; display: block; margin-top: 5px; }

        .result-box { background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
        .res-head { padding: 15px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; background: rgba(255,255,255,0.02); }
        .res-body { display: none; padding: 10px; font-family: monospace; font-size: 12px; border-top: 1px solid var(--border); max-height: 250px; overflow-y: auto; }
        
        .LIVE { color: #3fb950; } .INSUF { color: #d29922; } .CVV { color: #58a6ff; } .DEAD { color: #f85149; }
        .taken-time { color: #8b949e; font-size: 10px; margin-left: 10px; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header-box">
        <h1><i class="fa-solid fa-fire"></i> HEYOz Checker</h1>
        <div class="credit-badge" id="credit_display"><i class="fa-solid fa-coins"></i> Credits: <?php echo number_format(get_keys()[$_SESSION['user_key']]['credits']); ?></div>
    </div>
    
    <div id="status-display">SYSTEM READY TO SCAN</div>

    <select id="gate" class="gate-select">
        <option value="gate1">⚡ GATE 1: STRIPE $1.00 (Auth)</option>
        <option value="gate2">⚡ GATE 2: STRIPE $0.50 (Charge)</option>
    </select>

    <textarea id="list" placeholder="4111222233334444|01|26|123"></textarea>
    
    <div class="btn-group">
        <button id="btn" onclick="start()"><i class="fa-solid fa-play"></i> START SCANNING</button>
        <button id="stopBtn" onclick="stop()"><i class="fa-solid fa-stop"></i> STOP</button>
    </div>

    <div class="stats">
        <div class="stat-box"><small>Total</small><span id="c_total">0</span></div>
        <div class="stat-box"><small class="LIVE">Live</small><span id="c_live" class="LIVE">0</span></div>
        <div class="stat-box"><small class="INSUF">Insuf</small><span id="c_insuf" class="INSUF">0</span></div>
        <div class="stat-box"><small class="CVV">3DS</small><span id="c_cvv" class="CVV">0</span></div>
        <div class="stat-box"><small class="DEAD">Dead</small><span id="c_dead" class="DEAD">0</span></div>
    </div>

    <div class="result-box"><div class="res-head LIVE" onclick="toggleBox('l_live')"><span><i class="fa-solid fa-check-circle"></i> HIT / CHARGED</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_live"></div></div>
    <div class="result-box"><div class="res-head DEAD" onclick="toggleBox('l_dead')"><span><i class="fa-solid fa-times-circle"></i> DECLINED / DEAD</span> <i class="fa-solid fa-chevron-down"></i></div><div class="res-body" id="l_dead"></div></div>
</div>

<script>
    let counts = { LIVE: 0, INSUF: 0, CVV: 0, DEAD: 0 };
    let isRunning = false;

    function toggleBox(id) {
        const body = document.getElementById(id);
        body.style.display = body.style.display === "block" ? "none" : "block";
    }

    function stop() { isRunning = false; document.getElementById('stopBtn').style.display = 'none'; document.getElementById('btn').disabled = false; }

    async function start() {
        const textArea = document.getElementById('list');
        let lines = textArea.value.split('\n').filter(l => l.trim() !== "");
        if (lines.length === 0) return;
        document.getElementById('c_total').innerText = lines.length;

        isRunning = true;
        document.getElementById('btn').disabled = true;
        document.getElementById('stopBtn').style.display = 'block';

        while (lines.length > 0 && isRunning) {
            let line = lines[0].trim();
            document.getElementById('status-display').innerText = "Checking: " + line;
            const startTime = performance.now();

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: JSON.stringify({ card: line, gate: document.getElementById('gate').value }),
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                const timeTaken = ((performance.now() - startTime) / 1000).toFixed(2);

                if (data.status === "LOGOUT") {
                    alert("Credits Finished!");
                    window.location.reload();
                    return;
                }

                counts[data.status]++;
                if(document.getElementById('c_' + data.status.toLowerCase())) {
                    document.getElementById('c_' + data.status.toLowerCase()).innerText = counts[data.status];
                }

                const targetId = (data.status === "LIVE" || data.status === "INSUF" || data.status === "CVV") ? 'l_live' : 'l_dead';
                const target = document.getElementById(targetId);
                const item = document.createElement('div');
                item.style.padding = "8px 0"; item.style.borderBottom = "1px solid #21262d";
                item.innerHTML = `<span class="${data.status}">[${data.status}]</span> ${line} <i class="fa-solid fa-arrow-right" style="font-size:10px"></i> <span class="${data.status}">${data.msg}</span> <span class="taken-time">[${timeTaken}s]</span>`;
                target.insertBefore(item, target.firstChild);
                
                if(data.status === "LIVE") {
                    let currentCredit = parseInt(document.getElementById('credit_display').innerText.replace(/[^\d]/g, ''));
                    document.getElementById('credit_display').innerHTML = '<i class="fa-solid fa-coins"></i> Credits: ' + (currentCredit - 5);
                }

                lines.shift();
                textArea.value = lines.join('\n');
            } catch (e) { isRunning = false; }
        }
        stop();
        document.getElementById('status-display').innerText = "SCANNING FINISHED";
    }
</script>
</body>
</html>
