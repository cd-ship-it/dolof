-- ============================================================================
-- Dolos — RANDOM TEST ORDERS  (100 orders, generated 2026-08-30 14:51)
-- Portable: run against any database that already has the dolos_ tables
-- (production copy included). Box id / name / price are read from that
-- database's own dolos_boxes, so it adapts to the live menu.
--
-- Remove this test data later with:
--   DELETE FROM dolos_orders WHERE stripe_session_id LIKE 'seed_%';
-- ============================================================================

SET NAMES utf8mb4;
START TRANSACTION;

DELETE FROM dolos_orders WHERE stripe_session_id LIKE 'seed_%';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Pui Yee','Mak','puiyeemak633@example.com','','Tracy','Tracy Life Group','paid',0,'seed_1','stripe',NULL,1,'2026-08-24 11:43:38','2026-08-24 11:43:38');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wai Man','Kong','waimankong443@example.com','(415) 642-5783','Tracy','Tracy Life Group','paid',0,'seed_2','stripe',NULL,1,'2026-08-26 12:58:58','2026-08-26 12:58:58');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wai Kit','Ng','waikitng812@example.com','(925) 355-8051','San Leandro','OMG','paid',0,'seed_3','stripe',NULL,1,'2026-08-27 04:22:45','2026-08-27 04:22:45');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Yin','Hui','chunyinhui940@example.com','(510) 443-0517','Milpitas','Trio','paid',0,'seed_4','stripe',NULL,1,'2026-08-18 03:13:47','2026-08-18 03:13:47');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Kwan','Chan','hokwanchan140@example.com','(650) 742-4273','San Leandro','CCA','paid',0,'seed_5','stripe',NULL,1,'2026-08-23 01:21:40','2026-08-23 01:21:40');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wai Man','Choi','waimanchoi403@example.com','(415) 722-6562','Pleasanton','Faith','paid',0,'seed_6','stripe',NULL,1,'2026-08-28 10:40:16','2026-08-28 10:40:16');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Ming','Lee','hominglee136@example.com','(650) 386-8841','Pleasanton','RUTH','paid',0,'seed_7','stripe',NULL,1,'2026-08-22 23:09:32','2026-08-22 23:09:32');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Hei','Kong','chunheikong78@example.com','','San Leandro','Crosspoint Star','paid',0,'seed_8','stripe',NULL,1,'2026-08-27 14:53:34','2026-08-27 14:53:34');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Lok','To','kalokto405@example.com','(408) 572-0417','Pleasanton','Rejoice','paid',0,'seed_9','stripe',NULL,1,'2026-08-22 13:06:42','2026-08-22 13:06:42');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chi Wai','Ng','chiwaing256@example.com','(925) 407-5332','Tracy','Tracy Life Group','paid',0,'seed_10','stripe',NULL,1,'2026-08-19 21:30:15','2026-08-19 21:30:15');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Yin','Wong','chunyinwong88@example.com','(650) 416-5523','Pleasanton','Transform Treasure','paid',0,'seed_11','stripe',NULL,1,'2026-08-20 08:49:59','2026-08-20 08:49:59');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Hei','Wong','chunheiwong430@example.com','(669) 720-3132','Pleasanton','LIT','paid',0,'seed_12','stripe',NULL,1,'2026-08-25 06:44:55','2026-08-25 06:44:55');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Kit','Wong','chunkitwong917@example.com','','Milpitas','O2','paid',0,'seed_13','stripe',NULL,1,'2026-08-23 20:33:15','2026-08-23 20:33:15');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ching Yee','Mak','chingyeemak431@example.com','(925) 346-0986','San Leandro','Salt & Light','paid',0,'seed_14','stripe',NULL,1,'2026-08-22 19:59:21','2026-08-22 19:59:21');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Sze Wai','Hui','szewaihui60@example.com','(650) 840-1419','Pleasanton','','paid',0,'seed_15','stripe',NULL,1,'2026-08-27 05:01:47','2026-08-27 05:01:47');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wing Sze','To','wingszeto267@example.com','','Tracy','Tracy Life Group','paid',0,'seed_16','stripe',NULL,1,'2026-08-28 21:43:00','2026-08-28 21:43:00');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Wai','Yip','kawaiyip47@example.com','(925) 552-5472','Tracy','','paid',0,'seed_17','stripe',NULL,1,'2026-08-27 19:06:10','2026-08-27 19:06:10');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yee Man','Mak','yeemanmak335@example.com','','Pleasanton','Faith','paid',0,'seed_18','stripe',NULL,1,'2026-08-20 18:09:17','2026-08-20 18:09:17');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yuk Lan','To','yuklanto487@example.com','','Milpitas','O2','paid',0,'seed_19','stripe',NULL,1,'2026-08-17 16:16:04','2026-08-17 16:16:04');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yat Long','Chu','yatlongchu254@example.com','(925) 747-6172','Milpitas','Karpos','paid',0,'seed_20','stripe',NULL,1,'2026-08-24 16:13:04','2026-08-24 16:13:04');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ying','Lai','kayinglai467@example.com','(408) 785-8224','Pleasanton','RUTH','paid',0,'seed_21','stripe',NULL,1,'2026-08-24 22:18:59','2026-08-24 22:18:59');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Cheuk Yiu','Leung','cheukyiuleung112@example.com','(415) 732-8032','Milpitas','EGG','paid',0,'seed_22','stripe',NULL,1,'2026-08-21 01:26:11','2026-08-21 01:26:11');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Chun','Tsui','kachuntsui223@example.com','(415) 892-7065','Milpitas','EGG','paid',0,'seed_23','stripe',NULL,1,'2026-08-24 15:18:55','2026-08-24 15:18:55');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ho','Kwok','kahokwok463@example.com','(669) 213-9224','San Leandro','Karpos','paid',0,'seed_24','stripe',NULL,1,'2026-08-24 08:08:11','2026-08-24 08:08:11');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Kit','Kwan','chunkitkwan169@example.com','(415) 813-5118','Pleasanton','Faith','paid',0,'seed_25','stripe',NULL,1,'2026-08-26 12:58:16','2026-08-26 12:58:16');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ho','Hui','kahohui692@example.com','(408) 591-9297','Tracy','','paid',0,'seed_26','stripe',NULL,1,'2026-08-26 22:49:14','2026-08-26 22:49:14');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yat Long','Chan','yatlongchan362@example.com','(408) 875-7472','San Leandro','Young family','paid',0,'seed_27','stripe',NULL,1,'2026-08-24 20:47:09','2026-08-24 20:47:09');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Yin','Ho','hoyinho232@example.com','(669) 519-5091','San Leandro','One Family','paid',0,'seed_28','stripe',NULL,1,'2026-08-26 13:33:46','2026-08-26 13:33:46');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Sze Wai','Chu','szewaichu781@example.com','(415) 766-4519','San Leandro','Focus','paid',0,'seed_29','stripe',NULL,1,'2026-08-25 08:58:05','2026-08-25 08:58:05');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Ming','Lau','hominglau247@example.com','','Tracy','Tracy Life Group','paid',0,'seed_30','stripe',NULL,1,'2026-08-26 16:52:17','2026-08-26 16:52:17');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','Fong','heimanfong534@example.com','(650) 223-3610','San Leandro','','paid',0,'seed_31','stripe',NULL,1,'2026-08-21 00:30:26','2026-08-21 00:30:26');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Suk Yee','Fong','sukyeefong86@example.com','(925) 806-8479','Pleasanton','Faith','paid',0,'seed_32','stripe',NULL,1,'2026-08-21 10:41:29','2026-08-21 10:41:29');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ching Yee','Au','chingyeeau988@example.com','','Milpitas','In & Out','paid',0,'seed_33','stripe',NULL,1,'2026-08-30 00:05:02','2026-08-30 00:05:02');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Tsz Kwan','Ha','tszkwanha801@example.com','(650) 851-5118','Tracy','','paid',0,'seed_34','stripe',NULL,1,'2026-08-25 21:34:09','2026-08-25 21:34:09');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Sze Wai','Cheung','szewaicheung653@example.com','(650) 852-3675','San Leandro','Salt & Light','paid',0,'seed_35','stripe',NULL,1,'2026-08-29 23:58:38','2026-08-29 23:58:38');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ho','Cheung','kahocheung634@example.com','(925) 239-3525','Pleasanton','TBA','paid',0,'seed_36','stripe',NULL,1,'2026-08-17 18:21:43','2026-08-17 18:21:43');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yuk Lan','Tsui','yuklantsui868@example.com','(415) 233-8000','Pleasanton','','paid',0,'seed_37','stripe',NULL,1,'2026-08-26 01:48:40','2026-08-26 01:48:40');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yat Long','Hui','yatlonghui91@example.com','(650) 223-5507','Pleasanton','Love','paid',0,'seed_38','stripe',NULL,1,'2026-08-24 10:54:57','2026-08-24 10:54:57');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wai Kit','Ng','waikitng849@example.com','(669) 465-8681','Milpitas','Youth','paid',0,'seed_39','stripe',NULL,1,'2026-08-25 06:37:15','2026-08-25 06:37:15');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ching Yee','Cheung','chingyeecheung349@example.com','(408) 441-3902','Pleasanton','Transform Hope','paid',0,'seed_40','stripe',NULL,1,'2026-08-20 06:00:23','2026-08-20 06:00:23');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Chun','Leung','kachunleung843@example.com','(408) 273-4124','Pleasanton','LIT','paid',0,'seed_41','stripe',NULL,1,'2026-08-21 00:16:58','2026-08-21 00:16:58');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','Cheng','heimancheng188@example.com','(650) 918-4233','Tracy','Tracy Life Group','paid',0,'seed_42','stripe',NULL,1,'2026-08-20 07:45:37','2026-08-20 07:45:37');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Pui Yee','Fung','puiyeefung799@example.com','(408) 629-2007','Tracy','Tracy Life Group','paid',0,'seed_43','stripe',NULL,1,'2026-08-16 19:47:11','2026-08-16 19:47:11');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Kwan','Chow','hokwanchow888@example.com','','Pleasanton','Transform Hope','paid',0,'seed_44','stripe',NULL,1,'2026-08-27 15:42:57','2026-08-27 15:42:57');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hoi Ching','Lau','hoichinglau125@example.com','(669) 308-6778','Pleasanton','Faith','paid',0,'seed_45','stripe',NULL,1,'2026-08-30 12:51:32','2026-08-30 12:51:32');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Yin','Tsui','chunyintsui845@example.com','(669) 569-2084','Tracy','','paid',0,'seed_46','stripe',NULL,1,'2026-08-20 06:38:14','2026-08-20 06:38:14');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Hei','Kwok','chunheikwok397@example.com','','Pleasanton','RUTH','paid',0,'seed_47','stripe',NULL,1,'2026-08-19 19:05:29','2026-08-19 19:05:29');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Wai','Yuen','kawaiyuen158@example.com','','San Leandro','Crosspoint Star','paid',0,'seed_48','stripe',NULL,1,'2026-08-18 23:59:07','2026-08-18 23:59:07');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ho','Poon','kahopoon81@example.com','(415) 761-1462','Milpitas','EGG','paid',0,'seed_49','stripe',NULL,1,'2026-08-22 16:39:06','2026-08-22 16:39:06');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Man Kit','Kwan','mankitkwan623@example.com','(510) 580-4837','Milpitas','MAP','paid',0,'seed_50','stripe',NULL,1,'2026-08-18 15:35:52','2026-08-18 15:35:52');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wai Kit','Ha','waikitha903@example.com','(669) 582-4271','San Leandro','','paid',0,'seed_51','stripe',NULL,1,'2026-08-21 17:05:47','2026-08-21 17:05:47');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Kit','Lau','chunkitlau202@example.com','(650) 886-7504','Pleasanton','Love','paid',0,'seed_52','stripe',NULL,1,'2026-08-21 15:02:03','2026-08-21 15:02:03');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Pui Yee','Lee','puiyeelee11@example.com','(925) 801-7580','Milpitas','EGG','paid',0,'seed_53','stripe',NULL,1,'2026-08-29 21:49:42','2026-08-29 21:49:42');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Kwan','Chu','hokwanchu35@example.com','(510) 876-2477','Tracy','','paid',0,'seed_54','stripe',NULL,1,'2026-08-25 18:51:42','2026-08-25 18:51:42');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Mei Yee','Lam','meiyeelam435@example.com','(408) 321-9618','Milpitas','BMW','paid',0,'seed_55','stripe',NULL,1,'2026-08-19 15:21:47','2026-08-19 15:21:47');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ying','Wong','kayingwong373@example.com','(408) 308-2629','Tracy','Tracy Life Group','paid',0,'seed_56','stripe',NULL,1,'2026-08-28 08:39:59','2026-08-28 08:39:59');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Ming','Hui','hominghui699@example.com','(669) 283-8305','Milpitas','MAP','paid',0,'seed_57','stripe',NULL,1,'2026-08-17 07:15:31','2026-08-17 07:15:31');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Yin','Kwok','hoyinkwok346@example.com','(408) 859-5502','Tracy','Tracy Life Group','paid',0,'seed_58','stripe',NULL,1,'2026-08-17 23:58:21','2026-08-17 23:58:21');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Wai Lun','Ng','wailunng37@example.com','(415) 680-1449','Milpitas','EGG','paid',0,'seed_59','stripe',NULL,1,'2026-08-27 18:17:04','2026-08-27 18:17:04');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Suk Yee','Hui','sukyeehui685@example.com','(650) 415-5072','Milpitas','Mandarin','paid',0,'seed_60','stripe',NULL,1,'2026-08-16 16:30:05','2026-08-16 16:30:05');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Ming','Wu','homingwu191@example.com','(408) 634-5876','San Leandro','Grace','paid',0,'seed_61','stripe',NULL,1,'2026-08-25 12:53:49','2026-08-25 12:53:49');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Ming','Cheng','homingcheng27@example.com','(925) 848-1548','Pleasanton','LIT','paid',0,'seed_62','stripe',NULL,1,'2026-08-23 00:56:55','2026-08-23 00:56:55');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ying','Chow','kayingchow740@example.com','(415) 640-8968','Pleasanton','TBA','paid',0,'seed_63','stripe',NULL,1,'2026-08-26 16:19:57','2026-08-26 16:19:57');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ying','Chan','kayingchan121@example.com','(925) 264-9613','Tracy','Tracy Life Group','paid',0,'seed_64','stripe',NULL,1,'2026-08-19 06:40:21','2026-08-19 06:40:21');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Sze Wai','Cheung','szewaicheung244@example.com','(669) 604-7965','Tracy','Tracy Life Group','paid',0,'seed_65','stripe',NULL,1,'2026-08-27 09:23:36','2026-08-27 09:23:36');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ching Yee','Au','chingyeeau885@example.com','(925) 419-0337','Pleasanton','Rejoice','paid',0,'seed_66','stripe',NULL,1,'2026-08-22 11:53:03','2026-08-22 11:53:03');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Pui Yee','Fung','puiyeefung806@example.com','','San Leandro','Karpos','paid',0,'seed_67','stripe',NULL,1,'2026-08-29 20:11:40','2026-08-29 20:11:40');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Hei','Fong','chunheifong387@example.com','','San Leandro','Youth','paid',0,'seed_68','stripe',NULL,1,'2026-08-27 19:44:11','2026-08-27 19:44:11');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Tin Yau','Chu','tinyauchu802@example.com','','Pleasanton','Transform Hope','paid',0,'seed_69','stripe',NULL,1,'2026-08-26 20:43:03','2026-08-26 20:43:03');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Tin Yau','Cheng','tinyaucheng117@example.com','','Pleasanton','Youth','paid',0,'seed_70','stripe',NULL,1,'2026-08-18 16:13:49','2026-08-18 16:13:49');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chi Kin','Lai','chikinlai123@example.com','','San Leandro','','paid',0,'seed_71','stripe',NULL,1,'2026-08-19 23:11:59','2026-08-19 23:11:59');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yuk Lan','Poon','yuklanpoon937@example.com','(415) 504-1991','Tracy','Tracy Life Group','paid',0,'seed_72','stripe',NULL,1,'2026-08-18 11:55:52','2026-08-18 11:55:52');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Man Kit','Choi','mankitchoi666@example.com','(510) 742-4489','San Leandro','Mandarin','pending',0,'seed_73','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-27 15:13:32','2026-08-27 15:13:32');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Mei Ling','Yu','meilingyu916@example.com','(925) 557-0321','Pleasanton','Transform Treasure','pending',0,'seed_74','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-18 12:44:38','2026-08-18 12:44:38');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Cheuk Yiu','Leung','cheukyiuleung945@example.com','(408) 615-5031','Pleasanton','Transform Hope','pending',0,'seed_75','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-27 14:50:50','2026-08-27 14:50:50');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hoi Ching','Ha','hoichingha534@example.com','(925) 630-2241','San Leandro','Grace','pending',0,'seed_76','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-17 03:52:07','2026-08-17 03:52:07');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','Choi','heimanchoi958@example.com','(415) 556-2673','San Leandro','CCA','pending',0,'seed_77','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-17 07:44:12','2026-08-17 07:44:12');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Suk Yee','Hui','sukyeehui420@example.com','(415) 955-3652','Milpitas','Oikos','pending',0,'seed_78','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-22 02:01:18','2026-08-22 02:01:18');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','Yeung','heimanyeung727@example.com','(408) 734-5712','San Leandro','','pending',0,'seed_79','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-17 16:53:11','2026-08-17 16:53:11');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Ming','Fong','homingfong220@example.com','','San Leandro','Young family','pending',0,'seed_80','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-20 14:13:40','2026-08-20 14:13:40');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yuk Lan','Kwan','yuklankwan972@example.com','(650) 525-8042','San Leandro','One Family','pending',0,'seed_81','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-18 00:57:23','2026-08-18 00:57:23');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Yan','Kwok','kayankwok955@example.com','(669) 400-3443','San Leandro','Focus','pending',0,'seed_82','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-25 17:20:06','2026-08-25 17:20:06');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Nam','Lo','honamlo96@example.com','(408) 379-0947','Tracy','Tracy Life Group','pending',0,'seed_83','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-22 11:06:20','2026-08-22 11:06:20');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chi Wai','Cheung','chiwaicheung89@example.com','(510) 368-9098','Tracy','Tracy Life Group','pending',0,'seed_84','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-29 11:16:46','2026-08-29 11:16:46');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hoi Ching','Chow','hoichingchow933@example.com','','San Leandro','OMG','pending',0,'seed_85','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-26 23:43:16','2026-08-26 23:43:16');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ching Yee','Kwan','chingyeekwan916@example.com','','Pleasanton','','pending',0,'seed_86','stripe',DATE_ADD(NOW(), INTERVAL 30 MINUTE),0,'2026-08-28 14:04:29','2026-08-28 14:04:29');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','So','heimanso760@example.com','','Pleasanton','','expired',0,'seed_87','stripe',NULL,0,'2026-08-28 10:55:52','2026-08-28 10:55:52');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ching Yee','Chow','chingyeechow436@example.com','(925) 948-8156','San Leandro','WOW','expired',0,'seed_88','stripe',NULL,0,'2026-08-22 01:52:15','2026-08-22 01:52:15');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Mei Ling','Yu','meilingyu748@example.com','','Tracy','Tracy Life Group','expired',0,'seed_89','stripe',NULL,0,'2026-08-24 10:05:50','2026-08-24 10:05:50');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chun Yin','Yau','chunyinyau165@example.com','(510) 504-9813','Pleasanton','Transform Love','expired',0,'seed_90','stripe',NULL,0,'2026-08-24 13:42:28','2026-08-24 13:42:28');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Yee Man','Lai','yeemanlai897@example.com','(925) 654-0211','Pleasanton','Transform Treasure','expired',0,'seed_91','stripe',NULL,0,'2026-08-24 10:05:31','2026-08-24 10:05:31');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ho','Yau','kahoyau970@example.com','(408) 804-5365','San Leandro','','expired',0,'seed_92','stripe',NULL,0,'2026-08-30 00:22:51','2026-08-30 00:22:51');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','Tang','heimantang532@example.com','(415) 753-6825','Pleasanton','Mandarin','expired',0,'seed_93','stripe',NULL,0,'2026-08-27 20:01:39','2026-08-27 20:01:39');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='C';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='D';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ka Ho','Lo','kaholo210@example.com','(510) 764-7781','Milpitas','Trio','expired',0,'seed_94','stripe',NULL,0,'2026-08-29 12:41:05','2026-08-29 12:41:05');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Hei Man','Tsui','heimantsui632@example.com','(408) 442-9912','San Leandro','Crosspoint Star','expired',0,'seed_95','stripe',NULL,0,'2026-08-20 22:56:13','2026-08-20 22:56:13');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='E';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Sze Wai','Lee','szewailee509@example.com','(408) 448-5486','Tracy','Tracy Life Group','cancelled',0,'seed_96','stripe',NULL,0,'2026-08-26 23:16:43','2026-08-26 23:16:43');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 2 FROM dolos_boxes WHERE code='A';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Ho Yin','Mak','hoyinmak202@example.com','(669) 761-0777','Pleasanton','','cancelled',0,'seed_97','stripe',NULL,0,'2026-08-29 12:55:26','2026-08-29 12:55:26');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Chi Kin','Ng','chikinng672@example.com','(650) 958-1391','Tracy','Tracy Life Group','cancelled',0,'seed_98','stripe',NULL,0,'2026-08-22 03:21:38','2026-08-22 03:21:38');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Cheuk Yiu','So','cheukyiuso223@example.com','(650) 494-5216','Milpitas','BMW','cancelled',0,'seed_99','stripe',NULL,0,'2026-08-21 14:41:00','2026-08-21 14:41:00');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 1 FROM dolos_boxes WHERE code='B';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='D';

INSERT INTO dolos_orders (first_name,last_name,email,phone,campus,lift_group,status,total_amount_cents,stripe_session_id,payment_method,hold_expires_at,confirmation_email_sent,created_at,updated_at)
VALUES ('Tin Yau','Au','tinyauau741@example.com','(408) 443-2229','Milpitas','Trio','cancelled',0,'seed_100','stripe',NULL,0,'2026-08-30 01:57:34','2026-08-30 01:57:34');
SET @oid = LAST_INSERT_ID();
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 4 FROM dolos_boxes WHERE code='A';
INSERT INTO dolos_order_items (order_id,box_id,box_code,box_name,unit_price_cents,quantity)
  SELECT @oid, id, code, name, price_cents, 3 FROM dolos_boxes WHERE code='C';

-- Recompute each order total from its items (uses the target DB's prices).
UPDATE dolos_orders o
SET total_amount_cents = (SELECT COALESCE(SUM(unit_price_cents*quantity),0)
                            FROM dolos_order_items i WHERE i.order_id = o.id)
WHERE o.stripe_session_id LIKE 'seed_%';

COMMIT;
