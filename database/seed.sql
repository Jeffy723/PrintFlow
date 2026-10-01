USE printflow;

INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@printflow.local', '$2y$10$xzjoj5K/I/fcgMJc1bGgxe9lyrcY6HojyVpK/qHS9Sy4CA9CecrT6', 'admin'),
('Staff User', 'staff@printflow.local', '$2y$10$xzjoj5K/I/fcgMJc1bGgxe9lyrcY6HojyVpK/qHS9Sy4CA9CecrT6', 'staff');

INSERT INTO customers (name, phone, email, address) VALUES
('Aarav Printworks', '9876543210', 'hello@aaravprintworks.com', 'MG Road, Bengaluru'),
('Maya Studio', '9123456780', 'maya@mayastudio.in', 'Koramangala, Bengaluru'),
('Bright Media', '9988776655', 'contact@brightmedia.in', 'Indiranagar, Bengaluru');

INSERT INTO services (name, description, base_price, unit, is_active) VALUES
('Business Card', 'Premium visiting cards on matte or gloss stock', 120.00, 'pack', 1),
('Flyer', 'Single or double-sided promotional flyer', 200.00, '100 pcs', 1),
('Poster', 'Large-format poster printing', 550.00, 'piece', 1),
('Banner', 'Outdoor banner for events and promotions', 900.00, 'piece', 1),
('Brochure', 'Folded brochure printing service', 350.00, 'pack', 1),
('Booklet', 'Stapled booklet with custom pages', 700.00, 'book', 1),
('Label Print', 'Product labels and stickers', 180.00, 'sheet', 0);

INSERT INTO quotations
(quotation_number, customer_id, created_by, quotation_date, valid_until,
 status, subtotal, discount, total_amount, notes)
VALUES
('Q-2026-001', 1, 1, '2026-10-01', '2026-10-10',
 'Sent', 1190.00, 50.00, 1140.00,
 'Priority print run for launch campaign'),

('Q-2026-002', 2, 2, '2026-10-02', '2026-10-12',
 'Draft', 350.00, 0.00, 350.00,
 'Sample design approval pending');

INSERT INTO quotation_items
(quotation_id, service_id, description, quantity, unit_price,
 total_price, specifications)
VALUES
(1, 1, 'Business Card', 2, 120.00, 240.00,
 '2 packs, matte finish, black text'),

(1, 2, 'Flyer', 2, 200.00, 400.00,
 'A5, double-sided, 300 GSM'),

(1, 3, 'Poster', 1, 550.00, 550.00,
 '18x24 inches, matte'),

(2, 5, 'Brochure', 1, 350.00, 350.00,
 'Tri-fold brochure');

INSERT INTO orders
(order_number, customer_id, created_by, quotation_id, order_date,
 deadline, status, subtotal, discount, total_amount, notes)
VALUES
('ORD-2026-001', 1, 1, 1, '2026-10-03', '2026-10-08',
 'In Production', 1190.00, 50.00, 1140.00,
 'Rush job with delivery this week'),

('ORD-2026-002', 2, 2, 2, '2026-10-04', '2026-10-15',
 'Pending', 350.00, 0.00, 350.00,
 'Awaiting final approval');

INSERT INTO order_items
(order_id, service_id, description, quantity, unit_price,
 total_price, specifications)
VALUES
(1, 1, 'Business Card', 2, 120.00, 240.00,
 '2 packs, matte finish, black text'),

(1, 2, 'Flyer', 2, 200.00, 400.00,
 'A5, double-sided, 300 GSM'),

(1, 3, 'Poster', 1, 550.00, 550.00,
 '18x24 inches, matte'),

(2, 5, 'Brochure', 1, 350.00, 350.00,
 'Tri-fold brochure');

INSERT INTO payments
(order_id, amount, payment_method, payment_date,
 reference_number, notes, created_by)
VALUES
(1, 300.00, 'UPI', '2026-10-03',
 'UPI-1001', 'Advance payment', 1),

(2, 150.00, 'Cash', '2026-10-05',
 'CASH-957', 'Initial deposit', 2);

INSERT INTO deliveries
(order_id, delivery_type, address, scheduled_date,
 status, notes)
VALUES
(1, 'Delivery', 'MG Road, Bengaluru', '2026-10-09',
 'Pending', 'Customer requested evening delivery'),

(2, 'Pickup', NULL, '2026-10-16',
 'Pending', 'Pickup scheduled after final review');