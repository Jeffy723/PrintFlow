# Jeffy Developer Guidance

This file documents Jeffy's responsibilities for the PrintFlow project.

## Role

Jeffy owns the core application and the overall technical structure.

## Branch

feature/core

## Main responsibilities

Jeffy is responsible for:

- core architecture
- database connection and configuration
- authentication and authorization
- login and logout
- sessions and user state
- password handling
- role-based access control
- shared application infrastructure
- header, footer, and navigation
- dashboard
- order management
- final integration across modules
- testing of the application as a whole
- deployment preparation

## Primary areas

- config/
- includes/
- pages/auth/
- pages/orders/
- dashboard.php

## Order management ownership

Jeffy owns the main order module, including:

- create order
- edit order
- view order
- order items
- service selection
- quantity entry
- unit pricing
- specifications
- discount handling
- subtotal and total calculation
- deadline handling
- status transitions
- quotation association
- validation

Order states that Jeffy should honor:

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

## Dependencies on Jintu's modules

Jeffy depends on Jintu's business modules when integration requires them, especially for:

- customer records
- service records
- quotations
- payment records
- delivery records

Jeffy should coordinate with Jintu before making major changes that affect those modules or shared application structure.

## Files Jeffy should avoid modifying unnecessarily

Jeffy should not independently rewrite:

- pages/customers/
- pages/services/
- pages/quotations/
- pages/payments/
- pages/deliveries/

unless explicitly instructed or required for coordinated integration.

## Shared file ownership

Jeffy should primarily maintain:

- includes/header.php
- includes/footer.php
- public/css/style.css
- public/js/script.js

Major changes to shared files should be coordinated with Jintu and kept minimal.

## Testing expectations for Jeffy

Jeffy should test locally when implementing or integrating changes:

- login/logout flow
- session handling
- protected page access
- dashboard calculations
- order creation and validation
- subtotal and total logic
- order status transitions
- database integrity and foreign-key behavior
- broader app behavior after integration

## Reporting completed work

When Jeffy completes work, the agent should report:

- what changed
- which files were updated
- what was tested locally
- any remaining issues or follow-up required
- whether the change stayed within the approved core/order scope

See also:

- AGENTS.md
- docs/JINTU.md
- docs/DATABASE.md
- docs/WORKFLOW.md
