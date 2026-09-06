-- Attendee name lists (JSON arrays).
-- Adults: collected when attending_adults >= 1.
-- Children: collected when attending_children >= 1.
-- Run once on any database that already has attending_adults / attending_children.

ALTER TABLE `dolos_orders`
  ADD COLUMN `adult_names` TEXT NULL AFTER `attending_children`,
  ADD COLUMN `child_names` TEXT NULL AFTER `adult_names`;
