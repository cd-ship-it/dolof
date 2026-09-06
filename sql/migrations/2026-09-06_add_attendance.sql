-- Track adults and children (age 12 and below) attending with each order.
-- Run once on any database that already has dolos_orders.
--
-- Fresh install: adds the two columns.
-- If you already ran an earlier draft of this file that added attending_total /
-- attending_age13, rename those columns instead:
--   ALTER TABLE dolos_orders
--     CHANGE COLUMN attending_total attending_adults INT NOT NULL DEFAULT 0,
--     CHANGE COLUMN attending_age13 attending_children INT NOT NULL DEFAULT 0;

ALTER TABLE `dolos_orders`
  ADD COLUMN `attending_adults`   INT NOT NULL DEFAULT 0 AFTER `lift_group`,
  ADD COLUMN `attending_children` INT NOT NULL DEFAULT 0 AFTER `attending_adults`;
