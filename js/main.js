// =====================================================================
// main.js - Shared logic for every PLAYBOX page (nav, auth, toast, api)
// =====================================================================

const API_BASE = "../php/";

// ---------------------------------------------------------------------
// Generic API helper (wraps fetch, always sends/receives JSON + cookies)
// ---------------------------------------------------------------------
async function api(endpoint, { method = "GET", params = null, body = null } = {}) {
  let url = API_BASE + endpoint;
  if (params) {
    const qs = new URLSearchParams(params).toString();
    url += (url.includes("?") ? "&" : "?") + qs;
  }
  const opts = {
    method,
    credentials: "same-origin",
    headers: {},
  };
  if (body) {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(url, opts);
  let data;
  try {
    data = await res.json();
  } catch (e) {
    data = { success: false, message: "Invalid server response" };
  }
  if (!res.ok && res.status === 401) {
    // Not logged in -> redirect to login
    window.location.href = "index.php";
    return data;
  }
  return data;
}

// ---------------------------------------------------------------------
// App-wide currency: fetch once, expose a shared formatMoney() helper
// ---------------------------------------------------------------------
const CURRENCY_SYMBOLS = { USD: "$", KHR: "៛", EUR: "€", GBP: "£" };
window.APP_CURRENCY_SYMBOL = "$";

async function loadAppCurrency() {
  try {
    const res = await api("settings.php");
    if (res.success && res.data && res.data.currency) {
      window.APP_CURRENCY_SYMBOL = CURRENCY_SYMBOLS[res.data.currency] || "$";
    }
  } catch (e) { /* keep default */ }
}

function formatMoney(amount) {
  const n = Number(amount) || 0;
  return window.APP_CURRENCY_SYMBOL + n.toFixed(2);
}

// ---------------------------------------------------------------------
// Shared HTML-escaping helper (used by every page-specific script)
// ---------------------------------------------------------------------
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

// ---------------------------------------------------------------------
// Toast notifications
// ---------------------------------------------------------------------
function showToast(message, type = "") {
  let toast = document.querySelector(".toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.className = "toast " + type;
  requestAnimationFrame(() => toast.classList.add("show"));
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove("show"), 3000);
}

// ---------------------------------------------------------------------
// Auth guard - every dashboard page calls this on load
// ---------------------------------------------------------------------
async function requireAuth() {
  const res = await api("login.php?action=me");
  if (!res.logged_in) {
    window.location.href = "index.php";
    return null;
  }
  const nameEl = document.querySelector(".user-name");
  const roleEl = document.querySelector(".user-role");
  const avatarEl = document.querySelector(".avatar");
  if (nameEl) nameEl.textContent = res.user.full_name;
  if (roleEl) roleEl.textContent = res.user.role;
  if (avatarEl) avatarEl.textContent = res.user.full_name.charAt(0).toUpperCase();
  return res.user;
}

// ---------------------------------------------------------------------
// Profile / Logout - clicking the avatar opens the profile page,
// which has its own Log Out button.
// ---------------------------------------------------------------------
function setupLogout() {
  const profile = document.querySelector(".user-profile");
  if (!profile) return;
  profile.style.cursor = "pointer";
  profile.title = "View profile";
  profile.addEventListener("click", () => {
    window.location.href = "profile.php";
  });
}

// ---------------------------------------------------------------------
// Notification bell
// ---------------------------------------------------------------------
async function loadNotificationBell() {
  const bell = document.querySelector(".notification-bell");
  if (!bell) return;
  try {
    const res = await api("notifications.php");
    const dot = bell.querySelector(".notif-dot");
    if (dot) dot.style.display = res.unread_count > 0 ? "block" : "none";
  } catch (e) { /* ignore */ }
  bell.addEventListener("click", () => {
    window.location.href = "notifications.php";
  });
}

// ---------------------------------------------------------------------
// Navigation hover / active link / sidebar toggle
// ---------------------------------------------------------------------
function setupNavigation() {
  const list = document.querySelectorAll(".navigation li");
  list.forEach((item) => {
    item.addEventListener("mouseover", function () {
      list.forEach((i) => i.classList.remove("hovered"));
      this.classList.add("hovered");
    });
  });

  let currentFile = window.location.pathname.split("/").pop().toLowerCase();
  if (currentFile === "") currentFile = "index.php";
  list.forEach((item) => {
    const link = item.querySelector("a");
    if (!link) return;
    const href = link.getAttribute("href");
    if (!href) return;
    const linkFile = href.split("/").pop().split("?")[0].toLowerCase();
    item.classList.remove("active-page");
    if (linkFile === currentFile) item.classList.add("active-page");
  });

  const toggle = document.querySelector(".toggle");
  const navigation = document.querySelector(".navigation");
  const main = document.querySelector(".main");
  const backdrop = document.getElementById("navBackdrop");
  if (!toggle || !navigation || !main) return;

  const isMobile = () => window.innerWidth <= 900;

  function openNav() {
    navigation.classList.add("active");
    main.classList.add("active");
    if (backdrop) backdrop.classList.add("active");
    if (!isMobile()) sessionStorage.setItem("navActive", "true");
  }
  function closeNav() {
    navigation.classList.remove("active");
    main.classList.remove("active");
    if (backdrop) backdrop.classList.remove("active");
    if (!isMobile()) sessionStorage.setItem("navActive", "false");
  }

  // Restore the collapsed/expanded state on desktop only - on a phone the
  // sidebar is an off-canvas drawer and should always start closed on a
  // fresh page load, never re-open itself just because it was left open
  // on a previous page.
  if (!isMobile() && sessionStorage.getItem("navActive") === "true") {
    navigation.classList.add("active");
    main.classList.add("active");
  }

  toggle.onclick = function () {
    navigation.classList.contains("active") ? closeNav() : openNav();
  };

  if (backdrop) backdrop.addEventListener("click", closeNav);
}

// ---------------------------------------------------------------------
// Search box -> quick search across toys/customers/suppliers
// ---------------------------------------------------------------------
function setupGlobalSearch() {
  const box = document.querySelector(".search-box");
  const input = document.querySelector(".search-box input");
  if (!input) return;

  // On narrow screens the box is icon-only until tapped (see CSS); this is
  // inert on desktop since the input is already visible there.
  if (box) {
    box.addEventListener("click", () => {
      if (!box.classList.contains("open")) {
        box.classList.add("open");
        input.focus();
      }
    });
    input.addEventListener("blur", () => {
      if (!input.value.trim()) box.classList.remove("open");
    });
  }

  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && input.value.trim()) {
      window.location.href = "toys.php?search=" + encodeURIComponent(input.value.trim());
    }
  });
}

