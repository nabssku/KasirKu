# KasirKu API Documentation - V1

All API endpoints are prefixed with `/api/v1`.

## Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register a new tenant/user |
| POST | `/auth/login` | Login and get JWT token |
| POST | `/auth/logout` | Logout (Protected) |
| POST | `/auth/refresh` | Refresh JWT token (Protected) |
| GET | `/auth/me` | Get current user info (Protected) |

## Products
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/products` | List all products |
| POST | `/products` | Create a new product |
| GET | `/products/{id}` | Get product details |
| PUT | `/products/{id}` | Update product |
| DELETE | `/products/{id}` | Delete product |

## Categories
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/categories` | List all categories |
| POST | `/categories` | Create a new category |
| GET | `/categories/{id}` | Get category details |
| PUT | `/categories/{id}` | Update category |
| DELETE | `/categories/{id}` | Delete category |

## Customers
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/customers` | List all customers |
| POST | `/customers` | Create a new customer |
| GET | `/customers/{id}` | Get customer details |
| PUT | `/customers/{id}` | Update customer |
| DELETE | `/customers/{id}` | Delete customer |

## Transactions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/transactions` | List all transactions |
| POST | `/transactions` | Create a new transaction |
| GET | `/transactions/{id}` | Get transaction details |
| DELETE | `/transactions/{id}` | Soft-delete a transaction |

## Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reports/daily` | Get daily sales report |
| GET | `/reports/monthly` | Get monthly revenue report |
| GET | `/reports/top-products` | Get top selling products |
| GET | `/reports/export-csv` | Export transactions to CSV |
