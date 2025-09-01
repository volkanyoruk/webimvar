<?php
$formTitle = $isEdit ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı';
$formAction = $isEdit ? '/ops/users/edit' : '/ops/users/create';
$title = $formTitle;
ob_start();
?>
<div class="mx-auto max-w-lg bg-white p-6 rounded-xl shadow">
  <h1 class="text-xl font-semibold mb-4"><?= htmlspecialchars($formTitle) ?></h1>

  <?php if ($err = Session::getFlash('err')): ?>
    <div class="mb-4 rounded bg-red-50 text-red-700 px-3 py-2 text-sm">
      <?= htmlspecialchars($err) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="grid gap-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
    <?php endif; ?>

    <label class="text-sm text-slate-700" for="full_name">Ad Soyad</label>
    <input id="full_name" name="full_name" type="text" required 
           value="<?= htmlspecialchars($item['full_name'] ?? '') ?>"
           class="border rounded px-3 py-2">

    <label class="text-sm text-slate-700" for="email">E-posta</label>
    <input id="email" name="email" type="email" required autocomplete="username"
           value="<?= htmlspecialchars($item['email'] ?? '') ?>"
           class="border rounded px-3 py-2">

    <label class="text-sm text-slate-700" for="password">
      Şifre <?= $isEdit ? '(Boş bırakırsanız değişmez)' : '' ?>
    </label>
    <input id="password" name="password" type="password" 
           <?= $isEdit ? '' : 'required' ?>
           autocomplete="new-password" class="border rounded px-3 py-2">

    <label class="text-sm text-slate-700" for="role">Rol</label>
    <select id="role" name="role" class="border rounded px-3 py-2">
      <option value="user" <?= ($item['role'] ?? '') === 'user' ? 'selected' : '' ?>>Kullanıcı</option>
      <option value="manager" <?= ($item['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Müdür</option>
      <option value="admin" <?= ($item['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Yönetici</option>
      <option value="super_admin" <?= ($item['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Süper Yönetici</option>
    </select>

    <label class="inline-flex items-center gap-2 mt-2">
      <input type="checkbox" name="is_active" value="1" 
             <?= ((int)($item['is_active'] ?? 1)) ? 'checked' : '' ?>>
      <span>Aktif</span>
    </label>

    <div class="mt-3">
      <button class="rounded bg-slate-900 text-white px-4 py-2">
        <?= $isEdit ? 'Güncelle' : 'Kaydet' ?>
      </button>
      <a href="/ops/users" class="ml-2 underline">İptal</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';
?>