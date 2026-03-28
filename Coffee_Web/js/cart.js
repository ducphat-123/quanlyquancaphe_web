let cartPendingCheckout = null;

class CartCheckout {
  constructor(products, total) {
    this.products = products;
    this.total = total;
  }
}

function getSelectedProductIds() {
  const checkboxes = document.querySelectorAll(".item-select:checked");
  return Array.from(checkboxes).map((el) => parseInt(el.value, 10));
}

function calculateSelectedTotal() {
  const rows = document.querySelectorAll(".cart-row");
  let total = 0;
  rows.forEach((row) => {
    const id = row.getAttribute("data-id");
    const checkbox = row.querySelector(".item-select");
    if (checkbox && checkbox.checked) {
      const qty = parseInt(row.getAttribute("data-qty"), 10) || 0;
      const price = parseFloat(row.getAttribute("data-price")) || 0;
      total += price * qty;
    }
  });
  const totalElement = document.getElementById("grandTotal");
  if (totalElement) {
    totalElement.textContent = `${new Intl.NumberFormat("vi-VN").format(total)} VNĐ`;
  }
  const checkoutBtn = document.getElementById("checkoutBtn");
  if (checkoutBtn) {
    checkoutBtn.disabled = total <= 0;
  }
  return total;
}

function setupCartEvents() {
  const selectAll = document.getElementById("selectAll");
  const itemCheckboxes = document.querySelectorAll(".item-select");

  if (selectAll) {
    selectAll.addEventListener("change", () => {
      itemCheckboxes.forEach((cb) => (cb.checked = selectAll.checked));
      calculateSelectedTotal();
    });
  }

  itemCheckboxes.forEach((cb) => {
    cb.addEventListener("change", () => {
      const allChecked = Array.from(itemCheckboxes).every(
        (item) => item.checked,
      );
      if (selectAll) selectAll.checked = allChecked;
      calculateSelectedTotal();
    });
  });

  calculateSelectedTotal();
}

function openCartConfirmModal(selectedItems, selectedTotal) {
  const modal = document.getElementById("confirmModal");
  const modalIcon = document.getElementById("modalIcon");
  const modalTitle = document.getElementById("modalTitle");
  const modalImage = document.getElementById("modalImage");
  const modalProduct = document.getElementById("modalProduct");
  const modalQty = document.getElementById("modalQty");
  const modalTotal = document.getElementById("modalTotal");
  const modalAddress = document.getElementById("modalAddress");

  if (!modal) return;

  modalIcon.innerText = "💳";
  modalTitle.innerText = "XÁC NHẬN THANH TOÁN";

  const names = selectedItems.map((p) => `${p.name} (x${p.qty})`).join(" , ");
  const quantity = selectedItems.reduce((sum, p) => sum + Number(p.qty), 0);

  modalImage.src = "../..//images/no-image.png";
  modalProduct.innerText = names;
  modalQty.innerText = quantity;
  modalTotal.innerText = `${new Intl.NumberFormat("vi-VN").format(selectedTotal)} VNĐ`;
  modalAddress.innerText =
    typeof userAddress !== "undefined" && userAddress.trim() !== ""
      ? userAddress
      : "Chưa cập nhật địa chỉ";
  cartPendingCheckout = {
    selectedIds: selectedItems.map((p) => Number(p.id)),
    selectedItems,
    selectedTotal,
  };

  modal.classList.add("active");
}

function confirmCheckoutCart() {
  if (!cartPendingCheckout) return;
  const { selectedIds, selectedItems, selectedTotal } = cartPendingCheckout;

  const formBody = new URLSearchParams();
  formBody.append("ajax_checkout", "1");
  selectedIds.forEach((id) => formBody.append("selected[]", id));

  fetch("cart.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: formBody.toString(),
  })
    .then((res) => res.json())
    .then((data) => {
      closeModal();
      cartPendingCheckout = null;

      if (data.status === "success") {
        const productList = selectedItems.map((p) => p.name).join(", ");
        const productPrice = selectedTotal;
        if (typeof showNotification === "function") {
          showNotification(
            `Đã thanh toán: ${productList}`,
            "success",
            productList,
            productPrice,
          );
        } else {
          alert("✅ Thanh toán thành công!");
        }

        setTimeout(() => location.reload(), 1000);
      } else {
        if (typeof showNotification === "function") {
          showNotification(data.msg || "Thanh toán thất bại", "error", "", 0);
        } else {
          alert("❌ " + data.msg);
        }
      }
    })
    .catch((err) => {
      closeModal();
      const message = err?.message
        ? `Lỗi kết nối máy chủ: ${err.message}`
        : "Lỗi kết nối máy chủ";
      if (typeof showNotification === "function") {
        showNotification(message, "error", "", 0);
      } else {
        alert(`❌ ${message}`);
      }
    });
}

function checkoutCart() {
  const selectedIds = getSelectedProductIds();
  if (selectedIds.length === 0) {
    return alert("Vui lòng chọn ít nhất một sản phẩm để thanh toán.");
  }

  const selectedItems = checkout.products.filter((p) =>
    selectedIds.includes(parseInt(p.id, 10)),
  );
  const selectedTotal = selectedItems.reduce(
    (sum, p) => sum + (parseFloat(p.sumRaw) || 0),
    0,
  );

  openCartConfirmModal(selectedItems, selectedTotal);
}

document.addEventListener("DOMContentLoaded", setupCartEvents);
