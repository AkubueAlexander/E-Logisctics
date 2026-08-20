# E-Logisctics — Multi-Role Quick-Commerce & Delivery Platform

A production-style backend for a quick-commerce marketplace built with **Laravel 13**. The system supports four distinct user roles — **Customer**, **Driver**, **Store Manager**, and **Admin** — each with their own scoped API, and handles the full order lifecycle from store discovery to payment to delivery.

Architected real-world domain logic like role-based access, payment webhooks, wallet systems, and delivery state machines.

##  What It Does

- **Customers** discover nearby stores, browse menus, manage a cart, check out, pay via Flutterwave, track orders, and rate/dispute completed orders.
- **Drivers** accept or reject delivery pings, track mission itineraries, update live location, confirm pickup/arrival/delivery, and manage their wallet and withdrawals.
- **Store Managers** manage store profiles, product catalogs (with modifiers), staff invitations, sub-order acceptance/cancellation, and view store finance summaries.
- **Admins** manage global product category taxonomy across the platform.

## 🛠️ Tech Stack

- **Framework:** Laravel 13, PHP 8.3
- **Auth:** Laravel Sanctum (API tokens), Laravel Jetstream, two-factor authentication via Google2FA
- **Cache/Queue:** Redis (Predis)
- **Payments:** Flutterwave — including payment initialization, redirect verification, and webhook handling
- **Testing:** Pest PHP
- **Other:** OTP-based registration/password recovery, QR code generation (bacon/bacon-qr-code)

## 🧩 Key Architecture Decisions

- **Role-based route grouping** — API routes are cleanly segmented by role middleware (`role:driver`, `role:store_manager`, `role:admin`, `role:customer`), keeping authorization logic out of controllers.
- **Payment webhook handling** — a single, idempotent Flutterwave webhook entry point handles asynchronous payment confirmation, decoupled from the browser-redirect verification flow.
- **Wallet & withdrawal system** — drivers and stores each have wallet summaries and withdrawal flows tied to bank onboarding.
- **Order state machine** — orders move through acceptance, pickup, arrival, delivery, and exception states (e.g. no-show handling), modeled as discrete controller actions rather than a single bloated controller.
- **Geospatial discovery** — customers query nearby stores by location, with cached storefront menu responses for performance.

## 📡 Sample API Routes

```
POST   /api/v1/auth/register
POST   /api/v1/auth/verify-otp
POST   /api/v1/auth/login
GET    /api/v1/customer/stores/nearby
GET    /api/v1/customer/stores/{store}/menu
POST   /api/v1/customer/checkout
POST   /api/v1/payments/flutterwave/webhook
POST   /api/v1/driver/pings/{ping}/accept
POST   /api/v1/driver/sub-orders/{subOrder}/pickup
PATCH  /api/v1/store/sub-orders/{subOrder}/accept
GET    /api/v1/store/{store}/finance/summary
```

## ⚙️ Getting Started

```bash
git clone https://github.com/AkubueAlexander/E-Logisctics.git
cd E-Logisctics
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

## 📌 Project Status

This is an actively developed personal project used to demonstrate backend architecture, API design, and payment integration skills. Not currently deployed to a public demo — happy to walk through the code or run it locally on request.

## 👤 About Me

Built by Akubue Alexander — Laravel developer based in Lagos, Nigeria.
[GitHub](https://github.com/AkubueAlexander) · Open to backend/full-stack Laravel roles.
