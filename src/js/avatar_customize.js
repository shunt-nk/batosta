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
