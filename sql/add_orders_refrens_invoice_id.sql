-- Optional: store Refrens invoice _id after successful create (idempotent sync; avoids duplicate invoices).
-- Run once on your MySQL database for this project.
ALTER TABLE orders ADD COLUMN refrens_invoice_id VARCHAR(64) NULL DEFAULT NULL;
