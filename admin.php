<?php
// --- 1. Session Configuration (Browser ပိတ်လဲ ၇ ရက်အထိ မှတ်မိနေစေရန်) ---
$session_lifetime = 604800; // 7 days in seconds
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

// Admin အဖြစ် အသုံးပြုမည့် သတ်မှတ် Key
$admin_secret_key = "Dravan@123"; 

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

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Admin.php");
    exit;
}

// --- Login Logic (Admin Access) ---
if (isset($_POST['login_admin'])) {
    $input_key = trim($_POST['key']);
    
    // သင်သတ်မှတ်ထားသော Dravan@123 နှင့် ကိုက်ညီမှု ရှိမရှိ စစ်ဆေးခြင်း
    if ($input_key === $admin_secret_key) {
        // Browser ပိတ်ပြီးပြန်ဖွင့်လျှင် Error မပြစေရန် session_id ကို update လုပ်သည်
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_key'] = $input_key;
    } else {
        $error = "Invalid Admin Key!";
    }
}

// --- Admin Actions (Key Generate & Delete) ---
if (isset($_SESSION['admin_logged_in'])) {
    $all_keys = get_keys();
    $msg = "";

    // Key အသစ်ထုတ်ခြင်း
    if (isset($_POST['gen_key'])) {
        $days = intval($_POST['days']);
        $creds = intval($_POST['credits']);
        $new_k = "HEYOZ-" . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $all_keys[$new_k] = [
            'expiry' => date('Y-m-d', strtotime("+$days days")),
            'credits' => $creds,
            'session_id' => ""
        ];
        save_keys($all_keys);
        $msg = "Successfully Generated: $new_k";
    }

    // Key ဖျက်ခြင်း
    if (isset($_GET['delete'])) {
        $dk = $_GET['delete'];
        if (isset($all_keys[$dk])) {
            unset($all_keys[$dk]);
            save_keys($all_keys);
            header("Location: Admin.php");
            exit;
        }
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
            .login-box { background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 340px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
            h2 { margin-bottom: 25px; font-weight: 600; letter-spacing: 1px; color: #58a6ff; }
            input { width: 100%; padding: 15px; margin: 10px 0; background: rgba(1, 4, 9, 0.5); border: 1px solid #30363d; color: #58a6ff; border-radius: 12px; box-sizing: border-box; text-align: center; font-size: 16px; outline: none; }
            button { width: 100%; padding: 15px; background: linear-gradient(45deg, #238636, #2ea043); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 10px; }
            .err { color: #f85149; background: rgba(248, 81, 73, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>ADMIN ACCESS</h2>
            <?php if(isset($error)) echo "<div class='err'>$error</div>"; ?>
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', sans-serif; padding: 20px; margin: 0; }
        .container { max-width: 1000px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; background: #161b22; padding: 20px; border-radius: 15px; border: 1px solid #30363d; margin-bottom: 25px; }
        .card { background: #161b22; border: 1px solid #30363d; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        h3 { margin-top: 0; color: #58a6ff; }
        .form-group { display: flex; gap: 10px; flex-wrap: wrap; }
        input[type="number"] { background: #010409; border: 1px solid #30363d; color: #c9d1d9; padding: 12px; border-radius: 8px; width: 120px; outline: none; }
        .btn-gen { background: #238636; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #30363d; }
        th { color: #8b949e; font-size: 14px; text-transform: uppercase; }
        .btn-del { color: #f85149; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2 style="margin:0; color:#58a6ff;"><i class="fa-solid fa-user-shield"></i> HEYOz ADMIN PANEL</h2>
        <a href="?logout=1" style="color:#f85149; text-decoration:none; font-weight:bold; border:1px solid #f85149; padding:8px 15px; border-radius:8px;">LOGOUT</a>
    </div>

    <?php if($msg != "") echo "<div style='background:rgba(63,185,80,0.1); color:#3fb950; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid rgba(63,185,80,0.3);'>$msg</div>"; ?>

    <div class="card">
        <h3>Generate New License</h3>
        <form method="POST" class="form-group">
            <input type="number" name="days" placeholder="Days" value="30" min="1" required>
            <input type="number" name="credits" placeholder="Credits" value="1000" min="1" required>
            <button type="submit" name="gen_key" class="btn-gen">Create Key</button>
        </form>
    </div>

    <div class="card">
        <h3>Manage Keys</h3>
        <table>
            <thead>
                <tr>
                    <th>License Key</th>
                    <th>Expiry Date</th>
                    <th>Credits Remaining</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_keys as $k => $v): ?>
                <tr>
                    <td><code><?php echo $k; ?></code></td>
                    <td><?php echo $v['expiry']; ?></td>
                    <td><?php echo number_format($v['credits']); ?></td>
                    <td><a href="?delete=<?php echo $k; ?>" class="btn-del" onclick="return confirm('Delete?')">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
