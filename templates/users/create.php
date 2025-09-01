<?php
$title = 'Yeni Kullanıcı';
ob_start();
?>
<div class="mx-auto max-w-lg bg-white p-6 rounded-xl shadow">
  <h1 class="text-xl font-semibold mb-4">Yeni Kullanıcı Oluştur</h1>

  <form method="post" action="/ops/users/create" class="grid gap-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <label class="text-sm text-slate-700" for="full_name">Ad Soyad</label>
    <input id="full_name" name="full_name" type="text" required class="border rounded px-3 py-2">

    <label class="text-sm text-slate-700" for="email">E-posta</label>
    <input id="email" name="email" type="email" required autocomplete="username"
           class="border rounded px-3 py-2">

    <label class="text-sm text-slate-700" for="password">Şifre</label>
    <input id="password" name="password" type="password" required autocomplete="new-password"
           class="border rounded px-3 py-2">

    <label class="text-sm text-slate-700" for="role">Rol</label>
    <select id="role" name="role" class="border rounded px-3 py-2">
      <option value="user">Kullanıcı</option>
      <option value="manager">Müdür</option>
      <option value="admin">Yönetici</option>
      <option value="super_admin">Süper Yönetici</option>
    </select>

    <label class="inline-flex items-center gap-2 mt-2">
      <input type="checkbox" name="is_active" value="1" checked>
      <span>Aktif</span>
    </label>

    <div class="mt-3">
      <button class="rounded bg-slate-900 text-white px-4 py-2">Kaydet</button>
      <a href="/ops/users" class="ml-2 underline">İptal</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/admin.php';