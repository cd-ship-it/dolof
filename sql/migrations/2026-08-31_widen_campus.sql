-- Widen dolos_orders.campus so it fits the longer campus option
-- ("I don't regularly attend Crosspoint"). Fresh installs already get VARCHAR(50).
ALTER TABLE `dolos_orders` MODIFY `campus` VARCHAR(50) NOT NULL DEFAULT '';
