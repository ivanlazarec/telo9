const cartList = document.getElementById("cart-list");
const cartTotal = document.getElementById("cart-total");
const scanForm = document.getElementById("scan-form");
const scanFeedback = document.getElementById("scan-feedback");
const clearCartButton = document.getElementById("clear-cart");
const modal = document.getElementById("modal");
const modalTitle = document.getElementById("modal-title");
const modalSubmit = document.getElementById("modal-submit");
const closeModalButton = document.getElementById("close-modal");
const productForm = document.getElementById("product-form");
const productCode = document.getElementById("product-code");
const productName = document.getElementById("product-name");
const productStock = document.getElementById("product-stock");
const productPrice = document.getElementById("product-price");
const inventoryRows = document.getElementById("inventory-rows");
const inventoryEmpty = document.getElementById("inventory-empty");
const inventorySearch = document.getElementById("inventory-search");
const filterLowButton = document.getElementById("filter-low");
const filterResetButton = document.getElementById("filter-reset");
const alertsList = document.getElementById("alerts-list");
const alertsEmpty = document.getElementById("alerts-empty");
const statProducts = document.getElementById("stat-products");
const statUnits = document.getElementById("stat-units");
const statValue = document.getElementById("stat-value");
const statAlerts = document.getElementById("stat-alerts");
const tabLinks = document.querySelectorAll("[data-tab-target]");
const tabPanels = document.querySelectorAll(".tab-panel");
const addProductButtons = document.querySelectorAll(".js-add-product");

const STORAGE_KEY = "inventory-database-v1";
const LOW_STOCK = 20;
const CRITICAL_STOCK = 5;

let inventory = [];
let cartItems = [];
let searchTerm = "";
let filterLowOnly = false;
let editingCode = null;

const formatMoney = (value) =>
  new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  }).format(value);

const loadInventory = () => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    const parsed = stored ? JSON.parse(stored) : [];
    if (Array.isArray(parsed)) {
      return parsed;
    }
  } catch (error) {
    console.error("No se pudo leer la base de datos local", error);
  }
  return [];
};

const saveInventory = (items) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
};

const sortInventory = () => {
  inventory.sort((a, b) => a.name.localeCompare(b.name, "es"));
};

const resetFeedback = () => {
  scanFeedback.textContent = "";
  scanFeedback.classList.remove("scan__feedback--error");
};

const showFeedback = (message) => {
  scanFeedback.textContent = message;
  scanFeedback.classList.add("scan__feedback--error");
};

const getStockBadge = (stock) => {
  if (stock <= CRITICAL_STOCK) {
    return { label: "Crítico", className: "badge--danger" };
  }
  if (stock <= LOW_STOCK) {
    return { label: "Bajo", className: "badge--warn" };
  }
  return { label: "Ok", className: "badge--ok" };
};

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
  const info = inventory.find((item) => item.code === code);

  if (!info) {
    showFeedback("Código no registrado. Agregalo desde \"Nuevo producto\".");
    return;
  }

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
resetFeedback();
  renderCart();
};

const renderInventoryTable = () => {
  inventoryRows.innerHTML = "";
  const filtered = inventory.filter((item) => {
    const matchesSearch = [item.code, item.name]
      .join(" ")
      .toLowerCase()
      .includes(searchTerm);
    if (!matchesSearch) {
      return false;
    }
    if (filterLowOnly) {
      return item.stock <= LOW_STOCK;
    }
    return true;
  });

  if (!filtered.length) {
    inventoryEmpty.hidden = false;
    return;
  }

  inventoryEmpty.hidden = true;

  filtered.forEach((item) => {
    const row = document.createElement("div");
    row.className = "table__row";
    const badge = getStockBadge(item.stock);
    row.innerHTML = `
      <span>${item.code}</span>
      <span>${item.name}</span>
      <span class="badge ${badge.className}">${item.stock} u.</span>
      <span>${formatMoney(item.price)}</span>
      <button class="btn btn--ghost" type="button" data-action="edit" data-code="${
        item.code
      }">Editar</button>
    `;
    inventoryRows.appendChild(row);
  });
};

const renderAlerts = () => {
  alertsList.innerHTML = "";
  const alerts = inventory.filter((item) => item.stock <= LOW_STOCK);

  if (!alerts.length) {
    alertsEmpty.hidden = false;
    return;
  }

  alertsEmpty.hidden = true;

  alerts.forEach((item) => {
    const li = document.createElement("li");
    const badge = getStockBadge(item.stock);
    const title = item.stock <= CRITICAL_STOCK ? "Stock crítico" : "Stock bajo";
    const label = badge.className.includes("danger") ? "Urgente" : "Próximo";
    li.innerHTML = `
      <div>
        <h4>${title}</h4>
        <p>${item.name} (${item.stock} u.)</p>
      </div>
      <span class="badge ${badge.className}">${label}</span>
    `;
    alertsList.appendChild(li);
  });
};

