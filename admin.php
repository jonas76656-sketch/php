<?php
session_start();
$key_file = 'keys.json';
$admin_password = "Dravan@123";

if (isset($_GET['admin_logout'])) { unset($_SESSION['admin_logged_in']); header("Location: admin.php"); exit; }
if (isset($_POST['admin_login'])) {
    if ($_POST['admin_pw'] === $admin_password) { $_SESSION['admin_logged_in'] = true; }
    else { $login_error = "Incorrect Admin Password!"; }
}

if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin - HEYOz</title>
<style>
    body { background: #080a0f; color: #fff; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
    .box { background: rgba(22, 27, 34, 0.8); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 320px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
    input { width: 100%; padding: 12px; margin: 15px 0; background: #010409; border: 1px solid #30363d; color: #58a6ff; border-radius: 8px; text-align: center; }
    button { width: 100%; padding: 12px; background: #238636; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
</style></head>
<body><div class="box"><h2>🛡️ ADMIN</h2><form method="POST"><input type="password" name="admin_pw" placeholder="Admin Password" required><button type="submit" name="admin_login">ACCESS PANEL</button></form></div></body></html>
<?php exit; }

function get_data() { global $key_file; return file_exists($key_file) ? json_decode(file_get_contents($key_file), true) : []; }
function save_data($data) { global $key_file; file_put_contents($key_file, json_encode($data, JSON_PRETTY_PRINT)); }

if (isset($_POST['add_key'])) {
    $db = get_data();
    $key = !empty($_POST['custom_name']) ? trim($_POST['custom_name']) : "HEYOZ-" . strtoupper(substr(md5(uniqid()), 0, 8));
    $db[$key] = ['credits' => (int)$_POST['credits'], 'expiry' => $_POST['expiry'], 'session_id' => ""];
    save_data($db);
}
if (isset($_GET['delete'])) {
    $db = get_data(); unset($db[$_GET['delete']]); save_data($db);
    header("Location: admin.php"); exit;
}
$all_keys = get_data();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Admin Panel - HEYOz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: sans-serif; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .card { background: #161b22; border: 1px solid #30363d; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        input { background: #010409; border: 1px solid #30363d; padding: 10px; color: #58a6ff; border-radius: 5px; margin-right: 10px; }
        table { width: 100%; border-collapse: collapse; background: #161b22; border-radius: 10px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #30363d; }
        th { background: #21262d; }
        .btn-del { color: #f85149; text-decoration: none; border: 1px solid #f85149; padding: 5px 10px; border-radius: 5px; }
        .online { color: #58a6ff; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2 style="color:#58a6ff"><i class="fa-solid fa-shield-halved"></i> HEYOz MANAGEMENT</h2>
        <a href="?admin_logout" style="color:#f85149; text-decoration:none; font-weight:bold;">LOGOUT</a>
    </div>
    <div class="card">
        <form method="POST">
            <input type="number" name="credits" value="500" style="width:100px">
            <input type="date" name="expiry" value="2026-12-31">
            <input type="text" name="custom_name" placeholder="Custom Key Name">
            <button type="submit" name="add_key" style="padding:10px 20px; background:#238636; color:white; border:none; border-radius:5px; cursor:pointer;">CREATE KEY</button>
        </form>
    </div>
    <table>
        <thead><tr><th>KEY</th><th>CREDITS</th><th>EXPIRY</th><th>STATUS</th><th>ACTION</th></tr></thead>
        <tbody>
            <?php foreach($all_keys as $k => $v): $online = !empty($v['session_id']); ?>
            <tr>
                <td style="font-family:monospace; color:#58a6ff"><?php echo $k; ?></td>
                <td><?php echo $v['credits']; ?> Cr</td>
                <td><?php echo $v['expiry']; ?></td>
                <td class="<?php echo $online?'online':''; ?>"><?php echo $online?'● ONLINE':'○ OFFLINE'; ?></td>
                <td><a href="?delete=<?php echo $k; ?>" class="btn-del">DELETE</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
