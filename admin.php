<?php
session_start();
$key_file = 'keys.json';

// --- Admin Configuration ---
$admin_password = "Dravan@123";

// Logout for Admin
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Admin Login Logic
if (isset($_POST['admin_login'])) {
    if ($_POST['admin_pw'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Incorrect Admin Password!";
    }
}

// Admin Login မဝင်ရသေးပါက Login Form ပြရန်
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - HEYOz</title>
        <style>
            body { background: #0d1117; color: #c9d1d9; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-card { background: #161b22; padding: 30px; border-radius: 12px; border: 1px solid #30363d; width: 320px; text-align: center; }
            input { width: 100%; padding: 12px; margin: 15px 0; background: #010409; border: 1px solid #58a6ff; color: #58a6ff; border-radius: 8px; text-align: center; outline: none; }
            button { width: 100%; padding: 12px; background: #238636; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; }
            .err { color: #f85149; font-size: 14px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h2 style="color:#58a6ff;">🛡️ ADMIN ACCESS</h2>
            <?php if(isset($login_error)) echo "<div class='err'>$login_error</div>"; ?>
            <form method="POST">
                <input type="password" name="admin_pw" placeholder="Enter Admin Password" required>
                <button type="submit" name="admin_login">LOGIN TO PANEL</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

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
        'session_id' => "" 
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
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

$all_keys = get_data();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        /* သင့်ရဲ့ မူရင်း CSS များ ဤနေရာတွင် ရှိမည် */
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --blue: #58a6ff; --green: #238636; --red: #f85149; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 25px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--border); }
        th, td { padding: 15px; border-bottom: 1px solid var(--border); text-align: left; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .online { color: var(--blue); background: rgba(88, 166, 255, 0.1); border: 1px solid var(--blue); }
        .offline { color: #8b949e; background: rgba(139, 148, 158, 0.1); border: 1px solid #30363d; }
        .btn-del { color: var(--red); text-decoration: none; border: 1px solid var(--red); padding: 5px 10px; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>⚡ HEYOz ADMIN DASHBOARD</h1>
        <div>
            <a href="index.php" style="color: var(--blue); text-decoration: none; font-weight: bold; margin-right: 20px;">← Back</a>
            <a href="?admin_logout" style="color: var(--red); text-decoration: none; font-weight: bold;">[LOGOUT ADMIN]</a>
        </div>
    </header>

    <div class="card">
        <form method="POST">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <input type="number" name="credits" value="500">
                <input type="date" name="expiry" value="<?php echo date('Y-12-31'); ?>">
                <input type="text" name="custom_name" placeholder="Custom Key Name">
            </div>
            <button type="submit" name="add_key" style="background:var(--green); color:white; border:none; padding:12px; width:100%; border-radius:6px; cursor:pointer; margin-top:15px; font-weight:bold;">CREATE LICENSE KEY</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>KEY</th><th>CREDITS</th><th>EXPIRY</th><th>STATUS</th><th>ACTION</th></tr>
        </thead>
        <tbody>
            <?php foreach($all_keys as $key => $val): 
                $is_online = !empty($val['session_id']);
            ?>
            <tr>
                <td style="color:var(--blue); font-weight:bold;"><?php echo $key; ?></td>
                <td><?php echo number_format($val['credits']); ?> Cr</td>
                <td><?php echo $val['expiry']; ?></td>
                <td>
                    <?php if($is_online): ?>
                        <span class="badge online">ONLINE</span>
                    <?php else: ?>
                        <span class="badge offline">OFFLINE</span>
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
