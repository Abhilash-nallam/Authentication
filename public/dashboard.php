<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OTP Auth Dashboard</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="container">
    <header class="hero">
      <div>
        <p class="eyebrow">OTP Auth</p>
        <h1>Email OTP Verification Infrastructure</h1>
        <p>Phase 1 production-test dashboard.</p>
      </div>
      <span class="badge">Production Test</span>
    </header>

    <section class="grid">
      <article class="card"><h2>Core OTP</h2><p>Generate, expire, resend and verify email OTPs.</p></article>
      <article class="card"><h2>Amazon SES</h2><p>Transactional delivery through the AWS SDK for PHP.</p></article>
      <article class="card"><h2>API Security</h2><p>Bearer API keys, rate limiting and hashed OTP storage.</p></article>
      <article class="card"><h2>Coming Soon</h2><p>Phase 2 and Phase 3 capabilities remain intentionally open.</p></article>
    </section>

    <section class="card">
      <h2>Test API</h2>
      <p>Use an API key and the endpoints below from your test application.</p>
      <pre>POST /api/v1/otp/request
POST /api/v1/otp/verify
POST /api/v1/otp/resend
GET  /health</pre>
    </section>
  </main>
</body>
</html>
