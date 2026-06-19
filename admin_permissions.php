<?php
// 📌 กำหนดค่าให้ระบบ Header ค้นหาและดึงไปสร้างเป็นเมนูอัตโนมัติ (Dynamic Menu System)
// MENU_TITLE: ตั้งค่าสิทธิ์ครูผู้ช่วย
// MENU_ICON: key-round
// MENU_ORDER: am3

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ════════════════════════════════════════════════════════════════
// 🔒 ADMIN LEVEL 5 GUARD (เฉพาะยศสูงสุดระดับ 5 เท่านั้นที่เข้าหน้านี้ได้)
// ════════════════════════════════════════════════════════════════
$role = $_SESSION['role']    ?? null;
$uid  = $_SESSION['user_id'] ?? null;

if (file_exists('../db.php')) {
    require_once '../db.php';
} elseif (file_exists('db.php')) {
    require_once 'db.php';
}

function is_teacher($id) {
    if (!$id) return false;
    return (strpos(strtoupper(trim((string)$id)), 'T') === 0);
}

// ดึงระดับสิทธิ์ผู้ใช้ปัจจุบันเพื่อป้องกันคนยศต่ำแอบเข้าหน้านี้
function get_admin_level($userId, $connInstance) {
    if (!$userId || !$connInstance instanceof PDO) return 1;
    try {
        $stmt = $connInstance->prepare("SELECT admin_level FROM physics_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['admin_level'];
    } catch (Exception $e) {}
    return 5; // Fallback แรกสุด
}

$my_level = get_admin_level($uid, $conn ?? null);

if ($role !== 'Admin' || $uid === null || $my_level < 5) {
    // แสดงหน้าปฏิเสธสิทธิ์แบบหรูหรา มินิมอล มีสีสัน ไม่ล่มกลางทาง
    die('
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>สิทธิ์ไม่เพียงพอ - Access Denied</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/lucide@latest"></script>
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;800&display=swap" rel="stylesheet">
        <style>body { font-family: "Sarabun", sans-serif; }</style>
    </head>
    <body class="bg-[#FAF9F5] flex items-center justify-center min-h-screen p-6">
        <div class="max-w-md w-full bg-white border border-[#EBE7DF] rounded-3xl p-8 text-center shadow-lg animate-in fade-in duration-300">
            <div class="w-16 h-16 bg-rose-50 text-[#E76F51] rounded-2xl flex items-center justify-center mx-auto mb-4 border border-rose-100">
                <i data-lucide="shield-alert" class="w-8 h-8 stroke-[1.5]"></i>
            </div>
            <h2 class="text-xl font-extrabold text-[#23201F] mb-2">สิทธิ์ของคุณไม่เพียงพอ</h2>
            <p class="text-sm text-[#8E8775] leading-relaxed mb-6">หน้านี้จำกัดสิทธิ์เฉพาะสำหรับผู้ดูแลระบบยศระดับสูงสุด (Level 5) เท่านั้น</p>
            <a href="admin_physics.php" class="inline-block w-full py-3.5 bg-[#23201F] hover:bg-[#3D3735] text-white text-sm font-bold rounded-2xl transition shadow-md">กลับไปหน้าคะแนนฟิสิกส์</a>
        </div>
        <script>lucide.createIcons();</script>
    </body>
    </html>
    ');
}

// ════════════════════════════════════════════════════════════════
// 💾 AJAX ACTION HANDLER — บันทึกระดับยศและสิทธิ์
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {
    header('Content-Type: application/json');
    
    $target_user_id = trim($_POST['target_user_id'] ?? '');
    $target_level   = (int)($_POST['admin_level'] ?? 1);
    $allowed_pages  = $_POST['allowed_pages'] ?? [];

    if (!$target_user_id) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสผู้ใช้งานปลายทาง']);
        exit;
    }

    $json_pages = json_encode($allowed_pages, JSON_UNESCAPED_UNICODE);

    try {
        $stmt = $conn->prepare("INSERT INTO physics_permissions (user_id, admin_level, allowed_pages) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE admin_level = ?, allowed_pages = ?");
        $stmt->execute([$target_user_id, $target_level, $json_pages, $target_level, $json_pages]);
        
        echo json_encode(['status' => 'success', 'message' => 'แต่งตั้งยศและบันทึกสิทธิ์ผู้ใช้งานสำเร็จแล้ว']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'ล้มเหลวในการบันทึกลงฐานข้อมูล: ' . $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════
// 🔍 GET USER DATA AJAX — ดึงสิทธิ์เดิมของผู้ใช้มาแสดงผล Real-time
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_perms') {
    header('Content-Type: application/json');
    $target = trim($_GET['user_id'] ?? '');
    try {
        $stmt = $conn->prepare("SELECT admin_level, allowed_pages FROM physics_permissions WHERE user_id = ?");
        $stmt->execute([$target]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode([
                'status' => 'success',
                'admin_level' => (int)$row['admin_level'],
                'allowed_pages' => json_decode($row['allowed_pages'], true) ?: []
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'admin_level' => 1,
                'allowed_pages' => ['admin_physics.php'] // สิทธิ์เริ่มต้น
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ดึงรายชื่อคุณครูและแอดมินทั้งหมดจากระบบ (ไม่ใช้ Mock Data)
$admins_and_teachers = [];
if (isset($conn) && $conn instanceof PDO) {
    try {
        $stmt = $conn->query("SELECT user_id, user_name, role FROM students WHERE role = 'Admin' OR user_id LIKE 'T%'");
        $admins_and_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดลำดับยศและสิทธิ์ครูผู้สอน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Sarabun", sans-serif; overflow-x: hidden; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-[#FAF9F5] text-[#3D3735] font-sans antialiased">

    <?php 
    // 📌 ดึงแถบนำทางหลักมาเชื่อมต่อหน้าจออื่นๆ เข้าด้วยกัน
    if (file_exists('header.php')) {
        include 'header.php';
    } elseif (file_exists('../header.php')) {
        include '../header.php';
    } elseif (file_exists('includes/header.php')) {
        include 'includes/header.php';
    }
    ?>

    <!-- 🔐 MAIN PERMISSIONS SYSTEM CONTAINER -->
    <div class="bg-gradient-to-tr from-[#FAF9F5] via-[#F4F1EA] to-[#EFF3EE] min-h-screen py-10 w-full">
        <main class="max-w-4xl w-full mx-auto px-6 flex flex-col gap-8 animate-in fade-in duration-300">
            
            <!-- 📌 HEADER SECTION -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-[#E1DDD3]/60">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#D97706] to-[#B45309] flex items-center justify-center border border-[#FDE68A] text-white shadow-lg">
                        <i data-lucide="key-round" class="w-7 h-7 stroke-[1.5]"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-[#1C1917] tracking-tight">ระบบตั้งค่าสิทธิ์ครูผู้สอน ม.4/1</h2>
                        <p class="text-xs text-[#8E8775] font-bold uppercase tracking-wider mt-0.5">เฉพาะผู้ดูแลระบบสิทธิ์ยศระดับ 5 เท่านั้นที่สามารถจัดสรรสิทธิ์ได้</p>
                    </div>
                </div>
                <a href="admin_physics.php" class="inline-flex items-center gap-2 text-sm font-bold text-[#8E8775] hover:text-[#23201F] transition">
                    <i data-lucide="arrow-left" class="w-4 h-4 stroke-[2.5]"></i>
                    <span>กลับไปยังหน้าคะแนน</span>
                </a>
            </div>

            <!-- 📌 CONFIGURATION FORM (การจัดวางใหญ่ขึ้น คลีนขึ้น และสวยพรีเมียมด้วยคู่สีพาสเทล) -->
            <div class="bg-white border border-[#EBE7DF] rounded-3xl p-8 md:p-10 shadow-lg relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-2 bg-[#D97706]" />

                <form id="permissionForm" onsubmit="savePermissions(event)" class="space-y-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- เลือกบัญชีผู้ใช้งาน -->
                        <div>
                            <label class="block text-xs font-black text-stone-500 uppercase tracking-wider mb-3">เลือกคุณครูหรือผู้ใช้ที่ต้องการจัดสรรสิทธิ์</label>
                            <select id="target_user_id" name="target_user_id" onchange="loadUserPermissions()" class="block w-full p-4.5 bg-[#FAF9F5] border border-[#E1DDD3] focus:border-[#D97706] focus:ring-4 focus:ring-[#D97706]/5 rounded-2xl text-md font-semibold focus:outline-none transition">
                                <option value="">-- กรุณาเลือกบัญชีผู้ใช้ในระบบ --</option>
                                <?php foreach ($admins_and_teachers as $user): ?>
                                    <?php 
                                    $uName = htmlspecialchars($user['user_name']);
                                    $uId = htmlspecialchars($user['user_id']);
                                    $uTag = is_teacher($uId) ? ' [คุณครู]' : ' [แอดมิน]';
                                    ?>
                                    <option value="<?php echo $uId; ?>"><?php echo "{$uName} (รหัส: {$uId}){$uTag}"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- เลือกระดับขั้นยศ 1-5 -->
                        <div>
                            <label class="block text-xs font-black text-stone-500 uppercase tracking-wider mb-3">กำหนดลำดับขั้นยศ (Admin Level)</label>
                            <select id="admin_level" name="admin_level" class="block w-full p-4.5 bg-[#FAF9F5] border border-[#E1DDD3] focus:border-[#D97706] focus:ring-4 focus:ring-[#D97706]/5 rounded-2xl text-md font-bold transition focus:outline-none">
                                <option value="1">Level 1 - ผู้ช่วยสอน (สิทธิ์ดูคะแนนพื้นฐานในวิชา)</option>
                                <option value="2">Level 2 - ผู้ตรวจการ (มีสิทธิ์พิมพ์และออกเอกสารคะแนน)</option>
                                <option value="3">Level 3 - ผู้ควบคุมระบบฟิสิกส์ (จัดการคะแนนห้อง ม.4/1 ทั้งหมด)</option>
                                <option value="4">Level 4 - รองผู้ดูแลระบบหลัก (จัดการคะแนนและพอร์ตโฟลิโอได้)</option>
                                <option value="5">Level 5 - ผู้บริหารสิทธิ์สูงสุด (ควบคุม สั่งการ และกำหนดสิทธิ์ได้ทุกคน)</option>
                            </select>
                        </div>
                    </div>

                    <!-- รายการอนุญาตเข้าถึงหน้าต่างๆ (Authorize Allowed Pages) -->
                    <div class="bg-[#FAF9F5] border border-[#E1DDD3] rounded-2xl p-6 md:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <i data-lucide="lock" class="w-5 h-5 text-stone-500"></i>
                            <h4 class="text-xs font-black text-stone-500 uppercase tracking-widest">กำหนดหน้าเว็บไซต์ที่บัญชีนี้ได้รับสิทธิ์เข้าใช้งาน</h4>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- บังคับสิทธิ์หน้ารายงานคะแนนฟิสิกส์ -->
                            <label class="flex items-center gap-3.5 p-5 bg-white border border-[#E1DDD3] rounded-2xl cursor-pointer hover:border-[#52796F] transition">
                                <input type="checkbox" name="allowed_pages[]" value="admin_physics.php" class="w-5.5 h-5.5 accent-[#52796F] rounded-lg" checked disabled/>
                                <div>
                                    <span class="text-sm font-bold text-stone-800 block">จัดการคะแนนฟิสิกส์</span>
                                    <span class="text-[10px] text-stone-400 font-semibold block">admin_physics.php (สิทธิ์บังคับ)</span>
                                </div>
                            </label>

                            <!-- สิทธิ์หน้า portfolio.php -->
                            <label class="flex items-center gap-3.5 p-5 bg-white border border-[#E1DDD3] rounded-2xl cursor-pointer hover:border-[#52796F] transition">
                                <input type="checkbox" id="page_portfolio" name="allowed_pages[]" value="portfolio.php" class="w-5.5 h-5.5 accent-[#52796F] rounded-lg"/>
                                <div>
                                    <span class="text-sm font-bold text-stone-800 block">จัดการเกียรติบัตร</span>
                                    <span class="text-[10px] text-stone-400 font-semibold block">portfolio.php</span>
                                </div>
                            </label>

                            <!-- สิทธิ์หน้า admin_manage.php -->
                            <label class="flex items-center gap-3.5 p-5 bg-white border border-[#E1DDD3] rounded-2xl cursor-pointer hover:border-[#52796F] transition">
                                <input type="checkbox" id="page_manage" name="allowed_pages[]" value="admin_manage.php" class="w-5.5 h-5.5 accent-[#52796F] rounded-lg"/>
                                <div>
                                    <span class="text-sm font-bold text-stone-800 block">จัดการระบบระบบรวม</span>
                                    <span class="text-[10px] text-stone-400 font-semibold block">admin_manage.php</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- สรุปสถานะความปลอดภัยก่อนกดบันทึก -->
                    <div class="p-4 bg-amber-50/50 border border-amber-100 rounded-2xl flex items-start gap-3">
                        <i data-lucide="info" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            <strong>หมายเหตุความปลอดภัย:</strong> การมอบสิทธิ์ในหน้านี้มีผลทันทีหลังจากกดบันทึก หากคุณตั้งค่าสิทธิ์ให้บุคคลใดเป็น Level 5 บัญชีนั้นจะสามารถเข้าถึงและแก้ไขสิทธิ์ของผู้ใช้อื่นรวมถึงสิทธิ์ของคุณได้เช่นกัน
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="submit" id="btnSave" class="w-full sm:w-auto px-10 py-5 bg-[#23201F] hover:bg-[#3D3735] text-white text-xs font-black uppercase rounded-2xl transition-all shadow-md flex items-center justify-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4.5 h-4.5"></i>
                            <span>บันทึกการแต่งตั้งยศและมอบสิทธิ์</span>
                        </button>
                    </div>

                </form>
            </div>

        </main>
    </div>

    <!-- 3. PREMIUM FOOTER -->
    <footer class="py-12 bg-white border-t border-[#EBE7DF]/60 text-center relative z-10 w-full">
        <div class="max-w-4xl mx-auto px-6 text-xs text-[#8E8775] space-y-3">
            <p class="tracking-wide">ระบบวิเคราะห์ความก้าวหน้าและแสดงผลคะแนนฟิสิกส์รายบุคคล ชั้นมัธยมศึกษาปีที่ 4/1</p>
            <p class="font-extrabold text-[#7D7663] uppercase tracking-widest">DYNAMIC ACCESS CONTROL • AUTHORIZATION CONSOLE v1</p>
        </div>
    </footer>

    <!-- INTERACTIVE JAVASCRIPT SYSTEM -->
    <script>
        // 1. ฟังก์ชันโหลดสิทธิ์ที่มีอยู่จากฐานข้อมูลแบบเรียลไทม์เมื่อเปลี่ยนชื่อผู้ใช้ในฟอร์ม
        async function loadUserPermissions() {
            const userId = document.getElementById('target_user_id').value;
            if (!userId) return;

            // รีเซตสถานะการติ๊กเลือกใหม่ทั้งหมด
            document.getElementById('page_portfolio').checked = false;
            document.getElementById('page_manage').checked = false;
            document.getElementById('admin_level').value = "1";

            try {
                const res = await (await fetch(`admin_permissions.php?action=get_perms&user_id=${encodeURIComponent(userId)}`)).json();
                if (res.status === 'success') {
                    // กำหนดระดับยศที่มีอยู่ในปัจจุบัน
                    document.getElementById('admin_level').value = res.admin_level;
                    
                    // กำหนดสิทธิ์การเข้าถึงหน้าเว็บที่มีอยู่
                    if (res.allowed_pages.includes('portfolio.php')) {
                        document.getElementById('page_portfolio').checked = true;
                    }
                    if (res.allowed_pages.includes('admin_manage.php')) {
                        document.getElementById('page_manage').checked = true;
                    }
                }
            } catch (err) {
                console.error("ไม่สามารถตรวจสอบข้อมูลสิทธิ์เดิมได้", err);
            }
        }

        // 2. ฟังก์ชันส่งข้อมูลสิทธิ์บันทึกลง SQL ด้วย AJAX ไฮสปีด
        async function savePermissions(event) {
            event.preventDefault();
            const btn = document.getElementById('btnSave');
            const originalHTML = btn.innerHTML;

            const targetUserId = document.getElementById('target_user_id').value;
            if (!targetUserId) {
                alert('กรุณาเลือกคุณครูหรือบัญชีผู้ใช้งานที่ต้องการจัดสรรสิทธิ์ในระบบ');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4.5 h-4.5 animate-spin"></i> บันทึกข้อมูลสิทธิ์...';
            lucide.createIcons();

            const formData = new FormData(document.getElementById('permissionForm'));
            formData.append('target_user_id', targetUserId);
            // บังคับสิทธิ์เข้าถึงหน้ารายงานคะแนนฟิสิกส์เสมอกันทุกบัญชี
            formData.append('allowed_pages[]', 'admin_physics.php');

            try {
                const res = await (await fetch('admin_permissions.php?action=save', {
                    method: 'POST',
                    body: formData
                })).json();

                if (res.status === 'success') {
                    alert('✨ สำเร็จ: ' + res.message);
                } else {
                    alert('❌ เกิดข้อผิดพลาด: ' + res.message);
                }
            } catch (err) {
                alert('❌ เกิดข้อผิดพลาดทางเทคนิคในการเชื่อมต่อฐานข้อมูล');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                lucide.createIcons();
            }
        }

        // ดึงไอคอน Lucide ขึ้นมาทำงาน
        lucide.createIcons();
    </script>
</body>
</html>