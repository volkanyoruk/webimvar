<?php
/**
 * UsersController - Kullanıcı yönetimi sistemi
 * Müşterilerin listesi, arama, filtreleme ve yönetim işlemleri
 */
class UsersController
{
    private $db;
    private $userModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->userModel = new User();
    }

    /**
     * Auth kontrolü
     */
    private function requireAuth()
    {
        if (!Session::get('user_id')) {
            Response::redirect('/ops/login.php');
        }
    }

    /**
     * CSRF token oluştur
     */
    private function csrfToken()
    {
        $token = Session::get('csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

    /**
     * CSRF token kontrol
     */
    private function checkCsrf()
    {
        $sessionToken = Session::get('csrf_token');
        $postToken = $_POST['csrf_token'] ?? '';
        
        if (!$sessionToken || !hash_equals($sessionToken, $postToken)) {
            Session::flash('error', 'Güvenlik hatası. Lütfen tekrar deneyin.');
            return false;
        }
        return true;
    }

    /**
     * Ana kullanıcı listesi
     */
    public function index()
    {
        $this->requireAuth();

        // Arama ve filtreleme parametreleri
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        try {
            // Temel sorgu
            $whereConditions = [];
            $params = [];

            // Arama
            if (!empty($search)) {
                $whereConditions[] = "(u.email LIKE ? OR u.full_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }

            // Durum filtresi
            if (!empty($status)) {
                $whereConditions[] = "u.is_active = ?";
                $params[] = ($status === 'active') ? 1 : 0;
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Kullanıcıları çek
            $sql = "
                SELECT 
                    u.id,
                    u.email,
                    u.full_name,
                    u.role,
                    u.is_active,
                    u.created_at,
                    u.last_login,
                    u.login_count,
                    COUNT(s.id) as site_count
                FROM users u
                LEFT JOIN sites s ON u.id = s.user_id
                {$whereClause}
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $users = $this->db->query($sql, $params);

            // Toplam sayfa sayısı
            $countSql = "SELECT COUNT(DISTINCT u.id) as total FROM users u {$whereClause}";
            $totalResult = $this->db->queryOne($countSql, $params);
            $totalUsers = $totalResult['total'] ?? 0;
            $totalPages = ceil($totalUsers / $limit);

            // İstatistikler
            $stats = $this->getUserStats();

            // Template değişkenleri
            $title = 'Kullanıcı Yönetimi';
            $csrf_token = $this->csrfToken();

            // Template yükle
            $this->loadView('users/index', compact(
                'users', 'title', 'csrf_token', 'search', 'status', 
                'page', 'totalPages', 'totalUsers', 'stats'
            ));

        } catch (Exception $e) {
            Session::flash('error', 'Kullanıcılar yüklenirken hata oluştu: ' . $e->getMessage());
            $this->loadView('users/index', [
                'users' => [],
                'title' => 'Kullanıcı Yönetimi',
                'csrf_token' => $this->csrfToken(),
                'error' => true
            ]);
        }
    }

    /**
     * Kullanıcı detayları
     */
    public function view()
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Geçersiz kullanıcı ID.');
            Response::redirect('/ops/users.php');
        }

        try {
            // Kullanıcı bilgileri
            $sql = "
                SELECT 
                    u.*,
                    COUNT(s.id) as site_count,
                    MAX(s.created_at) as last_site_created
                FROM users u
                LEFT JOIN sites s ON u.id = s.user_id
                WHERE u.id = ?
                GROUP BY u.id
            ";
            
            $user = $this->db->queryOne($sql, [$id]);

            if (!$user) {
                Session::flash('error', 'Kullanıcı bulunamadı.');
                Response::redirect('/ops/users.php');
            }

            // Kullanıcının siteleri
            $sites = $this->db->query("
                SELECT * FROM sites 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ", [$id]);

            // Son aktiviteler (örnek)
            $activities = $this->db->query("
                SELECT * FROM system_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ", [$id]);

            $title = 'Kullanıcı Detayı: ' . $user['full_name'];
            $csrf_token = $this->csrfToken();

            $this->loadView('users/view', compact(
                'user', 'sites', 'activities', 'title', 'csrf_token'
            ));

        } catch (Exception $e) {
            Session::flash('error', 'Kullanıcı detayları yüklenirken hata: ' . $e->getMessage());
            Response::redirect('/ops/users.php');
        }
    }

    /**
     * Kullanıcı durumu değiştir (AJAX)
     */
    public function changeStatus()
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json(['error' => 'Method not allowed'], 405);
        }

        if (!$this->checkCsrf()) {
            Response::json(['error' => 'CSRF token invalid'], 400);
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 0);

        if ($userId <= 0) {
            Response::json(['error' => 'Geçersiz kullanıcı ID'], 400);
        }

        try {
            $result = $this->userModel->updateById($userId, [
                'is_active' => $newStatus
            ]);

            if ($result) {
                $statusText = $newStatus ? 'aktif' : 'pasif';
                Response::json([
                    'success' => true,
                    'message' => "Kullanıcı durumu {$statusText} olarak güncellendi."
                ]);
            } else {
                Response::json(['error' => 'Kullanıcı güncellenemedi'], 500);
            }

        } catch (Exception $e) {
            Response::json(['error' => 'Güncelleme hatası: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Kullanıcı arama (AJAX)
     */
    public function search()
    {
        $this->requireAuth();
        
        $query = $_GET['q'] ?? '';
        $limit = (int)($_GET['limit'] ?? 10);

        try {
            $sql = "
                SELECT id, email, full_name, is_active 
                FROM users 
                WHERE email LIKE ? OR full_name LIKE ?
                ORDER BY full_name ASC
                LIMIT {$limit}
            ";

            $users = $this->db->query($sql, ["%{$query}%", "%{$query}%"]);

            Response::json([
                'success' => true,
                'users' => $users
            ]);

        } catch (Exception $e) {
            Response::json(['error' => 'Arama hatası'], 500);
        }
    }

    /**
     * Kullanıcı istatistikleri
     */
    private function getUserStats()
    {
        try {
            $stats = [];

            // Toplam kullanıcı
            $result = $this->db->queryOne("SELECT COUNT(*) as count FROM users");
            $stats['total'] = $result['count'] ?? 0;

            // Aktif kullanıcı
            $result = $this->db->queryOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $stats['active'] = $result['count'] ?? 0;

            // Pasif kullanıcı
            $stats['inactive'] = $stats['total'] - $stats['active'];

            // Bu ay kayıt olan
            $result = $this->db->queryOne("
                SELECT COUNT(*) as count FROM users 
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stats['this_month'] = $result['count'] ?? 0;

            // Bugün kayıt olan
            $result = $this->db->queryOne("
                SELECT COUNT(*) as count FROM users 
                WHERE DATE(created_at) = CURDATE()
            ");
            $stats['today'] = $result['count'] ?? 0;

            // Son 7 günde giriş yapan
            $result = $this->db->queryOne("
                SELECT COUNT(*) as count FROM users 
                WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['active_last_week'] = $result['count'] ?? 0;

            return $stats;

        } catch (Exception $e) {
            return [
                'total' => 0, 'active' => 0, 'inactive' => 0, 
                'this_month' => 0, 'today' => 0, 'active_last_week' => 0
            ];
        }
    }

    /**
     * Template yükleme helper
     */
    private function loadView($view, $data = [])
    {
        extract($data);
        
        ob_start();
        include __DIR__ . "/../templates/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . '/../templates/layouts/admin.php';
    }
}