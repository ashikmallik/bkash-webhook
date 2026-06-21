<?php
session_start();
require_once 'classes/model.php';

$model = new Model();

$type = $_SESSION['type'];
$customerId = $_SESSION['customer_id'];

if (!isset($customerId)) {
    header("Location: index.php");
    exit();
}

        if($type === 'reseller'){
            $r = $model->rowSql("
        SELECT *
        FROM `_reseller_createuser`
        WHERE UserId='$customerId'
    ");

    $c = [
        'ag_name'            => $r['FullName'],
        'ag_email'           => $r['Email'],
        'ag_mobile_no'       => $r['MobileNo'],
        'ag_status'          => $r['Status'],
        'ag_office_address'  => $r['Address'],
        'cus_id'             => $r['UserName'],
        'mb'                 => 'Reseller',
        'taka'               => 0,
        'available_taka'               => $r['balance'],
        'gender'             => '-',
        'connectiontype'     => '-',
        'connection_date'    => $r['EntryDate'],
        'mikrotik_disconnect'=> '-',
        'ag_id'              => $r['UserId']
    ];

        }elseif($type === 'agent'){
            $c = $model->getCustomerById($customerId);
        }


// var_dump($c);exit;

if (!$c) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – <?= htmlspecialchars($c['ag_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --accent: #3b82f6;
            --bg: #f8fafc;
            --sidebar-bg: #0f172a;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --sidebar-text: #94a3b8;
            --sidebar-active: #f1f5f9;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }
        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-logo .logo-icon {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .sidebar-logo h2 {
            color: #f1f5f9;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .sidebar-logo p {
            color: var(--sidebar-text);
            font-size: 0.75rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 12px;
        }
        .nav-label {
            color: #475569;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 12px;
            margin: 16px 0 8px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            color: var(--sidebar-text);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.06); color: #f1f5f9; }
        .nav-item.active {
            background: rgba(37,99,235,0.2);
            color: #93c5fd;
        }
        .nav-item i { width: 18px; text-align: center; }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 10px;
            background: rgba(239,68,68,0.1);
            color: #fca5a5;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.2); }

        /* ── MAIN CONTENT ── */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar h1 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
        }
        .topbar p { color: var(--muted); font-size: 0.8rem; }

        .avatar-sm {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .content {
            padding: 32px;
            flex: 1;
        }

        /* ── PROFILE HERO ── */
        .profile-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            border-radius: 20px;
            padding: 36px;
            display: flex;
            align-items: center;
            gap: 28px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s ease both;
        }
 
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .avatar-lg {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 32px rgba(37,99,235,0.4);
            position: relative;
            z-index: 1;
        }
        .profile-info { position: relative; z-index: 1; }
        .profile-info h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .profile-info .email {
            color: #93c5fd;
            font-size: 0.875rem;
            margin-bottom: 12px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
            margin-left: 8px;
        }

        /* ── STATS ROW ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease 0.1s both;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
        }
        .stat-card p {
            font-size: 0.78rem;
            color: var(--muted);
            font-weight: 500;
        }

        /* ── DETAILS GRID ── */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            animation: fadeUp 0.5s ease 0.2s both;
        }
        .detail-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }
        .detail-card h3 {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--muted);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .detail-label {
            font-size: 0.72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text);
        }

        .full-width { grid-column: 1 / -1; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .stats-row { grid-template-columns: 1fr; }
            .details-grid { grid-template-columns: 1fr; }
            .content { padding: 16px; }
        }
        
        .profile-hero{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:20px;
            flex-wrap:wrap;
            background: linear-gradient(135deg, #0f172a, #1e3a5f);
            padding:28px;
            border-radius:20px;
        }
        
        /* LEFT SIDE */
        .profile-left{
            display:flex;
            align-items:center;
            gap:18px;
        }
        
        .avatar-lg{
            width:90px;
            height:90px;
            background:linear-gradient(135deg,#2563eb,#3b82f6);
            border-radius:20px;
            display:flex;
            justify-content:center;
            align-items:center;
            color:white;
            font-size:28px;
            font-weight:700;
        }
        
        .profile-info h2{
            color:white;
            margin-bottom:6px;
        }
        
        .profile-info .email{
            color:#93c5fd;
            font-size:14px;
            margin-bottom:10px;
        }
        
        /* BADGES */
        .badge{
            display:inline-flex;
            align-items:center;
            padding:5px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:600;
            margin-right:6px;
        }
        
        .badge.package{
            background:rgba(37,99,235,0.15);
            color:#60a5fa;
        }
        
        .badge.status{
            background:rgba(34,197,94,0.15);
            color:#22c55e;
        }
        
        .status-dot{
            width:8px;
            height:8px;
            background:#22c55e;
            border-radius:50%;
            margin-right:6px;
        }
        
        /* RIGHT BILL BOX */
        .bill-box{
            background:rgba(255,255,255,0.08);
            padding:18px;
            border-radius:14px;
            min-width:260px;
            backdrop-filter: blur(10px);
        }
        
        .bill-title{
            font-size:12px;
            color:#93c5fd;
            margin-bottom:8px;
            font-weight:600;
        }
        
        /* FORM */
        .bill-form{
            display:flex;
            gap:8px;
        }
        
        .bill-form input{
            flex:1;
            padding:10px;
            border-radius:10px;
            border:none;
            outline:none;
            font-weight:600;
        }
        
        .bill-form button{
            background:#2563eb;
            color:white;
            border:none;
            padding:10px 14px;
            border-radius:10px;
            cursor:pointer;
            font-weight:600;
            transition:0.2s;
        }
        
        .bill-form button:hover{
            background:#1d4ed8;
        }
        .error-message{
            background: #ffe5e5;
            color: #d8000c;
            padding: 15px 20px;
            border-left: 5px solid #ff0000;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 15px;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn{
            from{
                opacity: 0;
                transform: translateY(-10px);
            }
            to{
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success-message{
            background: #e7ffe5;
            color: #0f8a00;
            padding: 15px 20px;
            border-left: 5px solid #0f8a00;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 15px;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn{
            from{
                opacity: 0;
                transform: translateY(-10px);
            }
            to{
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-user-shield"></i></div>
        <h2>Customer Portal</h2>
        <p>Member Dashboard</p>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a href="dashboard.php" class="nav-item active">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>
        <!--<a href="#" class="nav-item">-->
        <!--    <i class="fas fa-user"></i> My Profile-->
        <!--</a>-->
        <!--<a href="#" class="nav-item">-->
        <!--    <i class="fas fa-file-invoice"></i> Invoices-->
        <!--</a>-->
        <!--<a href="#" class="nav-item">-->
        <!--    <i class="fas fa-headset"></i> Support-->
        <!--</a>-->
        <!--<div class="nav-label">Settings</div>-->
        <!--<a href="#" class="nav-item">-->
        <!--    <i class="fas fa-gear"></i> Preferences-->
        <!--</a>-->
        <!--<a href="#" class="nav-item">-->
        <!--    <i class="fas fa-shield-halved"></i> Security-->
        <!--</a>-->
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h1>
                Good 
                <?= (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening') ?>,
                <?= explode(' ', htmlspecialchars($c['ag_name']))[0] ?>! 👋
            </h1>

            <p><?= date('l, F j, Y') ?></p>
        </div>

        <div class="avatar-sm">
            <?= strtoupper(substr($c['ag_name'], 0, 1)) ?>
        </div>
    </div>

    <div class="content">
        <?php if(isset($_SESSION['msg'])): ?>

            <div class="error-message">
                <?php
                    echo $_SESSION['msg'];
                    unset($_SESSION['msg']);
                ?>
            </div>
        
        <?php endif; ?>
        <?php if(isset($_SESSION['successMsg'])): ?>

            <div class="success-message">
                <?php
                    echo $_SESSION['successMsg'];
                    unset($_SESSION['successMsg']);
                ?>
            </div>
        
        <?php endif; ?>
        
        <!-- Profile Hero -->
        <div class="profile-hero">

            <!-- LEFT -->
            <div class="profile-left">
        
                <div class="avatar-lg">
                    <?= strtoupper(substr($c['ag_name'], 0, 1)) ?>
                </div>
        
                <div class="profile-info">
        
                    <h2><?= htmlspecialchars($c['ag_name']) ?></h2>
        
                    <div class="email">
                        <i class="fas fa-envelope"></i>
                        <?= !empty($c['ag_email']) ? htmlspecialchars($c['ag_email']) : 'No Email Found'; ?>
                    </div>
        
                    <span class="badge package">
                        <?= htmlspecialchars($c['mb']) ?> Package
                    </span>
        
                    <span class="badge status">
                        <span class="status-dot"></span>
                        <?= ($c['ag_status'] == 1) ? 'Active' : 'Inactive'; ?>
                    </span>
                    <span class="badge package">
                        Disconnect Date: <?= htmlspecialchars($c['mikrotik_disconnect']) ?>
                    </span>
        
                </div>
        
            </div>
        
            <!-- RIGHT -->
            <div class="bill-box">

                <?php if($type == 'reseller'){ ?>

                    <div class="bill-title">Current Balance</div>
                    <h2 style="color:white">
                        <?= number_format($c['available_taka'],2) ?> TK
                    </h2>
                    
                    <?php } ?>
                    
                    <div class="bill-title">Monthly Bill</div>
                    
                    <form method="POST"
                          action="update_bill.php"
                          class="bill-form"
                          onsubmit="return checkBill()">
                    
                        <input type="number"
                               id="monthly_bill"
                               name="monthly_bill"
                               value="<?= htmlspecialchars($c['taka']) ?>">
                    
                        <button type="submit">Payment</button>
                    
                    </form>
                    
                    
    
                </div>

        
            </div>
        <!-- Stats -->
        <div class="stats-row">

            <!-- Member Since -->
            <div class="stat-card">

                <div class="stat-icon"
                     style="background:#eff6ff; color:#2563eb;">

                    <i class="fas fa-calendar-check"></i>

                </div>

                <div>
                    <h3>
                        <?= date('Y', strtotime($c['connection_date'])) ?>
                    </h3>

                    <p>Connected Since</p>
                </div>

            </div>

            <!-- Internet Package -->
            <div class="stat-card">

                <div class="stat-icon"
                     style="background:#f0fdf4; color:#16a34a;">

                    <i class="fas fa-wifi"></i>

                </div>

                <div>

                    <h3><?= htmlspecialchars($c['mb']) ?></h3>

                    <p>Internet Speed</p>

                </div>

            </div>

            <!-- Customer ID -->
            <div class="stat-card">

                <div class="stat-icon"
                     style="background:#fef9c3; color:#ca8a04;">

                    <i class="fas fa-id-badge"></i>

                </div>

                <div>

                    <h3>
                        #<?= str_pad($c['ag_id'], 4, '0', STR_PAD_LEFT) ?>
                    </h3>

                    <p>Customer ID</p>

                </div>

            </div>

        </div>

        <!-- Details -->
        <div class="details-grid">

            <!-- Personal Info -->
            <div class="detail-card">

                <h3>
                    <i class="fas fa-user"></i>
                    Personal Information
                </h3>

                <!-- Name -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-user"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Full Name
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($c['ag_name']) ?>
                        </div>

                    </div>
                </div>

                <!-- Email -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-envelope"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Email
                        </div>

                        <div class="detail-value">

                            <?= !empty($c['ag_email'])
                                ? htmlspecialchars($c['ag_email'])
                                : 'Not Provided'; ?>

                        </div>

                    </div>
                </div>

                <!-- Phone -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-phone"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Phone
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($c['ag_mobile_no']) ?>
                        </div>

                    </div>
                </div>

                <!-- Gender -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-venus-mars"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Gender
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($c['gender']) ?>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Account Info -->
            <div class="detail-card">

                <h3>
                    <i class="fas fa-shield-halved"></i>
                    Account Details
                </h3>

                <!-- Customer ID -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-hashtag"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Customer ID
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($c['cus_id']) ?>
                        </div>

                    </div>
                </div>

                <!-- Package -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-wifi"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Package
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($c['mb']) ?>
                        </div>

                    </div>
                </div>

                <!-- Connection Type -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Connection Type
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($c['connectiontype']) ?>
                        </div>

                    </div>
                </div>

                <!-- Join Date -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-calendar"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Connection Date
                        </div>

                        <div class="detail-value">
                            <?= date('d M Y', strtotime($c['connection_date'])) ?>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Address -->
            <div class="detail-card full-width">

                <h3>
                    <i class="fas fa-location-dot"></i>
                    Address Information
                </h3>

                <!-- Address -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-map-pin"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Office Address
                        </div>

                        <div class="detail-value">

                            <?= !empty($c['ag_office_address'])
                                ? htmlspecialchars($c['ag_office_address'])
                                : 'No Address Found'; ?>

                        </div>

                    </div>
                </div>

                <!-- Status -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-circle-check"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Account Status
                        </div>

                        <div class="detail-value"
                             style="color:#16a34a; font-weight:600;">

                            ● <?= ($c['ag_status'] == 1)
                                ? 'Active'
                                : 'Inactive'; ?>

                        </div>

                    </div>
                </div>

                <!-- Last Login -->
                <div class="detail-row">

                    <div class="detail-icon">
                        <i class="fas fa-clock"></i>
                    </div>

                    <div>

                        <div class="detail-label">
                            Last Login
                        </div>

                        <div class="detail-value">
                            <?= date('d M Y, h:i A') ?>
                        </div>

                    </div>
                </div>

            </div>

        </div>
      

    </div>

</div>

</body>
</html>

<?php
if($type == 'reseller'){
    $monthly_bill = $c['taka'];
    $total_days = date('t');
    $per_day_bill = round($monthly_bill / $total_days);
    ?>
    
    <script>
        function checkBill() {
    
        let billAmount = <?= round($per_day_bill, 2) ?>;
        let inputAmount = parseFloat(document.getElementById('monthly_bill').value);
    
        if (inputAmount < billAmount) {
            alert('কমপক্ষে ১ দিনের বিল (' + billAmount + ' টাকা) পরিশোধ করতে হবে!');
            return false;
        }
    
        return true;
    }
    </script>
    
<?php } ?>