// ---------------------------------------------------------------------
// Light / Dark mode toggle (topbar only - never touches the sidebar)
// ---------------------------------------------------------------------
function setupThemeToggle() {
  const btn = document.getElementById("themeToggle");
  const icon = document.getElementById("themeToggleIcon");
  if (!btn) return;

  function applyIcon() {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    if (icon) icon.setAttribute("name", isDark ? "sunny-outline" : "moon-outline");
  }
  applyIcon();

  btn.addEventListener("click", () => {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    if (isDark) {
      document.documentElement.removeAttribute("data-theme");
      localStorage.setItem("playbox-theme", "light");
    } else {
      document.documentElement.setAttribute("data-theme", "dark");
      localStorage.setItem("playbox-theme", "dark");
    }
    applyIcon();
  });
}

// ---------------------------------------------------------------------
// Modal helpers
// ---------------------------------------------------------------------
function openModal(id) {
  document.getElementById(id)?.classList.add("open");
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove("open");
}

// ---------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------
document.addEventListener("DOMContentLoaded", async () => {
  if (document.querySelector(".navigation")) {
    // Wire up the theme toggle first and synchronously - it must never wait
    // on network calls, or the icon stays wrong and the button stays dead
    // until requireAuth()/loadAppCurrency() come back.
    setupThemeToggle();
    await loadAppCurrency();
    await requireAuth();
    setupNavigation();
    setupLogout();
    loadNotificationBell();
    setupGlobalSearch();
  }
});
