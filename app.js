const cartList = document.getElementById("cart-list");
const cartTotal = document.getElementById("cart-total");
const scanForm = document.getElementById("scan-form");
const modal = document.getElementById("modal");
const addProductButton = document.getElementById("add-product");
const closeModalButton = document.getElementById("close-modal");
const productForm = document.getElementById("product-form");

const inventory = {
  7798108840007: { name: "Yerba orgánica 500g", price: 2300 },
  7791340076049: { name: "Galletas integrales", price: 1150 },
  7790742302201: { name: "Leche de almendras", price: 1980 },
};

const cartItems = [];

const formatMoney = (value) =>
  new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  }).format(value);

const renderCart = () => {
  cartList.innerHTML = "";

  if (!cartItems.length) {
    const empty = document.createElement("div");
    empty.className = "cart__item cart__item--empty";
    empty.innerHTML = "<p>Esperando lectura de código o carga manual.</p>";
    cartList.appendChild(empty);
  } else {
    cartItems.forEach((item) => {
      const row = document.createElement("div");
      row.className = "cart__item";
      row.innerHTML = `
        <div>
          <strong>${item.name}</strong>
          <p>${item.code}</p>
        </div>
        <span>${item.qty} u.</span>
        <strong>${formatMoney(item.total)}</strong>
      `;
      cartList.appendChild(row);
    });
  }

  const total = cartItems.reduce((sum, item) => sum + item.total, 0);
  cartTotal.textContent = formatMoney(total);
};

const addItem = (code) => {
  const info = inventory[code] || {
    name: "Producto manual",
    price: 1000,
  };

  const existing = cartItems.find((item) => item.code === code);
  if (existing) {
    existing.qty += 1;
    existing.total = existing.qty * existing.price;
  } else {
    cartItems.unshift({
      code,
      name: info.name,
      price: info.price,
      qty: 1,
      total: info.price,
    });
  }

  renderCart();
};

scanForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const formData = new FormData(scanForm);
  const code = formData.get("barcode").trim();

  if (!code) {
    return;
  }

  addItem(code);
  scanForm.reset();
});

const openModal = () => modal.classList.add("modal--open");
const closeModal = () => modal.classList.remove("modal--open");

addProductButton.addEventListener("click", openModal);
closeModalButton.addEventListener("click", closeModal);
modal.addEventListener("click", (event) => {
  if (event.target === modal) {
    closeModal();
  }
});

productForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const formData = new FormData(productForm);
  const code = formData.get("code").trim();
  const name = formData.get("name").trim();
  const price = Number(formData.get("price"));

  if (!code || !name || Number.isNaN(price)) {
    return;
  }

  inventory[code] = { name, price };
  closeModal();
  productForm.reset();
});

renderCart();