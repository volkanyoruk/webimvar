<?php
// /ops/controllers/PackageController.php
class PackageController
{
    private $packageModel;
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->packageModel = new Package();
    }

    private function requireAuth()
    {
        if (!Session::get('user_id')) {
            header('Location: /ops/login.php');
            exit;
        }
    }

    private function csrfToken()
    {
        $token = Session::get('csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

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
     * Paket listesi
     */
    public function index()
    {
        $this->requireAuth();
        
        $packages = $this->packageModel->all();
        foreach ($packages as &$package) {
            $package = $this->packageModel->decodeFeatures($package);
        }

        $title = 'Paket Yönetimi';
        $csrf_token = $this->csrfToken();
        
        // Template dosyasını yükle
        $this->loadView('packages/index', compact('packages', 'title', 'csrf_token'));
    }

    /**
     * Yeni paket formu
     */
    public function create()
    {
        $this->requireAuth();

        $title = 'Yeni Paket Oluştur';
        $csrf_token = $this->csrfToken();
        $package = [
            'name' => '',
            'display_name' => '',
            'description' => '',
            'price_monthly' => '',
            'price_yearly' => '',
            'disk_quota' => '',
            'bandwidth_quota' => '',
            'sites_limit' => 1,
            'databases_limit' => 1,
            'email_accounts_limit' => '',
            'is_active' => 1,
            'is_featured' => 0,
            'features' => [],
            'limits' => []
        ];
        $isEdit = false;

        $this->loadView('packages/form', compact('package', 'title', 'csrf_token', 'isEdit'));
    }

    /**
     * Paket kaydetme
     */
    public function store()
    {
        $this->requireAuth();

        if (!$this->checkCsrf()) {
            header('Location: /ops/packages.php');
            return;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
            'price_yearly' => (float)($_POST['price_yearly'] ?? 0),
            'disk_quota' => (int)($_POST['disk_quota'] ?? 0),
            'bandwidth_quota' => (int)($_POST['bandwidth_quota'] ?? 0),
            'sites_limit' => (int)($_POST['sites_limit'] ?? 1),
            'databases_limit' => (int)($_POST['databases_limit'] ?? 1),
            'email_accounts_limit' => (int)($_POST['email_accounts_limit'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'features' => [
                'ssl_certificate' => isset($_POST['feature_ssl']),
                'email_support' => isset($_POST['feature_email_support']),
                'backup_daily' => isset($_POST['feature_backup']),
                'cdn' => isset($_POST['feature_cdn']),
                'priority_support' => isset($_POST['feature_priority_support'])
            ],
            'limits' => [
                'disk_space_gb' => round(($data['disk_quota'] ?? 0) / 1024, 1),
                'bandwidth_gb' => round(($data['bandwidth_quota'] ?? 0) / 1024, 1),
                'domain_count' => $data['sites_limit'] ?? 1,
                'email_count' => $data['email_accounts_limit'] ?? 0,
                'database_count' => $data['databases_limit'] ?? 1
            ]
        ];

        // Validasyon
        if (empty($data['name']) || $data['price_monthly'] <= 0) {
            Session::flash('error', 'Paket adı ve aylık fiyat gereklidir.');
            header('Location: /ops/packages/create.php');
            return;
        }

        try {
            $packageId = $this->packageModel->createPackage($data);
            Session::flash('success', 'Paket başarıyla oluşturuldu.');
            header('Location: /ops/packages.php');
        } catch (Exception $e) {
            Session::flash('error', 'Paket oluşturulurken hata: ' . $e->getMessage());
            header('Location: /ops/packages/create.php');
        }
    }

    /**
     * Paket düzenleme formu
     */
    public function edit()
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        $package = $this->packageModel->find($id);

        if (!$package) {
            Session::flash('error', 'Paket bulunamadı.');
            header('Location: /ops/packages.php');
            return;
        }

        $package = $this->packageModel->decodeFeatures($package);
        $title = 'Paket Düzenle: ' . $package['name'];
        $csrf_token = $this->csrfToken();
        $isEdit = true;

        $this->loadView('packages/form', compact('package', 'title', 'csrf_token', 'isEdit'));
    }

    /**
     * Paket güncelleme
     */
    public function update()
    {
        $this->requireAuth();

        if (!$this->checkCsrf()) {
            header('Location: /ops/packages.php');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $package = $this->packageModel->find($id);

        if (!$package) {
            Session::flash('error', 'Paket bulunamadı.');
            header('Location: /ops/packages.php');
            return;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
            'price_yearly' => (float)($_POST['price_yearly'] ?? 0),
            'disk_quota' => (int)($_POST['disk_quota'] ?? 0),
            'bandwidth_quota' => (int)($_POST['bandwidth_quota'] ?? 0),
            'sites_limit' => (int)($_POST['sites_limit'] ?? 1),
            'databases_limit' => (int)($_POST['databases_limit'] ?? 1),
            'email_accounts_limit' => (int)($_POST['email_accounts_limit'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'features' => [
                'ssl_certificate' => isset($_POST['feature_ssl']),
                'email_support' => isset($_POST['feature_email_support']),
                'backup_daily' => isset($_POST['feature_backup']),
                'cdn' => isset($_POST['feature_cdn']),
                'priority_support' => isset($_POST['feature_priority_support'])
            ]
        ];

        try {
            $this->packageModel->updatePackage($id, $data);
            Session::flash('success', 'Paket başarıyla güncellendi.');
            header('Location: /ops/packages.php');
        } catch (Exception $e) {
            Session::flash('error', 'Paket güncellenirken hata: ' . $e->getMessage());
            header("Location: /ops/packages/edit.php?id={$id}");
        }
    }

    /**
     * Paket silme
     */
    public function delete()
    {
        $this->requireAuth();

        if (!$this->checkCsrf()) {
            header('Location: /ops/packages.php');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        
        // Aktif aboneliği olan paket silinemez (gelecekte kontrol edilecek)
        try {
            $this->packageModel->deleteById($id);
            Session::flash('success', 'Paket silindi.');
        } catch (Exception $e) {
            Session::flash('error', 'Paket silinirken hata: ' . $e->getMessage());
        }

        header('Location: /ops/packages.php');
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