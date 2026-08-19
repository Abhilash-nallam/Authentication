const pages = ["overview","otp","email","api","customers","logs","security","settings"];
let activity = JSON.parse(localStorage.getItem("otpAuthActivity") || "[]");
let customers = JSON.parse(localStorage.getItem("otpAuthCustomers") || "null") || [
  {name:"Demo Store", email:"admin@demostore.local", status:"Active", created:"Today"},
  {name:"SlotCare Demo", email:"support@slotcare.local", status:"Active", created:"Today"}
];
let requests = Number(localStorage.getItem("otpAuthRequests") || 0);
let verified = Number(localStorage.getItem("otpAuthVerified") || 0);

const $ = (id) => document.getElementById(id);

function toast(text, type="info") {
  const el = $("toast");
  el.textContent = text;
  el.className = `toast show ${type}`;
  setTimeout(() => el.className = "toast", 2800);
}

function addActivity(type, message) {
  activity.unshift({
    time: new Date().toLocaleString(),
    type,
    message
  });
  activity = activity.slice(0, 50);
  localStorage.setItem("otpAuthActivity", JSON.stringify(activity));
  renderActivity();
  renderLogs();
}

function navigate(page) {
  if (!pages.includes(page)) page = "overview";
  document.querySelectorAll(".page").forEach(p => p.classList.remove("active"));
  document.querySelector(`#page-${page}`).classList.add("active");
  document.querySelectorAll(".nav-item").forEach(b => b.classList.toggle("active", b.dataset.page === page));
  const label = document.querySelector(`.nav-item[data-page="${page}"]`);
  $("pageTitle").textContent = label ? label.textContent.trim() : "Overview";
  history.replaceState(null, "", `#${page}`);
  $("sidebar").classList.remove("open");
  if (page === "customers") renderCustomers();
  if (page === "logs") renderLogs();
  updateStats();
}

document.addEventListener("click", (e) => {
  const nav = e.target.closest("[data-page]");
  const link = e.target.closest("[data-page-link]");
  if (nav) navigate(nav.dataset.page);
  if (link) navigate(link.dataset.pageLink);
});

function updateStats() {
  $("statRequests").textContent = requests;
  $("statVerified").textContent = verified;
  $("statDelivery").textContent = requests ? "Tested" : "—";
}

function renderActivity() {
  const el = $("activityTable");
  if (!activity.length) {
    el.innerHTML = `<div class="empty">No activity yet. Run an OTP test to create events.</div>`;
    return;
  }
  el.innerHTML = `<table><thead><tr><th>Time</th><th>Type</th><th>Event</th></tr></thead><tbody>${
    activity.slice(0,8).map(a => `<tr><td>${escapeHtml(a.time)}</td><td><span class="tag ${a.type.toLowerCase()}">${escapeHtml(a.type)}</span></td><td>${escapeHtml(a.message)}</td></tr>`).join("")
  }</tbody></table>`;
}

function renderLogs() {
  const el = $("fullLogs");
  if (!activity.length) {
    el.innerHTML = `<div class="empty">No logs recorded.</div>`;
    return;
  }
  el.innerHTML = activity.map(a => `
    <div class="log-row">
      <span class="log-time">${escapeHtml(a.time)}</span>
      <span class="tag ${a.type.toLowerCase()}">${escapeHtml(a.type)}</span>
      <span>${escapeHtml(a.message)}</span>
    </div>`).join("");
}

function renderCustomers() {
  const q = ($("customerSearch")?.value || "").toLowerCase();
  const filtered = customers.filter(c => `${c.name} ${c.email} ${c.status}`.toLowerCase().includes(q));
  $("customerTable").innerHTML = filtered.length ? `<table><thead><tr><th>Customer</th><th>Email</th><th>Status</th><th>Created</th></tr></thead><tbody>${
    filtered.map(c => `<tr><td><b>${escapeHtml(c.name)}</b></td><td>${escapeHtml(c.email)}</td><td><span class="tag success">${escapeHtml(c.status)}</span></td><td>${escapeHtml(c.created)}</td></tr>`).join("")
  }</tbody></table>` : `<div class="empty">No customers found.</div>`;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

async function checkHealth() {
  $("healthStatus").textContent = "Checking…";
  $("statApi").textContent = "Checking…";
  try {
    const r = await fetch("/health", {cache:"no-store"});
    const data = await r.json();
    const ok = r.ok && data.success;
    $("healthStatus").textContent = ok ? "Online" : "Error";
    $("statApi").textContent = ok ? "Online" : "Error";
    $("statApi").className = ok ? "online" : "offline";
    if (ok) addActivity("INFO", "Local OTP API health check passed.");
  } catch {
    $("healthStatus").textContent = "Offline";
    $("statApi").textContent = "Offline";
    $("statApi").className = "offline";
    addActivity("ERROR", "Local OTP API health check failed.");
  }
}

function authHeaders() {
  const apiKey = $("otpApiKey").value.trim();
  if (apiKey) localStorage.setItem("otpAuthApiKey", apiKey);
  return {"Content-Type":"application/json", "Authorization":`Bearer ${apiKey}`};
}

async function sendOtp(action="request") {
  const apiKey = $("otpApiKey").value.trim();
  if (!apiKey) {
    showOtpMessage("Paste an API key before sending an OTP.", "error");
    return;
  }
  const email = $("otpEmail").value.trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showOtpMessage("Enter a valid email address.", "error");
    return;
  }
  const btn = $("sendOtpBtn");
  btn.disabled = true;
  btn.textContent = "Sending…";
  try {
    const r = await fetch(`/api/v1/otp/${action}`, {
      method:"POST",
      headers:authHeaders(),
      body:JSON.stringify({email, purpose:"registration"})
    });
    const data = await r.json();
    requests++;
    localStorage.setItem("otpAuthRequests", requests);
    if (!r.ok || !data.success) {
      showOtpMessage(data.error || data.message || "Unable to send OTP.", "error");
      addActivity("ERROR", `OTP send failed for ${email}: ${data.error || data.message || "unknown error"}`);
      return;
    }
    showOtpMessage(data.message || "OTP sent. Check your email.", "success");
    addActivity("SEND", `OTP sent to ${email}.`);
    $("otpCode").focus();
    updateStats();
  } catch {
    showOtpMessage("Cannot connect to the PHP API server.", "error");
    addActivity("ERROR", "OTP send request could not reach the server.");
  } finally {
    btn.disabled = false;
    btn.textContent = "Send OTP";
    updateStats();
  }
}

