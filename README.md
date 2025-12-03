# Laravel Flash-Sale Checkout API

**Project:** Flash-Sale Checkout (Concurrency & Correctness)  

---

## Summary

This API implements a flash-sale checkout system for a single limited-stock product.  
It handles high concurrency without overselling, supports short-lived holds, checkout, and an **idempotent payment webhook**.  
No frontend/UI is included; this is API-only.

---

## Key Concepts

- **Prevent Overselling:** Use `DB::transaction()` with `Product::lockForUpdate()` to ensure stock never drops below zero under concurrent requests.  
- **Holds / Reservations:**  
  - `POST /api/holds { product_id, qty }` creates a short-lived (~2 min) hold.  
  - Holds immediately reduce availability for other customers.  
  - Expired holds automatically release stock.  
- **Order Creation:**  
  - `POST /api/orders { hold_id }` converts a valid, unexpired hold into a pre-payment order.  
  - Each hold can only be used once.  
- **Payment Webhook:**  
  - `POST /api/payments/webhook` with an **idempotency key** updates order state to `paid` or `cancelled`.  
  - Webhook handling is **idempotent** and **out-of-order safe**.  
  - Ensures correct final state even if the webhook arrives multiple times or before order creation.  
- **Caching & Performance:**  
  - Short-term cache for availability to speed up reads under burst traffic.  
  - Avoids N+1 queries on listing endpoints.
  - 
  - ### Logging / Metrics
  All logs and metrics are tracked using **Laravel’s built-in logging system** (`storage/logs/laravel.log`).  
  Structured logging is used to monitor:  
- Stock contention and retries  
- Hold creation and expiry  
- Payment webhook processing and deduplication


---

## Factories / Seeders

 - **ProductSeeder**: Seeds 1 product with finite stock and price.  
 - **HoldFactory**: Generates hold instances for testing.  
 - **OrderFactory**: Generates orders linked to valid holds.
 - **ProductFactory:** Generates products with random name, price, and stock level.  
 - **WebhookTransactionFactory:** Generates webhook transaction records for testing payment handling.  


---

## API Endpoints

### 1. Product
### 2. Create Hold
### 3. Create Order
### 4. Payment Webhook


- In Payment Webhook `status` can be `success` or `failure`.  
- Idempotency ensures repeated webhook calls do **not** change stock/order incorrectly.

---

## Tests

- **Concurrency / Stock Boundary Test:** Multiple holds attempted in parallel; ensures no overselling.  
- **Hold Expiry Test:** Expired holds automatically return stock.  
- **Webhook Idempotency Test:** Same idempotency key repeated does not double-update order.  
- **Out-of-Order Webhook Test:** Webhook before order creation still resolves to correct final state.

---

## Setup / Installation

```bash
# Clone repository
git clone [your-repo-url]
cd flash-sale

# Install dependencies
composer install

# Copy env file and generate key
cp .env.example .env
php artisan key:generate

# Configure .env for database and cache

# Run migrations and seed initial data
php artisan migrate --seed

# Run project
php artisan serve

# Run tests
php artisan test


