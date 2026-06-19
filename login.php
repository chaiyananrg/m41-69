<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ ถ้ามี session อยู่แล้ว ข้ามหน้า login ไปเลย
if (isset($_SESSION['user_id'])) {
    header('Location: index');
    exit;
}

// ✅ ถ้ามี cookie จดจำ 30 วัน ให้กู้คืน session และข้ามหน้า login
if (!isset($_SESSION['user_id']) && isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $parts = explode('|', base64_decode($token));
    if (count($parts) === 2) {
        $_SESSION['user_id']   = $parts[0];
        $_SESSION['user_name'] = $parts[1];
        header('Location: index');
        exit;
    }
}

require_once '../db.php';

define('REAL_API_URL', 'https://ismp13.onrender.com/recognize');
define('MY_API_KEY', 'my_secret_key_12345');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'process_face') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์ภาพจากกล้อง']);
        exit;
    }

    $ch = curl_init();
    $cfile = curl_file_create($_FILES['file']['tmp_name'], $_FILES['file']['type'], $_FILES['file']['name']);
    $post_data = ['file' => $cfile, 'api_key' => MY_API_KEY];

    curl_setopt($ch, CURLOPT_URL, REAL_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $api_result = json_decode($response, true);
        $student_id = isset($api_result['id']) ? trim($api_result['id']) : '';

        if (!empty($student_id)) {
            try {
                $stmt = $conn->prepare("SELECT fullname FROM students WHERE student_id = :student_id LIMIT 1");
                $stmt->bindParam(':student_id', $student_id);
                $stmt->execute();
                $student_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($student_data) {
                    echo json_encode([
                        'status' => 'success',
                        'student_id' => $student_id,
                        'fullname' => $student_data['fullname']
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => "สแกนผ่านแต่ไม่พบรหัส $student_id ในฐานข้อมูล"]);
                }
            } catch (PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'ฐานข้อมูลขัดข้อง: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'API ไม่สามารถระบุตัวตนจากภาพถ่ายตรงนี้ได้']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ปฏิเสธสิทธิ์ หรือไม่พบใบหน้านี้ในระบบ']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approved_id'])) {
    $uid  = $_POST['approved_id'];
    $name = $_POST['approved_name'];

    $_SESSION['user_id']   = $uid;
    $_SESSION['user_name'] = $name;

    // 🍪 บันทึก cookie จดจำ 30 วัน (token เข้ารหัส base64)
    $token   = base64_encode($uid . '|' . $name);
    $expires = time() + (30 * 24 * 60 * 60); // 30 วัน
    setcookie('auth_token', $token, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    header('Location: index');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Biometric Auth</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
    // ── LINE in-app browser detection & redirect ──
    (function() {
        var ua = navigator.userAgent || '';
        var isLine = /Line\//.test(ua);
        if (!isLine) return; // not LINE browser — proceed normally

        // We are inside LINE WebView — camera is blocked.
        // Strategy: show an overlay prompting user to open in external browser.
        // On Android: use intent:// to force Chrome
        // On iOS: use openExternalBrowser=1 LINE URL scheme trick
        document.addEventListener('DOMContentLoaded', function() {
            var isIOS = /iP(hone|ad|od)/.test(ua);
            var isAndroid = /Android/.test(ua);
            var currentURL = window.location.href;

            // Build open-in-browser URL
            var externalURL = currentURL;
            if (isAndroid) {
                // intent scheme forces Chrome on Android
                externalURL = 'intent://' + currentURL.replace(/^https?:\/\//, '') +
                    '#Intent;scheme=https;package=com.android.chrome;end';
            } else if (isIOS) {
                // LINE iOS: append openExternalBrowser param
                var sep = currentURL.indexOf('?') >= 0 ? '&' : '?';
                externalURL = currentURL + sep + 'openExternalBrowser=1';
            }

            // Inject overlay
            var overlay = document.createElement('div');
            overlay.id = 'line-overlay';
            overlay.style.cssText = [
                'position:fixed;inset:0;z-index:99999',
                'background:linear-gradient(135deg,#0a0e1a 0%,#0d1530 100%)',
                'display:flex;flex-direction:column;align-items:center;justify-content:center',
                'padding:32px;text-align:center;font-family:IBM Plex Sans Thai,sans-serif'
            ].join(';');

            overlay.innerHTML = [
                '<div style="width:80px;height:80px;border-radius:50%;background:rgba(59,130,246,0.12);',
                'border:2px solid rgba(59,130,246,0.3);display:flex;align-items:center;',
                'justify-content:center;margin-bottom:24px;">',
                '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">',
                '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>',
                '<circle cx="12" cy="13" r="4"/></svg></div>',

                '<p style="color:#e2e8f0;font-size:18px;font-weight:700;margin:0 0 8px">',
                'ต้องเปิดในเบราว์เซอร์ภายนอก</p>',

                '<p style="color:#94a3b8;font-size:14px;line-height:1.6;margin:0 0 28px">',
                'LINE ไม่อนุญาตให้เข้าถึงกล้องโดยตรง<br>',
                'กรุณาเปิดหน้านี้ในเบราว์เซอร์ Chrome หรือ Safari</p>',

                '<a id="line-open-btn" href="' + externalURL + '" style="',
                'display:inline-flex;align-items:center;gap:10px;',
                'background:linear-gradient(135deg,#3b82f6,#06b6d4);',
                'color:#fff;font-size:15px;font-weight:600;',
                'padding:14px 28px;border-radius:99px;text-decoration:none;',
                'box-shadow:0 4px 20px rgba(59,130,246,0.4);">',
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">',
                '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
                '<polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
                'เปิดในเบราว์เซอร์</a>',

                '<p style="color:#475569;font-size:12px;margin:20px 0 0">',
                'หรือกดปุ่ม ··· แล้วเลือก "เปิดในเบราว์เซอร์"</p>',

                isIOS ? '<img src="" onerror="" style="display:none" id="line-ios-hint">' +
                '<p style="color:#64748b;font-size:12px;margin:8px 0 0">สำหรับ iOS: แตะ ··· → Open in Safari</p>' : ''
            ].join('');

            document.body.appendChild(overlay);

            // On iOS, also try the LINE openExternalBrowser trick via meta
            if (isIOS) {
                var meta = document.createElement('meta');
                meta.name = 'al:web:url';
                meta.content = externalURL;
                document.head.appendChild(meta);
                // Auto-navigate after short delay for iOS LINE
                setTimeout(function() {
                    window.location.href = externalURL;
                }, 800);
            }
        });
    })();
    </script>
    <style>
        :root {
            --bg: #f0f4ff;
            --surface: #ffffff;
            --surface2: #f7f9ff;
            --border: rgba(59,130,246,0.1);
            --border2: rgba(59,130,246,0.15);
            --accent: #3b82f6;
            --accent-glow: rgba(59,130,246,0.25);
            --accent2: #06b6d4;
            --success: #10b981;
            --success-glow: rgba(16,185,129,0.2);
            --error: #f43f5e;
            --error-glow: rgba(244,63,94,0.15);
            --text: #0f172a;
            --text-muted: #64748b;
            --text-dim: #475569;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'IBM Plex Sans Thai', 'Space Grotesk', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient background glow */
        body::before {
            content: '';
            position: fixed;
            top: -30%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(59,130,246,0.12) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -20%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(6,182,212,0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Floating particles */
        .particle {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: floatParticle linear infinite;
            opacity: 0;
        }
        @keyframes floatParticle {
            0% { transform: translateY(110vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 0.4; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* Card */
        .card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 380px;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 28px;
            padding: 32px 28px;
            box-shadow: 0 0 0 1px rgba(59,130,246,0.08), 0 20px 60px rgba(59,130,246,0.1), 0 4px 20px rgba(0,0,0,0.06);
            animation: cardEntrance 0.7s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.2);
            color: #2563eb;
            animation: badgePulse 3s ease-in-out infinite;
        }
        @keyframes badgePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.2); }
            50% { box-shadow: 0 0 0 6px rgba(59,130,246,0); }
        }
        .badge-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #3b82f6;
            animation: dotBlink 1.5s ease-in-out infinite;
        }
        @keyframes dotBlink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Header */
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.5px;
            margin: 12px 0 6px;
            animation: slideUp 0.6s 0.2s cubic-bezier(0.16,1,0.3,1) both;
        }
        .header p {
            font-size: 13px;
            color: var(--text-muted);
            animation: slideUp 0.6s 0.3s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Instruction banner - ABOVE camera */
        .instruction-banner {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.18);
            margin-bottom: 16px;
            animation: bannerIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        .instruction-banner.visible { display: flex; }
        @keyframes bannerIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .instruction-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(59,130,246,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #2563eb;
        }
        .instruction-text-content {
            flex: 1;
        }
        .instruction-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #3b82f6;
            margin-bottom: 2px;
        }
        .instruction-msg {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }

        /* Direction arrows - banner style */
        .arrow-banner {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 14px 20px;
            border-radius: 14px;
            background: rgba(59,130,246,0.12);
            border: 1px solid rgba(59,130,246,0.25);
            margin-bottom: 16px;
        }
        .arrow-banner.visible { display: flex; animation: bannerIn 0.3s cubic-bezier(0.16,1,0.3,1) both; }
        .arrow-pulse {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #93c5fd;
        }
        .arrow-icon-left { animation: arrowMoveLeft 0.8s ease-in-out infinite; }
        .arrow-icon-right { animation: arrowMoveRight 0.8s ease-in-out infinite; }
        @keyframes arrowMoveLeft {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-8px); }
        }
        @keyframes arrowMoveRight {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(8px); }
        }
        .direction-track {
            display: flex;
            gap: 4px;
        }
        .direction-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(59,130,246,0.3);
        }
        .direction-dot.active { background: #3b82f6; animation: dotScale 0.8s ease-in-out infinite; }
        @keyframes dotScale {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.4); opacity: 1; }
        }

        /* Camera ring */
        .camera-wrap {
            display: none;
            position: relative;
            width: 220px;
            height: 220px;
            margin: 0 auto 20px;
        }
        .camera-wrap.visible { display: block; }
        .camera-ring {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: conic-gradient(from 0deg, #3b82f6, #06b6d4, #3b82f6);
            animation: ringRotate 3s linear infinite;
            z-index: 1;
        }
        @keyframes ringRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .camera-ring-mask {
            position: absolute;
            inset: 2px;
            border-radius: 50%;
            background: #ffffff;
            z-index: 2;
        }
        .camera-inner {
            position: absolute;
            inset: 6px;
            border-radius: 50%;
            overflow: hidden;
            background: #000;
            z-index: 3;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        /* Scanner line */
        .scanner-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(59,130,246,0.8), transparent);
            animation: scan 2.5s ease-in-out infinite;
            z-index: 4;
            display: none;
        }
        .camera-wrap.visible .scanner-line { display: block; }
        @keyframes scan {
            0% { top: 10%; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 90%; opacity: 0; }
        }

        /* Corner brackets */
        .corner-bracket {
            position: absolute;
            width: 20px;
            height: 20px;
            z-index: 5;
        }
        .corner-bracket::before, .corner-bracket::after {
            content: '';
            position: absolute;
            background: #3b82f6;
            opacity: 0.8;
        }
        .corner-bracket.tl { top: 8px; left: 8px; }
        .corner-bracket.tl::before { top: 0; left: 0; width: 100%; height: 2px; }
        .corner-bracket.tl::after { top: 0; left: 0; width: 2px; height: 100%; }
        .corner-bracket.tr { top: 8px; right: 8px; }
        .corner-bracket.tr::before { top: 0; right: 0; width: 100%; height: 2px; }
        .corner-bracket.tr::after { top: 0; right: 0; width: 2px; height: 100%; }
        .corner-bracket.bl { bottom: 8px; left: 8px; }
        .corner-bracket.bl::before { bottom: 0; left: 0; width: 100%; height: 2px; }
        .corner-bracket.bl::after { bottom: 0; left: 0; width: 2px; height: 100%; }
        .corner-bracket.br { bottom: 8px; right: 8px; }
        .corner-bracket.br::before { bottom: 0; right: 0; width: 100%; height: 2px; }
        .corner-bracket.br::after { bottom: 0; right: 0; width: 2px; height: 100%; }

        /* Alert box */
        .alert-box {
            display: none;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 16px;
            border-radius: 14px;
            font-size: 12.5px;
            margin-bottom: 16px;
            animation: alertIn 0.35s cubic-bezier(0.16,1,0.3,1) both;
        }
        .alert-box.visible { display: flex; }
        @keyframes alertIn {
            from { opacity: 0; transform: scale(0.97) translateY(4px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .alert-box.loading { background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.15); color: #93c5fd; }
        .alert-box.success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); color: #6ee7b7; }
        .alert-box.error { background: rgba(244,63,94,0.08); border: 1px solid rgba(244,63,94,0.18); color: #fda4af; }
        .alert-icon { flex-shrink: 0; margin-top: 1px; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* Verification panel */
        .verify-panel {
            display: none;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 16px;
            text-align: center;
            animation: panelIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }
        .verify-panel.visible { display: block; }
        @keyframes panelIn {
            from { opacity: 0; transform: translateY(16px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .verify-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(6,182,212,0.06));
            border: 1px solid rgba(16,185,129,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            color: #10b981;
            animation: avatarPop 0.5s 0.2s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes avatarPop {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .verify-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .verify-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 19px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.3px;
            margin-bottom: 6px;
        }
        .verify-id-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 50px;
            background: rgba(59,130,246,0.05);
            border: 1px solid rgba(59,130,246,0.15);
            font-size: 11px;
            font-family: 'Space Grotesk', monospace;
            color: var(--text-dim);
            margin-bottom: 16px;
        }
        .btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 13px 16px;
            border-radius: 13px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'IBM Plex Sans Thai', sans-serif;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.16,1,0.3,1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            position: relative;
            overflow: hidden;
        }
        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0;
            transition: opacity 0.2s;
            background: rgba(255,255,255,0.08);
        }
        .btn:hover::after { opacity: 1; }
        .btn:active { transform: scale(0.97); }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 4px 20px rgba(37,99,235,0.4), 0 0 0 1px rgba(59,130,246,0.3);
        }
        .btn-primary:hover { box-shadow: 0 6px 28px rgba(37,99,235,0.55), 0 0 0 1px rgba(59,130,246,0.4); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.4; transform: none; cursor: not-allowed; }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid rgba(59,130,246,0.2);
        }
        .btn-secondary:hover { color: var(--text); }

        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
            box-shadow: 0 4px 20px rgba(5,150,105,0.35);
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 4px 0 20px;
        }
        .divider-line { flex: 1; height: 1px; background: var(--border); }
        .divider-text { font-size: 10px; color: var(--text-muted); letter-spacing: 0.05em; }

        /* Step indicators */
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 20px;
            animation: slideUp 0.6s 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        .step-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--border2);
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        .step-dot.active {
            width: 22px;
            border-radius: 3px;
            background: #3b82f6;
        }
        .step-dot.done { background: #10b981; }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 16px;
            font-size: 10.5px;
            color: var(--text-muted);
        }
        .footer span { color: var(--text-dim); }
    </style>
