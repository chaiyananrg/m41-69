<?php
// 📌 กำหนดค่าให้ระบบ Header ค้นหาและดึงไปสร้างเป็นเมนูอัตโนมัติ
// MENU_TITLE: จัดการระบบ
// MENU_ICON: shield-check
// MENU_ORDER: am1

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ════════════════════════════════════════════════════════════════
// 🔒 ADMIN GUARD
// ════════════════════════════════════════════════════════════════
function require_admin(): void {
    $role = $_SESSION['role']    ?? null;
    $uid  = $_SESSION['user_id'] ?? null;
    if ($role !== 'Admin' || $uid === null) {
        if (isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['status' => 'error', 'code' => 403, 'message' => 'Unauthorized: Admin session required']);
            exit;
        }
        header('Location: index.php', true, 302);
        exit;
    }
}
require_admin();
require_once '../db.php';

$current_user_id   = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? 'ผู้ดูแลระบบ';

// ─── แปลงรูปเป็น WebP ────────────────────────────────────────────────────────
function convertToWebP($source, $destination, $mime, $quality = 80) {
    $image = null;
    switch($mime) {
        case 'image/jpeg': case 'image/jpg': $image = @imagecreatefromjpeg($source); break;
        case 'image/png':  $image = @imagecreatefrompng($source); break;
        case 'image/webp': $image = @imagecreatefromwebp($source); break;
        case 'image/gif':  $image = @imagecreatefromgif($source);  break;
    }
    if ($image) {
        if ($mime == 'image/png') { imagepalettetotruecolor($image); imagealphablending($image, true); imagesavealpha($image, true); }
        $result = imagewebp($image, $destination, $quality);
        imagedestroy($image);
        return $result;
    }
    return false;
}

