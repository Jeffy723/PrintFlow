# PrintFlow AI Agent Instructions

This repository contains the PrintFlow printing job and order management system for a PHP + MySQL web application.

## Project purpose

PrintFlow manages the practical workflow of a printing business:

Customer
    ↓
Quotation
    ↓
Accepted
    ↓
Order
    ↓
Confirmed
    ↓
In Production
    ↓
Ready
    ↓
Delivery / Pickup
    ↓
Completed

Payments may occur during the order lifecycle as partial or full settlements against an order.

## Fixed technology stack

This project must remain a PHP + MySQL web application using:

- Frontend: HTML, CSS, vanilla JavaScript
- Backend: PHP
- Database: MySQL
- Local development: XAMPP, Apache, MySQL, phpMyAdmin
- Version control: Git and GitHub

Do not introduce or replace the stack with:

- React
- Node.js
- Express
- MongoDB
- PostgreSQL
- Supabase
- Firebase
- Laravel
- Django
- Composer
- unnecessary frameworks or services

Deployment must remain free and suitable for PHP + MySQL hosting.

## Current project state

This is an existing project already in progress. The repository already contains:

- working local XAMPP setup
- working Apache/PHP app
- working local MySQL database
- printflow database
- verified 9-table schema
- verified foreign-key relationships
- verified seed data
- PDO database connection
- existing folder structure
- Git repository
- GitHub repository
- working application modules

The GitHub repository is:

https://github.com/Jeffy723/PrintFlow

Do not rebuild the project or recreate infrastructure that already exists.

## Project structure

The current structure follows:

- config/
- includes/
- pages/
- public/
- database/
- index.php

Important folders and responsibilities:

- config/ - database connection and local configuration
- includes/ - shared PHP helpers, session/auth logic, and layout
- pages/auth/ - login and protected access
- pages/orders/ - order management (Jeffy)
- pages/customers/ - customer management (Jintu)
- pages/services/ - service management (Jintu)
- pages/quotations/ - quotation management (Jintu)
- pages/payments/ - payment management (Jintu)
- pages/deliveries/ - delivery management (Jintu)

## Database overview

The database is named printflow.

Approved tables:

1. users
2. customers
3. services
4. quotations
5. quotation_items
6. orders
7. order_items
8. payments
9. deliveries

The authoritative schema is in database/schema.sql.
The development and testing seed data is in database/seed.sql.

The schema already matches the verified database structure and business rules.
Do not change the schema without explicit approval.

## Local setup and database recreation

For a new developer:

1. Install XAMPP
2. Start Apache and MySQL
3. Open phpMyAdmin
4. Create a database named printflow
5. Import database/schema.sql
6. Optionally import database/seed.sql
7. Configure local credentials in config/database.local.php
8. Open http://localhost/PrintFlow/

The local database configuration must not be committed to source control.

## Team responsibilities

There are two developers implementing the application:

- Jeffy
- Jintu

### Jeffy responsibilities

Jeffy owns the core application and order work:

- architecture and common infrastructure
- database connection/configuration
- authentication and session handling
- login/logout and role checks
- shared layout and navigation
- dashboard
- order management
- final integration and deployment preparation

Primary areas:

- config/
- includes/
- pages/auth/
- pages/orders/
- dashboard.php

### Jintu responsibilities

Jintu owns the business support modules:

- customer management
- service management
- quotation management
- payment management
- delivery management
- search/filter functionality within those modules

Primary areas:

- pages/customers/
- pages/services/
- pages/quotations/
- pages/payments/
- pages/deliveries/

## Shared files

These files are shared application resources:

- includes/header.php
- includes/footer.php
- public/css/style.css
- public/js/script.js

Jeffy primarily owns the shared infrastructure, and Jintu must coordinate before making major shared-file changes.

## Git workflow

Approved branches:

- main
- feature/core
- feature/business

Rules:

- do not push directly to main during normal development
- keep feature work on the correct branch
- merge only after validation
- do not create unnecessary branches for small changes

## Business workflow

The business flow is:

Customer
    ↓
Quotation
    ↓
Order
    ↓
Production
    ↓
Ready
    ↓
Delivery / Pickup
    ↓
Completed

Quotation states:

- Draft
- Sent
- Accepted
- Rejected
- Expired

Order states:

- Pending
- Confirmed
- In Production
- Ready
- Completed
- Cancelled

### Order Management role permissions

These permissions apply only to Order Management. They do not define or change permissions for customers, services, quotations, payments, or deliveries; those modules remain under their respective owners and will be defined separately.

Admin:

- May view orders
- May create orders
- May advance orders through the valid status lifecycle
- May cancel orders when allowed by the order cancellation rules

Staff:

- May view orders
- May create orders
- May not advance order status
- May not cancel orders

Payment states:

- Pending
- Partial
- Paid

Delivery states:

- Pending
- Out for Delivery
- Delivered
- Cancelled

## Agent rules

Before changing code, every agent must:

1. Read AGENTS.md
2. Inspect relevant project files
3. Inspect relevant database documentation
4. Determine whether the requested work belongs to the assigned developer/module
5. Avoid modifying unrelated modules

Agents must not:

- change the technology stack
- redesign the database
- rename or delete tables
- change business workflow without approval
- rewrite unrelated modules
- modify another developer's module unnecessarily
- introduce frameworks or new runtime stacks
- commit secrets or local-only credentials
- silently make architectural decisions that override the project specification

If a requested change conflicts with the project specification, the agent must explain the conflict and request approval before making the change.

## Required reading for agents

When relevant, agents should read:

- docs/WORKFLOW.md
- docs/DATABASE.md
- docs/JEFFY.md
- docs/JINTU.md

These documents explain the business flow, database structure, and developer ownership boundaries.

## Testing requirements

Every implemented module should be tested locally with realistic sample data.

At minimum, verify:

- valid input
- invalid input
- empty values
- duplicate data where relevant
- database errors
- unauthorized access
- state transitions
- calculation correctness
- foreign-key relationships
- search/filter behavior

## Security requirements

- Use PHP with PDO
- Use prepared statements for all SQL
- Validate input on the server side
- Keep monetary calculations server-side
- Use password_hash() and password_verify() for user passwords
- Keep credentials out of committed files
- Use sessions and role checks for protected pages

## Documentation hierarchy

Use this hierarchy for project documentation:

AGENTS.md
    ↓
Common rules for all agents

docs/WORKFLOW.md
    ↓
Business workflow

docs/DATABASE.md
    ↓
Database structure and local setup

docs/JEFFY.md
    ↓
Jeffy's responsibilities

docs/JINTU.md
    ↓
Jintu's responsibilities

This documentation is the source of truth for AI work on the project.
