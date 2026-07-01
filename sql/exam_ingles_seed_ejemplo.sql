-- Datos de ejemplo (fases con nombre de texto)
-- Ejecutar después de exam_ingles_schema.sql

INSERT INTO en_vocabulario (fase, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta) VALUES
('A1 1-4','What is the opposite of "hot"?','cold','warm','cool','heat','A'),
('A1 1-4','Choose the correct word: I ___ to school every day.','go','goes','going','went','A'),
('A1 1-4','The word "book" means:','libro','mesa','puerta','silla','A'),
('A1 1-4','"Fast" is similar to:','quick','slow','late','early','A'),
('A1 1-4','Select the color: sky is usually ___.','blue','red','green','yellow','A'),
('A1 1-4','A person who teaches is a:','teacher','doctor','driver','chef','A'),
('A1 1-4','Plural of "child" is:','children','childs','childes','child','A'),
('A1 1-4','"Happy" means:','feliz','triste','enojado','cansado','A'),
('A1 1-4','Which is a fruit?','apple','car','house','pen','A'),
('A1 1-4','"Big" is the opposite of:','small','large','huge','tall','A'),
('B1+ 5-8','You drink:','water','chair','table','wall','A'),
('B1+ 5-8','"Dog" in Spanish is:','perro','gato','pájaro','pez','A'),
('B1+ 5-8','Morning comes after:','night','noon','evening','afternoon','A'),
('B1+ 5-8','"Run" is a:','verb','noun','adjective','adverb','A'),
('B1+ 5-8','The number after nine is:','ten','eight','eleven','seven','A'),
('B1+ 5-8','"Beautiful" describes:','appearance','sound','taste','smell','A'),
('B1+ 5-8','A place to sleep:','bed','kitchen','garden','street','A'),
('B1+ 5-8','"Yesterday" refers to:','the past','the future','today','now','A'),
('B1+ 5-8','"Easy" is the opposite of:','difficult','simple','soft','light','A'),
('B1+ 5-8','You use a ___ to write.','pen','shoe','hat','door','A');

INSERT INTO en_gramatica (fase, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta) VALUES
('A1 1-4','She ___ coffee every morning.','drinks','drink','drinking','drank','A'),
('A1 1-4','They ___ playing soccer now.','are','is','am','be','A'),
('A1 1-4','I have ___ finished my homework.','already','yet','since','for','A'),
('A1 1-4','He ___ to the park yesterday.','went','go','goes','going','A'),
('A1 1-4','This is ___ book.','my','me','I','mine','A'),
('A1 1-4','There ___ many students in the class.','are','is','am','be','A'),
('A1 1-4','Can you ___ me with this?','help','helps','helped','helping','A'),
('A1 1-4','We ___ lunch at 2 pm.','have','has','having','had','A'),
('A1 1-4','She is ___ than her sister.','taller','tall','tallest','more tall','A'),
('A1 1-4','I ___ TV when he called.','was watching','watch','watches','am watch','A'),
('B1+ 5-8','They have lived here ___ 2010.','since','for','ago','during','A'),
('B1+ 5-8','___ you like pizza?','Do','Does','Did','Are','A'),
('B1+ 5-8','He doesn''t ___ English well.','speak','speaks','speaking','spoke','A'),
('B1+ 5-8','The cat is ___ the table.','under','at','in','on','D'),
('B1+ 5-8','If it rains, we ___ stay home.','will','would','can','must','A'),
('B1+ 5-8','She asked me where I ___.','lived','live','lives','living','A'),
('B1+ 5-8','You ___ smoke here.','mustn''t','don''t have to','should','can','A'),
('B1+ 5-8','This is the ___ movie I have ever seen.','best','good','better','well','A'),
('B1+ 5-8','By next year, I ___ graduated.','will have','will','have','had','A'),
('B1+ 5-8','Neither Tom ___ Jerry came.','nor','or','and','but','A'),
('B1+ 5-8','I wish I ___ more time.','had','have','has','will have','A'),
('B1+ 5-8','The letter ___ by John.','was written','wrote','writes','is writing','A'),
('B1+ 5-8','She suggested ___ early.','leaving','leave','to leave','left','A'),
('B1+ 5-8','Hardly ___ he arrived when it started raining.','had','has','have','was','A'),
('B1+ 5-8','Not only did she sing, ___ she danced.','but also','and','or','nor','A'),
('B1+ 5-8','I''d rather you ___ here.','stayed','stay','staying','to stay','A'),
('B1+ 5-8','Despite ___ tired, he continued working.','being','be','been','is','A'),
('B1+ 5-8','The more you practice, ___ you get.','the better','better','best','more good','A'),
('B1+ 5-8','Had I known, I ___ differently.','would have acted','will act','acted','act','A'),
('B1+ 5-8','Scarcely ___ resources were available.','any','some','many','few','A');

INSERT INTO en_audios (fase, id_audio, nombre_audio, link_audio, script_audio) VALUES
('A1 1-4','AUD-A1-01','Daily routine','https://example.com/audio/a1-routine.mp3',
'Every morning, Sarah wakes up at seven. She has breakfast at home and takes the bus to school. Her favorite subject is English. After school she does her homework.');

INSERT INTO en_listening (fase, id_audio, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta) VALUES
('A1 1-4','AUD-A1-01','What time does she wake up?','6 am','7 am','8 am','9 am','B'),
('A1 1-4','AUD-A1-01','Where does she have breakfast?','At home','At school','At work','At a café','A'),
('A1 1-4','AUD-A1-01','How does she go to school?','By bus','By car','On foot','By bike','A'),
('A1 1-4','AUD-A1-01','What subject does she like most?','Math','English','Science','History','B'),
('A1 1-4','AUD-A1-01','What does she do after school?','Homework','Sports','Sleep','Cook','A'),
('A1 1-4','AUD-A1-01','Who does she study with?','Her friend','Her teacher','Her brother','Alone','A');

INSERT INTO en_lecturas (fase, id_lectura, nombre_lectura, lectura) VALUES
('B1+ 5-8','LEC-B1-01','The Park',
'Last Sunday, Maria went to the park with her family. They played games and had a picnic. The weather was sunny and warm. Maria''s little brother flew a kite. They stayed until evening and went home happy.');

INSERT INTO en_reading (fase, id_lectura, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta) VALUES
('B1+ 5-8','LEC-B1-01','Who went to the park?','Maria and her family','Maria alone','Her teacher','Her classmates','A'),
('B1+ 5-8','LEC-B1-01','What was the weather like?','Sunny and warm','Rainy','Cold','Snowy','A'),
('B1+ 5-8','LEC-B1-01','What did her brother do?','Flew a kite','Swam','Read a book','Cooked','A'),
('B1+ 5-8','LEC-B1-01','When did they go home?','In the evening','In the morning','At noon','At night','A'),
('B1+ 5-8','LEC-B1-01','How did they feel?','Happy','Sad','Angry','Tired','A'),
('B1+ 5-8','LEC-B1-01','What did they have at the picnic?','Food','Books','Toys','Clothes','A');

INSERT INTO en_writing (fase, pregunta) VALUES
('A1 1-4','Write a short paragraph (60-80 words) about your favorite hobby.'),
('B1+ 5-8','Describe your best friend. Include appearance and personality (60-80 words).');

INSERT INTO en_speaking (fase, pregunta) VALUES
('A1 1-4','Introduce yourself: name, age, where you live, and one hobby.'),
('A1 1-4','Describe a place you would like to visit and explain why.'),
('B1+ 5-8','Talk about what you did last weekend.');