async function verifyOtp() {
  const apiKey = $("otpApiKey").value.trim();
  if (!apiKey) return showOtpMessage("Paste an API key before verifying an OTP.", "error");
  const email = $("otpEmail").value.trim().toLowerCase();
  const otp = $("otpCode").value.trim();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showOtpMessage("Enter a valid email address.", "error");
  if (!/^\d{6}$/.test(otp)) return showOtpMessage("Enter the 6-digit OTP.", "error");
  const btn = $("verifyOtpBtn");
  btn.disabled = true;
  btn.textContent = "Verifying…";
  try {
    const r = await fetch("/api/v1/otp/verify", {
      method:"POST",
      headers:authHeaders(),
      body:JSON.stringify({email, purpose:"registration", otp})
    });
    const data = await r.json();
    if (!r.ok || !data.success) {
      showOtpMessage(data.error || data.message || "Invalid OTP.", "error");
      addActivity("ERROR", `OTP verification failed for ${email}.`);
      return;
    }
    verified++;
    localStorage.setItem("otpAuthVerified", verified);
    showOtpMessage(data.message || "Email verified successfully.", "success");
    addActivity("VERIFY", `Email verified successfully: ${email}.`);
    updateStats();
  } catch {
    showOtpMessage("Cannot connect to the PHP API server.", "error");
  } finally {
    btn.disabled = false;
    btn.textContent = "Verify OTP";
  }
}

function showOtpMessage(text, type) {
  const el = $("otpMessage");
  el.textContent = text;
  el.className = `notice ${type}`;
}

function addCustomer() {
  const name = prompt("Customer name:");
  if (!name) return;
  const email = prompt("Customer email:");
  if (!email) return;
  customers.push({name, email, status:"Active", created:"Today"});
  localStorage.setItem("otpAuthCustomers", JSON.stringify(customers));
  renderCustomers();
  addActivity("INFO", `Demo customer created: ${name}.`);
  toast("Demo customer added.", "success");
}

function clearLogs() {
  activity = [];
  localStorage.setItem("otpAuthActivity", JSON.stringify(activity));
  renderActivity();
  renderLogs();
  toast("Local logs cleared.");
}

function applyTheme(theme) {
  document.body.classList.toggle("dark", theme === "dark");
  localStorage.setItem("otpAuthTheme", theme);
}

$("sendOtpBtn").addEventListener("click", () => sendOtp());
$("verifyOtpBtn").addEventListener("click", verifyOtp);
$("resendOtpBtn").addEventListener("click", () => sendOtp("resend"));
$("healthCheckBtn").addEventListener("click", checkHealth);
$("apiHealthBtn").addEventListener("click", checkHealth);
$("refreshBtn").addEventListener("click", () => { checkHealth(); toast("Dashboard refreshed."); });
$("addCustomerBtn").addEventListener("click", addCustomer);
$("clearLogsBtn").addEventListener("click", clearLogs);
$("clearLogsBtn2").addEventListener("click", clearLogs);
$("customerSearch").addEventListener("input", renderCustomers);
$("mobileMenu").addEventListener("click", () => $("sidebar").classList.toggle("open"));
$("themeSelect").addEventListener("change", e => applyTheme(e.target.value));

$("otpCode").addEventListener("input", e => e.target.value = e.target.value.replace(/\D/g,"").slice(0,6));
$("otpApiKey").value = localStorage.getItem("otpAuthApiKey") || "";

$("copyDemoKey").addEventListener("click", async () => {
  try {
    await navigator.clipboard.writeText("oa_demo_local_only");
    toast("Demo key copied.");
  } catch {
    toast("Clipboard unavailable.");
  }
});

const savedTheme = localStorage.getItem("otpAuthTheme") || "light";
$("themeSelect").value = savedTheme;
applyTheme(savedTheme);

const initial = location.hash.replace("#","");
navigate(pages.includes(initial) ? initial : "overview");
renderActivity();
renderLogs();
renderCustomers();
updateStats();
checkHealth();
