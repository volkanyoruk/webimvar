<?php
/** @var array $stats */
/** @var string $title */
$title = $title ?? 'Dashboard';

$activeUsers  = (int)($stats['active_users'] ?? 0);
$activeSites  = (int)($stats['active_sites'] ?? 0);
$failed24h    = (int)($stats['failed_24h'] ?? 0);
$avgQueryMs   = $stats['avg_query_ms'] ?? null;
?>
<div class="mx-auto max-w-6xl p-6">
  <h1 class="text-xl font-semibold mb-4">Webimvar Ops</h1>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-white border rounded-xl p-5">
      <div class="text-sm text-slate-500 mb-2">Aktif Kullanıcı</div>
      <div class="text-3xl font-semibold"><?= (int)$activeUsers ?></div>
    </div>
    <div class="bg-white border rounded-xl p-5">
      <div class="text-sm text-slate-500 mb-2">Aktif Site</div>
      <div class="text-3xl font-semibold"><?= (int)$activeSites ?></div>
    </div>
    <div class="bg-white border rounded-xl p-5">
      <div class="text-sm text-slate-500 mb-2">24s Başarısız Giriş</div>
      <div class="text-3xl font-semibold"><?= (int)$failed24h ?></div>
    </div>
    <div class="bg-white border rounded-xl p-5">
      <div class="text-sm text-slate-500 mb-2">Ortalama Sorgu Süresi</div>
      <div class="text-3xl font-semibold">
        <?= is_numeric($avgQueryMs) ? (float)$avgQueryMs.' ms' : '—' ?>
      </div>
    </div>
  </div>

  <div class="mt-6 flex gap-3">
    <a href="/ops/users" class="px-4 py-2 bg-slate-800 text-white rounded">Kullanıcılar</a>
    <a href="/ops/users/create" class="px-4 py-2 bg-slate-600 text-white rounded">Yeni Kullanıcı</a>
    <button disabled class="px-4 py-2 bg-slate-300 text-slate-600 rounded">Site Yönetimi (yakında)</button>
  </div>
</div>