let selected = { subject: "", type: "", time: "" };

document.addEventListener("DOMContentLoaded", () => {
  updatePreview();
  insertAddTimeButton();
});

// ----------------------
// 選択処理
// ----------------------
function select(key, value) {
  selected[key] = value;
  updatePreview();
  highlightSelected(key, value);
}

function highlightSelected(key, value) {
  let className =
    key === "subject"
      ? ".subject-btn"
      : key === "type"
      ? ".type-btn"
      : ".time_btn";

  document.querySelectorAll(className).forEach((btn) => {
    // 時間ボタンの時、「+」ボタンはスキップ
    if (key === "time" && btn.textContent.trim() === "+") {
      btn.classList.remove("selected");
      return;
    }

    if (btn.innerText === value) {
      btn.classList.add("selected");
    } else {
      btn.classList.remove("selected");
    }
  });
}

// ----------------------
// プレビュー更新
// ----------------------
function updatePreview() {
  const subjectContainer = document.getElementById("selected-subject");
  const typeContainer = document.getElementById("selected-type");

  // 教科
  subjectContainer.innerHTML = "";
  if (selected.subject) {
    const matchedBtn = Array.from(
      document.querySelectorAll(".subject-btn")
    ).find((btn) => btn.innerText === selected.subject);
    if (matchedBtn) {
      const clone = matchedBtn.cloneNode(true);
      clone.disabled = true;
      subjectContainer.appendChild(clone);
    }
  } else {
    const dummy = createDummyButton("教科を選んでね");
    subjectContainer.appendChild(dummy);
  }

  // 種類
  typeContainer.innerHTML = "";
  if (selected.type) {
    const matchedBtn = Array.from(document.querySelectorAll(".type-btn")).find(
      (btn) => btn.innerText === selected.type
    );
    if (matchedBtn) {
      const clone = matchedBtn.cloneNode(true);
      clone.disabled = true;
      typeContainer.appendChild(clone);
    }
  } else {
    const dummy = createDummyButton("種類を選んでね");
    typeContainer.appendChild(dummy);
  }
}
function createDummyButton(text) {
  const btn = document.createElement("button");
  btn.textContent = text;
  btn.style.background = "#706A6A";
  btn.style.color = "#fff";
  btn.style.width = "150px";
  btn.style.height = "70px";
  btn.style.fontSize = "1.rem";
  btn.style.fontWeight = "bold";
  btn.style.borderRadius = "12px";
  btn.disabled = true;
  return btn;
}

// ----------------------
// 時間 +ボタンの追加
// ----------------------
function insertAddTimeButton() {
  const plusBtn = document.createElement("button");
  plusBtn.className = "time_btn";
  plusBtn.textContent = "+";
  plusBtn.type = "button"; // ← これを追加！
  plusBtn.onclick = openTimePicker;
  document.getElementById("times").appendChild(plusBtn);
}

// ----------------------
// 時間モーダル選択
// ----------------------
function openTimePicker() {
  const wrapper = document.createElement("div");
  wrapper.style.position = "fixed";
  wrapper.style.top = 0;
  wrapper.style.left = 0;
  wrapper.style.right = 0;
  wrapper.style.bottom = 0;
  wrapper.style.background = "rgba(0,0,0,0.6)";
  wrapper.style.display = "flex";
  wrapper.style.justifyContent = "center";
  wrapper.style.alignItems = "center";
  wrapper.style.zIndex = 9999;

  const modal = document.createElement("div");
  modal.style.background = "#fff";
  modal.style.padding = "20px";
  modal.style.borderRadius = "10px";
  modal.style.textAlign = "center";

  const select = document.createElement("select");
  select.style.fontSize = "1.2rem";
  for (let i = 1; i <= 120; i++) {
    const option = document.createElement("option");
    option.value = `${i}分`;
    option.textContent = `${i}分`;
    select.appendChild(option);
  }

  const confirm = document.createElement("button");
  confirm.textContent = "追加";
  confirm.style.margin = "10px";
  confirm.style.padding = "0.5rem 1rem";
  confirm.style.fontSize = "1.1rem";
  confirm.style.background = "#db9963";
  confirm.style.color = "#fff";
  confirm.style.borderRadius = "6px";
  confirm.onclick = () => {
    const time = select.value;
    addTimeToList(time);
    wrapper.remove();
    saveToServer("time", time);
  };

  const cancel = document.createElement("button");
  cancel.textContent = "キャンセル";
  cancel.style.margin = "10px";
  cancel.onclick = () => wrapper.remove();

  modal.appendChild(select);
  modal.appendChild(document.createElement("br"));
  modal.appendChild(confirm);
  modal.appendChild(cancel);
  wrapper.appendChild(modal);
  document.body.appendChild(wrapper);
}

// ----------------------
// 時間をDOMに追加
// ----------------------
function addTimeToList(time) {
  const container = document.getElementById("times");
  const plusBtn = container.querySelector(".time_btn:last-child");

  const btn = document.createElement("button");
  btn.type = "button";
  btn.className = "time_btn";
  btn.textContent = time;
  btn.onclick = () => select("time", time);

  container.insertBefore(btn, plusBtn);
}

// ----------------------
// モーダル処理
// ----------------------
function openModal() {
  if (!selected.subject || !selected.type || !selected.time) {
    alert("すべての項目を選んでください");
    return;
  }

  document.querySelector('input[name="subject"]').value = selected.subject;
  document.querySelector('input[name="type"]').value = selected.type;
  document.querySelector('input[name="time"]').value = selected.time;

  document.getElementById(
    "confirmContent"
  ).innerText = `教科：${selected.subject} / 種類：${selected.type} / 時間：${selected.time}`;
  document.getElementById("confirmModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("confirmModal").style.display = "none";
}

function addItem(key) {
  if (key === "type") {
    const input = document.getElementById("newType");
    const value = input.value.trim();
    if (!value) return;

    // 同じ種類が既にあるか確認
    const exists = Array.from(document.querySelectorAll(".type-btn")).some(
      (btn) => btn.textContent === value
    );
    if (exists) {
      alert("既に存在する種類です");
      return;
    }

    // ボタンを追加
    const newBtn = document.createElement("button");
    newBtn.type = "button";
    newBtn.className = "type-btn";
    newBtn.textContent = value;
    newBtn.onclick = () => select("type", value);

    document.getElementById("types").appendChild(newBtn);
    input.value = ""; // 入力欄をクリア
  }
}
