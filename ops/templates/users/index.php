<?php /* $title, $users, $csrf bekler */ ?>
<h1 class="text-xl font-semibold mb-4">Kullanıcılar</h1>

<?php if ($m = Session::getFlash('ok')): ?>
  <div class="mb-3 rounded bg-green-50 text-green-700 px-3 py-2 text-sm"><?= htmlspecialchars($m) ?></div>
<?php endif; ?>
<?php if ($m = Session::getFlash('err')): ?>
  <div class="mb-3 rounded bg-red-50 text-red-700 px-3 py-2 text-sm"><?= htmlspecialchars($m) ?></div>
<?php endif; ?>

<div class="mb-4">
  <a href="/ops/users/create" class="bg-slate-900 text-white px-4 py-2 rounded">Yeni Kullanıcı</a>
</div>

<div class="overflow-auto bg-white rounded-xl shadow">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50 text-slate-600">
      <tr>
        <th class="px-3 py-2 text-left">ID</th>
        <th class="px-3 py-2 text-left">Ad Soyad</th>
        <th class="px-3 py-2 text-left">E-posta</th>
        <th class="px-3 py-2 text-left">Rol</th>
        <th class="px-3 py-2 text-left">Durum</th>
        <th class="px-3 py-2"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $row): ?>
      <tr class="border-t">
        <td class="px-3 py-2"><?= (int)$row['id'] ?></td>
        <td class="px-3 py-2"><?= htmlspecialchars($row['full_name']) ?></td>
        <td class="px-3 py-2"><?= htmlspecialchars($row['email']) ?></td>
        <td class="px-3 py-2"><?= htmlspecialchars($row['role']) ?></td>
        <td class="px-3 py-2"><?= ((int)$row['is_active'] ? 'Aktif' : 'Pasif') ?></td>
        <td class="px-3 py-2 text-right">
          <a href="/ops/users/edit?id=<?= (int)$row['id'] ?>" class="px-3 py-1 rounded bg-slate-200 text-slate-700">Düzenle</a>
          <form method="post" action="/ops/users/delete" class="inline" onsubmit="return confirm('Silinsin mi?');">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button class="px-3 py-1 rounded bg-red-600 text-white">Sil</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
      <tr><td class="px-3 py-4 text-center text-slate-500" colspan="6">Kayıt yok</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>