# OTP-AUTH Demo Dashboard

This replacement adds a multi-page-feeling dashboard as a single-page local UI.

## Replace
Copy these three files into your existing project:

public/index.html
public/script.js
public/style.css

Do NOT replace server.js or .env.

## Run
From the project root:

npm start

Open:
http://localhost:3000

## Pages in the dashboard
- Overview
- OTP Verification
- Email
- API & Keys
- Customers
- Logs
- Security
- Settings

The OTP page uses the existing backend endpoints:
GET /health
POST /send-otp
POST /verify-otp

Customers, logs, and the API key shown in the dashboard are demo/local-browser UI data only.
