# EVENTURA ORGANIZER ADMIN PANEL - API DOCUMENTATION

## 📋 Table of Contents
- [Overview](#overview)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Installation](#installation)
- [Error Handling](#error-handling)
- [Response Format](#response-format)

## 🎯 Overview

Complete RESTful API for Eventura Organizer Admin Panel with JWT authentication, comprehensive CRUD operations, and analytics.

**Base URL:** `http://your-domain.com/api/`

## 🔐 Authentication

All protected endpoints require a JWT token in the Authorization header:

```
Authorization: Bearer {your-jwt-token}
```

### Auth Endpoints

#### 1. Register Organizer
```http
POST /auth/organizer_auth.php?endpoint=register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "9876543210",
  "password": "password123",
  "business_name": "Elite Events",
  "business_type": "Event Management"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "organizer_id": 1,
      "business_name": "Elite Events"
    }
  }
}
```

#### 2. Login
```http
POST /auth/organizer_auth.php?endpoint=login
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

#### 3. Get Profile
```http
GET /auth/organizer_auth.php?endpoint=get_profile
Headers: Authorization: Bearer {token}
```

#### 4. Update Profile
```http
POST /auth/organizer_auth.php?endpoint=update_profile
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "name": "John Doe Updated",
  "business_name": "Elite Events & Co",
  "description": "Premium event management services",
  "city": "Mumbai",
  "website": "https://eliteevents.com"
}
```

#### 5. Change Password
```http
POST /auth/organizer_auth.php?endpoint=change_password
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "current_password": "old_password",
  "new_password": "new_password"
}
```

---

## 📊 Dashboard Endpoints

### Get Dashboard Stats
```http
GET /organizer/organizer_api.php?endpoint=get_dashboard_stats
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_events": 25,
      "upcoming_events": 5,
      "completed_events": 20,
      "total_booking_requests": 15,
      "pending_requests": 3,
      "total_revenue": 250000.00,
      "pending_payments": 50000.00,
      "average_rating": 4.5,
      "total_reviews": 18
    },
    "upcoming_events": [...],
    "recent_bookings": [...],
    "revenue_by_month": [...]
  }
}
```

### Get Recent Activity
```http
GET /organizer/organizer_api.php?endpoint=get_recent_activity&limit=20
Headers: Authorization: Bearer {token}
```

---

## 📅 Event Management Endpoints

### 1. Get All Events
```http
GET /organizer/organizer_api.php?endpoint=get_events
Headers: Authorization: Bearer {token}

Query Parameters:
- status (optional): upcoming, completed, cancelled, draft
- category (optional): Wedding, Corporate, Birthday, etc.
- search (optional): search term
- page (optional): page number
- per_page (optional): items per page
```

**Example:**
```http
GET /organizer/organizer_api.php?endpoint=get_events&status=upcoming&page=1&per_page=10
```

### 2. Get Event Detail
```http
GET /organizer/organizer_api.php?endpoint=get_event_detail&event_id=123
Headers: Authorization: Bearer {token}
```

**Response includes:**
- Event details
- Assigned team members
- Tasks list
- Gallery images

### 3. Create Event
```http
POST /organizer/organizer_api.php?endpoint=create_event
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "title": "Grand Wedding Ceremony",
  "description": "Luxury wedding event",
  "category": "Wedding",
  "status": "upcoming",
  "event_date": "2025-06-15",
  "start_time": "18:00:00",
  "end_time": "23:00:00",
  "venue_name": "The Grand Palace",
  "venue_address": "123 Main Street, Mumbai",
  "city": "Mumbai",
  "state": "Maharashtra",
  "expected_guests": 500,
  "budget_min": 500000,
  "budget_max": 800000,
  "client_name": "Rahul & Priya",
  "client_email": "rahul@example.com",
  "client_phone": "9876543210",
  "cover_image": "https://example.com/image.jpg",
  "notes": "Premium package with full decoration"
}
```

### 4. Update Event
```http
POST /organizer/organizer_api.php?endpoint=update_event
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "event_id": 123,
  "status": "completed",
  "actual_guests": 480,
  "total_revenue": 750000,
  "notes": "Event completed successfully"
}
```

### 5. Delete Event
```http
DELETE /organizer/organizer_api.php?endpoint=delete_event&event_id=123
Headers: Authorization: Bearer {token}
```

---

## 📋 Booking Requests Endpoints

### 1. Get Booking Requests
```http
GET /organizer/organizer_api.php?endpoint=get_booking_requests&status=new
Headers: Authorization: Bearer {token}
```

### 2. Get Booking Request Detail
```http
GET /organizer/organizer_api.php?endpoint=get_booking_request_detail&booking_id=45
Headers: Authorization: Bearer {token}
```

### 3. Update Booking Status
```http
POST /organizer/organizer_api.php?endpoint=update_booking_status
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "booking_id": 45,
  "status": "contacted",
  "notes": "Called client, discussed requirements"
}
```

**Status Values:** `new`, `contacted`, `quoted`, `negotiating`, `approved`, `rejected`, `converted`

### 4. Create Quotation
```http
POST /organizer/organizer_api.php?endpoint=create_quotation
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "booking_request_id": 45,
  "total_amount": 500000,
  "discount_amount": 50000,
  "tax_amount": 45000,
  "items": [
    {
      "name": "Venue Booking",
      "quantity": 1,
      "rate": 200000,
      "amount": 200000
    },
    {
      "name": "Decoration",
      "quantity": 1,
      "rate": 150000,
      "amount": 150000
    },
    {
      "name": "Catering (500 guests)",
      "quantity": 500,
      "rate": 300,
      "amount": 150000
    }
  ],
  "terms_conditions": "50% advance required",
  "validity_date": "2025-02-15",
  "status": "sent"
}
```

### 5. Convert Booking to Event
```http
POST /organizer/organizer_api.php?endpoint=convert_to_event
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "booking_id": 45
}
```

---

## 👥 Team Management Endpoints

### 1. Get Team Members
```http
GET /organizer/organizer_api.php?endpoint=get_team_members
Headers: Authorization: Bearer {token}

