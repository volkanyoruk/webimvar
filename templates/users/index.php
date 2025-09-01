<?php
$title = 'Kullanıcılar';
ob_start();
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Kullanıcılar</h1>
  <a href="/ops/users/create" class="bg-slate-900 text-white px-3 py-2 rounded">Yeni Kullanıcı</a>
</div>

<?php if ($ok = Session::getFlash('ok')): ?>
  <div class="mb-4 rounded bg-green-50 text-green-700 px-3 py-2 text-sm">
    <?= htmlspecialchars($ok) ?>
  </div>
<?php endif; ?>

<?php if ($err = Session::getFlash('err')): ?>
  <div class="mb-4 rounded bg-red-50 text-red-700 px-3 py-2 text-sm">
    <?= htmlspecialchars($err) ?>
  </div>
<?php endif; ?>

<div class="overflow-x-auto bg-white rounded-xl shadow">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-100 text-slate-700">
      <tr>
        <th class="text-left px-3 py-2">ID</th>
        <th class="text-left px-3 py-2">Ad Soyad</th>
        <th class="text-left px-3 py-2">E-posta</th>
        <th class="text-left px-3 py-2">Rol</th>
        <th class="text-left px-3 py-2">Durum</th>
        <th class="text-left px-3 py-2">Oluşturma</th>
        <th class="text-right px-3 py-2">İşlemler</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= (int)$u['id'] ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($u['email'] ?? '') ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($u['role'] ?? '') ?></td>
          <td class="px-3 py-2">
            <?= ((int)($u['is_active'] ?? 0)) ? 'Aktif' : 'Pasif' ?>
          </td>
          <td class="px-3 py-2"><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
          <td class="px-3 py-2 text-right">
            <a href="/ops/users/edit?id=<?= (int)$u['id'] ?>" 
               class="px-2 py-1 rounded bg-blue-600 text-white text-xs mr-1">Düzenle</a>
            <form method="post" action="/ops/users/delete" 
                  onsubmit="return confirm('Silmek istediğinize emin misiniz?');" 
                  class="inline">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="px-2 py-1 rounded bg-red-600 text-white text-xs">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
        <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Kayıt yok</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';
?>