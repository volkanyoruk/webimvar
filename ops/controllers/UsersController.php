<?php
/**
 * UsersController – /ops/users akışı
 */
class UsersController
{
    private function csrfToken(): string {
        $t = Session::get('csrf');
        if (!$t) {
            $t = bin2hex(random_bytes(16));
            Session::set('csrf', $t);
        }
        return $t;
    }
    private function checkCsrf(): void {
        $ok = isset($_POST['csrf']) && hash_equals((string)Session::get('csrf'), (string)$_POST['csrf']);
        if (!$ok) {
            Session::flash('err', 'Geçersiz güvenlik anahtarı (CSRF).');
            Response::redirect('/ops/users');
            exit;
        }
    }

    /** Liste */
    public function index(): void {
        $user = new User();
        $users = $user->all();
        $title = 'Kullanıcılar';
        $csrf = $this->csrfToken();
        ob_start();
        include __DIR__ . '/../templates/users/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../templates/layouts/admin.php';
    }

    /** Yeni kullanıcı (GET) */
    public function createForm(): void {
        $title = 'Yeni Kullanıcı';
        $csrf = $this->csrfToken();
        $item = [
            'full_name' => '', 'email' => '', 'role' => 'user', 'is_active' => 1
        ];
        $isEdit = false;
        ob_start();
        include __DIR__ . '/../templates/users/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../templates/layouts/admin.php';
    }

    /** Yeni kullanıcı (POST) */
    public function createPost(): void {
        $this->checkCsrf();
        $full = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'user';
        $pass  = $_POST['password'] ?? '';

        if ($full === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            Session::flash('err', 'Alanları kontrol edin. (Geçerli e-posta, min 8 karakter şifre)');
            Response::redirect('/ops/users/create');
            return;
        }

        $user = new User();
        // email benzersizlik kontrolü
        $exists = $user->findBy('email', $email);
        if ($exists) {
            Session::flash('err', 'Bu e-posta zaten kayıtlı.');
            Response::redirect('/ops/users/create');
            return;
        }

        $user->create([
            'full_name' => $full,
            'email'     => $email,
            'password'  => password_hash($pass, PASSWORD_DEFAULT),
            'role'      => $role,
            'is_active' => 1
        ]);

        Session::flash('ok', 'Kullanıcı oluşturuldu.');
        Response::redirect('/ops/users');
    }

    /** Düzenleme (GET) */
    public function editForm(): void {
        $id = (int)($_GET['id'] ?? 0);
        $user = new User();
        $item = $user->find($id);
        if (!$item) {
            Session::flash('err', 'Kullanıcı bulunamadı.');
            Response::redirect('/ops/users');
            return;
        }
        $title = 'Kullanıcı Düzenle';
        $csrf = $this->csrfToken();
        $isEdit = true;
        ob_start();
        include __DIR__ . '/../templates/users/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../templates/layouts/admin.php';
    }

    /** Düzenleme (POST) */
    public function editPost(): void {
        $this->checkCsrf();
        $id   = (int)($_POST['id'] ?? 0);
        $full = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'user';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $newpass = $_POST['password'] ?? '';

        if ($full === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('err', 'Alanları kontrol edin.');
            Response::redirect('/ops/users/edit?id=' . $id);
            return;
        }

        $user = new User();
        // e-posta çakışması (başka kullanıcıyla)
        $row = $user->findBy('email', $email);
        if ($row && (int)$row['id'] !== $id) {
            Session::flash('err', 'Bu e-posta başka kullanıcıda kayıtlı.');
            Response::redirect('/ops/users/edit?id=' . $id);
            return;
        }

        $data = [
            'full_name' => $full,
            'email'     => $email,
            'role'      => $role,
            'is_active' => $active,
        ];
        if ($newpass !== '') {
            if (strlen($newpass) < 8) {
                Session::flash('err', 'Şifre en az 8 karakter olmalı.');
                Response::redirect('/ops/users/edit?id=' . $id);
                return;
            }
            $data['password'] = password_hash($newpass, PASSWORD_DEFAULT);
        }

        $user->updateById($id, $data);
        Session::flash('ok', 'Kullanıcı güncellendi.');
        Response::redirect('/ops/users');
    }

    /** Sil (POST) */
    public function deletePost(): void {
        $this->checkCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('err', 'Geçersiz istek.');
            Response::redirect('/ops/users');
            return;
        }
        $user = new User();
        $user->deleteById($id);
        Session::flash('ok', 'Kullanıcı silindi.');
        Response::redirect('/ops/users');
    }
}