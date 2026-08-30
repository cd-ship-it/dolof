-- Run once on any database that was created before campus / lift_group were added.
-- (Fresh installs already get these columns from dev_bootstrap.sql / production.sql.)
ALTER TABLE `dolos_orders`
  ADD COLUMN `campus`     VARCHAR(30) NOT NULL DEFAULT '' AFTER `phone`,
  ADD COLUMN `lift_group` VARCHAR(20) NOT NULL DEFAULT '' AFTER `campus`;
