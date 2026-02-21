# KasirKu Detailed API Documentation - All Endpoints

Comprehensive guide for frontend developers covering all V1 endpoints, payloads, and response structures.

## Base URL
`{{BACKEND_URL}}/api/v1`

## API Flows
*(See [detailed_api_docs.md](file:///d:/portofolio/KasirKu/backend_kasirku/docs/detailed_api_docs.md) for Flow diagrams)*

---

## 1. Authentication

### [POST] `/auth/register`
Register a new business (tenant) and an owner account.
**Payload:**
```json
{
  "tenant_name": "My Coffee Shop",
  "owner_name": "John Doe",
  "email": "owner@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "domain": "mycoffee" 
}
```
**Response (201 Created):**
```json
{
  "message": "Tenant registered successfully.",
  "data": {
    "tenant": { "id": "uuid", "name": "My Coffee Shop" },
    "user": { "id": "uuid", "name": "John Doe", "email": "owner@example.com" }
  }
}
```

### [POST] `/auth/login`
**Payload:**
```json
{
  "email": "owner@example.com",
  "password": "password123"
}
```
**Response (200 OK):**
```json
{
  "message": "Login successful.",
  "data": {
    "token": "JWT_TOKEN_HERE",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": { "id": "uuid", "name": "John Doe", "role": "owner" }
  }
}
```

---

## 2. Products

### [GET] `/products`
List all products. Supports filtering by category/search (via service).
**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Latte",
      "sku": "CF-LAT",
      "price": 25000,
      "stock": 100,
      "category": { "id": "uuid", "name": "Coffee" }
    }
  ]
}
```

### [POST] `/products`
**Payload:**
```json
{
  "category_id": "uuid",
  "name": "Latte",
  "sku": "CF-LAT",
  "price": 25000,
  "cost_price": 15000,
  "stock": 100,
  "min_stock": 10,
  "is_active": true
}
```

---

## 3. Categories

### [GET] `/categories`
**Response (200 OK):**
```json
{
  "success": true,
  "data": [{ "id": "uuid", "name": "Coffee", "slug": "coffee" }]
}
```

### [POST] `/categories`
**Payload:**
```json
{ "name": "Coffee" }
```

---

## 4. Customers

### [GET] `/customers`
**Pagination Parameters:** `page`, `per_page` (default 15).
**Response (200 OK):**
```json
{
  "data": [
    { "id": "uuid", "name": "Jane Smith", "email": "jane@example.com", "phone": "0812345" }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
```

### [POST] `/customers`
**Payload:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "0812345",
  "address": "Street A No. 1"
}
```

---

## 5. Transactions (POS)

### [POST] `/transactions`
Create a new sale.
**Payload:**
```json
{
  "items": [
    { "product_id": "uuid", "quantity": 1, "price": 25000 }
  ],
  "customer_id": "uuid (optional)",
  "discount": 5000,
  "paid_amount": 20000,
  "payment_method": "cash",
  "notes": "No sugar"
}
```
**Response (201 Created):**
```json
{
  "success": true,
  "message": "Transaction completed successfully.",
  "data": {
    "id": "uuid",
    "invoice_number": "INV-202310270001",
    "grand_total": 20000,
    "change_amount": 0,
    "items": [...]
  }
}
```

### [GET] `/transactions`
List transaction history.
**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    { "id": "uuid", "invoice_number": "INV-...", "grand_total": 20000, "status": "completed" }
  ],
  "meta": { "total": 100, "per_page": 15, ... }
}
```

---

## 6. Reports

### [GET] `/reports/daily?date=2023-10-27`
**Response (200 OK):**
```json
{
  "data": {
    "total_sales": 1500000,
    "transaction_count": 25,
    "average_transaction": 60000
  }
}
```

### [GET] `/reports/top-products`
**Response (200 OK):**
```json
{
  "data": [
    { "product_id": "uuid", "name": "Latte", "total_quantity": 45, "total_revenue": 1125000 }
  ]
}
```

### [GET] `/reports/export-csv?start_date=2023-10-01&end_date=2023-10-31`
**Response:** Binary File (CSV download).
