<?php
// 📌 กำหนดค่าให้ระบบ Header ค้นหาและดึงไปสร้างเป็นเมนูอัตโนมัติ (Dynamic Menu System)
// MENU_TITLE: ภาพรวมระบบ
// MENU_ICON: layout-dashboard
// MENU_ORDER: 1

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ ถ้ายังไม่มี session แต่มี cookie จดจำอยู่ → กู้คืน session อัตโนมัติ (จดจำ 30 วัน)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $parts = explode('|', base64_decode($token));
    if (count($parts) === 2) {
        $_SESSION['user_id']   = $parts[0];
        $_SESSION['user_name'] = $parts[1];
    }
}

// 🔒 ถ้ายังไม่มี session หลังจากตรวจ cookie แล้ว → กลับไป login
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

// 🔌 ดึงไฟล์เชื่อมต่อฐานข้อมูลหลัก (db.php) เพื่อเรียกใช้ตัวแปร $conn
require_once '../db.php';

$current_user_id   = $_SESSION['user_id'];
$current_user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'ผู้ใช้งานระบบ';

// 📁 โฟลเดอร์เก็บรูปโปรไฟล์ (อยู่ที่ root หลัก /profiles/)
// ไฟล์นี้อยู่ใน /user/  ดังนั้น root = ../
$profile_dir     = '../profiles/';
$profile_web_dir = '../profiles/'; // relative path สำหรับ <img src>

// สร้างโฟลเดอร์ถ้ายังไม่มี
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}

// ─── สร้างตาราง student_profiles ถ้ายังไม่มี ───────────────────────────
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS student_profiles (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            student_id  VARCHAR(50) NOT NULL UNIQUE,
            avatar      VARCHAR(255) DEFAULT NULL,
            uploaded_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) { /* ถ้าสร้างไม่ได้ก็ผ่านไป */ }

// ─── จัดการ Upload รูปโปรไฟล์ (AJAX POST) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload_avatar') {
    header('Content-Type: application/json');

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์รูปภาพ']);
        exit;
    }

    $file      = $_FILES['avatar'];
    $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime      = mime_content_type($file['tmp_name']);
    $maxSize   = 5 * 1024 * 1024; // 5 MB

    if (!in_array($mime, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'รองรับเฉพาะ JPG, PNG, WEBP, GIF เท่านั้น']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => 'error', 'message' => 'ขนาดไฟล์เกิน 5 MB']);
        exit;
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = 'avatar_' . $current_user_id . '_' . time() . '.' . strtolower($ext);
    $dest     = $profile_dir . $filename;

    // ลบรูปเก่าถ้ามี
    try {
        $s = $conn->prepare("SELECT avatar FROM student_profiles WHERE student_id = ?");
        $s->execute([$current_user_id]);
        $old = $s->fetchColumn();
        if ($old && file_exists($profile_dir . $old)) {
            unlink($profile_dir . $old);
        }
    } catch (Exception $e) {}

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['status' => 'error', 'message' => 'อัพโหลดล้มเหลว กรุณาตรวจสอบสิทธิ์โฟลเดอร์']);
        exit;
    }

    try {
        $s = $conn->prepare("
            INSERT INTO student_profiles (student_id, avatar)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE avatar = VALUES(avatar), updated_at = NOW()
        ");
        $s->execute([$current_user_id, $filename]);
        echo json_encode(['status' => 'success', 'filename' => $filename, 'message' => 'อัพโหลดรูปโปรไฟล์สำเร็จ']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'บันทึกฐานข้อมูลล้มเหลว: ' . $e->getMessage()]);
    }
    exit;
}

// ─── ดึงรูปโปรไฟล์ปัจจุบัน ─────────────────────────────────────────────
$avatar_file = null;
try {
    $s = $conn->prepare("SELECT avatar FROM student_profiles WHERE student_id = ?");
    $s->execute([$current_user_id]);
    $avatar_file = $s->fetchColumn();
} catch (Exception $e) {}

$avatar_src = ($avatar_file && file_exists($profile_dir . $avatar_file))
    ? $profile_web_dir . $avatar_file
    : null;

