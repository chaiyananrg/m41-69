<?php
// 📌 กำหนดค่าให้ระบบ Header ค้นหาและดึงไปสร้างเป็นเมนูอัตโนมัติ (Dynamic Menu System)
// MENU_TITLE: จัดการเกียรติบัตร
// MENU_ICON: award
// MENU_ORDER: 2

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ════════════════════════════════════════════════════════════════
// 🔒 USER GUARD — ต้อง login ก่อนเข้าถึงทุก request
// ไม่มี session = ปฏิเสธทันที ไม่มี fallback / default ใดๆ
// ════════════════════════════════════════════════════════════════
function require_user(): void {
    $uid  = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role']    ?? null;

    // ต้องมี user_id และ role ที่ถูกต้อง (User หรือ Admin)
    $valid_roles = ['User', 'Admin', 'Student'];
    if ($uid === null || !in_array($role, $valid_roles, true)) {
        // API / AJAX / POST → JSON 401
        if (
            isset($_GET['action']) ||
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ||
            (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        ) {
            header('Content-Type: application/json', true, 401);
            echo json_encode([
                'status'  => 'error',
                'code'    => 401,
                'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อนใช้งาน'
            ]);
            exit;
        }
        // Page request → redirect
        header('Location: index.php', true, 302);
        exit;
    }
}

// เรียกก่อนทุกอย่าง — ก่อน require_once db.php และก่อน logic ทั้งหมด
require_user();

require_once '../db.php';

// ค่าเหล่านี้ใช้ได้เฉพาะเมื่อผ่าน guard แล้ว (ไม่มีค่า default)
$current_user_id   = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? '';
$current_role      = $_SESSION['role'];

// ─── จัดการการอัปโหลด (Logic ส่วนหลังบ้าน) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // อัปโหลดรูปแบบปกติ
    if ($_GET['action'] === 'upload_image') {
        $title = $_POST['title'] ?? 'ไม่มีชื่อ';
        $activity_topic = $_POST['activity_topic'] ?? '';
        $category = $_POST['category'] ?? 'ทั่วไป';
        $description = $_POST['description'] ?? '';
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $filename = 'cert_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = '../uploads/certificates/' . $filename;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_path)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO certificates (user_id, title, activity_topic, category, description, file_path) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$current_user_id, $title, $activity_topic, $category, $description, $filename]);
                    echo json_encode(['status' => 'success', 'message' => 'บันทึกเกียรติบัตรสำเร็จ']);
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => 'DB Error']);
                }
            }
        }
        exit;
    }
    
    // บันทึกหน้าจาก PDF
    if ($_GET['action'] === 'save_extracted_page') {
        $data = json_decode(file_get_contents('php://input'), true);
        $image_data = $data['image'];
        $title = $data['title'];
        $activity_topic = $data['activity_topic'] ?? '';
        $category = $data['category'];
        
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);
        
        $filename = 'cert_pdf_' . time() . '_' . uniqid() . '.png';
        $upload_path = '../uploads/certificates/' . $filename;
        
        if (file_put_contents($upload_path, $decoded_image)) {
            try {
                $stmt = $conn->prepare("INSERT INTO certificates (user_id, title, activity_topic, category, file_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$current_user_id, $title, $activity_topic, $category, $filename]);
                echo json_encode(['status' => 'success', 'filename' => $filename]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'DB Error']);
            }
        }
        exit;
    }
}

