<?php
// Kullanıcı Ödeme Geçmişi
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../core/classes/DatabaseConfig.php';

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: users.php');
    exit;
}

try {
    $config = DatabaseConfig::getConfig();
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Kullanıcı bilgilerini getir
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Mevcut tabloları kontrol et
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Ödeme verilerini getir
$payments = [];
$invoices = [];
$subscriptions = [];
$payments_exist = false;

// Payments tablosu varsa gerçek veriler
if (in_array('payments', $tables)) {
    $payments_exist = true;
    
    $payments_query = "SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $payments_stmt = $pdo->prepare($payments_query);
    $payments_stmt->execute([$user_id]);
    $payments = $payments_stmt->fetchAll();
}

// Invoices tablosu varsa fatura verilerini getir
if (in_array('invoices', $tables)) {
    $invoices_query = "SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $invoices_stmt = $pdo->prepare($invoices_query);
    $invoices_stmt->execute([$user_id]);
    $invoices = $invoices_stmt->fetchAll();
}

// Subscriptions tablosu varsa abonelik verilerini getir
if (in_array('subscriptions', $tables)) {
    $subscriptions_query = "SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC";
    $subscriptions_stmt = $pdo->prepare($subscriptions_query);
    $subscriptions_stmt->execute([$user_id]);
    $subscriptions = $subscriptions_stmt->fetchAll();
}

// Demo veriler (tablolar yoksa)
if (!$payments_exist) {
    $payments = [
        [
            'id' => 1,
            'amount' => 199.00,
            'currency' => 'TRY',
            'status' => 'completed',
            'payment_method' => 'credit_card',
            'transaction_id' => 'TXN_' . date('Ymd') . '_001',
            'description' => 'Profesyonel Paket - Aylık Ödeme',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'processed_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ],
        [
            'id' => 2,
            'amount' => 199.00,
            'currency' => 'TRY',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'TXN_' . date('Ymd', strtotime('-35 days')) . '_001',
            'description' => 'Profesyonel Paket - Aylık Ödeme',
            'created_at' => date('Y-m-d H:i:s', strtotime('-35 days')),
            'processed_at' => date('Y-m-d H:i:s', strtotime('-34 days'))
        ],
        [
            'id' => 3,
            'amount' => 199.00,
            'currency' => 'TRY',
            'status' => 'pending',
            'payment_method' => 'credit_card',
            'transaction_id' => 'TXN_' . date('Ymd', strtotime('+25 days')) . '_001',
            'description' => 'Profesyonel Paket - Sonraki Ödeme',
            'created_at' => date('Y-m-d H:i:s'),
            'processed_at' => null
        ]
    ];
    
    $invoices = [
        [
            'id' => 1,
            'invoice_number' => 'INV-' . date('Y') . '-001',
            'amount' => 199.00,
            'tax_amount' => 35.82,
            'total_amount' => 234.82,
            'status' => 'paid',
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]
    ];
}

// Ödeme istatistikleri
$payment_stats = [
    'total_payments' => count($payments),
    'total_amount' => 0,
    'completed_payments' => 0,
    'pending_payments' => 0,
    'failed_payments' => 0,
    'last_payment' => null
];

foreach ($payments as $payment) {
    $payment_stats['total_amount'] += (float)($payment['amount'] ?? 0);
    
    switch ($payment['status'] ?? '') {
        case 'completed':
        case 'paid':
            $payment_stats['completed_payments']++;
            break;
        case 'pending':
            $payment_stats['pending_payments']++;
            break;
        case 'failed':
        case 'cancelled':
            $payment_stats['failed_payments']++;
            break;
    }
    
    if (!$payment_stats['last_payment'] || strtotime($payment['created_at']) > strtotime($payment_stats['last_payment'])) {
        $payment_stats['last_payment'] = $payment['created_at'];
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Geçmişi - <?= htmlspecialchars($user['username'] ?? $user['email']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .header p { color: #6c757d; }
        
        .user-info { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .user-details h3 { color: #2c3e50; margin-bottom: 5px; }
        .user-details p { color: #6c757d; margin: 2px 0; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .stat-title { color: #6c757d; font-size: 0.9rem; }
        .stat-icon { font-size: 1.5rem; }
        .stat-number { font-size: 1.8rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.8rem; color: #6c757d; }
        
        .stat-total { color: #007bff; }
        .stat-completed { color: #28a745; }
        .stat-pending { color: #ffc107; }
        .stat-failed { color: #dc3545; }
        
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .main-content { display: flex; flex-direction: column; gap: 20px; }
        .sidebar { display: flex; flex-direction: column; gap: 20px; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h2 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        
        .payment-item { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: transform 0.2s, box-shadow 0.2s; }
        .payment-item:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        .payment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .payment-amount { font-size: 1.3rem; font-weight: bold; color: #2c3e50; }
        .payment-status { padding: 4px 12px; border-radius: 16px; font-size: 0.8rem; font-weight: 500; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .payment-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .payment-detail { }
        .detail-label { font-size: 0.8rem; color: #6c757d; margin-bottom: 2px; }
        .detail-value { font-weight: 500; color: #2c3e50; }
        
        .invoice-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 6px; margin-bottom: 10px; }
        .invoice-info { }
        .invoice-number { font-weight: 600; color: #2c3e50; }
        .invoice-amount { font-size: 1.1rem; font-weight: bold; color: #28a745; }
        
        .subscription-item { padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 15px; }
        .subscription-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .subscription-name { font-weight: 600; color: #2c3e50; }
        .subscription-status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; }
        
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block; margin: 2px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state h3 { margin-bottom: 10px; }
        
        .method-icon { display: inline-block; margin-right: 8px; }
        
        @media (max-width: 768px) {
            .user-info { flex-direction: column; text-align: center; }
            .content-grid { grid-template-columns: 1fr; }
            .payment-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ödeme Geçmişi</h1>
            <p>Kullanıcının tüm ödeme işlemleri ve fatura geçmişi</p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($user['username'] ?? $user['email'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Kullanıcı') ?></h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                <p><strong>Kullanıcı ID:</strong> <?= $user['id'] ?></p>
                <p><strong>Kayıt Tarihi:</strong> <?= date('d.m.Y', strtotime($user['created_at'] ?? 'now')) ?></p>
            </div>
        </div>

        <?php if (!$payments_exist): ?>
            <div class="alert alert-info">
                <strong>Demo Mod:</strong> Payments tablosu bulunamadığı için demo veriler gösteriliyor. Gerçek ödeme takibi için gerekli tabloları oluşturun.
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Toplam Ödeme</div>
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-number stat-total"><?= number_format($payment_stats['total_amount'], 2) ?> ₺</div>
                <div class="stat-label"><?= $payment_stats['total_payments'] ?> işlem</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Başarılı Ödeme</div>
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-number stat-completed"><?= $payment_stats['completed_payments'] ?></div>
                <div class="stat-label">Tamamlanmış</div>
            </div>
            
            <?php if ($payment_stats['pending_payments'] > 0): ?>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Bekleyen Ödeme</div>
                    <div class="stat-icon">⏳</div>
                </div>
                <div class="stat-number stat-pending"><?= $payment_stats['pending_payments'] ?></div>
                <div class="stat-label">İşlem bekliyor</div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Son Ödeme</div>
                    <div class="stat-icon">📅</div>
                </div>
                <div class="stat-number" style="font-size: 1.2rem;">
                    <?= $payment_stats['last_payment'] ? date('d.m.Y', strtotime($payment_stats['last_payment'])) : 'Hiç' ?>
                </div>
                <div class="stat-label">
                    <?= $payment_stats['last_payment'] ? date('H:i', strtotime($payment_stats['last_payment'])) : '' ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <!-- Ödeme Geçmişi -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Ödeme İşlemleri</h2>
                        <div>
                            <a href="payment-add.php?user_id=<?= $user_id ?>" class="btn btn-success">Manuel Ödeme Ekle</a>
                            <a href="payment-refund.php?user_id=<?= $user_id ?>" class="btn btn-danger">İade İşlemi</a>
                        </div>
                    </div>

                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <h3>Henüz ödeme yok</h3>
                            <p>Bu kullanıcının henüz ödeme işlemi bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): 
                            $status_class = 'status-' . ($payment['status'] ?? 'pending');
                            $amount = number_format((float)($payment['amount'] ?? 0), 2);
                            $currency = $payment['currency'] ?? 'TRY';
                        ?>
                            <div class="payment-item">
                                <div class="payment-header">
                                    <div class="payment-amount"><?= $amount ?> <?= $currency ?></div>
                                    <div class="payment-status <?= $status_class ?>">
                                        <?= ucfirst($payment['status'] ?? 'Bilinmiyor') ?>
                                    </div>
                                </div>
                                
                                <div class="payment-details">
                                    <div class="payment-detail">
                                        <div class="detail-label">İşlem ID</div>
                                        <div class="detail-value"><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="payment-detail">
                                        <div class="detail-label">Ödeme Yöntemi</div>
                                        <div class="detail-value">
                                            <?php
                                            $method = $payment['payment_method'] ?? 'unknown';
                                            $method_labels = [
                                                'credit_card' => '💳 Kredi Kartı',
                                                'bank_transfer' => '🏦 Banka Havalesi',
                                                'paypal' => '💙 PayPal',
                                                'crypto' => '₿ Kripto Para',
                                                'cash' => '💵 Nakit'
                                            ];
                                            echo $method_labels[$method] ?? ucfirst($method);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="payment-detail">
                                        <div class="detail-label">Açıklama</div>
                                        <div class="detail-value"><?= htmlspecialchars($payment['description'] ?? 'Ödeme') ?></div>
                                    </div>
                                    <div class="payment-detail">
                                        <div class="detail-label">İşlem Tarihi</div>
                                        <div class="detail-value"><?= date('d.m.Y H:i', strtotime($payment['created_at'] ?? 'now')) ?></div>
                                    </div>
                                    <?php if ($payment['processed_at'] ?? false): ?>
                                    <div class="payment-detail">
                                        <div class="detail-label">İşlenme Tarihi</div>
                                        <div class="detail-value"><?= date('d.m.Y H:i', strtotime($payment['processed_at'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <!-- Faturalar -->
                <?php if (!empty($invoices)): ?>
                <div class="card">
                    <h2>Son Faturalar</h2>
                    <?php foreach ($invoices as $invoice): ?>
                        <div class="invoice-item">
                            <div class="invoice-info">
                                <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number'] ?? 'INV-000') ?></div>
                                <div style="font-size: 0.8rem; color: #6c757d;">
                                    <?= date('d.m.Y', strtotime($invoice['created_at'] ?? 'now')) ?>
                                </div>
                            </div>
                            <div class="invoice-amount"><?= number_format((float)($invoice['total_amount'] ?? $invoice['amount'] ?? 0), 2) ?> ₺</div>
                        </div>
                    <?php endforeach; ?>
                    <a href="user-invoices.php?id=<?= $user_id ?>" class="btn btn-primary" style="width: 100%;">Tüm Faturaları Görüntüle</a>
                </div>
                <?php endif; ?>

                <!-- Abonelikler -->
                <?php if (!empty($subscriptions)): ?>
                <div class="card">
                    <h2>Aktif Abonelikler</h2>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <div class="subscription-item">
                            <div class="subscription-header">
                                <div class="subscription-name"><?= htmlspecialchars($subscription['plan_name'] ?? 'Abonelik') ?></div>
                                <div class="subscription-status status-<?= $subscription['status'] ?? 'active' ?>">
                                    <?= ucfirst($subscription['status'] ?? 'active') ?>
                                </div>
                            </div>
                            <div style="font-size: 0.9rem; color: #6c757d;">
                                Sonraki ödeme: <?= date('d.m.Y', strtotime($subscription['next_billing_date'] ?? '+1 month')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Hızlı İşlemler -->
                <div class="card">
                    <h2>Hızlı İşlemler</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="payment-add.php?user_id=<?= $user_id ?>" class="btn btn-success">💰 Manuel Ödeme Ekle</a>
                        <a href="invoice-create.php?user_id=<?= $user_id ?>" class="btn btn-primary">🧾 Fatura Oluştur</a>
                        <a href="payment-refund.php?user_id=<?= $user_id ?>" class="btn btn-danger">↩️ İade İşlemi</a>
                        <a href="subscription-manage.php?user_id=<?= $user_id ?>" class="btn btn-secondary">📋 Abonelik Yönet</a>
                    </div>
                </div>

                <!-- Ödeme Bilgileri -->
                <div class="card">
                    <h2>Ödeme İstatistikleri</h2>
                    <div style="color: #6c757d; font-size: 0.9rem; line-height: 1.6;">
                        <p><strong>Ortalama ödeme:</strong> <?= $payment_stats['total_payments'] > 0 ? number_format($payment_stats['total_amount'] / $payment_stats['total_payments'], 2) : '0' ?> ₺</p>
                        <p><strong>Başarı oranı:</strong> <?= $payment_stats['total_payments'] > 0 ? round(($payment_stats['completed_payments'] / $payment_stats['total_payments']) * 100, 1) : 0 ?>%</p>
                        <p><strong>Son 30 gün:</strong> 
                            <?php
                            $recent_payments = array_filter($payments, function($p) {
                                return strtotime($p['created_at'] ?? '1970-01-01') > strtotime('-30 days');
                            });
                            echo count($recent_payments);
                            ?> ödeme
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="user-detail.php?id=<?= $user_id ?>" class="btn btn-primary">Kullanıcı Detayları</a>
            <a href="user-edit.php?id=<?= $user_id ?>" class="btn btn-secondary">Kullanıcıyı Düzenle</a>
            <a href="users.php" class="btn btn-secondary">Kullanıcı Listesi</a>
        </div>
    </div>
</body>
</html>