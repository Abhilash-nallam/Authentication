<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use OtpAuth\AdminService;
use OtpAuth\Database;
$admin=(new AdminService(Database::connection()))->fromSession((string)($_COOKIE['otp_auth_admin']??''));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OTP Auth — Admin</title><link rel="stylesheet" href="/assets/app.css"></head><body><main class="container">
<header class="hero"><div><p class="eyebrow">OTP Auth</p><h1>Admin Dashboard B</h1><p>Private platform control center.</p></div><span class="badge">Admin</span></header>
<?php if (!$admin): ?>
<section class="card"><h2>Admin Login</h2><form id="login"><input name="email" type="email" placeholder="Admin email" required><input name="password" type="password" placeholder="Password" required><button>Sign in</button></form></section>
<?php else: ?>
<section class="grid"><article class="card"><h2>Overview</h2><pre id="overview">Loading…</pre></article><article class="card"><h2>Session</h2><p><?=htmlspecialchars($admin['email'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($admin['role_name'],ENT_QUOTES,'UTF-8')?></p><button id="logout">Log out</button></article></section>
<section class="card"><h2>Customers</h2><div id="customers">Loading…</div></section>
<section class="card"><h2>API Keys</h2><div id="keys">Loading…</div></section>
<section class="card"><h2>OTP Events</h2><div id="events">Loading…</div></section>
<?php endif; ?><pre id="status"></pre></main>
<script>
const out=x=>document.getElementById('status').textContent=typeof x==='string'?x:JSON.stringify(x,null,2);
async function post(path,data={}){const r=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const j=await r.json();if(!r.ok)throw j;return j}
<?php if (!$admin): ?>
document.getElementById('login').onsubmit=async e=>{e.preventDefault();try{await post('/api/v1/admin/login',Object.fromEntries(new FormData(e.target)));location.reload()}catch(x){out(x)}};
<?php else: ?>
async function load(){try{const o=await post('/api/v1/admin/overview');document.getElementById('overview').textContent=JSON.stringify(o.overview,null,2);const c=await post('/api/v1/admin/customers');document.getElementById('customers').innerHTML=(c.customers||[]).map(x=>`<div><strong>${x.email}</strong> — ${x.status} <button onclick="setCustomer(${x.id},'${x.status==='suspended'?'active':'suspended'}')">${x.status==='suspended'?'Reactivate':'Suspend'}</button></div>`).join('')||'No customers.';const k=await post('/api/v1/admin/api-keys');document.getElementById('keys').innerHTML=(k.api_keys||[]).map(x=>`<div>${x.key_prefix} — ${x.customer_email||''} — ${x.status} ${x.status!=='revoked'?`<button onclick="revokeKey(${x.id})">Revoke</button>`:''}</div>`).join('')||'No keys.';const e=await post('/api/v1/admin/otp-logs',{limit:50});document.getElementById('events').textContent=JSON.stringify(e.events,null,2)}catch(x){out(x)}}
async function setCustomer(id,status){try{await post('/api/v1/admin/'+(status==='suspended'?'customer-suspend':'customer-reactivate'),{customer_id:id});load()}catch(x){out(x)}}
async function revokeKey(id){try{await post('/api/v1/admin/key-revoke',{key_id:id,reason:'Revoked by administrator'});load()}catch(x){out(x)}}
document.getElementById('logout').onclick=async()=>{await post('/api/v1/admin/logout');location.reload()};load();
<?php endif; ?>
</script></body></html>
