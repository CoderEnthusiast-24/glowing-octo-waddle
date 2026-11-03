-- ============================================================
-- DermAve IMS - Unified Directory table
-- Run this in phpMyAdmin (or mysql CLI) after creating the
-- database "dermave_ims" (or change the USE statement).
-- ============================================================

CREATE DATABASE IF NOT EXISTS dermave_ims
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dermave_ims;

-- ----------------------------------------------------------
-- Main table used by the Directory page.
-- products.php / machines.php / supplies.php filter by item_type.
-- ----------------------------------------------------------
DROP TABLE IF EXISTS directory_items;

CREATE TABLE directory_items (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_type           ENUM('product', 'machine', 'supply') NOT NULL,
  name                VARCHAR(255) NOT NULL,
  brand               VARCHAR(120) DEFAULT NULL,
  category            VARCHAR(150) DEFAULT NULL,   -- e.g. Machines, IV Fluids, Facial Equipment
  subcategory         VARCHAR(150) DEFAULT NULL,   -- finer grouping from the PDF
  description         TEXT DEFAULT NULL,
  dosage              VARCHAR(120) DEFAULT NULL,   -- strength / concentration (products)
  unit                VARCHAR(40)  DEFAULT NULL,   -- pcs, mL, vials, boxes, packs
  quantity            INT          NOT NULL DEFAULT 0,
  min_stock           INT          DEFAULT NULL,   -- threshold for "low" (mainly supplies)
  price               DECIMAL(12,2) DEFAULT NULL,
  batch_number        VARCHAR(80)  DEFAULT NULL,
  manufacturing_date  DATE         DEFAULT NULL,
  expiration_date     DATE         DEFAULT NULL,   -- products / some supplies
  last_maintenance    DATE         DEFAULT NULL,   -- machines
  next_maintenance    DATE         DEFAULT NULL,   -- machines
  status              ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  notes               TEXT DEFAULT NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_item_type (item_type),
  INDEX idx_status (status),
  INDEX idx_name (name),
  INDEX idx_category (category),
  INDEX idx_expiration (expiration_date),
  INDEX idx_next_maint (next_maintenance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Sample data (5 machines, 5 products, 5 supplies)
-- Drawn from "DermAve Combined Item List.pdf"
-- ============================================================

-- ----- MACHINES (5) -----
INSERT INTO directory_items
  (item_type, name, brand, category, subcategory, description, unit, quantity, last_maintenance, next_maintenance, status, notes)
VALUES
  ('machine', 'Dermaplaning Handpiece', NULL, 'Machines', 'Facial / Skin', 'Handheld dermaplaning device', 'unit', 1,
   CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 40 DAY, 'active', 'Routine blade/head check'),
  ('machine', 'Plasma Fibroblast Pen', NULL, 'Machines', 'Facial / Skin', 'Plasma fibroblast treatment pen', 'unit', 1,
   CURDATE() - INTERVAL 45 DAY, CURDATE() - INTERVAL 10 DAY, 'active', 'Overdue for tip inspection'),
  ('machine', 'LED Face Mask / Dome', NULL, 'Machines', 'Facial / Light Therapy', 'LED face mask or dome unit', 'unit', 2,
   CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 50 DAY, 'active', NULL),
  ('machine', 'HydraFacial Machine', NULL, 'Skin Care & Facial Treatments', 'Facial Machines', 'HydraFacial treatment system', 'unit', 1,
   CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 'active', NULL),
  ('machine', 'Cryolipolysis / CoolSculpting Machine', NULL, 'Body Contouring & Fat Reduction', 'Fat Reduction', 'Cryolipolysis body contouring device', 'unit', 1,
   CURDATE() - INTERVAL 30 DAY, CURDATE() + INTERVAL 60 DAY, 'active', 'Applicator seals checked');

-- ----- PRODUCTS (5) – injectables / IV / consumable products -----
INSERT INTO directory_items
  (item_type, name, brand, category, subcategory, description, dosage, unit, quantity, min_stock, price, batch_number, manufacturing_date, expiration_date, status, notes)
VALUES
  ('product', 'Glutathione Vials', NULL, 'IV Drip & Injection Consumables', 'Vitamins / Injectables',
   'Glutathione for IV push/drip (verify indication & authorization)', '600 mg', 'vial', 24, 10, 450.00,
   'GLU-2401', CURDATE() - INTERVAL 90 DAY, CURDATE() + INTERVAL 180 DAY, 'active', 'Also available 1.2 g / 5 g / 8 g'),
  ('product', 'Vitamin C (Ascorbic Acid) Ampoules', NULL, 'IV Drip & Injection Consumables', 'Vitamins / Injectables',
   'Ascorbic acid for IV / mesotherapy / topical', '500 mg', 'ampoule', 40, 15, 85.00,
   'VTC-118', CURDATE() - INTERVAL 60 DAY, CURDATE() + INTERVAL 120 DAY, 'active', 'Also 3 g strength'),
  ('product', 'Normal Saline (0.9% NaCl)', NULL, 'IV Drip & Injection Consumables', 'IV Fluids and Solutions',
   '0.9% sodium chloride IV solution', '500 mL', 'bag', 30, 12, 35.00,
   'NS-500-092', CURDATE() - INTERVAL 30 DAY, CURDATE() + INTERVAL 365 DAY, 'active', 'Also 100/250/1000 mL'),
  ('product', 'Hyaluronic Acid (Skin Booster / Filler)', NULL, 'Topical & Procedural Consumables', 'Injectable / Skin Rejuvenation',
   'HA for filler or skin booster / mesotherapy', '~20 mg/mL', 'syringe 1 mL', 18, 5, 2200.00,
   'HA-1ML-07', CURDATE() - INTERVAL 45 DAY, CURDATE() + INTERVAL 300 DAY, 'active', '0.5 / 1 / 2 mL sizes'),
  ('product', 'Botulinum Toxin Vials', NULL, 'Topical & Procedural Consumables', 'Injectable / Skin Rejuvenation',
   'Botulinum toxin (requires appropriate licensing)', '100 units', 'vial', 8, 3, 8500.00,
   'BTX-100-03', CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 90 DAY, 'active', 'Also 50 / 200 unit vials');

-- ----- SUPPLIES (5) – procedural / clinic consumables -----
INSERT INTO directory_items
  (item_type, name, brand, category, subcategory, description, unit, quantity, min_stock, price, status, notes)
VALUES
  ('supply', 'Syringes (Assorted Sizes)', NULL, 'IV Administration Equipment', 'IV Admin Supplies',
   'Disposable sterile syringes – specify sizes on order', 'pcs', 120, 40, 8.50, 'active', 'Common sizes 1 mL / 3 mL / 5 mL / 10 mL'),
  ('supply', 'Sterile Needles (Assorted Sizes)', NULL, 'IV Administration Equipment', 'IV Admin Supplies',
   'Disposable sterile needles – specify gauges/lengths', 'pcs', 200, 50, 3.25, 'active', NULL),
  ('supply', 'Alcohol Swabs', NULL, 'IV Administration Equipment', 'IV Admin Supplies',
   'Single-use alcohol prep pads', 'box', 25, 10, 45.00, 'active', NULL),
  ('supply', 'IV Bags (empty / administration sets related)', NULL, 'IV Administration Equipment', 'IV Admin Supplies',
   'IV administration bags / related consumables', 'pcs', 40, 15, 28.00, 'active', 'Distinct from pre-filled fluid bags'),
  ('supply', 'Numbing Cream (Lidocaine 5%)', NULL, 'Anesthetics and Topical Preparations', 'Topical Anesthetics',
   'Topical lidocaine cream 4–5%', 'tube', 12, 4, 180.00, 'active', 'Also gel and EMLA available');


-- Optional: quick verification queries
-- SELECT item_type, COUNT(*) FROM directory_items GROUP BY item_type;
-- SELECT id, item_type, name, quantity, status FROM directory_items ORDER BY item_type, name;

-- ----- MACHINES (10 Famous & Known Equipment) -----
INSERT INTO directory_items
  (item_type, name, brand, category, subcategory, description, unit, quantity, last_maintenance, next_maintenance, status, notes)
VALUES
  ('machine', 'HydraFacial Machine', NULL, 'Skin Care & Facial Treatments', 'Facial Machines', 
   'Standard facial workstation for deep cleansing, exfoliation, and hydration', 'unit', 1, 
   CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, 'active', 'Filter and tip assembly inspected'),

  ('machine', 'Diode Laser Hair Removal Machine', NULL, 'Laser and Energy-Based Treatment Machines', 'Hair Removal Lasers', 
   'Laser system for permanent hair reduction across various skin types', 'unit', 1, 
   CURDATE() - INTERVAL 12 DAY, CURDATE() + INTERVAL 48 DAY, 'active', 'Cooling system and handpiece verified'),

  ('machine', 'Pico Laser Machine', NULL, 'Laser and Energy-Based Treatment Machines', 'Pigmentation & Tattoo Lasers', 
   'Ultra-short pulse laser for tattoo removal, hyperpigmentation, and skin brightening', 'unit', 1, 
   CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 15 DAY, 'active', 'Overdue for flashlamp alignment check'),

  ('machine', 'CO2 Fractional Laser', NULL, 'Laser and Energy-Based Treatment Machines', 'Resurfacing Lasers', 
   'Fractional CO2 laser for acne scar resurfacing, deep skin renewal, and tissue tightening', 'unit', 1, 
   CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 40 DAY, 'active', 'Articulated arm calibrated'),

  ('machine', '12D HIFU Machine', NULL, 'Laser and Energy-Based Treatment Machines', 'Ultrasound Lifting', 
   'High-Intensity Focused Ultrasound used non-invasively for face lifts and skin tightening', 'unit', 1, 
   CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 50 DAY, 'active', 'Routine transducer cartridge check'),

  ('machine', 'Cryolipolysis / CoolSculpting Machine', NULL, 'Body Contouring & Fat Reduction', 'Fat Reduction', 
   'Industry-standard body contouring equipment for non-surgical fat reduction', 'unit', 1, 
   CURDATE() - INTERVAL 30 DAY, CURDATE() + INTERVAL 60 DAY, 'active', 'Applicator vacuum seals checked'),

  ('machine', 'LED Face Mask / Dome', NULL, 'Machines', 'Facial / Light Therapy', 
   'LED face mask or dome unit for post-treatment calming and acne therapy', 'unit', 2, 
   CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 75 DAY, 'active', 'All light wavelength diodes functional'),

  ('machine', 'Plasma Fibroblast Pen', NULL, 'Machines', 'Facial / Skin', 
   'Plasma fibroblast device for eyelid tightening and fine-line reduction', 'unit', 1, 
   CURDATE() - INTERVAL 45 DAY, CURDATE() - INTERVAL 10 DAY, 'active', 'Overdue for tip and power inspection'),

  ('machine', 'Radio Frequency (RF) Body Contouring Device', NULL, 'Body Contouring & Fat Reduction', 'RF Devices', 
   'Body contouring device for skin tightening and cellulitis reduction', 'unit', 1, 
   CURDATE() - INTERVAL 8 DAY, CURDATE() + INTERVAL 82 DAY, 'active', 'Grounding pad connection verified'),

  ('machine', '2-in-1 Facial Steamer', NULL, 'Skin Care & Facial Treatments', 'Facial Machines', 
   'Essential clinic facial steamer used to open pores prior to extractions', 'unit', 2, 
   CURDATE() - INTERVAL 25 DAY, CURDATE() + INTERVAL 35 DAY, 'active', 'Descaled heating element and glass jar');


-- ----- PRODUCTS (10 Famous & Known Patient Consumables / Depletables) -----
INSERT INTO directory_items
  (item_type, name, brand, category, subcategory, description, dosage, unit, quantity, min_stock, price, batch_number, manufacturing_date, expiration_date, status, notes)
VALUES
  ('product', 'Botulinum Toxin Vials', NULL, 'Topical & Procedural Consumables', 'Injectable / Skin Rejuvenation',
   'Botulinum toxin for wrinkle smoothing and masseter reduction', '100 units', 'vial', 8, 3, 8500.00,
   'BTX-100-03', CURDATE() - INTERVAL 20 DAY, CURDATE() + INTERVAL 90 DAY, 'active', 'Also available in 50 / 200 unit vials'),

  ('product', 'Hyaluronic Acid Filler', NULL, 'Topical & Procedural Consumables', 'Injectable / Skin Rejuvenation',
   'Dermal filler for volume restoration and lip augmentation', '~20 mg/mL', 'syringe 1 mL', 18, 5, 2200.00,
   'HA-1ML-07', CURDATE() - INTERVAL 45 DAY, CURDATE() + INTERVAL 300 DAY, 'active', 'Available in 0.5 / 1 / 2 mL syringes'),

  ('product', 'Glutathione Vials', NULL, 'IV Drip & Injection Consumables', 'Vitamins / Injectables',
   'Glutathione for IV push/drip therapy', '1.2 g', 'vial', 24, 10, 650.00,
   'GLU-2401', CURDATE() - INTERVAL 90 DAY, CURDATE() + INTERVAL 180 DAY, 'active', 'Also available in 600 mg / 5 g / 8 g'),

  ('product', 'Vitamin C (Ascorbic Acid) Ampoules', NULL, 'IV Drip & Injection Consumables', 'Vitamins / Injectables',
   'Ascorbic acid for IV drip, mesotherapy, or topical application', '500 mg', 'ampoule', 40, 15, 85.00,
   'VTC-118', CURDATE() - INTERVAL 60 DAY, CURDATE() + INTERVAL 120 DAY, 'active', 'Also available in 3 g strength'),

  ('product', 'Lemon Bottle Mesolipo Product', NULL, 'Body Contouring & Fat Reduction', 'Injectable Fat Reduction',
   'High-demand fat-dissolving injectable solution', '10 mL', 'vial', 15, 5, 1200.00,
   'LMN-883', CURDATE() - INTERVAL 30 DAY, CURDATE() + INTERVAL 210 DAY, 'active', 'Targeted mesolipo treatment'),

  ('product', 'Rejuran H', NULL, 'Topical & Procedural Consumables', 'Injectable / Skin Rejuvenation',
   'Polynucleotide (PN) skin booster for skin repair and elasticity', '2 mL', 'syringe 2 mL', 10, 4, 3800.00,
   'REJ-H-202', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 270 DAY, 'active', 'Rejuran S and Rejuran I also available'),

  ('product', 'Topical Lidocaine Cream 5%', NULL, 'Anesthetics and Topical Preparations', 'Topical Anesthetics',
   'High-strength topical numbing cream for procedural anesthesia', '5%', 'tube', 20, 6, 250.00,
   'LIDO-5-911', CURDATE() - INTERVAL 40 DAY, CURDATE() + INTERVAL 320 DAY, 'active', 'Apply 30-45 mins prior to treatment'),

  ('product', 'SPF 50 Sunscreen', NULL, 'Post-Treatment Products and Skin Care', 'Sun Protection',
   'Broad-spectrum UV protection for post-procedure aftercare', 'SPF 50', 'bottle', 35, 10, 420.00,
   'SPF-50-33', CURDATE() - INTERVAL 50 DAY, CURDATE() + INTERVAL 400 DAY, 'active', 'Essential clinic aftercare supply'),

  ('product', 'Glycolic Acid Chemical Peel', NULL, 'Chemical Peel and Specialized Treatment Products', 'Chemical Peels',
   'Chemical exfoliant solution for skin resurfacing', '30%', 'bottle 100 mL', 6, 2, 1500.00,
   'PEEL-GLY-30', CURDATE() - INTERVAL 75 DAY, CURDATE() + INTERVAL 250 DAY, 'active', 'Strengths range from 20% to 70%'),

  ('product', 'Microneedling 12-Pin Tips', NULL, 'Microneedling and Infusion Consumables', 'Microneedling Supplies',
   'Single-use sterile cartridge tip for automated microneedling pens', '12-pin', 'pcs', 100, 25, 45.00,
   'MN-12P-05', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 500 DAY, 'active', '36-pin and nano tips also available');


-- ----- SUPPLIES (10 Famous & Known General Clinic / Room Consumables) -----
INSERT INTO directory_items
  (item_type, name, brand, category, subcategory, description, unit, quantity, min_stock, price, status, notes)
VALUES
  ('supply', 'Nitrile Gloves', NULL, 'Disposables and Protective Wear', 'PPE Supplies',
   'Powder-free disposable nitrile examination gloves', 'box', 50, 15, 220.00, 'active', 'Assorted sizes S / M / L'),

  ('supply', 'Syringes (Assorted Sizes)', NULL, 'IV Administration Equipment and Supplies', 'IV Admin Supplies',
   'Disposable sterile syringes for injections and IV preparation', 'pcs', 120, 40, 8.50, 'active', 'Common sizes 1 mL / 3 mL / 5 mL / 10 mL'),

  ('supply', 'Sterile Needles (Assorted Sizes)', NULL, 'IV Administration Equipment and Supplies', 'IV Admin Supplies',
   'Disposable sterile needles for intradermal and intramuscular injection', 'pcs', 200, 50, 3.25, 'active', 'Assorted gauges and lengths'),

  ('supply', 'Alcohol Swabs', NULL, 'IV Administration Equipment and Supplies', 'IV Admin Supplies',
   'Single-use 70% isopropyl alcohol prep pads', 'box', 25, 10, 45.00, 'active', '100 swabs per box'),

  ('supply', 'Sharps Containers', NULL, 'Waste Disposal Supplies', 'Biohazard Disposal',
   'Puncture-resistant biohazard bin for used needles and blades', 'pcs', 8, 3, 180.00, 'active', '5 Litre capacity safety containers'),

  ('supply', 'Hand Sanitizer', NULL, 'Sanitization and Disinfection Supplies', 'Hygiene Consumables',
   '70% alcohol rinse-free hand disinfectant gel', 'bottle 500 mL', 15, 5, 110.00, 'active', 'Pump dispenser bottles'),

  ('supply', 'Cotton Pads', NULL, 'Cotton, Gauze, and Compresses', 'Cleansing Consumables',
   'Soft lint-free cotton pads for facial prep and makeup removal', 'pack', 40, 12, 35.00, 'active', '100 pads per pack'),

  ('supply', 'Treatment Bed Paper', NULL, 'Treatment Room Supplies', 'Room Hygiene',
   'Disposable protective bed paper roll for clinic examination tables', 'roll', 12, 4, 150.00, 'active', 'Standard width 60 cm'),

  ('supply', 'Face Masks', NULL, 'Disposables and Protective Wear', 'PPE Supplies',
   '3-ply surgical disposable protective face masks', 'box', 30, 10, 75.00, 'active', '50 masks per box'),

  ('supply', 'Laser Goggles', NULL, 'Laser and Light-Based Treatment Consumables', 'Laser Safety Wear',
   'Wavelength-specific protective eye safety goggles for operators', 'pcs', 5, 2, 1200.00, 'active', 'Verify OD rating against specific laser wavelength');
