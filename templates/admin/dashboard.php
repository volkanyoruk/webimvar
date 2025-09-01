<?php
$title = 'Dashboard';
ob_start();

// Stats verisini hazırla
try {
    $db = Database::getInstance();
    $stats = [
        'active_users' => $db->queryOne("SELECT COUNT(*) as count FROM users WHERE is_active=1")['count'] ?? 0,
        'active_sites' => 0, // Şimdilik
        'failed_24h' => 0,   // Şimdilik
        'avg_query_ms' => '—'
    ];
} catch (Exception $e) {
    $stats = ['active_users' => '—', 'active_sites' => '—', 'failed_24h' => '—', 'avg_query_ms' => '—'];
}
?>

<?php if ($flashOk = Session::getFlash('ok')): ?>
  <div class="mb-4 rounded bg-emerald-50 text-emerald-700 px-3 py-2 text-sm">
    <?= htmlspecialchars($flashOk) ?>
  </div>
<?php endif; ?>

<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="bg-white rounded-xl p-4 shadow">
    <div class="text-sm text-slate-500">Aktif Kullanıcı</div>
    <div class="text-3xl font-semibold mt-1"><?= htmlspecialchars($stats['active_users']) ?></div>
  </div>
  <div class="bg-white rounded-xl p-4 shadow">
    <div class="text-sm text-slate-500">Aktif Site</div>
    <div class="text-3xl font-semibold mt-1"><?= htmlspecialchars($stats['active_sites']) ?></div>
  </div>
  <div class="bg-white rounded-xl p-4 shadow">
    <div class="text-sm text-slate-500">24s Başarısız Giriş</div>
    <div class="text-3xl font-semibold mt-1"><?= htmlspecialchars($stats['failed_24h']) ?></div>
  </div>
  <div class="bg-white rounded-xl p-4 shadow">
    <div class="text-sm text-slate-500">Ortalama Sorgu Süresi</div>
    <div class="text-3xl font-semibold mt-1">
      <?= htmlspecialchars($stats['avg_query_ms']) ?><?= $stats['avg_query_ms'] === '—' ? '' : ' ms' ?>
    </div>
  </div>
</section>

<div class="mt-6 flex gap-2">
  <a href="/ops/users" class="px-3 py-2 rounded bg-slate-900 text-white text-sm">Kullanıcılar</a>
  <a href="/ops/users/create" class="px-3 py-2 rounded bg-slate-700 text-white text-sm">Yeni Kullanıcı</a>
  <button class="px-3 py-2 rounded bg-slate-200 text-slate-500 text-sm cursor-not-allowed">Site Yönetimi (yakında)</button>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';
?>