Query Parameters:
- role (optional): Photographer, Decorator, etc.
- is_active (optional): 1 or 0
```

### 2. Add Team Member
```http
POST /organizer/organizer_api.php?endpoint=add_team_member
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "name": "Amit Sharma",
  "role": "Photographer",
  "email": "amit@example.com",
  "phone": "9876543210",
  "specialization": "Wedding Photography",
  "experience_years": 5,
  "hourly_rate": 2000,
  "daily_rate": 15000
}
```

### 3. Update Team Member
```http
POST /organizer/organizer_api.php?endpoint=update_team_member
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "member_id": 10,
  "role": "Senior Photographer",
  "daily_rate": 20000,
  "availability_status": "available"
}
```

### 4. Delete Team Member
```http
DELETE /organizer/organizer_api.php?endpoint=delete_team_member&member_id=10
Headers: Authorization: Bearer {token}
```

---

## 🛠️ Services Endpoints

### 1. Get Services
```http
GET /organizer/organizer_api.php?endpoint=get_services&category=Photography
Headers: Authorization: Bearer {token}
```

### 2. Create Service
```http
POST /organizer/organizer_api.php?endpoint=create_service
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "service_name": "Premium Wedding Photography",
  "category": "Photography",
  "description": "Full day wedding photography with 2 photographers",
  "base_price": 50000,
  "price_unit": "per_day",
  "service_image": "https://example.com/service.jpg"
}
```

### 3. Update Service
```http
POST /organizer/organizer_api.php?endpoint=update_service
Headers: Authorization: Bearer {token}
```

### 4. Delete Service
```http
DELETE /organizer/organizer_api.php?endpoint=delete_service&service_id=15
Headers: Authorization: Bearer {token}
```

---

## 💰 Payments Endpoints

### 1. Get Payments
```http
GET /organizer/organizer_api.php?endpoint=get_payments
Headers: Authorization: Bearer {token}

