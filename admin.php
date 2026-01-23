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

// --- Database Functions ---
function get_keys() {
    global $key_file;
    if (!file_exists($key_file)) return [];
    return json_decode(file_get_contents($key_file), true) ?: [];
}

function save_keys($keys) {
    global $key_file;
    file_put_contents($key_file, json_encode($keys, JSON_PRETTY_PRINT));
}

// --- Login Logic (Admin ဝင်ရောက်မှုကို စစ်ဆေးခြင်း) ---
if (isset($_POST['login_key'])) {
    $input_key = trim($_POST['key']);
    $all_keys = get_keys();
    
    if (isset($all_keys[$input_key])) {
        // သက်တမ်းနှင့် Credit စစ်ဆေးခြင်း
        if (date('Y-m-d') > $all_keys[$input_key]['expiry']) {
            $error = "Key Expired!";
        } elseif ($all_keys[$input_key]['credits'] < 5) {
            $error = "Insufficient Credits!";
        } 
        else {
            // Browser ပြန်ဖွင့်လျှင် Error မပြစေရန် Session ID ကို Overwrite လုပ်သည်
            $all_keys[$input_key]['session_id'] = session_id();
            save_keys($all_keys);
            $_SESSION['user_key'] = $input_key;
            $_SESSION['logged_in'] = true;
        }
    } else { $error = "Invalid License Key!"; }
}

// --- Auto-Check Validity (အသုံးပြုနေစဉ်အတွင်း စစ်ဆေးရန်) ---
if (isset($_SESSION['logged_in'])) {
    $ckey = $_SESSION['user_key'];
    $all_keys = get_keys();
    
    if (!isset($all_keys[$ckey]) || 
        date('Y-m-d') > $all_keys[$ckey]['expiry'] || 
        $all_keys[$ckey]['credits'] < 5) {
        session_destroy();
        header("Location: Admin.php");
        exit;
    }
}

// --- Login Form UI ---
if (!isset($_SESSION['logged_in'])) {
    // ဤနေရာတွင် သင်၏ Login Form HTML ကို ထည့်ပါ (ယခင်ပို့ပေးထားသော Login Box ပုံစံအတိုင်းသုံးနိုင်သည်)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        </head>
    <body>
        <form method="POST">
            <input type="text" name="key" placeholder="Enter Admin/License Key" required>
            <button type="submit" name="login_key">Login to Admin</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// --- Admin Content (Login အောင်မြင်မှသာ အောက်ပါအပိုင်းကို မြင်ရမည်) ---
?>
<h1>Welcome to Admin Dashboard</h1>
