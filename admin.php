<?php
// --- 1. Session Configuration (Browser ပိတ်လဲ ၇ ရက်အထိ မှတ်မိနေစေရန်) ---
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

// --- Login Logic ---
if (isset($_POST['login_admin'])) {
    $input_key = trim($_POST['key']);
    $all_keys = get_keys();
    
    if (isset($all_keys[$input_key])) {
        // Admin Key ဟုတ်မဟုတ် စစ်ဆေးခြင်း (ဥပမာ- Credit များစွာရှိသော key သို့မဟုတ် သီးသန့် key)
        if ($all_keys[$input_key]['credits'] < 5) {
            $error = "Insufficient Credits for Admin Access!";
        } else {
            $all_keys[$input_key]['session_id'] = session_id();
            save_keys($all_keys);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_key'] = $input_key;
        }
    } else { $error = "Invalid Admin Key!"; }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Admin.php");
    exit;
}

// --- Admin Actions (Key Generate & Delete) ---
if (isset($_SESSION['admin_logged_in'])) {
    $all_keys = get_keys();

    // Key အသစ်ထုတ်ခြင်း
    if (isset($_POST['gen_key'])) {
        $new_k = "HEYOZ-" . strtoupper(substr(md5(uniqid()), 0, 8));
        $days = intval($_POST['days']);
        $creds = intval($_POST['credits']);
        $all_keys[$new_k] = [
            'expiry' => date('Y-m-d', strtotime("+$days days")),
            'credits' => $creds,
            'session_id' => ""
        ];
        save_keys($all_keys);
        $msg = "Generated: $new_k";
    }

    // Key ဖျက်ခြင်း
    if (isset($_GET['delete'])) {
        $dk = $_GET['delete'];
        unset($all_keys[$dk]);
        save_keys($all_keys);
        header("Location: Admin.php");
    }
}

// Login Form UI
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - HEYOz</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { background: #080a0f; color: #c9d1d9; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 340px; text-align: center; }
            input { width: 100%; padding: 15px; margin: 10px 0; background: rgba(1, 4, 9, 0.5); border: 1px solid #30363d; color: #58a6ff; border-radius: 12px; box-sizing: border-box; text-align: center; outline: none; }
            button { width: 100%; padding: 15px; background: #238636; color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; }
            .err { color: #f85149; background: rgba(248, 81, 73, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>ADMIN ACCESS</h2>
            <?php if($error) echo "<div class='err'>$error</div>"; ?>
            <form method="POST">
                <input type="text" name="key" placeholder="Enter Admin Key" required>
                <button type="submit" name="login_admin">LOGIN</button>
            </form>
        </div>
    </body>
    </html>
    <?php exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HEYOz</title>
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: sans-serif; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .card { background: #161b22; border: 1px solid #30363d; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #30363d; }
        th { color: #58a6ff; }
        .btn-gen { background: #238636; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-del { color: #f85149; text-decoration: none; font-weight: bold; }
        input[type="number"], input[type="text"] { background: #010409; border: 1px solid #30363d; color: white; padding: 8px; border-radius: 5px; margin-right: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Admin Dashboard</h1>
        <a href="?logout=1" style="color: #f85149;">Logout</a>
    </div>

    <?php if($msg) echo "<p style='color:#3fb950'>$msg</p>"; ?>

    <div class="card">
        <h3>Generate New Key</h3>
        <form method="POST">
            <input type="number" name="days" placeholder="Days" value="30" required>
            <input type="number" name="credits" placeholder="Credits" value="1000" required>
            <button type="submit" name="gen_key" class="btn-gen">Create Key</button>
        </form>
    </div>

    <div class="card">
        <h3>Manage Keys</h3>
        <table>
            <tr>
                <th>License Key</th>
                <th>Expiry</th>
                <th>Credits</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($all_keys as $k => $v): ?>
            <tr>
                <td><code><?php echo $k; ?></code></td>
                <td><?php echo $v['expiry']; ?></td>
                <td><?php echo number_format($v['credits']); ?></td>
                <td><?php echo empty($v['session_id']) ? "Offline" : "<span style='color:#3fb950'>Online</span>"; ?></td>
                <td><a href="?delete=<?php echo $k; ?>" class="btn-del" onclick="return confirm('Delete this key?')">Delete</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
