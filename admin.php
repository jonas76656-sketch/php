<?php
session_start();
$key_file = 'keys.json';

// --- Functions ---
function get_data() {
    global $key_file;
    if (!file_exists($key_file)) return [];
    return json_decode(file_get_contents($key_file), true) ?: [];
}

function save_data($data) {
    global $key_file;
    file_put_contents($key_file, json_encode($data, JSON_PRETTY_PRINT));
}

// Handle Add Key
if (isset($_POST['add_key'])) {
    $db = get_data();
    $custom_key = !empty($_POST['custom_name']) ? trim($_POST['custom_name']) : "HEYOZ-" . strtoupper(substr(md5(uniqid()), 0, 8));
    
    $db[$custom_key] = [
        'credits' => (int)$_POST['credits'],
        'expiry' => $_POST['expiry'],
        'session_id' => "" // အသစ်ထုတ်ချိန်မှာ ဘယ်သူမှမသုံးသေးကြောင်း သတ်မှတ်သည်
    ];
    save_data($db);
    $msg = "Successfully Generated: $custom_key";
}

// Handle Delete Key
if (isset($_GET['delete'])) {
    $db = get_data();
    $target = $_GET['delete'];
    if (isset($db[$target])) {
        unset($db[$target]);
        save_data($db);
        header("Location: admin.php");
        exit;
    }
}

$all_keys = get_data();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HEYOz ADMIN PANEL</title>
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --blue: #58a6ff; --green: #238636; --red: #f85149; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; }
        h1 { color: var(--blue); margin: 0; font-size: 24px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 25px; margin-bottom: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        input { width: 100%; background: #010409; border: 1px solid var(--border); border-radius: 6px; padding: 10px; color: var(--blue); box-sizing: border-box; }
        .btn-submit { background: var(--green); color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        th { background: #21262d; padding: 15px; text-align: left; font-size: 14px; border-bottom: 1px solid var(--border); }
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 14px; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .online { color: var(--blue); background: rgba(88, 166, 255, 0.1); border: 1px solid var(--blue); }
        .offline { color: #8b949e; background: rgba(139, 148, 158, 0.1); border: 1px solid #30363d; }
        .btn-del { color: var(--red); text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid var(--red); border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>⚡ HEYOz ADMIN DASHBOARD</h1>
        <a href="index.php" style="color: var(--blue); text-decoration: none; font-weight: bold;">← Back to Checker</a>
    </header>

    <div class="card">
        <form method="POST">
            <div class="form-grid">
                <div><input type="number" name="credits" value="500" placeholder="Credits"></div>
                <div><input type="date" name="expiry" value="<?php echo date('Y-12-31'); ?>"></div>
                <div><input type="text" name="custom_name" placeholder="Custom Key Name"></div>
            </div>
            <button type="submit" name="add_key" class="btn-submit">CREATE LICENSE KEY</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>LICENSE KEY</th>
                <th>CREDITS</th>
                <th>EXPIRY</th>
                <th>USAGE STATUS</th> <th>ACTION</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($all_keys as $key => $val): 
                // Session ID ရှိနေရင် သုံးစွဲသူရှိနေကြောင်း Online ပြမည်
                $is_online = !empty($val['session_id']);
            ?>
            <tr>
                <td style="font-family: monospace; font-weight: bold; color: var(--blue);"><?php echo $key; ?></td>
                <td><?php echo number_format($val['credits']); ?> Cr</td>
                <td><?php echo $val['expiry']; ?></td>
                <td>
                    <?php if($is_online): ?>
                        <span class="badge online">● ONLINE (In Use)</span>
                    <?php else: ?>
                        <span class="badge offline">○ OFFLINE</span>
                    <?php endif; ?>
                </td>
                <td><a href="?delete=<?php echo $key; ?>" class="btn-del">DELETE</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
