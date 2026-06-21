<?php
session_start();
require_once 'classes/model.php';

$model = new Model();

// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = trim($_POST['ip']);
    if (empty($ip)) {
        $error = "Customer ID required";
    } else {

        $customer = $model->rowSql("SELECT * FROM `_reseller_createuser` WHERE UserName='$ip'");

    
        if (!$customer) {
             $customer = $model->getCustomerByCusId($ip);

        }
        

        $customerType = isset($customer['res_id']) ? 'reseller' : 'agent';
        $customerID = $customer['UserId'] ?? $customer['ag_id'] ?? '';

        if ($customer) {
            $_SESSION['customer_id'] = $customerID;
            $_SESSION['customer_name'] = $customer['ag_name'];
            $_SESSION['type'] = $customerType;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid Customer ID";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --accent: #3b82f6;
            --bg: #0f172a;
            --surface: #1e293b;
            --border: #334155;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --error: #ef4444;
            --success: #22c55e;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(37,99,235,0.15) 0%, transparent 70%);
            top: -100px; left: -100px;
            animation: pulse 6s ease-in-out infinite;
        }
        body::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(59,130,246,0.1) 0%, transparent 70%);
            bottom: -100px; right: -100px;
            animation: pulse 6s ease-in-out infinite reverse;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 1; }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: fadeUp 0.6s ease both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px rgba(37,99,235,0.4);
        }
        .brand h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.5px;
        }
        .brand p {
            color: var(--muted);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
        }

        .error-box {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.875rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.9rem;
        }
        input[type="text"] {
            width: 100%;
            padding: 13px 14px 13px 40px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Sora', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
        }

        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--muted);
            font-size: 0.9rem;
            background: none;
            border: none;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: var(--text); }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            font-family: 'Sora', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(37,99,235,0.4);
            margin-top: 8px;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(37,99,235,0.5);
        }
        .btn-login:active { transform: translateY(0); }

        .hint {
            text-align: center;
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .hint strong { color: var(--accent); }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-user-shield"></i></div>
        <h1>Client Portal</h1>
        <p>Sign in to access your account</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>User ID</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="text" name="ip" placeholder="Enter customer Id"
                           value="<?= isset($_POST['ip']) ? htmlspecialchars($_POST['ip']) : '' ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right-to-bracket"></i> &nbsp;Sign In
            </button>
        </form>
    </div>
</div>
</body>
</html>
