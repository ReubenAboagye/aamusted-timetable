-- Fixed version of Fni.sql with corrected rooms INSERT statement
-- The original had 11 values but the table has 13 columns
-- Added missing building_id and stream_id columns

-- Copy the original file content but fix the rooms INSERT statement
-- This is a partial fix showing the corrected structure

-- Original table structure (correct):
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `building_id` int DEFAULT '1',
  `stream_id` int DEFAULT '1',
  `name` varchar(50) NOT NULL,
  `building` varchar(100) NOT NULL,
  `room_type` varchar(50) NOT NULL COMMENT 'Expected values: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium',
  `capacity` int NOT NULL,
  `stream_availability` json NOT NULL,
  `facilities` json DEFAULT NULL,
  `accessibility_features` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_name_building` (`name`,`building`),
  KEY `building_id` (`building_id`),
  KEY `stream_id` (`stream_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rooms_ibfk_2` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- CORRECTED INSERT STATEMENT (13 columns instead of 11):
-- Format: (id, building_id, stream_id, name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active, created_at, updated_at)

INSERT INTO `rooms` VALUES 
(1,1,1,'ROB ROOM 1','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','["wheelchair_access"]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(2,1,1,'ROB ROOM 2','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','["wheelchair_access"]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(3,1,1,'ROB ROOM 3','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(4,1,1,'ROB ROOM 4','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(5,1,1,'ROB ROOM 5','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(6,1,1,'ROB ROOM 6','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(7,1,1,'ROB ROOM 7','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(8,1,1,'ROB ROOM 8','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:04','2025-08-28 12:07:10'),
(9,1,1,'ROB ROOM 9','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(10,1,1,'ROB ROOM 10','ROB Building','lecture_hall',50,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(11,1,1,'ROB ROOM 11','ROB Building','lecture_hall',50,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(12,1,1,'ROB ROOM 12','ROB Building','lecture_hall',50,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(13,1,1,'ROB ROOM 13','ROB Building','lecture_hall',50,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(14,1,1,'ROB ROOM 14','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(15,1,1,'ROB ROOM 15','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(16,1,1,'ROB ROOM 17','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(17,1,1,'ROB ROOM 18','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(18,1,1,'ROB ROOM 19','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(19,1,1,'ROB ROOM 20','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(20,1,1,'ROB ROOM 21','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(21,1,1,'ROB ROOM 22','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(22,1,1,'ROB ROOM 23','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(23,1,1,'ROB ROOM 24','ROB Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','["wheelchair_access"]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(24,1,1,'ROB ROOM 25','ROB Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','["wheelchair_access"]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(25,1,1,'ROB L\\W','ROB Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(26,1,1,'ROB R\\W','ROB Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(27,1,1,'CBT CAT MAIN LOUNGE','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(28,1,1,'CBT FASHION UPSTAIRS ROOM 22','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(29,1,1,'CBT FASHION UPSTAIRS ROOM 2','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(30,1,1,'CBT MECHANICAL LAB','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(31,1,1,'CBT AUTO LAB 1','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(32,1,1,'CBT AUTO LAB 2','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(33,1,1,'CBT CONSTRUCTION LAB','CBT Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(34,1,1,'CBT WOOD LAB','CBT Building','lecture_hall',50,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(35,1,1,'NEW LIBRARY GF','New Library','lecture_hall',120,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(36,1,1,'NEW LIBRARY FF','New Library','lecture_hall',60,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(37,1,1,'T.L 1','Teaching Lab','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(38,1,1,'T.L 2','Teaching Lab','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(39,1,1,'T.L 3','Teaching Lab','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(40,1,1,'T.L 4','Teaching Lab','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(41,1,1,'GLRM 1','General Lecture Room','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(42,1,1,'GLRM 2','General Lecture Room','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(43,1,1,'GLRM 3','General Lecture Room','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(44,1,1,'NEW AUDITORIUM','New Auditorium','lecture_hall',120,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(45,1,1,'NEW BUILDING ROOM 1','New Building','lecture_hall',120,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(46,1,1,'UBS GF 1','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(47,1,1,'UBS GF 3','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(48,1,1,'UBS GF 4','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(49,1,1,'UBS GF 5','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(50,1,1,'UBS GF 6','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(51,1,1,'UBS GF 7','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(52,1,1,'UBS GF 8','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(53,1,1,'UBS GF 9','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(54,1,1,'UBS GF 10','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(55,1,1,'UBS GF 11','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(56,1,1,'UBS GF 12','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(57,1,1,'UBS FF 1','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(58,1,1,'UBS FF 2','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(59,1,1,'UBS FF 3','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(60,1,1,'UBS FF 4','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(61,1,1,'UBS FF 5','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(62,1,1,'UBS FF 6','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(63,1,1,'UBS FF 7','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(64,1,1,'UBS FF 8','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(65,1,1,'UBS FF 9','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(66,1,1,'UBS FF 10','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(67,1,1,'UBS FF 11','UBS Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(68,1,1,'UBS SF 1','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(69,1,1,'UBS SF 2','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(70,1,1,'UBS SF 3','UBS Building','lecture_hall',30,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(71,1,1,'UBS SF 4','UBS Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(72,1,1,'UBS SF 5','UBS Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(73,1,1,'UBS SF 6','UBS Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(74,1,1,'UBS SF 7','UBS Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(75,1,1,'UBS SF 8','UBS Building','lecture_hall',40,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(76,1,1,'NFB GF','NFB Building','lecture_hall',200,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(77,1,1,'NFB FF','NFB Building','lecture_hall',250,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:10'),
(78,1,1,'AUTONOMY HALL ROOM 1','Autonomy Hall','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(79,1,1,'AUTONOMY HALL ROOM 2','Autonomy Hall','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(80,1,1,'AUTONOMY HALL ROOM 3','Autonomy Hall','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(81,1,1,'NEW LECTURE BLOCK GF ROOM 1','New Lecture Block','lecture_hall',150,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(82,1,1,'NEW LECTURE BLOCK GF ROOM 2','New Lecture Block','lecture_hall',150,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(83,1,1,'NEW LECTURE BLOCK FF ROOM 1','New Lecture Block','lecture_hall',150,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(84,1,1,'NEW LECTURE BLOCK FF ROOM 2','New Lecture Block','lecture_hall',150,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(85,1,1,'NEW LECTURE BLOCK SF','New Lecture Block','lecture_hall',300,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09'),
(86,1,1,'CONSTRUCTION DRAWING STUDIO','Construction Building','lecture_hall',70,'["regular", "evening", "weekend"]','["whiteboard"]','[]',1,'2025-08-28 12:05:05','2025-08-28 12:07:09');

-- SUMMARY OF THE FIX:
-- 1. Added building_id = 1 (assuming building ID 1 exists)
-- 2. Added stream_id = 1 (assuming stream ID 1 exists)
-- 3. Now each row has 13 values matching the 13 columns in the table
-- 4. The INSERT statement should now work without the "Column count doesn't match value count" error

-- Note: You may need to ensure that:
-- - A building with ID 1 exists in the buildings table
-- - A stream with ID 1 exists in the streams table
-- - Or modify the values to match existing building_id and stream_id values
