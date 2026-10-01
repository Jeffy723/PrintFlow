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

See also:

- AGENTS.md
- docs/JEFFY.md
- docs/DATABASE.md
- docs/WORKFLOW.md
