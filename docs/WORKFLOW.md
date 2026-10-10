# PrintFlow Business Workflow

This document describes the operational workflow of the PrintFlow application and how the modules interact.

## Primary lifecycle

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

This is the core business flow for a printing business. The system supports a realistic production cycle from customer contact through final delivery and completion.

## Quotation workflow

A quotation represents a proposed print job or service package for a customer.

Quotation states:

- Draft
- Sent
- Accepted
- Rejected
- Expired

Typical flow:

Draft
    ↓
Sent
    ↓
Accepted / Rejected

Accepted quotations usually lead to an order. Rejected or expired quotations do not become active orders unless recreated or revised.

## Order workflow

An order represents the confirmed production job.

Order states:

- Pending
- Confirmed
- In Production
- Ready
- Completed
- Cancelled

Typical flow:

Pending
    ↓
Confirmed
    ↓
In Production
    ↓
Ready
    ↓
Completed

Cancellation should only be allowed in appropriate early stages, not after production has already advanced too far.

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

## Payment workflow

Payments are associated with orders and track what has been received.

Payment states:

- Pending
- Partial
- Paid

Rules:

- payment amounts must be positive
- total paid must not exceed the order total
- partial payments are allowed
- remaining balance is calculated from the order total minus payments received

## Delivery workflow

Deliveries are linked to orders and represent pickup or delivery fulfilment.

Delivery states:

- Pending
- Out for Delivery
- Delivered
- Cancelled

Rules:

- delivery type is either Pickup or Delivery
- Delivery requires an address
- Pickup can omit the address
- an order should generally be Ready before delivery is completed

## Module interaction

The modules are connected in an operational chain:

Customers
    ↓
Quotations
    ↓
Orders
    ↓
Order Items
    ↓
Production
    ↓
Delivery

Important relationships:

- Customers are linked to quotations and orders
- Quotations contain quotation items that reference services
- Orders contain order items that reference services
- Payments are tied to orders
- Deliveries are tied to orders
- Services can be used by both quotations and orders

## Difference between quotation, order, payment, and delivery

### Quotation

A quotation is a proposed estimate or quote for a customer. It is not yet a confirmed job. It includes the customer, services, quantities, pricing, validation dates, and discount information.

### Order

An order is a confirmed production job. It is created from a customer request, often based on an accepted quotation. It contains the actual production details and the final ordered items.

### Payment

A payment records cash received against an order. Partial payments are valid. Payment records track the amount, method, date, and reference details.

### Delivery

A delivery or pickup record schedules how the completed order will be handed over to the customer.

## Operational notes for agents

- Do not change the workflow without project approval.
- Keep the business order consistent with the database design.
- Preserve the separation between estimates (quotations) and confirmed jobs (orders).
- Keep payment records attached to orders and not treated as standalone business entities.
- Keep deliveries tied to orders so each job has a delivery or pickup trace.

See also:

- docs/DATABASE.md
- docs/JEFFY.md
- docs/JINTU.md
