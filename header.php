<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔌 ทำการดึงตัวเชื่อมต่อฐานข้อมูลหลักขยับขึ้น 1 ชั้นโฟลเดอร์ (หากยังไม่มี)
require_once __DIR__ . '/../db.php';

// 👤 ตั้งค่าเริ่มต้นเพื่อความปลอดภัย (Fallback Parameters)
$user_display_name = 'ผู้ใช้งานระบบ';
$user_profile_img  = ''; 
$is_admin_user     = false;
$is_teacher_user   = false;
$teacher_level     = 0;
$allowed_admin_pages = [];

// 🔍 ค้นหาข้อมูลผู้ใช้และจำแนกสิทธิ์สดตรงจาก Database MySQL
if (isset($_SESSION['user_id'])) {
    $session_uid = $_SESSION['user_id'];
    
    try {
        // 🛡️ ขั้นที่ 1: ตรวจเช็คข้อมูลจากตารางผู้ดูแลระบบหลัก (admins)
        $stmt_admin = $conn->prepare("SELECT name FROM admins WHERE username = :uid LIMIT 1");
        $stmt_admin->bindParam(':uid', $session_uid);
        $stmt_admin->execute();
        $admin_data = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        
        if ($admin_data) {
            $user_display_name = $admin_data['name'];
            $is_admin_user     = true;
            $_SESSION['role']  = 'Admin'; // ล็อคบทบาทเพื่อใช้ในสิทธิ์หน้าอื่นๆ
        } else {
            // 🎓 ขั้นที่ 2: ตรวจสอบจากตาราง teachers (หากรหัสขึ้นต้นด้วย T)
            if (substr($session_uid, 0, 1) === 'T') {
                $stmt_teacher = $conn->prepare("SELECT fullname, subject, profile_img, role FROM teachers WHERE teacher_id = :uid LIMIT 1");
                $stmt_teacher->bindParam(':uid', $session_uid);
                $stmt_teacher->execute();
                $teacher_data = $stmt_teacher->fetch(PDO::FETCH_ASSOC);
                
                if ($teacher_data) {
                    $user_display_name = $teacher_data['fullname'];
                    $user_profile_img  = $teacher_data['profile_img'] ?? '';
                    $is_teacher_user   = true;
                    $_SESSION['is_teacher'] = true;
                    $_SESSION['role']  = 'Teacher';
                    
                    // ✅ ดึงสิทธิ์จากตาราง physics_permissions
                    try {
                        $stmt_perm = $conn->prepare("SELECT admin_level, allowed_pages FROM physics_permissions WHERE user_id = :uid LIMIT 1");
                        $stmt_perm->bindParam(':uid', $session_uid);
                        $stmt_perm->execute();
                        $perm_data = $stmt_perm->fetch(PDO::FETCH_ASSOC);
                        
                        if ($perm_data && $perm_data['admin_level'] >= 5) {
                            $is_admin_user = true; // ระดับ 5+ ถือว่าเป็นผู้ดูแลระบบเสริม
                            $_SESSION['role'] = 'Admin';
                            $teacher_level = $perm_data['admin_level'];
                            
                            // ✅ 解析允许的页面列表 (หากมี)
                            if (!empty($perm_data['allowed_pages'])) {
                                $allowed_admin_pages = explode(',', $perm_data['allowed_pages']);
                                $allowed_admin_pages = array_map('trim', $allowed_admin_pages);
                            }
                        }
                    } catch (Exception $e) {}
                } else {
                    // ↪️ หากไม่พบในตาราง teachers แต่รหัสเริ่มด้วย T ให้ถือว่าเป็นนักเรียน
                    $stmt_student = $conn->prepare("SELECT fullname, role, profile_img FROM students WHERE student_id = :uid LIMIT 1");
                    $stmt_student->bindParam(':uid', $session_uid);
                    $stmt_student->execute();
                    $student_data = $stmt_student->fetch(PDO::FETCH_ASSOC);
                    
                    if ($student_data) {
                        $user_display_name = $student_data['fullname'];
                        $user_profile_img  = $student_data['profile_img'] ?? '';
                        
                        if (isset($student_data['role']) && strtolower(trim($student_data['role'])) === 'admin') {
                            $is_admin_user    = true;
                            $_SESSION['role'] = 'Admin';
                        } else {
                            $_SESSION['role'] = 'Student';
                        }
                    }
                }
            } else {
                // 🎓 ขั้นที่ 3: หากไม่มีรหัสในตารางแอดมินหลัก ให้มาตรวจที่ตารางนักเรียน
                $stmt_student = $conn->prepare("SELECT fullname, role, profile_img FROM students WHERE student_id = :uid LIMIT 1");
                $stmt_student->bindParam(':uid', $session_uid);
                $stmt_student->execute();
                $student_data = $stmt_student->fetch(PDO::FETCH_ASSOC);
                
                if ($student_data) {
                    $user_display_name = $student_data['fullname'];
                    $user_profile_img  = $student_data['profile_img'] ?? '';
                    
                    // ตรวจสอบค่าคอลัมน์ role ในตาราง students เพิ่มเติม (เช่น 'Admin' หรือ 'User')
                    if (isset($student_data['role']) && strtolower(trim($student_data['role'])) === 'admin') {
                        $is_admin_user    = true;
                        $_SESSION['role'] = 'Admin';
                    } else {
                        $is_admin_user    = false;
                        $_SESSION['role'] = 'Student';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        // กรณีเชื่อมต่อฐานข้อมูลขัดข้อง ให้ใช้ค่าความทรงจำ Session เดิมไปก่อน
        $user_display_name = $_SESSION['user_name'] ?? 'ผู้ใช้งานระบบ';
        $is_admin_user     = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');
        $is_teacher_user   = isset($_SESSION['is_teacher']) && $_SESSION['is_teacher'];
    }
}

// 3. วิเคราะห์ชื่อหน้าและ URL ปัจจุบันโดยทำการถอดถอนนามสกุล .php ออกทั้งหมด
$current_file = basename($_SERVER['PHP_SELF']);
$current_page_clean = pathinfo($current_file, PATHINFO_FILENAME);

// 🔍 4. ระบบ Dynamic Scan: ท่องโฟลเดอร์อ่านไฟล์เพื่อนำมาแสดงบนหน้า Header อัตโนมัติ
$student_menus = [];
$admin_menus   = [];
$dir_files     = glob("*.php"); 

foreach ($dir_files as $file) {
    // ข้ามไฟล์ระบบหลักที่ห้ามนำมาแสดงบนแท็บนำทาง
    if (in_array($file, ['db.php', 'header.php', 'login.php', 'logout.php'])) {
        continue;
    }
    
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // ค้นหาคีย์คอมเมนต์หลักของโปรเจกต์
        if (preg_match('/MENU_TITLE:\s*(.+)/', $content, $title_match)) {
            $file_name_info = pathinfo($file, PATHINFO_FILENAME);
            preg_match('/MENU_ICON:\s*([a-zA-Z0-9-]+)/', $content, $icon_match);
            preg_match('/MENU_ORDER:\s*([a-zA-Z0-9-]+)/', $content, $order_match);
            
            $menu_data = [
                'title' => trim($title_match[1]),
                'icon'  => isset($icon_match[1]) ? trim($icon_match[1]) : 'file-text',
                'link'  => $file_name_info, // ❌ ตัดนามสกุลไฟล์เรียบร้อย
                'order' => isset($order_match[1]) ? trim($order_match[1]) : '99'
            ];

            // ⚠️ แยกหมวดหมู่แอดมิน: ค้นหารหัสย่อ "am" ใน MENU_ORDER
            if (strpos($menu_data['order'], 'am') !== false) {
                $admin_menus[] = $menu_data;
            } else {
                $student_menus[] = $menu_data;
            }
        }
    }
}

// ทำการเรียงลำดับเมนูให้สวยงาม
function sort_my_header_menus($a, $b) { return strnatcmp($a['order'], $b['order']); }
usort($student_menus, 'sort_my_header_menus');
usort($admin_menus, 'sort_my_header_menus');

// 🛡️ กรองเมนูแอดมินสำหรับครูตามสิทธิ์ (physics_permissions)
if ($is_teacher_user && !empty($allowed_admin_pages)) {
    $admin_menus = array_filter($admin_menus, function($item) use ($allowed_admin_pages) {
        return in_array($item['link'], $allowed_admin_pages);
    });
}
?>

<!-- 🎨 สไตล์หลักสแกนหน้าแบบ Luxury Light (Apple Studio Style) -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif; }
    .premium-blur { backdrop-filter: blur(24px); background-color: rgba(255, 255, 255, 0.82); }
    .active-pill { background-color: #171717; color: #ffffff; }
    .admin-pill { background-color: #f59e0b; color: #ffffff; } /* แอดมินสไตล์ปุ่มสีทองแอมเบอร์พรีเมียม */
    .teacher-badge { background-color: #8b5cf6; color: #ffffff; } /* บัตรครูสีม่วง */
</style>

<!-- 🧭 แถบเมนูหลัก Header Shell -->
<header class="sticky top-0 z-50 w-full border-b border-neutral-200/60 premium-blur transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- 👤 ส่วนข้อมูลผู้ใช้ (รูปโปรไฟล์อวาตาร์ + ชื่อและระดับสิทธิ์ ดึงสดตรง) -->
            <div class="flex items-center gap-3 bg-neutral-50 border border-neutral-200/40 pl-2 pr-4 py-1.5 rounded-full shadow-[0_2px_10px_rgba(0,0,0,0.01)] hover:bg-neutral-100/40 transition duration-200">
                <div class="relative flex-shrink-0">
                    <?php if (!empty($user_profile_img) && filter_var($user_profile_img, FILTER_VALIDATE_URL)): ?>
                        <!-- แสดงรูปจริงกรณีผู้ใช้อัปโหลดลงในระบบ -->
                        <img class="w-8 h-8 rounded-full object-cover border border-neutral-200 shadow-sm" src="<?php echo htmlspecialchars($user_profile_img); ?>" alt="Profile">
                    <?php else: ?>
                        <!-- รูปอวาตาร์มินิมอลสีเทาแบบพาสเทลละมุนตา -->
                        <div class="w-8 h-8 rounded-full bg-neutral-200/80 border border-neutral-300/40 flex items-center justify-center text-neutral-500 shadow-inner">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                    <?php endif; ?>
                    <!-- จุดบอกสถานะผู้ใช้ (แอดมินจะกะพริบแสงไฟสีส้มเหลือง, ครูจะเป็นสีม่วง) -->
                    <span class="absolute bottom-0 right-0 block h-2 w-2 rounded-full <?php 
                        if ($is_admin_user && $is_teacher_user) {
                            echo 'bg-purple-500 animate-pulse'; // ครูที่มีสิทธิ์แอดมิน
                        } elseif ($is_admin_user) {
                            echo 'bg-amber-500 animate-pulse'; // แอดมิน
                        } elseif ($is_teacher_user) {
                            echo 'bg-indigo-500'; // ครู
                        } else {
                            echo 'bg-emerald-500'; // นักเรียน
                        }
                    ?> ring-2 ring-white"></span>
                </div>
                
                <div class="flex flex-col text-left">
                    <span class="text-[9px] font-bold tracking-wider uppercase leading-none mb-0.5 <?php 
                        if ($is_admin_user && $is_teacher_user) {
                            echo 'text-purple-600'; // ครูที่มีสิทธิ์แอดมิน
                        } elseif ($is_admin_user) {
                            echo 'text-amber-600'; // แอดมิน
                        } elseif ($is_teacher_user) {
                            echo 'text-indigo-600'; // ครู
                        } else {
                            echo 'text-neutral-400'; // นักเรียน
                        }
                    ?>">
                        <?php 
                            if ($is_admin_user && $is_teacher_user) {
                                echo '👑 Teacher Admin';
                            } elseif ($is_admin_user) {
                                echo '👑 Administrator';
                            } elseif ($is_teacher_user) {
                                echo '🎓 Teacher';
                            } else {
                                echo '🎓 Student';
                            }
                        ?>
                    </span>
                    <span class="text-xs font-semibold text-neutral-800 tracking-tight leading-tight max-w-[130px] truncate">
                        <?php echo htmlspecialchars($user_display_name); ?>
                    </span>
                </div>
            </div>

            <!-- 💻 รายการเมนูสลับหน้าสำหรับการแสดงผลผ่านคอมพิวเตอร์ -->
            <div class="hidden md:flex items-center gap-4">
                <!-- 🔹 แท็บเมนูผู้ใช้งานนักเรียนทั่วไป -->
                <nav class="flex items-center gap-1 bg-neutral-100/80 p-1 rounded-xl border border-neutral-200/30">
                    <?php foreach ($student_menus as $item): 
                        $isActive = ($current_page_clean === $item['link']);
                    ?>
                        <!-- 🔗 URL ตรงแบบไม่มีนามสกุลไฟล์ .php -->
                        <a href="<?php echo $item['link']; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all duration-300 <?php echo $isActive ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:text-neutral-900 hover:bg-white'; ?>">
                            <i data-lucide="<?php echo $item['icon']; ?>" class="w-3.5 h-3.5"></i>
                            <?php echo $item['title']; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- 👑 แท็บเมนูควบคุมสำหรับผู้ดูแลระบบ (Admin) จะแสดงเฉพาะเมื่อผู้ใช้มีสิทธิ์ -->
                <?php if ($is_admin_user && !empty($admin_menus)): ?>
                    <div class="h-4 w-px bg-neutral-200"></div> 
                    <nav class="flex items-center gap-1 bg-amber-50/60 p-1 rounded-xl border border-amber-100/50 animate-fade-in">
                        <?php foreach ($admin_menus as $item): 
                            $isActive = ($current_page_clean === $item['link']);
                        ?>
                            <!-- 🔗 URL ตรงแบบไม่มีนามสกุลไฟล์ .php -->
                            <a href="<?php echo $item['link']; ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all duration-300 <?php echo $isActive ? 'bg-amber-500 text-white shadow-md' : 'text-amber-700 hover:text-amber-900 hover:bg-amber-100'; ?>">
                                <i data-lucide="<?php echo $item['icon']; ?>" class="w-3.5 h-3.5"></i>
                                <?php echo $item['title']; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>

            <!-- 🚪 ปุ่มออกจากระบบ -->
            <div class="hidden md:flex items-center">
                <!-- 🔗 ลิงก์ตรงแบบไม่มีนามสกุลไฟล์ .php -->
                <a href="logout" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-medium text-neutral-500 hover:text-rose-600 hover:bg-rose-50/60 transition-all duration-200">
                    <i data-lucide="power" class="w-3.5 h-3.5"></i>
                    ออกจากระบบ
                </a>
            </div>

            <!-- 📱 เมนูลัดสำหรับหน้าจอมือถือ (Hamburger Button) -->
            <div class="flex md:hidden">
                <button type="button" onclick="toggleMobileNavbarMenu()" class="inline-flex items-center justify-center p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 transition-all">
                    <i id="hamb-icon" data-lucide="menu" class="w-5 h-5"></i>
                </button>
            </div>

        </div>
    </div>

    <!-- 📱 แผงรายการเมนูบนมือถือ (จัดแบ่งหมวดหมู่แอดมิน/นักเรียนเป็นระเบียบ) -->
    <div id="mob-dropdown" class="hidden md:hidden border-t border-neutral-200/60 bg-white/95 backdrop-blur-xl animate-fade-in">
        <div class="px-3 pt-2 pb-4 space-y-2">
            
            <div class="px-4 py-1 text-[10px] font-bold text-neutral-400 uppercase tracking-wider">ภาพรวมเมนูระบบ</div>
            <?php foreach ($student_menus as $item): 
                $isActive = ($current_page_clean === $item['link']);
            ?>
                <!-- 🔗 URL ตรงแบบไม่มีนามสกุลไฟล์ .php -->
                <a href="<?php echo $item['link']; ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-medium transition-all <?php echo $isActive ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-700 hover:bg-neutral-100'; ?>">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-4 h-4"></i>
                    <?php echo $item['title']; ?>
                </a>
            <?php endforeach; ?>
            
            <!-- แสดงแผงเมนูลัดสำหรับ Admin บนมือถือ -->
            <?php if ($is_admin_user && !empty($admin_menus)): ?>
                <div class="pt-2 mt-2 border-t border-neutral-100 px-4 py-1 text-[10px] font-bold text-amber-500 uppercase tracking-wider">ระบบผู้ดูแลระบบ (Admin)</div>
                <?php foreach ($admin_menus as $item): 
                    $isActive = ($current_page_clean === $item['link']);
                ?>
                    <!-- 🔗 URL ตรง��บบไม่มีนามสกุลไฟล์ .php -->
                    <a href="<?php echo $item['link']; ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-medium transition-all <?php echo $isActive ? 'bg-amber-500 text-white shadow-md' : 'text-amber-700 hover:bg-amber-50'; ?>">
                        <i data-lucide="<?php echo $item['icon']; ?>" class="w-4 h-4"></i>
                        <?php echo $item['title']; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="pt-2 mt-2 border-t border-neutral-100">
                <!-- 🔗 ลิงก์ตรงแบบไม่มีนามสกุลไฟล์ .php -->
                <a href="logout" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs font-medium text-rose-600 hover:bg-rose-50/50">
                    <i data-lucide="power" class="w-4 h-4"></i>
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    // สคริปต์ความปลอดภัยในการขยายเมนูดาวน์สำหรับหน้าจอมือถือ
    function toggleMobileNavbarMenu() {
        const dropdown = document.getElementById('mob-dropdown');
        const icon = document.getElementById('hamb-icon');
        
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            icon.setAttribute('data-lucide', 'x');
        } else {
            dropdown.classList.add('hidden');
            icon.setAttribute('data-lucide', 'menu');
        }
        lucide.createIcons();
    }

    document.addEventListener("DOMContentLoaded", () => {
        lucide.createIcons();
    });
</script>
