# Jintu Developer Guidance

This file documents Jintu's responsibilities for the PrintFlow project.

## Role

Jintu owns the business support modules of the application.

## Branch

feature/business

## Main responsibilities

Jintu is responsible for:

- customer management
- service management
- quotation management
- payment management
- delivery management
- search and filter behavior within those modules
- validation specific to the business modules

## Primary areas

- pages/customers/
- pages/services/
- pages/quotations/
- pages/payments/
- pages/deliveries/

## Customer management

Jintu owns:

- add customer
- view customer
- edit customer
- delete where appropriate
- filter/search customer data
- validation for customer records

## Service management

Jintu owns:

- add service
- view service
- edit service
- activate/deactivate service
- pricing
- search/filter behavior

## Quotation management

Jintu owns:

- create quotation
- edit quotation
- view quotation
- quotation items
- customer selection
- service selection
- quantities and pricing
- discount handling
- status updates
- validation

Quotation states include:

- Draft
- Sent
- Accepted
- Rejected
- Expired

## Payment management

Jintu owns:

- record payment
- payment history
- payment method handling
- reference numbers
- partial payments
- remaining balance checks
- payment validation

## Delivery management

Jintu owns:

- pickup and delivery scheduling
- address handling
- scheduled dates
- delivery status
- delivered marking
- delivery information records

## Dependencies on Jeffy's core

Jintu should use the existing project infrastructure, including:

- database connection
- authentication
- sessions
- common layout
- project architecture

Jintu should not independently rewrite:

- config/
- includes/
- pages/auth/
- pages/orders/
- dashboard.php

unless explicitly instructed or required for coordinated integration.

## Shared file ownership

Jintu should avoid unnecessary changes to shared files such as:

- includes/header.php
- includes/footer.php
- public/css/style.css
- public/js/script.js

If a change is required to a shared resource, coordinate with Jeffy before proceeding.

## Testing expectations for Jintu

Jintu should test locally for:

- customer create/edit/delete workflows
- service creation and activation
- quotation creation and item totals
- payment amount validation and balance logic
- delivery scheduling and state handling
- search/filter behavior
- unauthorized access and module boundaries

## Reporting completed work

When Jintu completes work, the agent should report:

- what changed
- which files were updated
- which business module was affected
- what was tested locally
- any integration issues or follow-up requirements

## Implemented module behavior and UI

The business pages implement the following module-specific behavior:

- Customers: create, edit, delete, and search by name, phone, or email; name and phone are required.
- Services: create and edit services, select an active status, delete, and search by name or description; names must be unique and base prices cannot be negative.
- Quotations: create, edit, view, and search by quotation number, customer, or status; quotation dates, status, discount, active services, and item quantities are validated. Item prices and quotation totals are calculated server-side. The form supports dynamically adding and removing item rows without a UI-imposed item limit.
- Payments: record payments and view payment history and outstanding balances; payment order, amount, method, and remaining order balance are checked before recording.
- Deliveries: schedule, edit, and view deliveries; the order, delivery type, required address, scheduled date, and status are validated.

The business pages use the existing PrintFlow cards, forms, tables, buttons, and active navigation treatment. Scoped shared styles provide consistent search toolbars, form action alignment, action buttons, and horizontally scrollable tables on small screens. No navigation structure or shared application logic was changed.

The implementation is limited to Jintu's business modules and their presentation. Authentication, shared layout/navigation logic, database configuration, and order management remain within Jeffy's core responsibilities. The approved schema and business workflow were not changed.

## Implementation files

The business module implementation and presentation changes are in:

- pages/customers/index.php
- pages/services/index.php
- pages/quotations/index.php
- pages/payments/index.php
- pages/deliveries/index.php
- public/css/style.css (scoped business-page presentation styles)

## Verification performed

The five business PHP pages passed PHP syntax checks. Local browser checks confirmed that each page renders with its matching active navigation item, and the table containers remain usable on a narrow viewport without widening the page. Customer, service, quotation, and delivery edit forms rendered; the payment form rendered.

Quotation rows were added and removed in the browser, and quotations with 1, 3, 4, and 6 items were saved and reopened. Saved item counts and totals matched the server-calculated results for the test data.

Submitting the existing search forms for customers, services, and quotations produced a PDO `HY093` invalid-parameter-number error during verification. Their repeated named-placeholder query patterns were present in the base revision and were not changed as part of this work. Search submission was therefore not verified as working.

See also:

- AGENTS.md
- docs/JEFFY.md
- docs/DATABASE.md
- docs/WORKFLOW.md
