let pendingAction = null;
let notifications = [];

const NOTIFICATION_API = "../phpUser/notifications.php";

/* ===== NOTIFICATION API ===== */
function loadNotifications() {
  fetch(NOTIFICATION_API)
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        notifications = data.data || [];
        updateNotificationPanel();
        updateBadge();
      }
    })
    .catch((err) => {
      console.error("Load notifications failed", err);
    });
}

function saveNotification(
  message,
  type = "info",
  productName = "",
  productPrice = 0,
) {
  const payload = new URLSearchParams();
  payload.append("action", "add");
  payload.append("message", message);
  payload.append("type", type);
  payload.append("product_name", productName);
  payload.append("product_price", productPrice);

  return fetch(NOTIFICATION_API, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: payload.toString(),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        const notif = data.notification;
        if (notif) {
          // Kiểm tra trùng trước khi thêm vào local array
          const exists = notifications.some((n) => n.id === notif.id);
          if (!exists) {
            notifications.unshift(notif);
            updateNotificationPanel();
            updateBadge();
          }
        }
      }
      return data;
    });
}

function markNotificationRead(id) {
  const payload = new URLSearchParams();
  payload.append("action", "read");
  payload.append("id", id);

  return fetch(NOTIFICATION_API, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: payload.toString(),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        notifications = notifications.filter((n) => n.id !== id);
        updateNotificationPanel();
        updateBadge();
      }
      return data;
    });
}

/* ===== NOTIFICATION PANEL ===== */
function toggleNotificationPanel() {
  const panel = document.getElementById("notificationPanel");
  panel.classList.toggle("active");
}

function closeNotificationPanel() {
  const panel = document.getElementById("notificationPanel");
  panel.classList.remove("active");
}

/* ===== ADD NOTIFICATION LOCAL (fallback only) ===== */
function addNotificationToPanel(message, type, productName, productPrice) {
  const timestamp = new Date().toLocaleTimeString("vi-VN");

  const notification = {
    id: Date.now(),
    message,
    type,
    timestamp,
    productName,
    productPrice,
  };

  notifications.unshift(notification);
  updateNotificationPanel();
}

/* ===== DELETE NOTIFICATION ===== */
function deleteNotification(notifId) {
  markNotificationRead(notifId).catch(() => {
    // Nếu API lỗi, vẫn cập nhật local
    notifications = notifications.filter((n) => n.id !== notifId);
    updateNotificationPanel();
    updateBadge();
  });
}

/* ===== UPDATE BADGE ===== */
function updateBadge() {
  const badge = document.getElementById("notificationBadge");
  if (!badge) return;
  badge.innerText = notifications.length;
}

/* ===== RENDER PANEL ===== */
function updateNotificationPanel() {
  const notificationList = document.getElementById("notificationList");

  if (!notificationList) return;

  if (notifications.length === 0) {
    notificationList.innerHTML =
      '<p class="empty-message">Chưa có thông báo</p>';
    return;
  }

  notificationList.innerHTML = notifications
    .map(
      (notif) => `
      <div class="notification-item ${notif.type}" onclick="deleteNotification(${notif.id})">
        <div class="notification-item-icon">
          ${notif.type === "success" ? "✓" : "✕"}
        </div>

        <div class="notification-item-content">
          <div class="notification-item-title">
            ${notif.type === "success" ? "Thành công" : "Lỗi"}
          </div>

          <div class="notification-item-product">
            <strong>${notif.productName}</strong> 
            - ${Number(notif.productPrice).toLocaleString()} VNĐ
          </div>

          <div class="notification-item-message">
            ${notif.message}
          </div>

          <div class="notification-item-time">
            ${notif.timestamp}
          </div>
        </div>
      </div>
    `,
    )
    .join("");
}

/* ===== MODAL ===== */
function openModal(type, id, btn) {
  if (typeof loadNotifications === "function") {
    loadNotifications();
  }
  const card = btn.closest(".product-card");

  if (!card) return;

  const name = card.querySelector("h3")?.innerText || "";
  const price = parseInt(
    card.querySelector(".price")?.innerText.replace(/\D/g, "") || 0,
  );
  const qty = parseInt(card.querySelector(".qty")?.value || 1);
  const img = card.querySelector(".product-image")?.src || "";

  if (qty <= 0) {
    showNotification("Số lượng không hợp lệ!", "error");
    return;
  }

  const total = price * qty;

  const modal = document.getElementById("confirmModal");
  const modalIcon = document.getElementById("modalIcon");
  const modalTitle = document.getElementById("modalTitle");
  const modalImage = document.getElementById("modalImage");
  const modalProduct = document.getElementById("modalProduct");
  const modalQty = document.getElementById("modalQty");
  const modalTotal = document.getElementById("modalTotal");
  const modalAddress = document.getElementById("modalAddress");

  if (type === "cart") {
    modalIcon.innerText = "🛒";
    modalTitle.innerText = "THÊM GIỎ HÀNG";
  } else {
    modalIcon.innerText = "💳";
    modalTitle.innerText = "XÁC NHẬN MUA HÀNG";
  }

  modalImage.src = img;
  modalProduct.innerText = name;
  modalQty.innerText = qty;
  modalTotal.innerText = total.toLocaleString() + " VNĐ";
  modalAddress.innerText =
    typeof userAddress !== "undefined" && userAddress.trim() !== ""
      ? userAddress
      : "Chưa cập nhật địa chỉ";

  pendingAction = { type, id, qty, productName: name, productPrice: price };

  modal.classList.add("active");
}

function closeModal() {
  const modal = document.getElementById("confirmModal");
  modal.classList.remove("active");
  pendingAction = null;
}

/* ===== CONFIRM ACTION ===== */
function confirmAction() {
  if (!pendingAction) return;

  const { type, id, qty, productName, productPrice } = pendingAction;

  fetch("products.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `product_id=${id}&quantity=${qty}&type=${type}`,
  })
    .then((res) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then((data) => {
      closeModal();
      showNotification(
        data.msg || "Có lỗi xảy ra",
        data.status === "success" ? "success" : "error",
        productName,
        productPrice,
      );
    })
    .catch((err) => {
      closeModal();
      showNotification(`Lỗi kết nối server! (${err.message})`, "error");
    });
}

/* ===== TOAST NOTIFICATION ===== */
function showNotification(
  msg,
  type = "success",
  productName = "",
  productPrice = 0,
) {
  // Hiển thị toast
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.innerHTML = `
    <div class="notification-content">
      ${type === "success" ? "✓" : "✕"} ${msg}
    </div>
  `;

  document.body.appendChild(notification);
  setTimeout(() => notification.classList.add("show"), 10);

  // Lưu vào DB → saveNotification() tự cập nhật panel (KHÔNG dùng addNotificationToPanel ở đây)
  saveNotification(msg, type, productName, productPrice).catch(() => {
    // Fallback nếu DB lỗi: thêm local
    addNotificationToPanel(msg, type, productName, productPrice);
    updateBadge();
  });

  // Toast tự ẩn sau 3 giây
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

/* ===== ACTION ===== */
function addToCart(id, btn) {
  openModal("cart", id, btn);
}

function buyNow(id, btn) {
  openModal("buy", id, btn);
}

document.addEventListener("DOMContentLoaded", () => {
  if (typeof loadNotifications === "function") {
    loadNotifications();
  }
});