</head>
<body>

    <!-- Floating particles -->
    <div id="particles"></div>

    <div class="card">

        <!-- Header -->
        <div class="header">
            <div class="badge">
                <div class="badge-dot"></div>
                Live Biometric Auth
            </div>
            <h1>ยืนยันตัวตน</h1>
            <p>สแกนใบหน้าเพื่อเข้าสู่ระบบ</p>
        </div>

        <!-- Step indicators -->
        <div class="steps">
            <div class="step-dot active" id="step1"></div>
            <div class="step-dot" id="step2"></div>
            <div class="step-dot" id="step3"></div>
        </div>

        <!-- Alert box -->
        <div class="alert-box" id="alert-box">
            <div class="alert-icon" id="alert-icon"></div>
            <span id="alert-text"></span>
        </div>

        <!-- Instruction banner (above camera) -->
        <div class="instruction-banner" id="instruction-banner">
            <div class="instruction-icon" id="instruction-icon">
                <i data-lucide="eye" style="width:16px;height:16px;"></i>
            </div>
            <div class="instruction-text-content">
                <div class="instruction-label">คำสั่งระบบ</div>
                <div class="instruction-msg" id="instruction-msg">กำลังเตรียมระบบ...</div>
            </div>
        </div>

        <!-- Direction arrow banner -->
        <div class="arrow-banner" id="arrow-banner-left">
            <div class="arrow-pulse">
                <i data-lucide="chevron-left" class="arrow-icon-left" style="width:22px;height:22px;color:#3b82f6;"></i>
                <i data-lucide="chevron-left" class="arrow-icon-left" style="width:18px;height:18px;color:rgba(59,130,246,0.5);animation-delay:0.15s;"></i>
            </div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;">หันใบหน้าไปทางซ้าย</div>
                <div style="font-size:11px;color:#64748b;margin-top:2px;">ช้าๆ แล้วกลับมามองตรง</div>
            </div>
            <div class="direction-track">
                <div class="direction-dot active" style="animation-delay:0s;"></div>
                <div class="direction-dot active" style="animation-delay:0.2s;"></div>
                <div class="direction-dot" style="animation-delay:0.4s;"></div>
            </div>
        </div>

        <div class="arrow-banner" id="arrow-banner-right">
            <div class="direction-track">
                <div class="direction-dot" style="animation-delay:0.4s;"></div>
                <div class="direction-dot active" style="animation-delay:0.2s;"></div>
                <div class="direction-dot active" style="animation-delay:0s;"></div>
            </div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;">หันใบหน้าไปทางขวา</div>
                <div style="font-size:11px;color:#64748b;margin-top:2px;">ช้าๆ แล้วกลับมามองตรง</div>
            </div>
            <div class="arrow-pulse">
                <i data-lucide="chevron-right" class="arrow-icon-right" style="width:18px;height:18px;color:rgba(59,130,246,0.5);animation-delay:0.15s;"></i>
                <i data-lucide="chevron-right" class="arrow-icon-right" style="width:22px;height:22px;color:#3b82f6;"></i>
            </div>
        </div>

        <!-- Motion progress bar -->
        <div class="motion-bar-wrap" id="motion-bar-wrap">
            <div class="motion-bar-label">
                <span id="motion-bar-dir">การตรวจจับการเคลื่อนไหว</span>
                <span id="motion-bar-pct">0%</span>
            </div>
            <div class="motion-bar-track">
                <div class="motion-bar-fill" id="motion-bar-fill"></div>
            </div>
        </div>

        <!-- Camera -->
        <div class="camera-wrap" id="camera-container">
            <div class="camera-ring"></div>
            <div class="camera-ring-mask"></div>
            <div class="camera-inner">
                <video id="webcam" autoplay playsinline></video>
                <canvas id="holo-canvas"></canvas>
                <div class="scanner-line"></div>
            </div>
            <!-- SVG progress ring around camera -->
            <svg class="rotate-ring-svg" id="rotate-ring-svg" viewBox="0 0 240 240">
                <circle class="ring-track" cx="120" cy="120" r="112"/>
                <circle class="ring-fill"  id="ring-fill-el" cx="120" cy="120" r="112"
                        stroke-dasharray="703.7" stroke-dashoffset="703.7"
                        transform="rotate(-90 120 120)"/>
            </svg>
            <div class="corner-bracket tl"></div>
            <div class="corner-bracket tr"></div>
            <div class="corner-bracket bl"></div>
            <div class="corner-bracket br"></div>
        </div>

        <!-- Verification result -->
        <div class="verify-panel" id="verification-panel">
            <div class="verify-avatar">
                <i data-lucide="user-check" style="width:24px;height:24px;"></i>
            </div>
            <div class="verify-label">ผลการตรวจสอบ</div>
            <div class="verify-name" id="student-name-label">-</div>
            <div class="verify-id-badge">
                <i data-lucide="hash" style="width:10px;height:10px;"></i>
                <span id="student-id-label"></span>
            </div>
            <form action="login.php" method="POST" class="btn-row">
                <input type="hidden" name="approved_id" id="form-student-id">
                <input type="hidden" name="approved_name" id="form-student-name">
                <button type="button" onclick="restartScanProcess()" class="btn btn-secondary">
                    <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> สแกนใหม่
                </button>
                <button type="submit" class="btn btn-success">
                    <i data-lucide="log-in" style="width:14px;height:14px;"></i> เข้าสู่ระบบ
                </button>
            </form>
        </div>

        <!-- Controls -->
        <div id="control-zone">
            <button id="start-stream-btn" onclick="openWebcam()" class="btn btn-primary">
                <i data-lucide="camera" style="width:16px;height:16px;"></i> เริ่มสแกนใบหน้า
            </button>

        </div>

        <div class="footer">
            ปลอดภัยด้วย <span>Biometric Liveness Detection</span>
        </div>
    </div>

    <canvas id="snapshot-canvas" style="display:none;"></canvas>

    <style>
        /* Countdown overlay */
        .countdown-overlay {
            position: absolute;
            inset: 6px;
            border-radius: 50%;
            background: rgba(0,0,0,0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .countdown-overlay.visible { opacity: 1; }
        .countdown-number {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 72px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            animation: countPop 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes countPop {
            from { transform: scale(1.6); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        /* Hologram mesh canvas overlay */
        #holo-canvas {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            z-index: 6;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s;
        }
        #holo-canvas.visible { opacity: 1; }
        /* SVG progress ring around camera */
        .rotate-ring-svg {
            position: absolute;
            inset: -10px;
            z-index: 7;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s;
        }
        .rotate-ring-svg.visible { opacity: 1; }
        .ring-track { fill: none; stroke: rgba(59,130,246,0.15); stroke-width: 4; }
        .ring-fill  { fill: none; stroke: #3b82f6; stroke-width: 4;
                      stroke-linecap: round; transition: stroke-dashoffset 0.2s ease-out; }
        .ring-fill.done { stroke: #10b981; }
        /* Front-face step */
        .frontface-panel {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 14px 18px;
            background: rgba(16,185,129,0.07);
            border: 1px solid rgba(16,185,129,0.2);
            border-radius: 16px;
            margin-bottom: 12px;
            animation: bannerIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        .frontface-panel.visible { display: flex; }
        .frontface-title { font-size: 14px; font-weight: 700; color: #065f46; }
        .frontface-sub   { font-size: 12px; color: #10b981; }
        /* Motion progress bar */
        .motion-bar-wrap {
            display: none;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 12px;
        }
        .motion-bar-wrap.visible { display: flex; }
        .motion-bar-label {
            font-size: 11px; font-weight: 600; color: var(--text-muted);
            display: flex; justify-content: space-between;
        }
        .motion-bar-track {
            width: 100%; height: 8px;
            background: rgba(59,130,246,0.1);
            border-radius: 99px; overflow: hidden;
        }
        .motion-bar-fill {
            height: 100%; width: 0%;
            border-radius: 99px;
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
            transition: width 0.12s ease-out, background 0.3s;
        }
        .motion-bar-fill.done { background: linear-gradient(90deg, #10b981, #059669); }
    </style>

    <script>
        // Particles
        (function() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 12; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                const size = Math.random() * 3 + 1;
                const colors = ['rgba(59,130,246,0.35)', 'rgba(6,182,212,0.3)', 'rgba(16,185,129,0.25)'];
                p.style.cssText = `
                    width: ${size}px;
                    height: ${size}px;
                    left: ${Math.random() * 100}%;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    animation-duration: ${8 + Math.random() * 12}s;
                    animation-delay: ${Math.random() * 10}s;
                `;
                container.appendChild(p);
            }
        })();

        lucide.createIcons();

        // DOM refs
        const videoEl        = document.getElementById('webcam');
        const canvasEl       = document.getElementById('snapshot-canvas');
        const startBtn       = document.getElementById('start-stream-btn');
        const livenessBtn    = document.getElementById('liveness-check-btn');
        const cameraWrap     = document.getElementById('camera-container');
        const instructionBanner = document.getElementById('instruction-banner');
        const instructionMsg = document.getElementById('instruction-msg');
        const arrowLeft      = document.getElementById('arrow-banner-left');
        const arrowRight     = document.getElementById('arrow-banner-right');
        const alertBox       = document.getElementById('alert-box');
        const alertIcon      = document.getElementById('alert-icon');
        const alertText      = document.getElementById('alert-text');
        const verifyPanel    = document.getElementById('verification-panel');
        const nameLabel      = document.getElementById('student-name-label');
        const idLabel        = document.getElementById('student-id-label');
        const formId         = document.getElementById('form-student-id');
        const formName       = document.getElementById('form-student-name');
        const step1          = document.getElementById('step1');
        const step2          = document.getElementById('step2');
        const step3          = document.getElementById('step3');

        // Inject countdown overlay into camera-inner
        const cameraInner = document.querySelector('.camera-inner');
        const countdownOverlay = document.createElement('div');
        countdownOverlay.className = 'countdown-overlay';
        countdownOverlay.innerHTML = '<span class="countdown-number" id="count-num"></span>';
        cameraInner.appendChild(countdownOverlay);
        const countNum = document.getElementById('count-num');

        // Inject front-face panel above controls
        const controlZone = document.getElementById('control-zone');
        const frontfacePanel = document.createElement('div');
        frontfacePanel.className = 'frontface-panel';
        frontfacePanel.id = 'frontface-panel';
        frontfacePanel.innerHTML = `
            <div class="frontface-title">✅ ผ่าน Liveness แล้ว</div>
            <div class="frontface-sub">มองตรงเข้ากล้อง กำลังถ่ายภาพหน้าตรง...</div>
        `;
        controlZone.parentNode.insertBefore(frontfacePanel, controlZone);

        let stream         = null;
        let motionInterval = null;
        let prevFrame      = null;

        function setStep(n) {
            [step1, step2, step3].forEach((d, i) => {
                d.className = 'step-dot';
                if (i + 1 < n) d.classList.add('done');
                else if (i + 1 === n) d.classList.add('active');
            });
        }

        function setAlert(type, msg) {
            alertBox.className = 'alert-box';
            if (!msg) return;
            alertBox.classList.add('visible', type);
            const icons = { loading: 'loader', error: 'alert-triangle', success: 'check-circle' };
            alertIcon.innerHTML = `<i data-lucide="${icons[type]}" style="width:16px;height:16px;" class="${type === 'loading' ? 'spin' : ''}"></i>`;
            alertText.textContent = msg;
            lucide.createIcons();
        }

        function showInstruction(msg, icon = 'eye') {
            instructionBanner.classList.add('visible');
            instructionMsg.textContent = msg;
            document.getElementById('instruction-icon').innerHTML = `<i data-lucide="${icon}" style="width:16px;height:16px;"></i>`;
            lucide.createIcons();
        }
        function hideInstruction() { instructionBanner.classList.remove('visible'); }

        // ── STEP 1: Open webcam, wait until video is playing, then countdown ──
        async function openWebcam() {
            setAlert('', '');
            startBtn.disabled = true;
            startBtn.style.opacity = '0.5';
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 400 }, height: { ideal: 400 } }
                });
                videoEl.srcObject = stream;
                cameraWrap.classList.add('visible');
                startBtn.style.display = 'none';
                showInstruction('กำลังเปิดกล้อง...', 'eye');
                setStep(2);

                // Wait until video has real frames before starting countdown
                await new Promise((resolve, reject) => {
                    const timeout = setTimeout(() => reject(new Error('video_timeout')), 8000);
                    videoEl.onloadedmetadata = () => {
                        videoEl.play().then(() => {
                            clearTimeout(timeout);
                            resolve();
                        }).catch(reject);
                    };
                    videoEl.onerror = () => { clearTimeout(timeout); reject(new Error('video_error')); };
                });

                showInstruction('มองตรงนิ่งๆ กำลังนับถอยหลัง...', 'eye');
                startMotionTracker();
                startCountdown(3, () => triggerInteractiveChallenge());

            } catch (e) {
                stopTracks();
                cameraWrap.classList.remove('visible');
                startBtn.style.display = 'flex';
                startBtn.disabled = false;
                startBtn.style.opacity = '1';
                var isLine = /Line\//.test(navigator.userAgent || '');
                if (isLine) {
                    setAlert('error', 'LINE ไม่รองรับกล้อง — กรุณากดปุ่ม ··· แล้วเลือก "เปิดในเบราว์เซอร์"');
                } else if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                    setAlert('error', 'กรุณาอนุญาตการเข้าถึงกล้องในเบราว์เซอร์แล้วลองใหม่');
                } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
                    setAlert('error', 'ไม่พบกล้องในอุปกรณ์นี้ กรุณาเชื่อมต่อกล้องแล้วลองใหม่');
                } else if (e.message === 'video_timeout') {
                    setAlert('error', 'กล้องใช้เวลานานเกินไป กรุณาลองใหม่');
                } else {
                    setAlert('error', 'ไม่สามารถเปิดกล้องได้: ' + (e.message || e.name));
                }
            }
        }

        function startCountdown(seconds, callback) {
            let remaining = seconds;
            countdownOverlay.classList.add('visible');
            // Force re-animation each tick
            function tick() {
                countNum.style.animation = 'none';
                countNum.offsetHeight; // reflow
                countNum.style.animation = '';
                countNum.textContent = remaining;
                if (remaining <= 0) {
                    countdownOverlay.classList.remove('visible');
                    callback();
                    return;
                }
                remaining--;
                setTimeout(tick, 1000);
            }
            tick();
        }

        // ════════════════════════════════════════════════
        // HOLOGRAM MESH + 360° ROTATION LIVENESS ENGINE
        // ════════════════════════════════════════════════
        const SAMPLE_W = 80, SAMPLE_H = 80;
        // Total motion accumulator — we only need raw total for 360° spin detection
        let totalMotion = 0;
        const THRESHOLD_TOTAL = 4000; // pixels-changed required to pass
        const CHALLENGE_MS    = 5000; // 5 second window

        let progressInterval = null;
        let holoRafId = null;
        let holoCanvas = null, holoCtx = null;

        // ── Hologram mesh renderer ──
        function startHoloMesh(cvs) {
            holoCanvas = cvs;
            holoCtx = cvs.getContext('2d');
            let t = 0;
            const nodes = [];
            const W = cvs.offsetWidth || 210, H = cvs.offsetHeight || 210;
            cvs.width = W; cvs.height = H;
            // Generate mesh nodes (polar grid inside circle)
            for (let ring = 1; ring <= 5; ring++) {
                const r = (ring / 5.5) * (W * 0.46);
                const count = ring * 6;
                for (let i = 0; i < count; i++) {
                    const a = (i / count) * Math.PI * 2;
                    nodes.push({ x: W/2 + Math.cos(a)*r, y: H/2 + Math.sin(a)*r,
                                 ox: W/2 + Math.cos(a)*r, oy: H/2 + Math.sin(a)*r,
                                 phase: Math.random()*Math.PI*2, speed: 0.4+Math.random()*0.8 });
                }
            }

            function drawFrame() {
                holoCtx.clearRect(0, 0, W, H);
                // Clip to circle
                holoCtx.save();
                holoCtx.beginPath();
                holoCtx.arc(W/2, H/2, W/2 - 2, 0, Math.PI*2);
                holoCtx.clip();

                t += 0.025;
                // Animate node positions slightly
                nodes.forEach(n => {
                    n.x = n.ox + Math.sin(t * n.speed + n.phase) * 3;
                    n.y = n.oy + Math.cos(t * n.speed + n.phase) * 3;
                });

                // Draw edges between nearby nodes
                const EDGE_DIST = W * 0.22;
                holoCtx.lineWidth = 0.7;
                for (let i = 0; i < nodes.length; i++) {
                    for (let j = i+1; j < nodes.length; j++) {
                        const dx = nodes[i].x - nodes[j].x;
                        const dy = nodes[i].y - nodes[j].y;
                        const d = Math.sqrt(dx*dx + dy*dy);
                        if (d < EDGE_DIST) {
                            const alpha = (1 - d/EDGE_DIST) * 0.55;
                            // Cycle hue: cyan → blue → green
                            const hue = (200 + Math.sin(t*0.7 + i*0.1)*40) % 360;
                            holoCtx.strokeStyle = `hsla(${hue},100%,65%,${alpha})`;
                            holoCtx.beginPath();
                            holoCtx.moveTo(nodes[i].x, nodes[i].y);
                            holoCtx.lineTo(nodes[j].x, nodes[j].y);
                            holoCtx.stroke();
                        }
                    }
                }
                // Draw nodes
                nodes.forEach((n, i) => {
                    const hue = (190 + Math.sin(t + i*0.2)*50) % 360;
                    const alpha = 0.5 + 0.5*Math.sin(t*n.speed + n.phase);
                    holoCtx.beginPath();
                    holoCtx.arc(n.x, n.y, 1.5, 0, Math.PI*2);
                    holoCtx.fillStyle = `hsla(${hue},100%,75%,${alpha})`;
                    holoCtx.fill();
                });

                // Scan line sweep
                const scanY = ((t * 30) % (H + 20)) - 10;
                const grad = holoCtx.createLinearGradient(0, scanY-4, 0, scanY+4);
                grad.addColorStop(0, 'transparent');
                grad.addColorStop(0.5, 'rgba(99,220,255,0.25)');
                grad.addColorStop(1, 'transparent');
                holoCtx.fillStyle = grad;
                holoCtx.fillRect(0, scanY-4, W, 8);

                holoCtx.restore();
                holoRafId = requestAnimationFrame(drawFrame);
            }
            drawFrame();
        }

        function stopHoloMesh() {
            if (holoRafId) { cancelAnimationFrame(holoRafId); holoRafId = null; }
            if (holoCtx && holoCanvas) {
                holoCtx.clearRect(0, 0, holoCanvas.width, holoCanvas.height);
            }
        }

        // ── Motion tracker (total motion only — direction doesn't matter) ──
        function startMotionTracker() {
            const ctx = canvasEl.getContext('2d');
            canvasEl.width = SAMPLE_W; canvasEl.height = SAMPLE_H;
            motionInterval = setInterval(() => {
                if (!stream || videoEl.paused) return;
                ctx.drawImage(videoEl, 0, 0, SAMPLE_W, SAMPLE_H);
                const frame = ctx.getImageData(0, 0, SAMPLE_W, SAMPLE_H).data;
                if (prevFrame) {
                    let delta = 0;
                    for (let i = 0; i < frame.length; i += 4) {
                        const lum  = 0.299*frame[i]   + 0.587*frame[i+1]   + 0.114*frame[i+2];
                        const lumP = 0.299*prevFrame[i]+ 0.587*prevFrame[i+1]+ 0.114*prevFrame[i+2];
                        if (Math.abs(lum - lumP) > 15) delta++;
                    }
                    totalMotion += delta;
                }
                prevFrame = new Uint8ClampedArray(frame);
            }, 80);
        }

        // ── LIVENESS: 360° rotation challenge ──
        function triggerInteractiveChallenge() {
            totalMotion = 0;
            prevFrame = null;
            arrowLeft.classList.remove('visible');
            arrowRight.classList.remove('visible');
            setStep(3);

            // Show hologram overlay
            const holoEl = document.getElementById('holo-canvas');
            const ringSvg = document.getElementById('rotate-ring-svg');
            const ringFill = document.getElementById('ring-fill-el');
            const circumference = 703.7;

            holoEl.classList.add('visible');
            ringSvg.classList.add('visible');
            startHoloMesh(holoEl);

            showInstruction('หมุนหน้าช้าๆ รอบๆ ซ้าย-ขวา-กลับมาตรง', 'rotate-cw');

            const barWrap = document.getElementById('motion-bar-wrap');
            const barFill = document.getElementById('motion-bar-fill');
            const barPct  = document.getElementById('motion-bar-pct');
            const barDir  = document.getElementById('motion-bar-dir');
            barDir.textContent = 'ตรวจจับการเคลื่อนไหวรอบๆ';
            barFill.className = 'motion-bar-fill';
            barFill.style.width = '0%';
            barPct.textContent = '0%';
            barWrap.classList.add('visible');

            // Update ring + bar in real time
            progressInterval = setInterval(() => {
                const pct = Math.min(100, Math.round((totalMotion / THRESHOLD_TOTAL) * 100));
                barFill.style.width = pct + '%';
                barPct.textContent = pct + '%';
                // SVG ring: dashoffset from full → 0
                const offset = circumference * (1 - pct/100);
                ringFill.style.strokeDashoffset = offset;
                if (pct >= 100) {
                    barFill.classList.add('done');
                    ringFill.classList.add('done');
                }
            }, 100);

            setTimeout(() => {
                clearInterval(progressInterval);
                stopHoloMesh();
                holoEl.classList.remove('visible');
                ringSvg.classList.remove('visible');
                ringFill.classList.remove('done');
                barWrap.classList.remove('visible');

                const passed = totalMotion >= THRESHOLD_TOTAL;

                if (!passed) {
                    const got = Math.round((totalMotion/THRESHOLD_TOTAL)*100);
                    setAlert('error', `ตรวจจับการเคลื่อนไหวได้ ${got}% กรุณาหมุนหน้าให้มากและช้าลง`);
                    showInstruction('ไม่ผ่าน — หมุนหน้าให้ครบรอบกว่านี้', 'alert-triangle');
                    stopTracks();
                    cameraWrap.classList.remove('visible');
                    startBtn.style.display = 'flex';
                    startBtn.disabled = false;
                    startBtn.style.opacity = '1';
                    setStep(1);
                    return;
                }
                // Passed → take front-face photo
                showInstruction('ผ่านแล้ว! มองตรงเข้ากล้อง กำลังถ่ายภาพหน้าตรง...', 'check-circle');
                document.getElementById('frontface-panel').classList.add('visible');
                startCountdown(2, captureFrontFaceAndSend);
            }, CHALLENGE_MS);
        }

        // ── STEP 3: Capture front face → send to real API ──
        function captureFrontFaceAndSend() {
            document.getElementById('frontface-panel').classList.remove('visible');
            hideInstruction();
            setAlert('loading', 'กำลังส่งภาพหน้าตรงไปยัง API เพื่อตรวจสอบ...');

            canvasEl.width  = videoEl.videoWidth;
            canvasEl.height = videoEl.videoHeight;
            const ctx = canvasEl.getContext('2d');
            ctx.translate(canvasEl.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

            stopTracks();
            cameraWrap.classList.remove('visible');

            canvasEl.toBlob(blob => {
                const formData = new FormData();
                formData.append('file', blob, 'frontface.jpg');
                fetch('login.php?action=process_face', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            setAlert('success', 'ยืนยันตัวตนสำเร็จ! โปรดตรวจสอบรายชื่อแล้วกดเข้าสู่ระบบ');
                            nameLabel.textContent = data.fullname;
                            idLabel.textContent   = data.student_id;
                            formId.value          = data.student_id;
                            formName.value        = data.fullname;
                            verifyPanel.classList.add('visible');
                            lucide.createIcons();
                        } else {
                            setAlert('error', data.message);
                            startBtn.style.display = 'flex';
                            startBtn.disabled = false;
                            startBtn.style.opacity = '1';
                        }
                    })
                    .catch(() => {
                        setAlert('error', 'ไม่สามารถส่งข้อมูลไปยัง API ได้ กรุณาลองใหม่');
                        startBtn.style.display = 'flex';
                        startBtn.disabled = false;
                        startBtn.style.opacity = '1';
                    });
            }, 'image/jpeg', 0.92);
        }

        function stopTracks() {
            if (stream)          { stream.getTracks().forEach(t => t.stop()); stream = null; }
            if (motionInterval)  { clearInterval(motionInterval); motionInterval = null; }
            if (progressInterval){ clearInterval(progressInterval); progressInterval = null; }
            stopHoloMesh();
            prevFrame = null; totalMotion = 0;
        }

        function restartScanProcess() {
            verifyPanel.classList.remove('visible');
            document.getElementById('frontface-panel').classList.remove('visible');
            document.getElementById('motion-bar-wrap').classList.remove('visible');
            document.getElementById('holo-canvas').classList.remove('visible');
            document.getElementById('rotate-ring-svg').classList.remove('visible');
            countdownOverlay.classList.remove('visible');
            alertBox.className = 'alert-box';
            hideInstruction();
            setStep(1);
            openWebcam();
        }

    </script>
</body>
</html>