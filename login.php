<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>GovConnect — Secure Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-dark: #0f172a;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.1);
            --text-muted: #94a3b8;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 41, 59, 1) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #f8fafc;
        }

        .container {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 24px;
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Left Side: Form Area */
        .form-box {
            padding: 60px;
            background: rgba(15, 23, 42, 0.6);
        }

        .form-header { margin-bottom: 32px; }
        .form-header h2 { font-size: 28px; font-weight: 700; margin: 0 0 8px; }
        .form-header p { color: var(--text-muted); font-size: 14px; }

        .row { margin-bottom: 20px; position: relative; }
        .row i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

        .control {
            width: 100%;
            padding: 14px 14px 14px 45px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .control:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: #fff;
            transition: 0.3s;
            font-size: 16px;
            margin-top: 10px;
        }

        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); }

        .forgot { text-align: center; margin-top: 20px; }
        .forgot a { color: var(--text-muted); text-decoration: none; font-size: 14px; }
        .forgot a:hover { color: #fff; }

        /* Right Side: Info Area */
        .info-box {
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid var(--border);
        }

        .role-selector {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 30px 0;
        }

        .role-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: #fff;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.3s;
        }

        .role-btn i { width: 20px; color: var(--text-muted); }
        .role-btn:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.3); }
        .role-btn.active { background: rgba(37, 99, 235, 0.1); border-color: var(--primary); }
        .role-btn.active i { color: var(--primary); }

        .message { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }

        .register-link { text-align: center; margin-top: auto; font-size: 14px; color: var(--text-muted); }
        .register-link a { color: var(--primary); text-decoration: none; font-weight: 600; }

        .hidden { display: none; }

        @media(max-width: 900px) {
            .container { grid-template-columns: 1fr; }
            .info-box { border-left: none; border-top: 1px solid var(--border); padding: 40px; }
            .form-box { padding: 40px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-box">
        <?php if($error): ?>
            <div class="message error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
        <?php endif; ?>

        <form id="userForm" class="auth-form" method="post" action="/govconnect/login_process.php">
            <input type="hidden" name="role" value="user"/>
            <div class="form-header">
                <h2>Citizen Login</h2>
                <p>Report and track local government issues.</p>
            </div>
            <div class="row"><i class="fa-solid fa-envelope"></i><input class="control" type="email" name="email" placeholder="Email Address" required></div>
            <div class="row"><i class="fa-solid fa-lock"></i><input class="control" type="password" name="password" placeholder="Password" required></div>
            <button type="submit" class="btn">Sign In</button>
        </form>

        <form id="adminForm" class="auth-form hidden" method="post" action="/govconnect/login_process.php">
            <input type="hidden" name="role" value="admin"/>
            <div class="form-header">
                <h2>Administration</h2>
                <p>System oversight and management portal.</p>
            </div>
            <div class="row"><i class="fa-solid fa-user-shield"></i><input class="control" type="email" name="email" placeholder="Admin Email" required></div>
            <div class="row"><i class="fa-solid fa-lock"></i><input class="control" type="password" name="password" placeholder="Password" required></div>
            <button type="submit" class="btn">Admin Access</button>
        </form>

        <form id="responseForm" class="auth-form hidden" method="post" action="/govconnect/login_process.php">
            <input type="hidden" name="role" value="response"/>
            <div class="form-header">
                <h2>Response Team</h2>
                <p>Field operative and dispatcher portal.</p>
            </div>
            <div class="row"><i class="fa-solid fa-truck-pickup"></i><input class="control" type="email" name="email" placeholder="Official Email" required></div>
            <div class="row"><i class="fa-solid fa-lock"></i><input class="control" type="password" name="password" placeholder="Password" required></div>
            <button type="submit" class="btn">Team Login</button>
        </form>

        <div class="forgot"><a href="/govconnect/forgot_password.php">Forgot your credentials?</a></div>
    </div>

    <div class="info-box">
        <h3 style="margin:0 0 10px; font-size: 18px;">Select Portal</h3>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Choose your account type to proceed to the secure dashboard.</p>
        
        <div class="role-selector">
            <button class="role-btn active" id="btnUser" onclick="switchRole('user')">
                <i class="fa-solid fa-user"></i>
                <span>Citizen Portal</span>
            </button>
            <button class="role-btn" id="btnAdmin" onclick="switchRole('admin')">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Administration</span>
            </button>
            <button class="role-btn" id="btnResponse" onclick="switchRole('response')">
                <i class="fa-solid fa-shuttle-space"></i>
                <span>Response Team</span>
            </button>
        </div>

        <div class="register-link">
            New to the platform? <a href="/govconnect/register.php">Create account</a>
        </div>
    </div>
</div>

<script>
    function switchRole(role) {
        // Hide all forms
        document.querySelectorAll('.auth-form').forEach(f => f.classList.add('hidden'));
        // Remove active class from all buttons
        document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
        
        // Show selected
        if(role === 'user') {
            document.getElementById('userForm').classList.remove('hidden');
            document.getElementById('btnUser').classList.add('active');
        } else if(role === 'admin') {
            document.getElementById('adminForm').classList.remove('hidden');
            document.getElementById('btnAdmin').classList.add('active');
        } else if(role === 'response') {
            document.getElementById('responseForm').classList.remove('hidden');
            document.getElementById('btnResponse').classList.add('active');
        }
        
        localStorage.setItem('activeRole', role);
    }

    // Init on load
    window.onload = () => {
        const savedRole = localStorage.getItem('activeRole') || 'user';
        switchRole(savedRole);
    }
</script>

</body>
</html>
