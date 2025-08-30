-- Example: drop (edit constraint names to match your DB)
ALTER TABLE class_courses DROP FOREIGN KEY fk_class_courses_stream;
ALTER TABLE class_courses DROP INDEX idx_class_courses_stream_id;
ALTER TABLE class_courses DROP COLUMN stream_id;

ALTER TABLE course_room_types DROP FOREIGN KEY fk_course_room_types_stream;
ALTER TABLE course_room_types DROP INDEX idx_course_room_types_stream_id;
ALTER TABLE course_room_types DROP COLUMN stream_id;

ALTER TABLE courses DROP FOREIGN KEY fk_courses_stream;
ALTER TABLE courses DROP INDEX idx_courses_stream_id;
ALTER TABLE courses DROP COLUMN stream_id;

ALTER TABLE lecturer_courses DROP FOREIGN KEY fk_lecturer_courses_stream;
ALTER TABLE lecturer_courses DROP INDEX idx_lecturer_courses_stream_id;
ALTER TABLE lecturer_courses DROP COLUMN stream_id;

ALTER TABLE lecturers DROP FOREIGN KEY fk_lecturers_stream;
ALTER TABLE lecturers DROP INDEX idx_lecturers_stream_id;
ALTER TABLE lecturers DROP COLUMN stream_id;

ALTER TABLE programs DROP FOREIGN KEY fk_programs_stream;
ALTER TABLE programs DROP INDEX idx_programs_stream_id;
ALTER TABLE programs DROP COLUMN stream_id;

ALTER TABLE timetable DROP FOREIGN KEY fk_timetable_stream;
ALTER TABLE timetable DROP INDEX idx_timetable_stream_id;
ALTER TABLE timetable DROP COLUMN stream_id;

ALTER TABLE timetable_lecturers DROP FOREIGN KEY fk_timetable_lecturers_stream;
ALTER TABLE timetable_lecturers DROP INDEX idx_timetable_lecturers_stream_id;
ALTER TABLE timetable_lecturers DROP COLUMN stream_id;

-- If rooms has stream_id and you want to remove it:
ALTER TABLE rooms DROP FOREIGN KEY rooms_ibfk_2; -- adjust name if different
ALTER TABLE rooms DROP COLUMN stream_id;

-- If departments had stream_id and you want to remove it:
ALTER TABLE departments DROP FOREIGN KEY fk_departments_stream;
ALTER TABLE departments DROP COLUMN stream_id;