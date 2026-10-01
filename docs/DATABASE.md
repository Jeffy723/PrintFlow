# PrintFlow Database Documentation

This document describes the approved PrintFlow database and how to recreate it locally.

## Database name

printflow

## Authoritative schema

The authoritative database structure is in:

- database/schema.sql

The development sample data is in:

- database/seed.sql

The database schema is the source of truth for the approved design. The seed file is for local testing and demonstration.

## Approved tables

### 1. users

Purpose:
Stores application users and their access roles.

Important columns:

- id
- name
- email
- password
- role
- created_at

Business rules:

- email is unique
- passwords must be stored as hashed values
- role is admin or staff

### 2. customers

Purpose:
Stores printing business customers.

Important columns:

- id
- name
- phone
- email
- address
- created_at

Business rules:

- name is required
- phone is required
- email is optional

### 3. services

Purpose:
Stores print services offered by the business.

Important columns:

- id
- name
- description
- base_price
- unit
- is_active
- created_at

Business rules:

- service names are unique
- base_price cannot be negative
- inactive services are not used for new orders
- service pricing is stored independently from order items

### 4. quotations

Purpose:
Stores quotation records prepared for customers.

Important columns:

- id
- quotation_number
- customer_id
- created_by
- quotation_date
- valid_until
- status
- subtotal
- discount
- total_amount
- notes
- created_at

Business rules:

- belongs to a customer
- created by a user
- status is one of Draft, Sent, Accepted, Rejected, Expired
- subtotal, discount, and total_amount cannot be negative
- valid_until cannot be before quotation_date

### 5. quotation_items

Purpose:
Stores services or items included in a quotation.

Important columns:

- id
- quotation_id
- service_id
- description
- quantity
- unit_price
- total_price
- specifications

Business rules:

- quantity must be greater than zero
- unit_price and total_price cannot be negative
- item pricing is stored as part of the quotation item

### 6. orders

Purpose:
Stores confirmed printing jobs.

Important columns:

- id
- order_number
- customer_id
- created_by
- quotation_id
- order_date
- deadline
- status
- subtotal
- discount
- total_amount
- notes
- created_at
- updated_at

Business rules:

- customer is required
- order status is one of Pending, Confirmed, In Production, Ready, Completed, Cancelled
- deadline cannot be earlier than order_date
- monetary values cannot be negative
- totals are recalculated server-side

### 7. order_items

Purpose:
Stores items included in an order.

Important columns:

- id
- order_id
- service_id
- description
- quantity
- unit_price
- total_price
- specifications

Business rules:

- quantity must be greater than zero
- unit_price and total_price cannot be negative
- each item stores its own price independently from the service base price

### 8. payments

Purpose:
Stores payment records for orders.

Important columns:

- id
- order_id
- amount
- payment_method
- payment_date
- reference_number
- notes
- created_by

Business rules:

- amount must be greater than zero
- total paid cannot exceed order total
- partial payments are allowed

### 9. deliveries

Purpose:
Stores pickup or delivery information for an order.

Important columns:

- id
- order_id
- delivery_type
- address
- scheduled_date
- status
- delivered_at
- notes

Business rules:

- delivery_type is Pickup or Delivery
- address is required for Delivery
- address can be empty for Pickup
- delivery completion should align with order readiness

## Major relationships

- quotations.customer_id -> customers.id
- quotations.created_by -> users.id

- quotation_items.quotation_id -> quotations.id
- quotation_items.service_id -> services.id

- orders.customer_id -> customers.id
- orders.created_by -> users.id
- orders.quotation_id -> quotations.id

- order_items.order_id -> orders.id
- order_items.service_id -> services.id

- payments.order_id -> orders.id
- payments.created_by -> users.id

- deliveries.order_id -> orders.id

These relationships are part of the approved database design and should remain consistent.

## Local database creation steps

To recreate the local database:

1. Start XAMPP
2. Start Apache and MySQL
3. Open phpMyAdmin
4. Create a database named printflow
5. Import database/schema.sql
6. Import database/seed.sql
7. Configure local database credentials in config/database.local.php
8. Open http://localhost/PrintFlow/

Do not store real secrets or personal credentials in repository files.

## Notes for agents

- database/schema.sql is authoritative
- database/seed.sql is development data
- do not silently redesign the schema
- do not add alternative table names or duplicate structures
- do not change relationships without explicit approval

See also:

- docs/WORKFLOW.md
- docs/JEFFY.md
- docs/JINTU.md
