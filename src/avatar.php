<?php
require 'includes/db.php';
session_start();

$user_id = $_SESSION["user"]["id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $gender = $_POST["gender"];

    // すでにアバターがあるか確認
    $stmt = $pdo->prepare("SELECT * FROM avatars WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $avatar = $stmt->fetch();

    if ($avatar) {
        // 更新
        $stmt = $pdo->prepare("UPDATE avatars SET gender = ? WHERE user_id = ?");
        $stmt->execute([$gender, $user_id]);
    } else {
        // 新規作成
        $stmt = $pdo->prepare("INSERT INTO avatars (user_id, gender) VALUES (?, ?)");
        $stmt->execute([$user_id, $gender]);
    }

    echo "✅ アバターを更新しました！";
}

// アバター表示
$stmt = $pdo->prepare("SELECT * FROM avatars WHERE user_id = ?");
$stmt->execute([$user_id]);
$avatar = $stmt->fetch();
?>

<h2>アバター設定</h2>
<form method="POST">
  <label>
    性別：
    <select name="gender">
      <option value="male" <?= $avatar && $avatar['gender'] === 'male' ? 'selected' : '' ?>>男</option>
      <option value="female" <?= $avatar && $avatar['gender'] === 'female' ? 'selected' : '' ?>>女</option>
      <option value="other" <?= $avatar && $avatar['gender'] === 'other' ? 'selected' : '' ?>>その他</option>
    </select>
  </label>
  <button type="submit">保存</button>
</form>

<?php if ($avatar): ?>
  <p>現在の設定：<?= htmlspecialchars($avatar['gender']) ?>（レベル <?= $avatar['level'] ?>）</p>
<?php endif; ?>

<p><a href="dashboard.php">← ダッシュボードへ戻る</a></p>
