<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use OtpAuth\CustomerService;
use OtpAuth\Database;
$customer=(new CustomerService(Database::connection()))->customerFromSession((string)($_COOKIE['otp_auth_session']??''));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OTP Auth Dashboard</title><link rel="stylesheet" href="/assets/app.css"></head><body><main class="container">
<header class="hero"><div><p class="eyebrow">OTP Auth</p><h1>Customer Dashboard</h1><p>Projects, domain verification, API keys and OTP infrastructure.</p></div><span class="badge">Secure Session</span></header>
<?php if (!$customer): ?>
<section class="grid"><article class="card"><h2>Register</h2><form id="register"><input name="email" type="email" placeholder="Email" required><input name="password" type="password" minlength="10" placeholder="Password (10+ chars)" required><button>Create account</button></form></article><article class="card"><h2>Login</h2><form id="login"><input name="email" type="email" placeholder="Email" required><input name="password" type="password" placeholder="Password" required><button>Login</button></form></article></section>
<?php else: ?>
<section class="card"><h2>Welcome</h2><p><?=htmlspecialchars($customer['email'],ENT_QUOTES,'UTF-8')?></p><button id="logout">Log out</button></section><section class="card"><h2>Create project</h2><form id="project"><input name="name" placeholder="Project name" required><button>Create project</button></form></section><section class="card"><h2>Projects</h2><div id="projects">Loading…</div></section>
<?php endif; ?><pre id="status"></pre></main>
<script>
const out=x=>document.getElementById('status').textContent=typeof x==='string'?x:JSON.stringify(x,null,2);
const csrf=()=>decodeURIComponent(document.cookie.split('; ').find(x=>x.startsWith('otp_auth_csrf='))?.split('=')[1]||'');
async function post(path,data){const h={'Content-Type':'application/json'},c=csrf();if(c)h['X-CSRF-Token']=c;const r=await fetch(path,{method:'POST',headers:h,body:JSON.stringify(data)}),j=await r.json();if(!r.ok)throw j;return j}
<?php if (!$customer): ?>
document.getElementById('register').onsubmit=async e=>{e.preventDefault();try{out(await post('/api/v1/customer/register',Object.fromEntries(new FormData(e.target))))}catch(x){out(x)}};
document.getElementById('login').onsubmit=async e=>{e.preventDefault();try{out(await post('/api/v1/customer/login',Object.fromEntries(new FormData(e.target))));location.reload()}catch(x){out(x)}};
<?php else: ?>
document.getElementById('logout').onclick=async()=>{try{await post('/api/v1/customer/logout',{});location.reload()}catch(x){out(x)}};
document.getElementById('project').onsubmit=async e=>{e.preventDefault();try{out(await post('/api/v1/customer/project-create',Object.fromEntries(new FormData(e.target))));load()}catch(x){out(x)}};
async function load(){try{const j=await post('/api/v1/customer/projects',{});document.getElementById('projects').innerHTML=(j.projects||[]).map(p=>`<p><strong>${p.name}</strong> — ${p.status} ${p.otp_subdomain||''}</p>`).join('')||'No projects yet.'}catch(x){out(x)}}load();
<?php endif; ?>
</script></body></html>
