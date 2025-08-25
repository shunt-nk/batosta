<?php
// index.php
declare(strict_types=1);
session_start();
require __DIR__ . '/includes/db.php';

/**
 * ユーザーがアバター作成済みかどうか（= body スロットが保存済みか）を判定
 */
function hasAvatarBody(PDO $pdo, int $userId): bool {
    $st = $pdo->prepare("SELECT 1 FROM user_avatar_parts WHERE user_id = ? AND slot = 'body' LIMIT 1");
    $st->execute([$userId]);
    return (bool)$st->fetchColumn();
}

/**
 * すでにセッションがある状態で index に来た場合、
 * 未作成なら avatar_create.php?first=1 へ一度だけ誘導、作成済みなら dashboard.php へ。
 */
if (!empty($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    $userId = (int)$_SESSION['user']['id'];
    try {
        if (hasAvatarBody($pdo, $userId)) {
            header('Location: dashboard.php'); // ← 作成済みならダッシュボード
            exit;
        } else {
            header('Location: avatar_create.php?first=1'); // ← 未作成は一度だけ作成へ
            exit;
        }
    } catch (Throwable $e) {
        // 何かあってもログイン画面は表示できるように握りつぶす
        error_log('[index.php] hasAvatarBody check failed: '.$e->getMessage());
    }
}

// エラーメッセージ用
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォーム送信（ログイン）
    $email    = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($email === '' || $password === '') {
        $error = 'メールアドレスとパスワードを入力してください。';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
                // 認証OK → セッション保存
                $_SESSION['user'] = [
                    'id'    => (int)$user['id'],
                    'name'  => $user['name'] ?? '',
                    'email' => $user['email'] ?? '',
                ];

                // アバター作成済みか判定して遷移先を決める
                $userId = (int)$user['id'];
                if (hasAvatarBody($pdo, $userId)) {
                    header('Location: dashboard.php');
                    exit;
                } else {
                    header('Location: avatar_create.php?first=1'); // ← ここで一度だけ作成へ
                    exit;
                }
            } else {
                // 認証NG
                $error = 'メールアドレスまたはパスワードが違います。';
            }
        } catch (Throwable $e) {
            error_log('[index.php] login error: '.$e->getMessage());
            $error = 'サーバーエラーが発生しました。しばらくしてからお試しください。';
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ログイン | バトスタ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/index.css">
  <script src="js/index.js" defer></script>
</head>
<body>
  <div class="form_container">
    <img src="assets/icons/logo.png" alt="ロゴ" width="120" height="120">
    <form method="POST" autocomplete="off" novalidate>
      <h2>ログイン</h2>

      <?php if ($error): ?>
        <div class="error" style="color:#c62828; margin-bottom:8px;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <input class="user_text" type="email" name="email" placeholder="メールアドレス" required>
      <input class="user_text" type="password" name="password" id="password" placeholder="パスワード" required>

      <label><input type="checkbox" class="custom-checkbox" onclick="togglePassword()"> パスワードを表示</label>
      <button type="submit">ログイン</button>

      <a href="reset_request.php" class="reset">パスワードを忘れた方は<span>こちら</span></a>
      <a href="register.php" class="new">新規登録はこちら</a>
    </form>
  </div>
</body>
</html>
