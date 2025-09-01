<?php
// /ops/models/Package.php
class Package extends Model
{
    protected $table = 'packages';
    protected $primaryKey = 'id';
    protected $fillable = [
        'name', 'display_name', 'slug', 'description', 'price_monthly', 'price_yearly', 
        'currency', 'features', 'limits', 'disk_quota', 'bandwidth_quota', 
        'sites_limit', 'databases_limit', 'email_accounts_limit', 'subdomains_limit',
        'is_active', 'is_featured', 'sort_order'
    ];

    /**
     * Aktif paketleri getir
     */
    public function getActivePackages($featured_first = true)
    {
        $orderBy = $featured_first ? 'is_featured DESC, sort_order ASC, price_monthly ASC' : 'sort_order ASC, price_monthly ASC';
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY {$orderBy}";
        return $this->db->query($sql);
    }

    /**
     * Paket özelliklerini decode et
     */
    public function decodeFeatures($package)
    {
        if (isset($package['features']) && $package['features']) {
            $package['features'] = json_decode($package['features'], true) ?: [];
        } else {
            $package['features'] = [];
        }
        
        if (isset($package['limits']) && $package['limits']) {
            $package['limits'] = json_decode($package['limits'], true) ?: [];
        } else {
            $package['limits'] = [];
        }
        
        return $package;
    }

    /**
     * Paket oluşturma
     */
    public function createPackage($data)
    {
        // JSON alanlarını encode et
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        if (isset($data['limits']) && is_array($data['limits'])) {
            $data['limits'] = json_encode($data['limits']);
        }

        // Slug oluştur
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = $this->createSlug($data['name']);
        }
        
        // Display name ekle
        if (empty($data['display_name'])) {
            $data['display_name'] = $data['name'];
        }

        return $this->create($data);
    }

    /**
     * Paket güncelleme
     */
    public function updatePackage($id, $data)
    {
        // JSON alanlarını encode et
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        if (isset($data['limits']) && is_array($data['limits'])) {
            $data['limits'] = json_encode($data['limits']);
        }

        return $this->updateById($id, $data);
    }

    /**
     * Slug oluştur
     */
    private function createSlug($name)
    {
        // Türkçe karakterleri değiştir
        $turkish = ['ç', 'ğ', 'ı', 'İ', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'Ö', 'Ş', 'Ü'];
        $english = ['c', 'g', 'i', 'I', 'o', 's', 'u', 'C', 'G', 'O', 'S', 'U'];
        $name = str_replace($turkish, $english, $name);
        
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Benzersizlik kontrolü
        $original_slug = $slug;
        $counter = 1;
        while ($this->findBy('slug', $slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Paket istatistikleri
     */
    public function getStats()
    {
        return [
            'total' => $this->count(),
            'active' => $this->count('is_active = 1'),
            'featured' => $this->count('is_featured = 1'),
            'avg_price' => $this->db->queryOne("SELECT AVG(price_monthly) as avg FROM {$this->table} WHERE is_active = 1")['avg']
        ];
    }

    /**
     * Format price
     */
    public function formatPrice($amount)
    {
        return number_format($amount, 0, ',', '.') . ' ₺';
    }

    /**
     * Format storage
     */
    public function formatStorage($mb)
    {
        if ($mb >= 1024) {
            return round($mb / 1024, 1) . ' GB';
        }
        return $mb . ' MB';
    }
}