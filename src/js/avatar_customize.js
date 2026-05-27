function showEquipModal(equipment) {
  document.getElementById("modal-name").innerText = equipment.name;
  document.getElementById("modal-image").src =
    "assets/avatars/" + equipment.image_path;
  document.getElementById("modal-attack").innerText =
    "攻撃力: " + equipment.attack;
  document.getElementById("modal-defense").innerText =
    "防御力: " + equipment.defense;
  document.getElementById("modal-slot").value = equipment.slot;
  document.getElementById("modal-equipment-id").value = equipment.equipment_id;
  document.getElementById("equipModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("equipModal").style.display = "none";
}
// 一覧→プレビューの即時反映（装備は preview_equip を使う）
document.querySelectorAll(".equip-card").forEach((btn) => {
  btn.addEventListener("click", () => {
    const slot = btn.dataset.slot;
    const id = btn.dataset.id;
    const previewSrc = btn.dataset.previewSrc; // PHP 側で assets/avatars/{slot}/{avatar_path} を埋め込み

    const previewImg = document.getElementById(`preview-${slot}`);
    if (previewImg && previewSrc) {
      previewImg.src = previewSrc;
      previewImg.alt = `${slot} preview`;
    }
    const hid = document.getElementById(`equip-id-${slot}`);
    if (hid) hid.value = id;
  });
});

// 「外す」→ 未装備アイコン（assets/icons/{slot}/{empty_icon_path}）
document.querySelectorAll(".btn-unequip").forEach((btn) => {
  btn.addEventListener("click", () => {
    const slot = btn.dataset.slot;
    const wrap = document.querySelector(
      `.equip-preview__item[data-slot="${slot}"]`
    );
    const emptySrc = wrap ? wrap.dataset.emptySrc : "";

    const previewImg = document.getElementById(`preview-${slot}`);
    if (previewImg && emptySrc) {
      previewImg.src = emptySrc;
      previewImg.alt = `${slot} empty`;
    }
    const hid = document.getElementById(`equip-id-${slot}`);
    if (hid) hid.value = "";
  });
});