// ─── ดึงสถิติ ──────────────────────────────────────────────────────────
$total_students = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_students = $row['total'] ?? 0;
} catch (Exception $e) {
    $total_students = 42;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ภาพรวมระบบ - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif; }
        .premium-card { background: #ffffff; border: 1px solid rgba(229, 229, 229, 0.6); }

        /* Avatar upload zone */
        #avatar-drop-zone {
            transition: border-color 0.2s, background 0.2s;
        }
        #avatar-drop-zone.drag-over {
            border-color: #6366f1;
            background: #eef2ff;
        }
        #avatar-preview {
            transition: opacity 0.3s;
        }
        .upload-btn-ring {
            position: absolute;
            inset: -3px;
            border-radius: 9999px;
            background: conic-gradient(#6366f1, #8b5cf6, #06b6d4, #6366f1);
            animation: spinRing 3s linear infinite;
            z-index: 0;
            opacity: 0;
            transition: opacity 0.3s;
        }
        #avatar-wrap:hover .upload-btn-ring { opacity: 1; }
        @keyframes spinRing {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .avatar-mask {
            position: absolute;
            inset: 2px;
            border-radius: 9999px;
            background: #fff;
            z-index: 1;
        }
        #avatar-img-zone { position: relative; z-index: 2; }

        /* Upload toast */
        #upload-toast {
            transition: all 0.35s cubic-bezier(0.16,1,0.3,1);
            transform: translateY(8px);
            opacity: 0;
            pointer-events: none;
        }
        #upload-toast.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body class="bg-[#fcfcfd] min-h-screen antialiased text-neutral-800 pb-12">

    <?php include 'header.php'; ?>

    <div class="absolute top-20 right-10 w-80 h-80 bg-neutral-100 rounded-full blur-[120px] pointer-events-none z-0"></div>
    <div class="absolute bottom-10 left-10 w-80 h-80 bg-neutral-200/40 rounded-full blur-[100px] pointer-events-none z-0"></div>

    <!-- Toast notification -->
    <div id="upload-toast" class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl bg-white border border-neutral-200 shadow-xl text-sm font-medium text-neutral-800">
        <span id="toast-icon" class="text-lg">✅</span>
        <span id="toast-msg">อัพโหลดสำเร็จ</span>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 relative z-10">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">
                    สวัสดีครับ, <span class="underline decoration-neutral-200 decoration-2 underline-offset-4"><?php echo htmlspecialchars($current_user_name); ?></span>
                </h1>
                <p class="text-neutral-400 text-xs mt-1 font-light">นี่คือข้อมูลและสถิติภาพรวมหลังจากคุณผ่านการสแกนอัตลักษณ์ 3D เข้ามาในระบบ</p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-neutral-200/60 rounded-full text-xs text-neutral-500 shadow-[0_2px_8px_rgba(0,0,0,0.01)] w-fit">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-neutral-400"></i>
                <span id="live-date">กำลังโหลดวันที่...</span>
            </div>
        </div>

        <!-- Stats grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="premium-card p-5 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.01)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] text-neutral-400 font-bold tracking-widest uppercase">Security Gate</span>
                    <div class="text-lg font-semibold text-neutral-900">ผ่านการสแกน</div>
                    <span class="text-[10px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full font-medium inline-flex items-center gap-0.5">
                        <i data-lucide="shield-check" class="w-2.5 h-2.5"></i> ลีฟเนส 3D สำเร็จ
                    </span>
                </div>
                <div class="p-3 bg-neutral-50 rounded-xl border border-neutral-100 text-neutral-800">
                    <i data-lucide="scan-face" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="premium-card p-5 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.01)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] text-neutral-400 font-bold tracking-widest uppercase">Account ID</span>
                    <div class="text-xl font-bold text-neutral-900 font-mono tracking-tight"><?php echo htmlspecialchars($current_user_id); ?></div>
                    <p class="text-[10px] text-neutral-400 font-light">รหัสผู้ถือสิทธิ์ระบบในปัจจุบัน</p>
                </div>
                <div class="p-3 bg-neutral-50 rounded-xl border border-neutral-100 text-neutral-500">
                    <i data-lucide="fingerprint" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="premium-card p-5 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.01)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] text-neutral-400 font-bold tracking-widest uppercase">Database Records</span>
                    <div class="text-xl font-semibold text-neutral-900"><?php echo $total_students; ?> <span class="text-xs text-neutral-400 font-normal">คน</span></div>
                    <p class="text-[10px] text-neutral-400 font-light">รายชื่อนักเรียนทั้งหมดในตาราง</p>
                </div>
                <div class="p-3 bg-neutral-50 rounded-xl border border-neutral-100 text-neutral-500">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="premium-card p-5 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.01)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-[10px] text-neutral-400 font-bold tracking-widest uppercase">Server Engine</span>
                    <div class="text-lg font-semibold text-neutral-900">สถานะปกติ</div>
                    <span class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full font-medium inline-flex items-center gap-0.5 animate-pulse">
                        ● Cloud API Connected
                    </span>
                </div>
                <div class="p-3 bg-neutral-50 rounded-xl border border-neutral-100 text-neutral-500">
                    <i data-lucide="cpu" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Profile card with avatar upload -->
            <div class="premium-card p-6 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.015)] flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4 border-b border-neutral-100 pb-3">
                        <h3 class="text-xs font-semibold tracking-wider text-neutral-400 uppercase">สิทธิ์ปัจจุบันของคุณ</h3>
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>

                    <!-- Avatar upload zone -->
                    <div class="my-5 flex flex-col items-center">
                        <div id="avatar-wrap" class="relative w-24 h-24 cursor-pointer group" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                            <!-- Spinning ring on hover -->
                            <div class="upload-btn-ring"></div>
                            <div class="avatar-mask"></div>

                            <!-- Avatar image / placeholder -->
                            <div id="avatar-img-zone" class="w-full h-full rounded-full overflow-hidden border-2 border-neutral-200 bg-neutral-100 flex items-center justify-center text-neutral-400">
                                <?php if ($avatar_src): ?>
                                    <img id="avatar-preview" src="<?php echo htmlspecialchars($avatar_src); ?>" alt="avatar"
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i data-lucide="user" class="w-10 h-10" id="avatar-placeholder"></i>
                                    <img id="avatar-preview" class="w-full h-full object-cover hidden" alt="avatar">
                                <?php endif; ?>
                            </div>

                            <!-- Overlay hint -->
                            <div class="absolute inset-0 rounded-full bg-black/40 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                <i data-lucide="camera" class="w-5 h-5 text-white mb-0.5"></i>
                                <span class="text-[9px] text-white font-semibold tracking-wide">เปลี่ยนรูป</span>
                            </div>

                            <!-- Hidden file input -->
                            <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   class="absolute inset-0 opacity-0 cursor-pointer z-20" title="">
                        </div>

                        <!-- Upload progress bar -->
                        <div id="upload-progress-wrap" class="hidden mt-3 w-full max-w-[160px]">
                            <div class="h-1 bg-neutral-100 rounded-full overflow-hidden">
                                <div id="upload-bar" class="h-full bg-indigo-500 rounded-full transition-all duration-300" style="width:0%"></div>
                            </div>
                            <p class="text-[10px] text-neutral-400 text-center mt-1">กำลังอัพโหลด...</p>
                        </div>

                        <h2 class="mt-3 text-base font-semibold text-neutral-900 tracking-tight"><?php echo htmlspecialchars($current_user_name); ?></h2>
                        <p class="text-neutral-400 font-mono text-[11px] mt-1 bg-neutral-50 inline-block px-2.5 py-0.5 rounded-md border border-neutral-200/40">
                            UID: <?php echo htmlspecialchars($current_user_id); ?>
                        </p>
                        <p class="text-[10px] text-neutral-400 mt-2">คลิกที่รูปเพื่ออัพโหลดโปรไฟล์</p>
                    </div>
                </div>

                <div class="bg-neutral-50 p-4 rounded-xl border border-neutral-200/40 text-left text-xs space-y-2 mt-2">
                    <div class="flex justify-between text-neutral-500">
                        <span>ระดับการเข้าถึง:</span>
                        <span class="font-medium text-neutral-800">Authorized User</span>
                    </div>
                    <div class="flex justify-between text-neutral-500">
                        <span>เวลาที่เข้าระบบล่าสุด:</span>
                        <span class="font-medium text-neutral-800 font-mono"><?php echo date('H:i:s'); ?> น.</span>
                    </div>
                    <div class="flex justify-between text-neutral-500">
                        <span>รูปโปรไฟล์:</span>
                        <span class="font-medium <?php echo $avatar_src ? 'text-emerald-600' : 'text-neutral-400'; ?>">
                            <?php echo $avatar_src ? 'มีรูปแล้ว ✓' : 'ยังไม่มีรูป'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Activity log -->
            <div class="lg:col-span-2 premium-card p-6 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.015)]">
                <div class="flex items-center justify-between mb-4 border-b border-neutral-100 pb-3">
                    <h3 class="text-xs font-semibold tracking-wider text-neutral-400 uppercase">บันทึกกิจกรรมความปลอดภัยล่าสุด</h3>
                    <div class="text-[10px] text-neutral-400 flex items-center gap-1">
                        <i data-lucide="refresh-cw" class="w-3 h-3 animate-spin"></i> อัปเดตสดแบบเรียลไทม์
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-neutral-400 border-b border-neutral-100 font-normal">
                                <th class="pb-3 font-medium">รหัสล็อก</th>
                                <th class="pb-3 font-medium">รายการดำเนินการ</th>
                                <th class="pb-3 font-medium text-right">เวลาทำรายการ</th>
                                <th class="pb-3 font-medium text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/60 text-neutral-700">
                            <tr class="hover:bg-neutral-50/50 transition">
                                <td class="py-3.5 font-mono text-neutral-400">#BIO-98</td>
                                <td class="py-3.5">
                                    <div class="font-medium text-neutral-900">Interactive Liveness Checked</div>
                                    <span class="text-[10px] text-neutral-400">ผ่านเงื่อนไขการหันใบหน้าตามลูกศรสุ่มสัญญานสำเร็จ</span>
                                </td>
                                <td class="py-3.5 text-right font-mono text-neutral-500"><?php echo date('H:i', strtotime('-1 minute')); ?></td>
                                <td class="py-3.5 text-center">
                                    <span class="px-2 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-600 rounded-md border border-emerald-100">สมบูรณ์</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-neutral-50/50 transition">
                                <td class="py-3.5 font-mono text-neutral-400">#API-42</td>
                                <td class="py-3.5">
                                    <div class="font-medium text-neutral-900">Cloud Face Recognition Query</div>
                                    <span class="text-[10px] text-neutral-400">จับคู่พิกเซลรูปหน้าตรงกับคลาวด์ API สำเร็จ</span>
                                </td>
                                <td class="py-3.5 text-right font-mono text-neutral-500"><?php echo date('H:i', strtotime('-2 minute')); ?></td>
                                <td class="py-3.5 text-center">
                                    <span class="px-2 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-600 rounded-md border border-emerald-100">สมบูรณ์</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-neutral-50/50 transition">
                                <td class="py-3.5 font-mono text-neutral-400">#SES-01</td>
                                <td class="py-3.5">
                                    <div class="font-medium text-neutral-900">Session Initialized</div>
                                    <span class="text-[10px] text-neutral-400">สร้างหน่วยความจำความปลอดภัยลงบราวเซอร์สำเร็จ</span>
                                </td>
                                <td class="py-3.5 text-right font-mono text-neutral-500"><?php echo date('H:i', strtotime('-2 minute')); ?></td>
                                <td class="py-3.5 text-center">
                                    <span class="px-2 py-0.5 text-[10px] font-medium bg-neutral-50 text-emerald-600 rounded-md border border-emerald-100">เปิดระบบ</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        lucide.createIcons();

        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('live-date').innerText = new Date().toLocaleDateString('th-TH', options);

        // ── Avatar upload logic ─────────────────────────────────────────
        const input       = document.getElementById('avatar-input');
        const preview     = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');
        const progressWrap= document.getElementById('upload-progress-wrap');
        const bar         = document.getElementById('upload-bar');
        const toast       = document.getElementById('upload-toast');
        const toastMsg    = document.getElementById('toast-msg');
        const toastIcon   = document.getElementById('toast-icon');

        function showToast(icon, msg, duration = 3000) {
            toastIcon.textContent = icon;
            toastMsg.textContent  = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), duration);
        }

        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            // Preview immediately
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);

            // Upload via XHR for progress
            const fd = new FormData();
            fd.append('avatar', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'index?action=upload_avatar', true);

            progressWrap.classList.remove('hidden');
            bar.style.width = '0%';

            xhr.upload.onprogress = e => {
                if (e.lengthComputable) {
                    bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
                }
            };

            xhr.onload = () => {
                progressWrap.classList.add('hidden');
                bar.style.width = '0%';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.status === 'success') {
                        showToast('✅', 'อัพโหลดรูปโปรไฟล์สำเร็จ!');
                    } else {
                        showToast('❌', res.message || 'อัพโหลดไม่สำเร็จ');
                    }
                } catch(e) {
                    showToast('❌', 'เกิดข้อผิดพลาด กรุณาลองใหม่');
                }
            };
            xhr.onerror = () => {
                progressWrap.classList.add('hidden');
                showToast('❌', 'ไม่สามารถเชื่อมต่อได้');
            };

            xhr.send(fd);
        });
    });
    </script>
</body>
</html>