function openModal(description) {
  document.getElementById("methodText").innerText = description;
  document.getElementById("methodModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("methodModal").style.display = "none";
}