Query Parameters:
- status (optional): pending, completed, failed
- start_date (optional): YYYY-MM-DD
- end_date (optional): YYYY-MM-DD
```

### 2. Record Payment
```http
POST /organizer/organizer_api.php?endpoint=record_payment
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "event_id": 123,
  "amount": 100000,
  "payment_type": "advance",
  "payment_method": "upi",
  "payment_status": "completed",
  "payment_date": "2025-01-15 10:30:00",
  "client_name": "Rahul Verma",
  "reference_number": "UPI123456",
  "notes": "Advance payment received"
}
```

**Payment Types:** `advance`, `partial`, `final`, `refund`
**Payment Methods:** `cash`, `card`, `upi`, `bank_transfer`, `cheque`, `online`

### 3. Get Revenue Stats
```http
GET /organizer/organizer_api.php?endpoint=get_revenue_stats&period=month
Headers: Authorization: Bearer {token}
```

**Period Values:** `month`, `quarter`, `year`

---

## 🧾 Invoice Endpoints

### 1. Get Invoices
```http
GET /organizer/organizer_api.php?endpoint=get_invoices&status=paid
Headers: Authorization: Bearer {token}
```

### 2. Create Invoice
```http
POST /organizer/organizer_api.php?endpoint=create_invoice
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "event_id": 123,
  "client_name": "Rahul Verma",
  "client_email": "rahul@example.com",
  "client_phone": "9876543210",
  "client_address": "123 Main St, Mumbai",
  "subtotal": 500000,
  "discount_amount": 50000,
  "tax_amount": 45000,
  "total_amount": 495000,
  "paid_amount": 200000,
  "items": [
    {
      "description": "Venue Booking",
      "quantity": 1,
      "rate": 200000,
      "amount": 200000
    }
  ],
  "invoice_date": "2025-01-15",
  "due_date": "2025-02-15",
  "status": "sent",
  "notes": "Payment due in 30 days"
}
```

### 3. Update Invoice
```http
POST /organizer/organizer_api.php?endpoint=update_invoice
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "invoice_id": 20,
  "paid_amount": 495000,
  "status": "paid"
}
```

---

## ⭐ Reviews Endpoints

### 1. Get Reviews
```http
GET /organizer/organizer_api.php?endpoint=get_reviews
Headers: Authorization: Bearer {token}
```

### 2. Respond to Review
```http
POST /organizer/organizer_api.php?endpoint=respond_to_review
Headers: Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "review_id": 5,
  "response": "Thank you for your wonderful feedback! We're glad you enjoyed our service."
}
```

---

## 📈 Analytics Endpoints

### 1. Get Analytics
```http
GET /organizer/organizer_api.php?endpoint=get_analytics&start_date=2025-01-01&end_date=2025-01-31
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "events_by_category": [
      {"category": "Wedding", "count": 10, "revenue": 1000000},
      {"category": "Corporate", "count": 5, "revenue": 500000}
    ],
    "conversion_stats": {
      "total_requests": 20,
      "converted": 15,
      "conversion_rate": 75.00
    },
    "top_team_members": [
      {
        "name": "Amit Sharma",
        "role": "Photographer",
        "events_count": 8,
        "total_earnings": 120000
      }
    ]
  }
}
```

### 2. Get Event Trends
```http
GET /organizer/organizer_api.php?endpoint=get_event_trends&months=6
Headers: Authorization: Bearer {token}
```

### 3. Get Revenue Trends
```http
GET /organizer/organizer_api.php?endpoint=get_revenue_trends&months=12
Headers: Authorization: Bearer {token}
```

---

## 🔔 Notifications Endpoints

### 1. Get Notifications
```http
GET /organizer/organizer_api.php?endpoint=get_notifications&unread_only=1&limit=20
Headers: Authorization: Bearer {token}
```

### 2. Mark Notification as Read
```http
POST /organizer/organizer_api.php?endpoint=mark_notification_read&notification_id=25
Headers: Authorization: Bearer {token}
```

---

## 🚀 Installation

### 1. Database Setup

```bash
# Import the database schema
mysql -u root -p eventura_db_v2 < organizer_database_schema.sql
```

### 2. Configure Database

Edit `config/database.php`:

```php
private $host = "localhost";
private $db_name = "eventura_db_v2";
private $username = "root";
private $password = "your_password";
```

### 3. Set JWT Secret

Edit `utils/helpers.php`:

```php
private static $secret_key = "your-unique-secret-key-change-this";
```

### 4. Set Upload Directory

Ensure uploads directory exists and is writable:

```bash
mkdir -p uploads/{events,services,team,profiles}
chmod -R 755 uploads
```

### 5. Enable CORS (if needed)

The API already includes CORS headers. Adjust in each PHP file if needed:

```php
header("Access-Control-Allow-Origin: *");
```

---

## ⚠️ Error Handling

All API responses follow a standard format:

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... },
  "timestamp": 1704556800
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": "Validation error message"
  },
  "timestamp": 1704556800
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## 🔒 Security Notes

1. **Change JWT Secret:** Update the secret key in production
2. **Use HTTPS:** Always use HTTPS in production
3. **Validate Input:** All inputs are sanitized and validated
4. **SQL Injection:** All queries use prepared statements
5. **Password Hashing:** Uses bcrypt for password hashing
6. **Token Expiry:** JWT tokens expire after 30 days
7. **File Upload:** Only specific file types allowed
8. **Database Backup:** Regular backups recommended

---

## 📝 Notes

- All dates should be in `YYYY-MM-DD` format
- All timestamps in `YYYY-MM-DD HH:MM:SS` format
- All amounts are in decimal format (e.g., 50000.00)
- JSON fields are automatically parsed in responses
- Pagination is included in list endpoints
- Activity logs are automatically created for important actions

---

## 🆘 Support

For issues or questions:
- Email: dev@eventura.com
- GitHub: https://github.com/eventura/admin-api

---

**Version:** 1.0
**Last Updated:** January 2025