// ─── API Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    // 🏆 เกียรติบัตร
    if ($_GET['action'] === 'upload_admin_cert') {
        $title = $_POST['title'] ?? 'เกียรติบัตรส่วนกลาง';
        $recipient = $_POST['recipient_name'] ?? '';
        $category  = $_POST['category'] ?? 'ประกาศ';
        if (isset($_FILES['files']) && $_FILES['files']['error'][0] === UPLOAD_ERR_OK) {
            $ext      = strtolower(pathinfo($_FILES['files']['name'][0], PATHINFO_EXTENSION));
            $filename = 'admin_cert_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = '../uploads/certificates/' . $filename;
            if (!is_dir('../uploads/certificates/')) mkdir('../uploads/certificates/', 0777, true);
            if (move_uploaded_file($_FILES['files']['tmp_name'][0], $upload_path)) {
                $stmt = $conn->prepare("INSERT INTO certificates (user_id, title, activity_topic, category, file_path, is_admin_shared) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$current_user_id, $title, $recipient, $category, $filename]);
                echo json_encode(['status' => 'success']);
            } else { echo json_encode(['status' => 'error', 'message' => 'Upload failed']); }
        } else { echo json_encode(['status' => 'error', 'message' => 'No valid file received']); }
        exit;
    }

    if ($_GET['action'] === 'get_group_files') {
        $title = $_POST['title'] ?? '';
        $stmt  = $conn->prepare("SELECT id, file_path, created_at FROM certificates WHERE title = ? AND is_admin_shared = 1 ORDER BY created_at DESC");
        $stmt->execute([$title]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['action'] === 'delete_cert_single') {
        $id = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("SELECT file_path FROM certificates WHERE id = ? AND is_admin_shared = 1");
        $stmt->execute([$id]);
        $cert = $stmt->fetch();
        if ($cert) {
            $path = '../uploads/certificates/' . $cert['file_path'];
            if (file_exists($path)) @unlink($path);
            $conn->prepare("DELETE FROM certificates WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($_GET['action'] === 'delete_cert_group') {
        $title = $_POST['title'] ?? '';
        $stmt  = $conn->prepare("SELECT file_path FROM certificates WHERE title = ? AND is_admin_shared = 1");
        $stmt->execute([$title]);
        foreach ($stmt->fetchAll() as $cert) { $path = '../uploads/certificates/' . $cert['file_path']; if (file_exists($path)) @unlink($path); }
        $conn->prepare("DELETE FROM certificates WHERE title = ? AND is_admin_shared = 1")->execute([$title]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // 📸 อัลบั้มกิจกรรม
    if ($_GET['action'] === 'create_admin_album_init') {
        $name     = $_POST['album_name'] ?? 'อัลบั้มส่วนกลาง';
        $category = $_POST['category']   ?? 'กิจกรรมส่วนกลาง';
        try {
            $stmt = $conn->prepare("INSERT INTO activity_albums (user_id, album_name, category, is_admin_shared) VALUES (?, ?, ?, 1)");
            $stmt->execute([$current_user_id, $name, $category]);
            echo json_encode(['status' => 'success', 'album_id' => $conn->lastInsertId()]);
        } catch (Exception $e) { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($_GET['action'] === 'upload_admin_album_image') {
        $album_id = $_POST['album_id'] ?? 0;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && $album_id > 0) {
            $tmp_name = $_FILES['file']['tmp_name'];
            $mime     = mime_content_type($tmp_name);
            if (!is_dir('../uploads/admin-albums/')) mkdir('../uploads/admin-albums/', 0777, true);
            if (strpos($mime, 'image/') === 0) {
                $filename    = 'adm_act_' . time() . '_' . uniqid() . '.webp';
                $upload_path = '../uploads/admin-albums/' . $filename;
                if (!convertToWebP($tmp_name, $upload_path, $mime, 80)) {
                    $ext      = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                    $filename = 'adm_act_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($tmp_name, '../uploads/admin-albums/' . $filename);
                }
                $conn->prepare("INSERT INTO activity_images (album_id, file_path) VALUES (?, ?)")->execute([$album_id, $filename]);
                echo json_encode(['status' => 'success']);
            } else { echo json_encode(['status' => 'error']); }
        } else { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($_GET['action'] === 'get_album_images_admin') {
        $album_id = $_POST['album_id'] ?? 0;
        // รองรับ sort_order ถ้ามีคอลัมน์ มิฉะนั้น fallback ไป id ASC
        try {
            $stmt = $conn->prepare("SELECT id, file_path FROM activity_images WHERE album_id = ? ORDER BY sort_order ASC, id ASC");
        } catch (Exception $e) {
            $stmt = $conn->prepare("SELECT id, file_path FROM activity_images WHERE album_id = ? ORDER BY id ASC");
        }
        $stmt->execute([$album_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['action'] === 'delete_album_image_single') {
        $id   = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("SELECT file_path FROM activity_images WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetch();
        if ($img) {
            $path = '../uploads/admin-albums/' . $img['file_path'];
            if (file_exists($path)) @unlink($path);
            $conn->prepare("DELETE FROM activity_images WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($_GET['action'] === 'delete_album_group') {
        $album_id = $_POST['album_id'] ?? 0;
        $stmt = $conn->prepare("SELECT file_path FROM activity_images WHERE album_id = ?");
        $stmt->execute([$album_id]);
        foreach ($stmt->fetchAll() as $img) { $path = '../uploads/admin-albums/' . $img['file_path']; if (file_exists($path)) @unlink($path); }
        $conn->prepare("DELETE FROM activity_images WHERE album_id = ?")->execute([$album_id]);
        $conn->prepare("DELETE FROM activity_albums WHERE id = ? AND is_admin_shared = 1")->execute([$album_id]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // 🔀 บันทึกลำดับหลัง Drag & Drop
    if ($_GET['action'] === 'reorder_album_images') {
        $orders = json_decode($_POST['orders'] ?? '[]', true);
        if (is_array($orders)) {
            try {
                $stmt = $conn->prepare("UPDATE activity_images SET sort_order = ? WHERE id = ?");
                foreach ($orders as $item) { $stmt->execute([(int)$item['order'], (int)$item['id']]); }
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => 'sort_order column may not exist']); }
        } else { echo json_encode(['status' => 'error']); }
        exit;
    }

    if ($_GET['action'] === 'edit_album') {
        $album_id   = intval($_POST['album_id']   ?? 0);
        $album_name = trim($_POST['album_name']   ?? '');
        $category   = trim($_POST['category']     ?? '');
        if (!$album_id || $album_name === '') { echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']); exit; }
        $conn->prepare("UPDATE activity_albums SET album_name = ?, category = ? WHERE id = ? AND is_admin_shared = 1")->execute([$album_name, $category, $album_id]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($_GET['action'] === 'edit_cert_group') {
        $old_title     = $_POST['old_title']      ?? '';
        $new_title     = trim($_POST['new_title']     ?? '');
        $new_recipient = trim($_POST['new_recipient'] ?? '');
        if ($old_title === '' || $new_title === '') { echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']); exit; }
        $conn->prepare("UPDATE certificates SET title = ?, activity_topic = ? WHERE title = ? AND is_admin_shared = 1")->execute([$new_title, $new_recipient, $old_title]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// ─── ดึงข้อมูลแสดงผล ─────────────────────────────────────────────────────────
$stats = ['students' => 0, 'certs' => 0, 'albums' => 0];
try {
    $stats['students'] = $conn->query("SELECT COUNT(*) FROM student_profiles")->fetchColumn();
    $stats['certs']    = $conn->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $stats['albums']   = $conn->query("SELECT COUNT(*) FROM activity_albums")->fetchColumn();
} catch (Exception $e) {}

$admin_cert_groups = [];
try {
    $admin_cert_groups = $conn->query("SELECT title, activity_topic, COUNT(*) as total_files, MAX(created_at) as latest_date FROM certificates WHERE is_admin_shared = 1 GROUP BY title, activity_topic ORDER BY latest_date DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$admin_albums_list = [];
try {
    $admin_albums_list = $conn->query("SELECT a.id, a.album_name, a.category, a.created_at, (SELECT COUNT(*) FROM activity_images WHERE album_id = a.id) as total_images FROM activity_albums a WHERE a.is_admin_shared = 1 ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการระบบ - Admin Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif; }
        .premium-card { background: #ffffff; border: 1px solid rgba(229,229,229,0.6); }
        .modal-enter { animation: fadeIn 0.2s ease-out forwards; }
        @keyframes fadeIn { from { opacity:0; transform:scale(0.97); } to { opacity:1; transform:scale(1); } }

        /* ── Drop zone ── */
        .file-drop-zone {
            border: 2px dashed #e5e7eb;
            border-radius: 1rem;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .file-drop-zone.dragover { border-color: #6366f1; background: #eef2ff; }
        .file-drop-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }

        /* ── Progress ── */
        .progress-fill { transition: width .25s ease; }

        /* ── Drag items ── */
        .drag-item { cursor: grab; touch-action: none; }
        .drag-item:active { cursor: grabbing; }
        .drag-over { outline: 2px dashed #f59e0b; outline-offset:2px; background:#fffbeb; border-radius:.75rem; }
        .drag-ghost { opacity:.35; }

        /* ── Mobile card-table ── */
        @media (max-width:639px) {
            .data-table thead { display:none; }
            .data-table tbody tr {
                display:flex; flex-direction:column;
                border:1px solid #f3f4f6; border-radius:1rem;
                margin-bottom:.625rem; padding:.75rem 1rem;
                background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.04);
            }
            .data-table td { display:flex; align-items:flex-start; justify-content:space-between; padding:.2rem 0; font-size:.8125rem; border:none !important; }
            .data-table td::before { content:attr(data-label); font-size:.6875rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.04em; flex-shrink:0; margin-right:.5rem; padding-top:.1rem; }
            .data-table td.action-cell { flex-wrap:wrap; gap:.375rem; justify-content:flex-end; padding-top:.5rem; }
            .data-table td.action-cell::before { content:''; }
        }
    </style>
</head>
<body class="bg-[#f8f8fa] min-h-screen antialiased text-neutral-800 pb-16">

    <?php include 'header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 sm:mt-10 relative z-10">

        <!-- ── Page header ── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6 sm:mb-8">
            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-neutral-900">
                จัดการข้อมูลส่วนกลาง
                <span class="text-indigo-600 font-bold bg-indigo-50 px-2.5 py-1 rounded-full text-xs ml-1.5">ADMIN</span>
            </h1>
            <div class="flex gap-2 flex-wrap">
                <?php foreach ([
                    ['icon'=>'users',  'color'=>'emerald', 'count'=>$stats['students'], 'label'=>'นักเรียน'],
                    ['icon'=>'award',  'color'=>'indigo',  'count'=>$stats['certs'],    'label'=>'เกียรติบัตร'],
                    ['icon'=>'images', 'color'=>'amber',   'count'=>$stats['albums'],   'label'=>'อัลบั้ม'],
                ] as $s): ?>
                <div class="bg-white px-3 py-2 rounded-xl shadow-sm border border-neutral-100 flex items-center gap-2">
                    <i data-lucide="<?php echo $s['icon']; ?>" class="w-4 h-4 text-<?php echo $s['color']; ?>-500 flex-shrink-0"></i>
                    <span class="text-sm font-bold"><?php echo $s['count']; ?> <span class="text-xs text-neutral-400 font-normal"><?php echo $s['label']; ?></span></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             UPLOAD FORMS
        ═════════════════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 mb-8 sm:mb-10">

            <!-- 🏆 เกียรติบัตร -->
            <div class="premium-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl shadow-sm border-t-4 border-indigo-500 flex flex-col">
                <h3 class="text-base sm:text-lg font-bold text-neutral-900 mb-5 flex items-center gap-2">
                    <i data-lucide="award" class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-500"></i>
                    เผยแพร่เกียรติบัตรส่วนกลาง
                </h3>
                <form id="adminCertForm" class="space-y-4 flex-1 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-neutral-400 uppercase">ชื่อเกียรติบัตร (หมวดหมู่)</label>
                                <input type="text" name="title" required placeholder="เช่น รางวัลนักเรียนดีเด่น"
                                    class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-neutral-400 uppercase">กลุ่มผู้รับเป้าหมาย</label>
                                <input type="text" name="recipient_name" placeholder="เช่น ม.4/1, ทุกคน"
                                    class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none">
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-neutral-400 uppercase">ไฟล์เกียรติบัตร (รูปภาพ / PDF)</label>
                            <div class="file-drop-zone flex flex-col items-center justify-center gap-2 p-5 text-center"
                                 id="certDropZone"
                                 ondragover="zoneDragOver(event,'certDropZone')"
                                 ondragleave="zoneDragLeave('certDropZone')"
                                 ondrop="zoneDrop(event,'certFiles','certDropZone','certFileCount')">
                                <input type="file" name="files[]" id="certFiles" multiple required accept="image/*,application/pdf"
                                       onchange="updateZoneCount('certFiles','certFileCount')">
                                <i data-lucide="upload-cloud" class="w-8 h-8 text-indigo-300 pointer-events-none"></i>
                                <p class="text-sm text-neutral-500 pointer-events-none">วางไฟล์ที่นี่ หรือ <span class="text-indigo-600 font-semibold">กดเลือกไฟล์</span></p>
                                <p class="text-[11px] text-neutral-400 pointer-events-none">JPG · PNG · PDF — เลือกได้หลายไฟล์</p>
                                <div id="certFileCount" class="text-xs font-semibold text-indigo-700 hidden pointer-events-none"></div>
                            </div>
                        </div>
                    </div>

                    <div id="uploadProgressCert" class="hidden mt-3 p-3.5 bg-indigo-50 rounded-xl border border-indigo-100">
                        <div class="flex justify-between text-xs font-bold text-indigo-800 mb-2">
                            <span id="progressTextCert">กำลังเตรียมไฟล์...</span>
                            <span id="progressPercentCert">0%</span>
                        </div>
                        <div class="w-full bg-indigo-200 rounded-full h-2.5">
                            <div id="progressBarCert" class="progress-fill bg-indigo-600 h-2.5 rounded-full" style="width:0%"></div>
                        </div>
                        <p id="progressDetailCert" class="text-[11px] text-indigo-600 mt-1.5"></p>
                    </div>

                    <button type="submit" id="submitBtnCert"
                        class="w-full py-3.5 mt-4 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 active:scale-95 transition-all shadow-md shadow-indigo-200 flex items-center justify-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i> เริ่มอัปโหลดเกียรติบัตร
                    </button>
                </form>
            </div>

            <!-- 📸 อัลบั้มภาพ -->
            <div class="premium-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl shadow-sm border-t-4 border-amber-500 flex flex-col">
                <h3 class="text-base sm:text-lg font-bold text-neutral-900 mb-5 flex items-center gap-2">
                    <i data-lucide="images" class="w-5 h-5 sm:w-6 sm:h-6 text-amber-500"></i>
                    สร้างอัลบั้มภาพส่วนกลาง
                    <span class="text-[10px] bg-amber-100 text-amber-700 px-2 py-1 rounded-full font-bold">WEBP</span>
                </h3>
                <form id="adminAlbumForm" class="space-y-4 flex-1 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-neutral-400 uppercase">ชื่ออัลบั้มกิจกรรม</label>
                                <input type="text" name="album_name" required placeholder="เช่น วันสถาปนาโรงเรียน 2568"
                                    class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400 outline-none">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-neutral-400 uppercase">หมวดหมู่</label>
                                <input type="text" name="category" value="กิจกรรมโรงเรียน" required
                                    class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400 outline-none">
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-neutral-400 uppercase">รูปภาพ (แปลง WebP อัตโนมัติ)</label>
                            <div class="file-drop-zone flex flex-col items-center justify-center gap-2 p-5 text-center"
                                 id="albumDropZone"
                                 ondragover="zoneDragOver(event,'albumDropZone')"
                                 ondragleave="zoneDragLeave('albumDropZone')"
                                 ondrop="zoneDrop(event,'albumFiles','albumDropZone','albumFileCount')">
                                <input type="file" name="images[]" id="albumFiles" multiple required accept="image/*"
                                       onchange="updateZoneCount('albumFiles','albumFileCount')">
                                <i data-lucide="image-plus" class="w-8 h-8 text-amber-300 pointer-events-none"></i>
                                <p class="text-sm text-neutral-500 pointer-events-none">วางรูปที่นี่ หรือ <span class="text-amber-600 font-semibold">กดเลือกรูป</span></p>
                                <p class="text-[11px] text-neutral-400 pointer-events-none">JPG · PNG · HEIC — เลือกได้หลายรูป</p>
                                <div id="albumFileCount" class="text-xs font-semibold text-amber-700 hidden pointer-events-none"></div>
                            </div>
                        </div>
                    </div>

                    <div id="uploadProgressAlbum" class="hidden mt-3 p-3.5 bg-amber-50 rounded-xl border border-amber-100">
                        <div class="flex justify-between text-xs font-bold text-amber-800 mb-2">
                            <span id="progressTextAlbum">กำลังเตรียมไฟล์...</span>
                            <span id="progressPercentAlbum">0%</span>
                        </div>
                        <div class="w-full bg-amber-200 rounded-full h-2.5">
                            <div id="progressBarAlbum" class="progress-fill bg-amber-500 h-2.5 rounded-full" style="width:0%"></div>
                        </div>
                        <p id="progressDetailAlbum" class="text-[11px] text-amber-700 mt-1.5"></p>
                    </div>

                    <button type="submit" id="submitBtnAlbum"
                        class="w-full py-3.5 mt-4 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 active:scale-95 transition-all shadow-md shadow-slate-200 flex items-center justify-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> ยืนยันสร้างและอัปโหลดอัลบั้ม
                    </button>
                </form>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             ตารางเกียรติบัตร
        ═════════════════════════════════════════ -->
        <div class="premium-card rounded-2xl sm:rounded-3xl shadow-sm overflow-hidden bg-white mb-6 sm:mb-10 border-t-4 border-indigo-500">
            <div class="p-4 sm:p-6 border-b border-neutral-100 bg-neutral-50/50 flex items-center gap-2">
                <i data-lucide="folder-kanban" class="w-4 h-4 text-indigo-500"></i>
                <h3 class="text-sm font-bold text-neutral-900 uppercase tracking-wider">รายการเกียรติบัตรส่วนกลางทั้งหมด</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse data-table">
                    <thead class="bg-neutral-50 text-[10px] font-bold text-neutral-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-5 py-4">ชื่อกลุ่มเกียรติบัตร</th>
                            <th class="px-5 py-4">กลุ่มผู้รับ</th>
                            <th class="px-5 py-4 text-center">จำนวนไฟล์</th>
                            <th class="px-5 py-4">อัปเดตล่าสุด</th>
                            <th class="px-5 py-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        <?php foreach ($admin_cert_groups as $group): ?>
                        <tr class="hover:bg-indigo-50/30 transition">
                            <td class="px-5 py-4 font-bold text-neutral-800 text-sm" data-label="ชื่อกลุ่ม"><?php echo htmlspecialchars($group['title']); ?></td>
                            <td class="px-5 py-4 text-xs text-neutral-500" data-label="กลุ่มผู้รับ"><?php echo htmlspecialchars($group['activity_topic'] ?? '-'); ?></td>
                            <td class="px-5 py-4 text-center" data-label="จำนวน"><span class="px-3 py-1 bg-indigo-100 text-indigo-700 font-bold rounded-full text-xs"><?php echo $group['total_files']; ?> ไฟล์</span></td>
                            <td class="px-5 py-4 text-[11px] text-neutral-400" data-label="อัปเดต"><?php echo date('d/m/Y H:i', strtotime($group['latest_date'])); ?></td>
                            <td class="px-5 py-4 text-right action-cell">
                                <button onclick="editCertGroup('<?php echo htmlspecialchars(addslashes($group['title'])); ?>','<?php echo htmlspecialchars(addslashes($group['activity_topic'] ?? '')); ?>')"
                                    class="px-3 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold hover:bg-indigo-600 hover:text-white transition shadow-sm">✏️ แก้ไข</button>
                                <button onclick="viewGroupFiles('<?php echo htmlspecialchars(addslashes($group['title'])); ?>')"
                                    class="px-3 py-2 bg-white border border-neutral-200 text-neutral-600 rounded-lg text-xs font-bold hover:bg-indigo-50 transition shadow-sm">ดู/ลบทีละไฟล์</button>
                                <button onclick="deleteGroup('<?php echo htmlspecialchars(addslashes($group['title'])); ?>')"
                                    class="px-3 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-bold hover:bg-red-600 hover:text-white transition shadow-sm">ลบทั้งกลุ่ม</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($admin_cert_groups)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-neutral-400 text-xs">ไม่มีข้อมูลเกียรติบัตร</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             ตารางอัลบั้ม
        ═════════════════════════════════════════ -->
        <div class="premium-card rounded-2xl sm:rounded-3xl shadow-sm overflow-hidden bg-white mb-10 border-t-4 border-amber-400">
            <div class="p-4 sm:p-6 border-b border-neutral-100 bg-neutral-50/50 flex items-center gap-2">
                <i data-lucide="images" class="w-4 h-4 text-amber-500"></i>
                <h3 class="text-sm font-bold text-neutral-900 uppercase tracking-wider">รายการอัลบั้มกิจกรรมส่วนกลาง</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse data-table">
                    <thead class="bg-neutral-50 text-[10px] font-bold text-neutral-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-5 py-4">ชื่ออัลบั้มกิจกรรม</th>
                            <th class="px-5 py-4">หมวดหมู่</th>
                            <th class="px-5 py-4 text-center">รูปภาพ</th>
                            <th class="px-5 py-4">สร้างเมื่อ</th>
                            <th class="px-5 py-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        <?php foreach ($admin_albums_list as $album): ?>
                        <tr class="hover:bg-amber-50/30 transition">
                            <td class="px-5 py-4 font-bold text-neutral-800 text-sm" data-label="ชื่ออัลบั้ม"><?php echo htmlspecialchars($album['album_name']); ?></td>
                            <td class="px-5 py-4 text-xs text-neutral-500" data-label="หมวดหมู่"><?php echo htmlspecialchars($album['category']); ?></td>
                            <td class="px-5 py-4 text-center" data-label="รูปภาพ"><span class="px-3 py-1 bg-amber-100 text-amber-700 font-bold rounded-full text-xs"><?php echo $album['total_images']; ?> รูป</span></td>
                            <td class="px-5 py-4 text-[11px] text-neutral-400" data-label="สร้างเมื่อ"><?php echo date('d/m/Y H:i', strtotime($album['created_at'])); ?></td>
                            <td class="px-5 py-4 text-right action-cell">
                                <button onclick="editAlbum(<?php echo $album['id']; ?>,'<?php echo htmlspecialchars(addslashes($album['album_name'])); ?>','<?php echo htmlspecialchars(addslashes($album['category'])); ?>')"
                                    class="px-3 py-2 bg-amber-50 text-amber-700 rounded-lg text-xs font-bold hover:bg-amber-500 hover:text-white transition shadow-sm">✏️ แก้ไข</button>
                                <button onclick="openAddImagesModal(<?php echo $album['id']; ?>,'<?php echo htmlspecialchars(addslashes($album['album_name'])); ?>')"
                                    class="px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-500 hover:text-white transition shadow-sm">➕ เพิ่มรูป</button>
                                <button onclick="viewAlbumAdmin(<?php echo $album['id']; ?>,'<?php echo htmlspecialchars(addslashes($album['album_name'])); ?>')"
                                    class="px-3 py-2 bg-white border border-neutral-200 text-neutral-600 rounded-lg text-xs font-bold hover:bg-amber-50 transition shadow-sm">จัดการ/เรียง</button>
                                <button onclick="deleteAlbumGroup(<?php echo $album['id']; ?>,'<?php echo htmlspecialchars(addslashes($album['album_name'])); ?>')"
                                    class="px-3 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-bold hover:bg-red-600 hover:text-white transition shadow-sm">ลบทั้งอัลบั้ม</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($admin_albums_list)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-neutral-400 text-xs">ไม่มีข้อมูลอัลบั้ม</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════════
         MODAL: ดู/จัดการ/เรียงรูป (Drag & Drop)
    ════════════════════════════════════════════════════ -->
    <div id="manageFilesModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-end sm:items-center justify-center sm:p-4 backdrop-blur-sm">
        <div class="bg-white w-full sm:max-w-4xl rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] sm:max-h-[85vh] modal-enter">
            <div class="p-4 sm:p-6 border-b border-neutral-100 flex justify-between items-center bg-neutral-50 flex-shrink-0">
                <h3 class="font-bold text-base sm:text-lg text-neutral-900 flex items-center gap-2" id="modalTitle">ไฟล์ในกลุ่ม</h3>
                <button onclick="closeModal()" class="p-2 hover:bg-neutral-200 rounded-full text-neutral-500 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div id="dragHint" class="hidden px-4 sm:px-6 pt-3 pb-1 flex-shrink-0">
                <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 flex items-center gap-2">
                    <i data-lucide="move" class="w-3 h-3 flex-shrink-0"></i>
                    ลากรูปเพื่อเรียงลำดับ — กด <strong class="ml-1">บันทึกลำดับ</strong> เพื่อยืนยัน
                </p>
            </div>
            <div id="dragSaveBar" class="hidden px-4 sm:px-6 pt-2 pb-1 flex-shrink-0">
                <button onclick="saveOrder()" id="saveOrderBtn"
                    class="w-full py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 active:scale-95 transition-all">
                    💾 บันทึกลำดับรูปภาพ
                </button>
            </div>
            <div class="p-4 sm:p-6 overflow-y-auto flex-1 bg-neutral-50/50">
                <div id="fileListGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4"></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         MODAL: เพิ่มรูปภาพเข้าอัลบั้มที่มีอยู่แล้ว
    ════════════════════════════════════════════════════ -->
    <div id="addImagesModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-end sm:items-center justify-center sm:p-4 backdrop-blur-sm">
        <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden modal-enter">
            <div class="p-4 sm:p-6 border-b border-neutral-100 bg-neutral-50 flex justify-between items-center">
                <h3 class="font-bold text-base sm:text-lg text-neutral-900 flex items-center gap-2">
                    <i data-lucide="image-plus" class="w-5 h-5 text-emerald-500"></i> เพิ่มรูปภาพเข้าอัลบั้ม
                </h3>
                <button onclick="closeAddImagesModal()" class="p-2 hover:bg-neutral-200 rounded-full text-neutral-500 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5 sm:p-6 space-y-4">
                <input type="hidden" id="addImgAlbumId">
                <p class="text-sm text-neutral-600">อัลบั้ม: <span id="addImgAlbumName" class="font-bold text-neutral-900"></span></p>
                <div class="file-drop-zone flex flex-col items-center justify-center gap-2 p-5 text-center"
                     id="addImgDropZone"
                     ondragover="zoneDragOver(event,'addImgDropZone')"
                     ondragleave="zoneDragLeave('addImgDropZone')"
                     ondrop="zoneDrop(event,'addImgFiles','addImgDropZone','addImgFileCount')">
                    <input type="file" id="addImgFiles" multiple accept="image/*"
                           onchange="updateZoneCount('addImgFiles','addImgFileCount')">
                    <i data-lucide="image-plus" class="w-8 h-8 text-emerald-300 pointer-events-none"></i>
                    <p class="text-sm text-neutral-500 pointer-events-none">วางรูปที่นี่ หรือ <span class="text-emerald-600 font-semibold">กดเลือกรูป</span></p>
                    <div id="addImgFileCount" class="text-xs font-semibold text-emerald-700 hidden pointer-events-none"></div>
                </div>
                <div id="uploadProgressAdd" class="hidden p-3.5 bg-emerald-50 rounded-xl border border-emerald-100">
                    <div class="flex justify-between text-xs font-bold text-emerald-800 mb-2">
                        <span id="progressTextAdd">กำลังอัปโหลด...</span>
                        <span id="progressPercentAdd">0%</span>
                    </div>
                    <div class="w-full bg-emerald-200 rounded-full h-2.5">
                        <div id="progressBarAdd" class="progress-fill bg-emerald-500 h-2.5 rounded-full" style="width:0%"></div>
                    </div>
                </div>
                <div class="flex gap-3 pt-1">
                    <button onclick="closeAddImagesModal()" class="flex-1 py-3 bg-neutral-100 text-neutral-600 rounded-xl font-bold text-sm hover:bg-neutral-200 transition">ยกเลิก</button>
                    <button onclick="startAddImages()" id="startAddImgBtn"
                        class="flex-1 py-3 bg-emerald-500 text-white rounded-xl font-bold text-sm hover:bg-emerald-600 active:scale-95 transition-all shadow-md shadow-emerald-200">
                        อัปโหลดรูปภาพ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Edit Modal: Album ── -->
    <div id="editAlbumModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-end sm:items-center justify-center sm:p-4 backdrop-blur-sm">
        <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden modal-enter">
            <div class="p-4 sm:p-6 border-b border-neutral-100 bg-neutral-50 flex justify-between items-center">
                <h3 class="font-bold text-base sm:text-lg text-neutral-900 flex items-center gap-2">
                    <i data-lucide="pencil" class="w-5 h-5 text-amber-500"></i> แก้ไขอัลบั้มกิจกรรม
                </h3>
                <button onclick="closeEditAlbumModal()" class="p-2 hover:bg-neutral-200 rounded-full text-neutral-500 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5 sm:p-6 space-y-4">
                <input type="hidden" id="editAlbumId">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-neutral-400 uppercase">ชื่ออัลบั้มกิจกรรม</label>
                    <input type="text" id="editAlbumName" class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-neutral-400 uppercase">หมวดหมู่</label>
                    <input type="text" id="editAlbumCategory" class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400 outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="closeEditAlbumModal()" class="flex-1 py-3 bg-neutral-100 text-neutral-600 rounded-xl font-bold text-sm hover:bg-neutral-200 transition">ยกเลิก</button>
                    <button onclick="saveEditAlbum()" id="saveAlbumBtn" class="flex-1 py-3 bg-amber-500 text-white rounded-xl font-bold text-sm hover:bg-amber-600 active:scale-95 transition-all shadow-md shadow-amber-200">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Edit Modal: Cert ── -->
    <div id="editCertModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-end sm:items-center justify-center sm:p-4 backdrop-blur-sm">
        <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden modal-enter">
            <div class="p-4 sm:p-6 border-b border-neutral-100 bg-neutral-50 flex justify-between items-center">
                <h3 class="font-bold text-base sm:text-lg text-neutral-900 flex items-center gap-2">
                    <i data-lucide="pencil" class="w-5 h-5 text-indigo-500"></i> แก้ไขกลุ่มเกียรติบัตร
                </h3>
                <button onclick="closeEditCertModal()" class="p-2 hover:bg-neutral-200 rounded-full text-neutral-500 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5 sm:p-6 space-y-4">
                <input type="hidden" id="editCertOldTitle">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-neutral-400 uppercase">ชื่อกลุ่มเกียรติบัตร (หมวดหมู่)</label>
                    <input type="text" id="editCertNewTitle" class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-neutral-400 uppercase">กลุ่มผู้รับเป้าหมาย</label>
                    <input type="text" id="editCertRecipient" class="w-full px-4 py-3 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="closeEditCertModal()" class="flex-1 py-3 bg-neutral-100 text-neutral-600 rounded-xl font-bold text-sm hover:bg-neutral-200 transition">ยกเลิก</button>
                    <button onclick="saveEditCert()" id="saveCertBtn" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 active:scale-95 transition-all shadow-md shadow-indigo-200">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════ SCRIPTS ═══════════════════════════════ -->
    <script>
    document.addEventListener("DOMContentLoaded", () => lucide.createIcons());

    // ─── Drop Zone helpers ────────────────────────────────────────────────────
    function zoneDragOver(e, zoneId) {
        e.preventDefault();
        document.getElementById(zoneId).classList.add('dragover');
    }
    function zoneDragLeave(zoneId) {
        document.getElementById(zoneId).classList.remove('dragover');
    }
    function zoneDrop(e, inputId, zoneId, countId) {
        e.preventDefault();
        document.getElementById(zoneId).classList.remove('dragover');
        const input = document.getElementById(inputId);
        const dt = new DataTransfer();
        [...e.dataTransfer.files].forEach(f => dt.items.add(f));
        input.files = dt.files;
        updateZoneCount(inputId, countId);
    }
    function updateZoneCount(inputId, countId) {
        const n = document.getElementById(inputId).files.length;
        const el = document.getElementById(countId);
        if (n > 0) { el.textContent = `เลือกแล้ว ${n} ไฟล์`; el.classList.remove('hidden'); }
        else { el.classList.add('hidden'); }
    }

    // ─── Parallel upload (concurrency 4 = ~4x faster) ─────────────────────────
    async function parallelUpload({ files, buildFd, url, onProgress, concurrency = 4 }) {
        let done = 0, success = 0, errors = 0;
        const queue = [...files];
        async function worker() {
            while (queue.length > 0) {
                const file = queue.shift();
                if (!file) return;
                try {
                    const res = await (await fetch(url, { method: 'POST', body: buildFd(file) })).json();
                    if (res.status === 'success') success++; else errors++;
                } catch { errors++; }
                done++;
                onProgress(done, files.length, success, errors);
            }
        }
        const workers = [];
        for (let i = 0; i < Math.min(concurrency, files.length); i++) workers.push(worker());
        await Promise.all(workers);
        return { success, errors };
    }

    function setProgress(barId, textId, percentId, pct, text, detail, detailId) {
        document.getElementById(barId).style.width = pct + '%';
        document.getElementById(percentId).innerText = pct + '%';
        if (text) document.getElementById(textId).innerText = text;
        if (detailId && detail !== undefined) document.getElementById(detailId).innerText = detail;
    }

    // ─── 🏆 อัปโหลดเกียรติบัตร ────────────────────────────────────────────────
    document.getElementById('adminCertForm').onsubmit = async (e) => {
        e.preventDefault();
        const form  = e.target;
        const btn   = document.getElementById('submitBtnCert');
        const files = document.getElementById('certFiles').files;
        if (!files.length) return;
        btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed');
        document.getElementById('uploadProgressCert').classList.remove('hidden');

        const title     = form.querySelector('input[name="title"]').value;
        const recipient = form.querySelector('input[name="recipient_name"]').value;

        const { success, errors } = await parallelUpload({
            files: [...files],
            url: 'admin_manage.php?action=upload_admin_cert',
            buildFd(file) {
                const fd = new FormData();
                fd.append('title', title); fd.append('recipient_name', recipient); fd.append('files[]', file);
                return fd;
            },
            concurrency: 4,
            onProgress(done, total, success, errors) {
                const pct = Math.round((done / total) * 100);
                setProgress('progressBarCert','progressTextCert','progressPercentCert', pct,
                    `⬆️ อัปโหลด ${done}/${total}`,
                    `สำเร็จ ${success}${errors > 0 ? ` · ผิดพลาด ${errors}` : ''}`,
                    'progressDetailCert');
            }
        });
        setProgress('progressBarCert','progressTextCert','progressPercentCert', 100, `✅ เสร็จแล้ว ${success} ไฟล์`, '', 'progressDetailCert');
        setTimeout(() => location.reload(), 700);
    };

    // ─── 📸 อัปโหลดอัลบั้มใหม่ ────────────────────────────────────────────────
    document.getElementById('adminAlbumForm').onsubmit = async (e) => {
        e.preventDefault();
        const form  = e.target;
        const btn   = document.getElementById('submitBtnAlbum');
        const files = document.getElementById('albumFiles').files;
        if (!files.length) return;
        btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed');
        document.getElementById('uploadProgressAlbum').classList.remove('hidden');
        document.getElementById('progressTextAlbum').innerText = 'กำลังสร้างอัลบั้ม...';

        let albumId = 0;
        try {
            const initData = await (await fetch('admin_manage.php?action=create_admin_album_init', { method: 'POST', body: new FormData(form) })).json();
            if (initData.status !== 'success') throw new Error();
            albumId = initData.album_id;
        } catch { alert('สร้างอัลบั้มไม่สำเร็จ'); location.reload(); return; }

        const { success, errors } = await parallelUpload({
            files: [...files],
            url: 'admin_manage.php?action=upload_admin_album_image',
            buildFd(file) {
                const fd = new FormData();
                fd.append('album_id', albumId); fd.append('file', file);
                return fd;
            },
            concurrency: 4,
            onProgress(done, total, success, errors) {
                const pct = Math.round((done / total) * 100);
                setProgress('progressBarAlbum','progressTextAlbum','progressPercentAlbum', pct,
                    `⬆️ แปลงและอัปโหลด ${done}/${total}`,
                    `สำเร็จ ${success}${errors > 0 ? ` · ผิดพลาด ${errors}` : ''}`,
                    'progressDetailAlbum');
            }
        });
        setProgress('progressBarAlbum','progressTextAlbum','progressPercentAlbum', 100, `✅ เสร็จแล้ว ${success} รูป`, '', 'progressDetailAlbum');
        setTimeout(() => location.reload(), 700);
    };

    // ─── ➕ เพิ่มรูปเข้าอัลบั้มที่มีอยู่ ──────────────────────────────────────
    function openAddImagesModal(albumId, albumName) {
        document.getElementById('addImgAlbumId').value = albumId;
        document.getElementById('addImgAlbumName').textContent = albumName;
        document.getElementById('addImgFiles').value = '';
        document.getElementById('addImgFileCount').classList.add('hidden');
        document.getElementById('uploadProgressAdd').classList.add('hidden');
        document.getElementById('startAddImgBtn').disabled = false;
        document.getElementById('startAddImgBtn').classList.remove('opacity-50');
        document.getElementById('addImagesModal').classList.remove('hidden');
        lucide.createIcons();
    }
    function closeAddImagesModal() { document.getElementById('addImagesModal').classList.add('hidden'); }

    async function startAddImages() {
        const albumId = document.getElementById('addImgAlbumId').value;
        const files   = document.getElementById('addImgFiles').files;
        if (!files.length) { alert('กรุณาเลือกรูปภาพก่อน'); return; }
        const btn = document.getElementById('startAddImgBtn');
        btn.disabled = true; btn.classList.add('opacity-50');
        document.getElementById('uploadProgressAdd').classList.remove('hidden');

        const { success, errors } = await parallelUpload({
            files: [...files],
            url: 'admin_manage.php?action=upload_admin_album_image',
            buildFd(file) {
                const fd = new FormData();
                fd.append('album_id', albumId); fd.append('file', file);
                return fd;
            },
            concurrency: 4,
            onProgress(done, total, s, er) {
                const pct = Math.round((done / total) * 100);
                document.getElementById('progressBarAdd').style.width = pct + '%';
                document.getElementById('progressPercentAdd').innerText = pct + '%';
                document.getElementById('progressTextAdd').innerText = `${done}/${total} รูป`;
            }
        });
        document.getElementById('progressTextAdd').innerText = `✅ สำเร็จ ${success} รูป`;
        setTimeout(() => { closeAddImagesModal(); location.reload(); }, 700);
    }

    // ─── 🗂️ Modal ดู/จัดการ/เรียงรูป ────────────────────────────────────────
    const modal    = document.getElementById('manageFilesModal');
    const fileGrid = document.getElementById('fileListGrid');
    let currentAlbumId = null;

    function closeModal() { modal.classList.add('hidden'); }

    async function deleteGroup(title) {
        if (!confirm(`ยืนยันการลบกลุ่มเกียรติบัตร "${title}" ทั้งหมด?`)) return;
        const fd = new FormData(); fd.append('title', title);
        const res = await (await fetch('admin_manage.php?action=delete_cert_group', { method:'POST', body:fd })).json();
        if (res.status === 'success') location.reload();
    }

    async function viewGroupFiles(title) {
        document.getElementById('modalTitle').innerText = `เกียรติบัตร: ${title}`;
        document.getElementById('dragHint').classList.add('hidden');
        document.getElementById('dragSaveBar').classList.add('hidden');
        modal.classList.remove('hidden');
        fileGrid.innerHTML = '<p class="col-span-full text-center py-8 text-neutral-400">กำลังโหลด...</p>';
        const fd = new FormData(); fd.append('title', title);
        const files = await (await fetch('admin_manage.php?action=get_group_files', { method:'POST', body:fd })).json();
        renderModalGrid(files, 'cert');
    }

    async function deleteCertSingle(id) {
        if (!confirm('ลบไฟล์เกียรติบัตรนี้?')) return;
        const card = document.getElementById(`item_card_${id}`);
        card.style.opacity = '.4';
        const fd = new FormData(); fd.append('id', id);
        const res = await (await fetch('admin_manage.php?action=delete_cert_single', { method:'POST', body:fd })).json();
        if (res.status === 'success') card.remove(); else { card.style.opacity='1'; alert('ลบไม่สำเร็จ'); }
    }

    async function deleteAlbumGroup(id, name) {
        if (!confirm(`ยืนยันการลบอัลบั้ม "${name}" พร้อมรูปทั้งหมด?`)) return;
        const fd = new FormData(); fd.append('album_id', id);
        const res = await (await fetch('admin_manage.php?action=delete_album_group', { method:'POST', body:fd })).json();
        if (res.status === 'success') location.reload();
    }

    async function viewAlbumAdmin(id, name) {
        currentAlbumId = id;
        document.getElementById('modalTitle').innerHTML = `<i data-lucide="images" class="w-5 h-5 inline text-amber-500"></i> จัดการอัลบั้ม: ${name}`;
        document.getElementById('dragHint').classList.remove('hidden');
        document.getElementById('dragSaveBar').classList.remove('hidden');
        document.getElementById('saveOrderBtn').textContent = '💾 บันทึกลำดับรูปภาพ';
        document.getElementById('saveOrderBtn').disabled = false;
        modal.classList.remove('hidden');
        fileGrid.innerHTML = '<p class="col-span-full text-center py-8 text-neutral-400">กำลังโหลด...</p>';
        lucide.createIcons();
        const fd = new FormData(); fd.append('album_id', id);
        const files = await (await fetch('admin_manage.php?action=get_album_images_admin', { method:'POST', body:fd })).json();
        renderModalGrid(files, 'album');
    }

    async function deleteAlbumImageSingle(id) {
        if (!confirm('ลบรูปภาพนี้ออกจากอัลบั้ม?')) return;
        const card = document.getElementById(`item_card_${id}`);
        card.style.opacity = '.4';
        const fd = new FormData(); fd.append('id', id);
        const res = await (await fetch('admin_manage.php?action=delete_album_image_single', { method:'POST', body:fd })).json();
        if (res.status === 'success') card.remove(); else { card.style.opacity='1'; alert('ลบไม่สำเร็จ'); }
    }

    function renderModalGrid(files, type) {
        fileGrid.innerHTML = '';
        if (!files.length) { fileGrid.innerHTML = '<p class="col-span-full text-center py-8 text-neutral-400">ไม่มีข้อมูล</p>'; return; }

        files.forEach(f => {
            const ext    = f.file_path.split('.').pop().toLowerCase();
            const isPdf  = ext === 'pdf';
            const folder = type === 'cert' ? 'certificates' : 'admin-albums';
            const delFn  = type === 'cert' ? `deleteCertSingle(${f.id})` : `deleteAlbumImageSingle(${f.id})`;
            const preview = isPdf
                ? `<div class="aspect-[4/3] bg-red-50 flex items-center justify-center border-b"><i data-lucide="file-text" class="w-10 h-10 text-red-400"></i></div>`
                : `<img src="../uploads/${folder}/${f.file_path}" class="w-full aspect-[4/3] object-cover border-b" loading="lazy">`;

            const card = document.createElement('div');
            card.className = 'bg-white border rounded-xl overflow-hidden shadow-sm hover:shadow-md flex flex-col' +
                             (type === 'album' ? ' drag-item select-none' : '');
            card.id = `item_card_${f.id}`;
            card.dataset.id = f.id;
            if (type === 'album') {
                card.draggable = true;
                card.addEventListener('dragstart', dragStart);
                card.addEventListener('dragover',  dragOver);
                card.addEventListener('drop',      dragDrop);
                card.addEventListener('dragend',   dragEnd);
                card.addEventListener('touchstart', touchStart, { passive: true });
                card.addEventListener('touchmove',  touchMove,  { passive: false });
                card.addEventListener('touchend',   touchEnd,   { passive: true });
            }
            card.innerHTML = `
                ${preview}
                <div class="p-2 flex flex-col gap-1.5 text-center">
                    ${type === 'album' ? '<p class="text-[10px] text-neutral-400">≡ ลากเพื่อเรียง</p>' : ''}
                    <a href="../uploads/${folder}/${f.file_path}" target="_blank" class="text-[10px] text-indigo-600 hover:underline">ดูไฟล์</a>
                    <button onclick="${delFn}" class="w-full py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-bold hover:bg-red-600 hover:text-white transition">ลบทิ้ง</button>
                </div>`;
            fileGrid.appendChild(card);
        });
        lucide.createIcons();
    }

    // ─── Mouse Drag & Drop (เรียงลำดับ) ──────────────────────────────────────
    let dragSrc = null;
    function dragStart()       { dragSrc = this; this.classList.add('drag-ghost'); }
    function dragOver(e)       { e.preventDefault(); if (this !== dragSrc) this.classList.add('drag-over'); }
    function dragDrop(e)       {
        e.preventDefault(); this.classList.remove('drag-over');
        if (this === dragSrc) return;
        const nodes = [...fileGrid.children];
        const si = nodes.indexOf(dragSrc), di = nodes.indexOf(this);
        if (si < di) fileGrid.insertBefore(dragSrc, this.nextSibling);
        else         fileGrid.insertBefore(dragSrc, this);
    }
    function dragEnd()         { this.classList.remove('drag-ghost'); [...fileGrid.children].forEach(c => c.classList.remove('drag-over')); }

    // ─── Touch Drag (mobile) ──────────────────────────────────────────────────
    let tItem = null, tClone = null;
    function touchStart() {
        tItem = this;
        const r = this.getBoundingClientRect();
        tClone = this.cloneNode(true);
        tClone.style.cssText = `position:fixed;opacity:.8;pointer-events:none;width:${r.width}px;z-index:9999;top:${r.top}px;left:${r.left}px;border-radius:.75rem;box-shadow:0 8px 30px rgba(0,0,0,.2)`;
        document.body.appendChild(tClone);
        this.classList.add('drag-ghost');
    }
    function touchMove(e) {
        e.preventDefault();
        if (!tClone) return;
        const t = e.touches[0];
        tClone.style.top  = (t.clientY - tClone.offsetHeight / 2) + 'px';
        tClone.style.left = (t.clientX - tClone.offsetWidth  / 2) + 'px';
        tClone.style.display = 'none';
        const el = document.elementFromPoint(t.clientX, t.clientY);
        tClone.style.display = '';
        const target = el ? el.closest('[data-id]') : null;
        [...fileGrid.children].forEach(c => c.classList.remove('drag-over'));
        if (target && target !== tItem) target.classList.add('drag-over');
    }
    function touchEnd(e) {
        if (!tItem || !tClone) return;
        tClone.remove(); tClone = null;
        tItem.classList.remove('drag-ghost');
        const t = e.changedTouches[0];
        const el = document.elementFromPoint(t.clientX, t.clientY);
        const target = el ? el.closest('[data-id]') : null;
        [...fileGrid.children].forEach(c => c.classList.remove('drag-over'));
        if (target && target !== tItem) {
            const nodes = [...fileGrid.children];
            const si = nodes.indexOf(tItem), di = nodes.indexOf(target);
            if (si < di) fileGrid.insertBefore(tItem, target.nextSibling);
            else         fileGrid.insertBefore(tItem, target);
        }
        tItem = null;
    }

    // ─── 💾 บันทึกลำดับ ───────────────────────────────────────────────────────
    async function saveOrder() {
        const btn = document.getElementById('saveOrderBtn');
        btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
        const orders = [...fileGrid.children].map((c, i) => ({ id: c.dataset.id, order: i }));
        const fd = new FormData(); fd.append('orders', JSON.stringify(orders));
        const res = await (await fetch('admin_manage.php?action=reorder_album_images', { method:'POST', body:fd })).json();
        if (res.status === 'success') {
            btn.textContent = '✅ บันทึกแล้ว';
            setTimeout(() => { btn.disabled = false; btn.textContent = '💾 บันทึกลำดับรูปภาพ'; }, 2000);
        } else {
            btn.disabled = false; btn.textContent = '💾 บันทึกลำดับรูปภาพ';
            alert('บันทึกไม่สำเร็จ — ตรวจสอบคอลัมน์ sort_order ในตาราง activity_images');
        }
    }

    // ─── ✏️ Edit modals ───────────────────────────────────────────────────────
    function editAlbum(id, name, cat) {
        document.getElementById('editAlbumId').value       = id;
        document.getElementById('editAlbumName').value     = name;
        document.getElementById('editAlbumCategory').value = cat;
        document.getElementById('editAlbumModal').classList.remove('hidden');
    }
    function closeEditAlbumModal() { document.getElementById('editAlbumModal').classList.add('hidden'); }
    async function saveEditAlbum() {
        const btn = document.getElementById('saveAlbumBtn');
        btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
        const fd = new FormData();
        fd.append('album_id',   document.getElementById('editAlbumId').value);
        fd.append('album_name', document.getElementById('editAlbumName').value);
        fd.append('category',   document.getElementById('editAlbumCategory').value);
        const res = await (await fetch('admin_manage.php?action=edit_album', { method:'POST', body:fd })).json();
        if (res.status === 'success') location.reload();
        else { btn.disabled = false; btn.textContent = 'บันทึก'; alert('บันทึกไม่สำเร็จ'); }
    }

    function editCertGroup(title, recipient) {
        document.getElementById('editCertOldTitle').value  = title;
        document.getElementById('editCertNewTitle').value  = title;
        document.getElementById('editCertRecipient').value = recipient;
        document.getElementById('editCertModal').classList.remove('hidden');
    }
    function closeEditCertModal() { document.getElementById('editCertModal').classList.add('hidden'); }
    async function saveEditCert() {
        const btn = document.getElementById('saveCertBtn');
        btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
        const fd = new FormData();
        fd.append('old_title',     document.getElementById('editCertOldTitle').value);
        fd.append('new_title',     document.getElementById('editCertNewTitle').value);
        fd.append('new_recipient', document.getElementById('editCertRecipient').value);
        const res = await (await fetch('admin_manage.php?action=edit_cert_group', { method:'POST', body:fd })).json();
        if (res.status === 'success') location.reload();
        else { btn.disabled = false; btn.textContent = 'บันทึก'; alert('บันทึกไม่สำเร็จ'); }
    }
    </script>
</body>
</html>