// ─── ดึงข้อมูลและจัดการแสดงผล (สิทธิ์การเข้าถึง & จัดกลุ่ม) ─────────────────────
$all_certificates = [];
try {
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE user_id = ? OR is_admin_shared = 1 ORDER BY created_at DESC");
    $stmt->execute([$current_user_id]);
    $all_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$my_certs = [];       // เกียรติบัตรของฉันเอง
$admin_albums = [];   // เกียรติบัตรส่วนกลางที่จัดเป็นกลุ่ม

foreach ($all_certificates as $cert) {
    if ($cert['is_admin_shared'] == 1) {
        
        // 1. คัดกรองสิทธิ์การมองเห็น
        $target = trim($cert['activity_topic']);
        $can_see = false;
        
        // ถ้าเป็นค่าว่าง, "ทุกคน", "all" ให้เห็นทุกคน
        if (empty($target) || mb_strtolower($target) === 'ทุกคน' || mb_strtolower($target) === 'all' || mb_stripos($target, 'ทุก') !== false) {
            $can_see = true;
        } 
        // ถ้าระบุรหัสนักเรียน/ชื่อ เช็คว่ามีข้อมูลผู้ใช้ปัจจุบันอยู่ในนั้นหรือไม่
        else if (stripos($target, $current_user_id) !== false || stripos($target, $current_user_name) !== false) {
            $can_see = true;
        }

        // 2. ถ้ามีสิทธิ์เห็น ให้นำไปจัดกลุ่มตามชื่อ (Title) เป็นอัลบั้ม
        if ($can_see) {
            $title = $cert['title'];
            if (!isset($admin_albums[$title])) {
                $admin_albums[$title] = [
                    'title' => $title,
                    'category' => $cert['category'],
                    'created_at' => $cert['created_at'],
                    'target' => $target,
                    'items' => []
                ];
            }
            $admin_albums[$title]['items'][] = $cert;
        }
        
    } else {
        // ของที่ผู้ใช้อัปโหลดเอง
        $my_certs[] = $cert;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเกียรติบัตรและผลงาน - Portfolio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif; }
        .premium-card { background: #ffffff; border: 1px solid rgba(229, 229, 229, 0.6); }
        .pdf-viewer-container { max-height: 500px; overflow-y: auto; scrollbar-width: thin; }
        .page-canvas { border: 1px solid #ddd; margin-bottom: 10px; cursor: pointer; transition: transform 0.2s; width: 100%; border-radius: 0.75rem; }
        .page-canvas:hover { transform: scale(1.02); border-color: #6366f1; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .page-item { position: relative; }
        .page-match-badge { position: absolute; top: 12px; right: 12px; background: #ef4444; color: white; padding: 4px 10px; border-radius: 99px; font-size: 10px; font-weight: bold; z-index: 10; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .modal-enter { animation: fadeIn 0.2s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-[#fcfcfd] min-h-screen antialiased text-neutral-800 pb-12">

    <?php include 'header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 relative z-10">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">
                    สวัสดีครับ, <span class="underline decoration-indigo-200 decoration-2 underline-offset-4"><?php echo htmlspecialchars($current_user_name); ?></span>
                </h1>
                <p class="text-neutral-400 text-xs mt-1 font-light">รหัสของคุณ: <?php echo $current_user_id; ?></p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-neutral-200/60 rounded-full text-xs text-neutral-500 shadow-sm w-fit">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-neutral-400"></i>
                <span id="live-date">กำลังโหลดวันที่...</span>
            </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <h2 class="text-lg font-bold text-neutral-800 flex items-center gap-2">
                <i data-lucide="award" class="w-5 h-5 text-indigo-600"></i>
                แฟ้มสะสมผลงานของคุณ
            </h2>
            <div class="flex gap-2">
                <button type="button" onclick="openUploadModal('image')" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition shadow-sm text-sm font-medium">
                    <i data-lucide="image" class="w-4 h-4"></i> เพิ่มรูปภาพ
                </button>
                <button type="button" onclick="openUploadModal('pdf')" class="flex items-center gap-2 px-4 py-2 bg-white border border-neutral-200 text-neutral-700 rounded-xl hover:bg-neutral-50 transition shadow-sm text-sm font-medium">
                    <i data-lucide="file-text" class="w-4 h-4"></i> แยก PDF เป็นรูป
                </button>
            </div>
        </div>

        <div class="premium-card p-4 rounded-2xl mb-8 flex flex-wrap gap-4 items-center shadow-sm">
            <div class="relative flex-1 min-w-[250px]">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400"></i>
                <input type="text" id="gallerySearch" placeholder="ค้นหาชื่อเกียรติบัตร หรือหมวดหมู่..." class="w-full pl-10 pr-4 py-2 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" id="certificateGrid">
            <?php if (empty($my_certs) && empty($admin_albums)): ?>
                <div class="col-span-full py-20 text-center text-neutral-400">
                    <i data-lucide="award" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                    <p>ยังไม่มีเกียรติบัตรในระบบ เริ่มอัปโหลดผลงานแรกของคุณเลย!</p>
                </div>
            <?php else: ?>

                <?php foreach ($admin_albums as $title => $album): 
                    // เก็บข้อมูล JSON เพื่อส่งไปให้ Modal ประมวลผล
                    $album_json = htmlspecialchars(json_encode($album), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="premium-card rounded-2xl overflow-hidden group hover:shadow-xl transition-all duration-300 cert-item cursor-pointer" 
                         data-title="<?php echo htmlspecialchars($title); ?>" 
                         data-category="<?php echo htmlspecialchars($album['category']); ?>"
                         onclick="openAlbumModal('<?php echo $album_json; ?>')">
                         
                        <div class="aspect-[4/3] bg-neutral-100 relative overflow-hidden flex items-center justify-center">
                            <div class="absolute inset-0 bg-indigo-900/5 group-hover:bg-indigo-900/10 transition-colors"></div>
                            
                            <?php 
                                $first_item = $album['items'][0]['file_path'];
                                $is_pdf = (strtolower(pathinfo($first_item, PATHINFO_EXTENSION)) === 'pdf');
                            ?>
                            <?php if (!$is_pdf): ?>
                                <img src="../uploads/certificates/<?php echo $first_item; ?>" class="absolute inset-0 w-full h-full object-cover opacity-50 blur-[2px] group-hover:scale-105 transition-transform duration-500">
                            <?php endif; ?>

                            <div class="relative z-10 flex flex-col items-center">
                                <i data-lucide="layers" class="w-12 h-12 text-indigo-600 mb-2 drop-shadow-md"></i>
                                <span class="bg-white text-indigo-600 text-xs font-bold px-3 py-1 rounded-full shadow-md">
                                    <?php echo count($album['items']); ?> รายการ
                                </span>
                            </div>
                            
                            <span class="absolute top-3 left-3 px-2 py-0.5 bg-amber-500 text-white text-[9px] font-bold rounded shadow-sm uppercase tracking-tighter z-20">ประกาศส่วนกลาง</span>
                            
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center z-20">
                                <span class="text-white font-bold text-sm bg-indigo-600/80 px-4 py-2 rounded-full backdrop-blur-sm">เปิดดูอัลบั้ม</span>
                            </div>
                        </div>
                        <div class="p-4 bg-white relative z-10">
                            <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider"><?php echo htmlspecialchars($album['category']); ?></span>
                            <h3 class="text-sm font-semibold text-neutral-900 mt-1 line-clamp-1"><?php echo htmlspecialchars($title); ?></h3>
                            <p class="text-[10px] text-neutral-400 mt-1 flex items-center gap-1">
                                <i data-lucide="users" class="w-3 h-3"></i>
                                ผู้รับ: <?php echo htmlspecialchars($album['target'] ? $album['target'] : 'ทุกคน'); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($my_certs as $cert): ?>
                    <div class="premium-card rounded-2xl overflow-hidden group hover:shadow-xl transition-all duration-300 cert-item" 
                         data-title="<?php echo htmlspecialchars($cert['title']); ?>" 
                         data-category="<?php echo htmlspecialchars($cert['category']); ?>">
                        <div class="aspect-[4/3] bg-neutral-100 relative overflow-hidden">
                            <img src="../uploads/certificates/<?php echo htmlspecialchars($cert['file_path']); ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                 onerror="this.src='https://via.placeholder.com/400x300?text=Error+Loading'">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                <a href="../uploads/certificates/<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" class="p-2 bg-white rounded-full text-neutral-800 hover:bg-indigo-50 transition shadow-sm"><i data-lucide="eye" class="w-4 h-4"></i></a>
                                <a href="../uploads/certificates/<?php echo htmlspecialchars($cert['file_path']); ?>" download="<?php echo htmlspecialchars($cert['title']); ?>" class="p-2 bg-white rounded-full text-indigo-600 hover:bg-indigo-50 transition shadow-sm"><i data-lucide="download" class="w-4 h-4"></i></a>
                                <button onclick="deleteCert(<?php echo $cert['id']; ?>)" class="p-2 bg-white rounded-full text-red-500 hover:bg-red-50 transition shadow-sm"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                        <div class="p-4">
                            <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider"><?php echo htmlspecialchars($cert['category']); ?></span>
                            <h3 class="text-sm font-semibold text-neutral-900 mt-1 line-clamp-1"><?php echo htmlspecialchars($cert['title']); ?></h3>
                            <p class="text-[10px] text-neutral-400 mt-1 flex items-center gap-1">
                                <i data-lucide="clock" class="w-3 h-3"></i>
                                <?php echo date('d M Y', strtotime($cert['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </main>

    <div id="albumModal" class="fixed inset-0 bg-black/70 z-[110] hidden flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white w-full max-w-5xl rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] modal-enter">
            
            <div class="p-6 border-b border-neutral-100 flex justify-between items-center bg-neutral-50 shrink-0">
                <div>
                    <span class="text-[10px] font-bold text-amber-500 bg-amber-50 px-2 py-1 rounded uppercase tracking-wider mb-2 inline-block">ประกาศส่วนกลาง</span>
                    <h3 class="font-bold text-xl text-neutral-900" id="albumModalTitle">ชื่ออัลบั้ม...</h3>
                </div>
                <div class="flex gap-2">
                    <button onclick="downloadEntireAlbum()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition shadow-sm">
                        <i data-lucide="download-cloud" class="w-4 h-4"></i> ดาวน์โหลดทั้งหมด
                    </button>
                    <button onclick="closeAlbumModal()" class="p-2 hover:bg-neutral-200 rounded-xl text-neutral-500 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 bg-neutral-50/50">
                <div id="albumGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    </div>
            </div>
        </div>
    </div>

    <div id="uploadModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0 flex flex-col max-h-[90vh]" id="modalContainer">
            <div class="p-6 border-b border-neutral-100 flex justify-between items-center bg-white shrink-0">
                <h3 class="text-lg font-bold text-neutral-900" id="modalTitle">อัปโหลดเกียรติบัตรส่วนตัว</h3>
                <button type="button" onclick="closeModal()" class="text-neutral-400 hover:text-neutral-600 p-1 hover:bg-neutral-50 rounded-lg transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1">
                <form id="imageUploadForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-neutral-500 uppercase">ชื่อผลงาน</label>
                            <input type="text" name="title" id="cert_title" required class="w-full px-4 py-2 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-neutral-500 uppercase">หมวดหมู่</label>
                            <select name="category" id="cert_category" class="w-full px-4 py-2 bg-neutral-50 border border-neutral-200 rounded-xl text-sm focus:outline-none">
                                <option value="academic">วิชาการ</option>
                                <option value="sport">กีฬา</option>
                                <option value="art">ศิลปะ/ดนตรี</option>
                                <option value="volunteer">จิตอาสา</option>
                                <option value="other">อื่น ๆ</option>
                            </select>
                        </div>
                    </div>

                    <div id="imageUploadArea" class="space-y-4">
                        <div class="border-2 border-dashed border-neutral-200 rounded-2xl p-8 text-center hover:border-indigo-400 hover:bg-indigo-50/30 transition cursor-pointer group" onclick="document.getElementById('fileInput').click()">
                            <input type="file" id="fileInput" class="hidden" accept="image/*">
                            <i data-lucide="upload-cloud" class="w-10 h-10 text-neutral-300 mx-auto mb-2 group-hover:text-indigo-400 transition-colors"></i>
                            <p class="text-sm text-neutral-500">คลิกเพื่อเลือกไฟล์รูปภาพ</p>
                            <div id="imagePreviewContainer" class="hidden mt-4">
                                <img id="imagePreview" class="max-h-40 mx-auto rounded-xl shadow-sm">
                            </div>
                        </div>
                        <button type="submit" id="submitBtn" class="w-full py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition">
                            บันทึกข้อมูล
                        </button>
                    </div>

                    <div id="pdfProcessingUI" class="hidden space-y-4 border-t pt-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-indigo-600 uppercase tracking-wider">แยกรูปจากไฟล์ PDF</p>
                            <button type="button" onclick="searchInPdf()" class="text-[10px] bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-bold">ค้นหาชื่อของคุณ</button>
                        </div>
                        <div id="pdfCanvasContainer" class="pdf-viewer-container grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-neutral-50 rounded-2xl border"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // PDF Setup
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        let currentPdf = null;
        let pdfPages = [];
        let currentAlbumItems = []; // เก็บตัวแปรไว้สำหรับกดโหลดทั้งหมด

        document.addEventListener("DOMContentLoaded", () => {
            lucide.createIcons();
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('live-date').innerText = new Date().toLocaleDateString('th-TH', options);
            document.getElementById('gallerySearch').addEventListener('input', filterGallery);
        });

        function filterGallery() {
            const query = document.getElementById('gallerySearch').value.toLowerCase();
            document.querySelectorAll('.cert-item').forEach(item => {
                const title = item.getAttribute('data-title').toLowerCase();
                const cat = item.getAttribute('data-category').toLowerCase();
                item.style.display = (title.includes(query) || cat.includes(query)) ? 'block' : 'none';
            });
        }

        // ─── จัดการ Album ส่วนกลาง ───────────────────────────────────────
        function openAlbumModal(albumJsonStr) {
            const album = JSON.parse(albumJsonStr);
            currentAlbumItems = album.items; // เตรียมไว้เผื่อกดดาวน์โหลด

            document.getElementById('albumModalTitle').innerText = album.title;
            const grid = document.getElementById('albumGrid');
            grid.innerHTML = '';

            album.items.forEach((item, index) => {
                const ext = item.file_path.split('.').pop().toLowerCase();
                const isPdf = ext === 'pdf';
                const fileUrl = '../uploads/certificates/' + item.file_path;
                
                let previewHtml = isPdf 
                    ? `<div class="aspect-[4/3] bg-red-50 flex flex-col items-center justify-center border-b border-neutral-100"><i data-lucide="file-text" class="w-10 h-10 text-red-400 mb-2"></i><span class="text-[10px] font-bold text-red-600">PDF Document</span></div>` 
                    : `<img src="${fileUrl}" class="w-full aspect-[4/3] object-cover border-b border-neutral-100" onerror="this.src='https://via.placeholder.com/150'">`;

                const card = document.createElement('div');
                card.className = "bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition relative flex flex-col group";
                
                card.innerHTML = `
                    ${previewHtml}
                    <div class="p-3 text-center flex-1 flex flex-col justify-between gap-3">
                        <span class="text-xs text-neutral-500 font-medium truncate w-full block">ไฟล์ที่ ${index + 1}</span>
                        <div class="flex gap-2">
                            <a href="${fileUrl}" target="_blank" class="flex-1 py-1.5 bg-neutral-100 text-neutral-700 rounded-lg text-xs font-bold hover:bg-neutral-200 transition">เปิดดู</a>
                            <a href="${fileUrl}" download="${album.title}_${index + 1}" class="flex-1 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-600 hover:text-white transition">โหลด</a>
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });

            document.getElementById('albumModal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeAlbumModal() {
            document.getElementById('albumModal').classList.add('hidden');
        }

        async function downloadEntireAlbum() {
            if (!currentAlbumItems || currentAlbumItems.length === 0) return;
            if (!confirm(`ต้องการดาวน์โหลดไฟล์ทั้งหมด ${currentAlbumItems.length} ไฟล์ ใช่หรือไม่?\n(เบราว์เซอร์อาจจะถามการอนุญาตให้ดาวน์โหลดหลายไฟล์)`)) return;
            
            // ใช้เทคนิคหน่วงเวลาเล็กน้อยเพื่อป้องกัน Browser Block การดาวน์โหลดหลายไฟล์
            for (let i = 0; i < currentAlbumItems.length; i++) {
                const link = document.createElement('a');
                link.href = '../uploads/certificates/' + currentAlbumItems[i].file_path;
                link.download = document.getElementById('albumModalTitle').innerText + '_' + (i + 1);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                await new Promise(resolve => setTimeout(resolve, 800)); // หน่วงเวลา 0.8 วินาทีต่อไฟล์
            }
        }


        // ─── จัดการ Upload ของตัวเอง ───────────────────────────────────────
        function openUploadModal(type) {
            const modal = document.getElementById('uploadModal');
            const container = document.getElementById('modalContainer');
            const pdfUI = document.getElementById('pdfProcessingUI');
            const imgArea = document.getElementById('imageUploadArea');
            
            modal.classList.remove('hidden');
            setTimeout(() => { container.classList.remove('scale-95', 'opacity-0'); }, 10);
            
            if (type === 'pdf') {
                document.getElementById('modalTitle').innerText = 'แยกหน้า PDF เป็นรูปภาพ';
                pdfUI.classList.remove('hidden');
                imgArea.classList.add('hidden');
                document.getElementById('fileInput').accept = "application/pdf";
                document.getElementById('fileInput').click();
            } else {
                document.getElementById('modalTitle').innerText = 'อัปโหลดรูปเกียรติบัตรส่วนตัว';
                pdfUI.classList.add('hidden');
                imgArea.classList.remove('hidden');
                document.getElementById('fileInput').accept = "image/*";
            }
        }

        function closeModal() {
            const container = document.getElementById('modalContainer');
            container.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('uploadModal').classList.add('hidden');
                document.getElementById('imageUploadForm').reset();
                document.getElementById('imagePreviewContainer').classList.add('hidden');
                document.getElementById('pdfCanvasContainer').innerHTML = '';
                currentPdf = null;
            }, 200);
        }

        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.type === 'application/pdf') {
                processPdf(file);
            } else {
                const reader = new FileReader();
                reader.onload = (event) => {
                    document.getElementById('imagePreview').src = event.target.result;
                    document.getElementById('imagePreviewContainer').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        // ฟังก์ชัน PDF Upload ของนักเรียน (อันเดิม)
        async function processPdf(file) {
            const container = document.getElementById('pdfCanvasContainer');
            container.innerHTML = '<p class="col-span-full text-center py-10 text-xs text-neutral-400">กำลังประมวลผล PDF...</p>';
            const reader = new FileReader();
            reader.onload = async function() {
                const typedarray = new Uint8Array(this.result);
                currentPdf = await pdfjsLib.getDocument(typedarray).promise;
                container.innerHTML = '';
                pdfPages = [];
                for (let i = 1; i <= currentPdf.numPages; i++) {
                    const page = await currentPdf.getPage(i);
                    const viewport = page.getViewport({ scale: 0.5 });
                    const wrapper = document.createElement('div');
                    wrapper.className = 'page-item';
                    wrapper.id = `page-wrapper-${i}`;
                    const canvas = document.createElement('canvas');
                    canvas.className = 'page-canvas';
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                    wrapper.appendChild(canvas);
                    container.appendChild(wrapper);
                    const textContent = await page.getTextContent();
                    const textStr = textContent.items.map(item => item.str).join(' ');
                    pdfPages.push({ pageNum: i, text: textStr });
                    canvas.onclick = () => savePdfPage(i);
                }
                searchInPdf();
            };
            reader.readAsArrayBuffer(file);
        }

        function searchInPdf() {
            const myName = "<?php echo $current_user_name; ?>";
            pdfPages.forEach(page => {
                const wrapper = document.getElementById(`page-wrapper-${page.pageNum}`);
                if (page.text.includes(myName)) {
                    if (!wrapper.querySelector('.page-match-badge')) {
                        const badge = document.createElement('div');
                        badge.className = 'page-match-badge';
                        badge.innerText = '✨ พบชื่อคุณ';
                        wrapper.appendChild(badge);
                        wrapper.classList.add('ring-2', 'ring-indigo-500', 'rounded-xl');
                    }
                }
            });
        }

        async function savePdfPage(pageNum) {
            const title = document.getElementById('cert_title').value || `เกียรติบัตรหน้า ${pageNum}`;
            const category = document.getElementById('cert_category').value;
            if (!confirm(`บันทึกหน้า ${pageNum} เข้าแฟ้มผลงานส่วนตัว?`)) return;
            const page = await currentPdf.getPage(pageNum);
            const viewport = page.getViewport({ scale: 2.0 });
            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
            const imageData = canvas.toDataURL('image/png');
            const response = await fetch('portfolio.php?action=save_extracted_page', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: imageData, title: title, category: category })
            });
            const res = await response.json();
            if (res.status === 'success') { location.reload(); }
        }

        document.getElementById('imageUploadForm').onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('fileInput');
            if (document.getElementById('imageUploadArea').classList.contains('hidden')) return;
            if (!fileInput.files[0]) { alert('กรุณาเลือกไฟล์'); return; }
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> กำลังบันทึก...';
            lucide.createIcons();
            const formData = new FormData(e.target);
            formData.append('file', fileInput.files[0]);
            try {
                const response = await fetch('portfolio.php?action=upload_image', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.status === 'success') { location.reload(); } else { alert(res.message); }
            } catch (err) { alert('เกิดข้อผิดพลาด'); } finally { btn.disabled = false; }
        };

        function deleteCert(id) { if (confirm('ลบเกียรติบัตรส่วนตัวชิ้นนี้?')) { /* เพิ่ม Code ลบถ้าต้องการ */ } }
    </script>
</body>
</html>