const renderStats = () => {
  const totalProducts = inventory.length;
  const totalUnits = inventory.reduce((sum, item) => sum + item.stock, 0);
  const totalValue = inventory.reduce((sum, item) => sum + item.stock * item.price, 0);
  const totalAlerts = inventory.filter((item) => item.stock <= LOW_STOCK).length;

  statProducts.textContent = totalProducts.toString();
  statUnits.textContent = totalUnits.toString();
  statValue.textContent = formatMoney(totalValue);
  statAlerts.textContent = totalAlerts.toString();
};

const renderAll = () => {
  renderCart();
  renderInventoryTable();
  renderAlerts();
  renderStats();
};

const openModal = (product = null) => {
  if (product) {
    modalTitle.textContent = "Editar producto";
    modalSubmit.textContent = "Actualizar";
    editingCode = product.code;
    productCode.value = product.code;
    productName.value = product.name;
    productStock.value = product.stock;
    productPrice.value = product.price;
  } else {
    modalTitle.textContent = "Nuevo producto";
    modalSubmit.textContent = "Guardar";
    editingCode = null;
    productForm.reset();
  }
  modal.classList.add("modal--open");
};

const closeModal = () => {
  modal.classList.remove("modal--open");
  editingCode = null;
  productForm.reset();
};

const updateInventory = (updatedItem) => {
  if (editingCode) {
    inventory = inventory.filter((item) => item.code !== editingCode);
  }

  const existingIndex = inventory.findIndex((item) => item.code === updatedItem.code);
  if (existingIndex >= 0) {
    inventory.splice(existingIndex, 1, updatedItem);
  } else {
    inventory.push(updatedItem);
  }

  sortInventory();
  saveInventory(inventory);
};

const setActiveTab = (tabId) => {
  tabPanels.forEach((panel) => {
    panel.classList.toggle("tab-panel--active", panel.id === tabId);
  });
  tabLinks.forEach((link) => {
    const target = link.getAttribute("href").replace("#", "");
    link.classList.toggle("nav__link--active", target === tabId);
  });
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

clearCartButton.addEventListener("click", () => {
  cartItems = [];
  renderCart();
});

addProductButtons.forEach((button) => {
  button.addEventListener("click", () => openModal());
});

closeModalButton.addEventListener("click", closeModal);
modal.addEventListener("click", (event) => {
  if (event.target === modal) {
    closeModal();
  }
});

productForm.addEventListener("submit", (event) => {
  event.preventDefault();
const code = productCode.value.trim();
  const name = productName.value.trim();
  const stock = Number(productStock.value);
  const price = Number(productPrice.value);
  
  if (!code || !name || Number.isNaN(stock) || Number.isNaN(price)) {
    return;
  }

 updateInventory({ code, name, stock, price });
  closeModal();
 renderAll();
});

inventoryRows.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-action='edit']");
  if (!button) {
    return;
  }
  const code = button.dataset.code;
  const product = inventory.find((item) => item.code === code);
  if (product) {
    openModal(product);
  }
});

inventorySearch.addEventListener("input", (event) => {
  searchTerm = event.target.value.trim().toLowerCase();
  renderInventoryTable();
});

filterLowButton.addEventListener("click", () => {
  filterLowOnly = true;
  filterLowButton.classList.add("btn--active");
  filterResetButton.classList.remove("btn--active");
  renderInventoryTable();
});

filterResetButton.addEventListener("click", () => {
  filterLowOnly = false;
  filterLowButton.classList.remove("btn--active");
  filterResetButton.classList.add("btn--active");
  renderInventoryTable();
});

filterResetButton.classList.add("btn--active");

tabLinks.forEach((link) => {
  link.addEventListener("click", (event) => {
    event.preventDefault();
    const target = link.getAttribute("href").replace("#", "");
    setActiveTab(target);
    history.replaceState(null, "", `#${target}`);
  });
});

window.addEventListener("hashchange", () => {
  const hash = window.location.hash.replace("#", "");
  if (hash) {
    setActiveTab(hash);
  }
});

inventory = loadInventory();
if (window.location.hash) {
  setActiveTab(window.location.hash.replace("#", ""));
}
renderAll();