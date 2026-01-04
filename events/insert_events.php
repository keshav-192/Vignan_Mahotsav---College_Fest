<?php
require_once __DIR__ . '/../config/config.php';

// Array of all events (Cultural and Sports in the specified sequence)
$events = [
    // Cultural -> Music
    ['Single Jod', 'Cultural', 'Music', 'Solo classical/jugal style performance', '- Solo performance only.\n- Any classical or semi-classical composition permitted.\n- Maximum time: 8 minutes.\n- Bring your own tanpura/track/instrument if required.', '₹5000', '₹2500', 'music: 9111222333', 'coord: 9444555666', 'https://i.ytimg.com/vi/9g5k9-7a3Q4/maxresdefault.jpg'],
    ['Western Vocal', 'Cultural', 'Music', 'Solo western vocal performance', '- Solo performance only.\n- Any western genre permitted.\n- Maximum time: 5 minutes.\n- Backing track allowed (bring on USB).', '₹4500', '₹2200', 'vocal: 9777888999', 'coord: 9666777888', 'https://www.shutterstock.com/image-photo/young-woman-singing-on-stage-260nw-1234567890.jpg'],
    ['Instrumental', 'Cultural', 'Music', 'Solo instrumental performance', '- Any instrument allowed.\n- Maximum time: 6 minutes.\n- Participant must bring their own instrument.\n- Basic amplification provided.', '₹5000', '₹2500', 'inst: 9000111222', 'coord: 9333444555', 'https://www.shutterstock.com/image-photo/young-man-playing-guitar-260nw-1234567891.jpg'],
    ['Duet Performance', 'Cultural', 'Music', 'Duet vocal or instrumental performance', '- Team of 2 participants.\n- Any genre (vocal/instrumental) permitted.\n- Maximum time: 6 minutes.\n- Own instruments/tracks required.', '₹5500', '₹2800', 'duet: 9222333444', 'coord: 9555666777', 'https://www.shutterstock.com/image-photo/duet-performance-stage-260nw-1234567892.jpg'],

    // Cultural -> Dance
    ['Bharatanatyam', 'Cultural', 'Dance', 'Classical Bharatanatyam solo performance', '- Solo performance only.\n- Any classical Bharatanatyam composition permitted.\n- Maximum time: 8 minutes.\n- Traditional costume mandatory.\n- Track to be provided by participant (USB).', '₹4500', '₹2200', 'dance: 9123001122', 'coord: 9822212456', 'https://i.ytimg.com/vi/pYfQ4UnU3qA/maxresdefault.jpg'],
    ['Western Dance', 'Cultural', 'Dance', 'Contemporary/Western dance performance', '- Solo or duo permitted.\n- Any western dance form allowed.\n- Maximum time: 7 minutes.\n- Appropriate costume required.\n- Bring your own track.', '₹4000', '₹2000', 'john: 9988776655', 'mary: 9988774455', 'https://www.shutterstock.com/image-photo/contemporary-dance-performance-260nw-1234567893.jpg'],
    ['Folk Dance', 'Cultural', 'Dance', 'Traditional folk dance performance', '- Solo or group performance allowed.\n- Any traditional folk style permitted.\n- Maximum time: 10 minutes.\n- Traditional attire preferred.\n- Own music arrangement required.', '₹5000', '₹2500', 'contact1: 9123456789', 'contact2: 9876543210', 'https://i.ytimg.com/vi/xyz1234567/maxresdefault.jpg'],
    ['Group Dance', 'Cultural', 'Dance', 'Group dance performance', '- Team size: 6-16 members.\n- Any genre allowed.\n- Maximum time: 8 minutes (including setup).\n- Props allowed (safe only).\n- Track must be submitted beforehand.', '₹7000', '₹3500', 'lead: 9001112223', 'coord: 9334445556', 'https://www.shutterstock.com/image-photo/group-dance-performance-260nw-1234567894.jpg'],

    // Cultural -> Dramatics
    ['MonoCasting', 'Cultural', 'Dramatics', 'Solo dramatic monologue performance', '- Solo performance only.\n- Maximum time: 5 minutes.\n- Props allowed (handheld).\n- No vulgar/offensive content.', '₹4000', '₹2000', 'drama1: 9888999000', 'drama2: 9111222333', 'https://www.shutterstock.com/image-photo/dramatic-monologue-performance-260nw-1234567895.jpg'],
    ['Drama', 'Cultural', 'Dramatics', 'Short drama/skit', '- Team size: 6-12 members.\n- Maximum time: 12 minutes.\n- Any theme allowed.\n- Basic props permissible.', '₹7000', '₹3500', 'drama3: 9222333444', 'drama4: 9555666777', 'https://www.shutterstock.com/image-photo/drama-performance-260nw-1234567896.jpg'],

    // Cultural -> Literary
    ['Debate', 'Cultural', 'Literary', 'Debate competition', '- Team of 2 members.\n- Topic announced on the spot.\n- Time: 3 min per speaker.\n- Rebuttal round may be included.', '₹5000', '₹2500', 'debate1: 9000111222', 'debate2: 9333444555', 'https://www.shutterstock.com/image-photo/debate-competition-260nw-1234567897.jpg'],
    ['Poetry', 'Cultural', 'Literary', 'Poetry recitation', '- Solo performance.\n- Maximum time: 5 minutes.\n- Original or published works allowed.\n- Any language permitted.', '₹3000', '₹1500', 'poet1: 9444555666', 'poet2: 9777888999', 'https://www.shutterstock.com/image-photo/poetry-recitation-260nw-1234567898.jpg'],
    ['Quiz', 'Cultural', 'Literary', 'General quiz competition', '- Team of 2-3 members.\n- Prelims + Finals format.\n- Mixed topics (current affairs, science, culture).', '₹4000', '₹2000', 'quiz1: 9666777888', 'quiz2: 9999000111', 'https://www.shutterstock.com/image-photo/quiz-competition-260nw-1234567899.jpg'],
    ['Extempore', 'Cultural', 'Literary', 'On-the-spot speaking', '- Solo speaking event.\n- Topic given 2 minutes before.\n- Speaking time: 2-3 minutes.\n- No prompts allowed.', '₹3000', '₹1500', 'ext1: 9222333444', 'ext2: 9555666777', 'https://www.shutterstock.com/image-photo/extempore-speaking-260nw-1234567900.jpg'],

    // Cultural -> Fine Arts
    ['Painting', 'Cultural', 'Fine Arts', 'On-spot painting competition', '- Solo participation.\n- Time: 2 hours.\n- Canvas and basic colors provided.\n- Theme announced on spot.', '₹4000', '₹2000', 'art1: 9666777888', 'art2: 9999000111', 'https://www.shutterstock.com/image-photo/painting-competition-260nw-1234567901.jpg'],
    ['Sketching', 'Cultural', 'Fine Arts', 'On-spot sketching competition', '- Solo participation.\n- Time: 90 minutes.\n- Pencils provided.\n- Theme announced on spot.', '₹3000', '₹1500', 'sketch1: 9222333444', 'sketch2: 9555666777', 'https://www.shutterstock.com/image-photo/sketching-competition-260nw-1234567902.jpg'],
    ['Rangoli', 'Cultural', 'Fine Arts', 'Rangoli design competition', '- Solo or pair participation.\n- Time: 90 minutes.\n- Materials to be brought by participants.\n- Theme announced on spot.', '₹3500', '₹1800', 'rang1: 9888999000', 'rang2: 9111222333', 'https://www.shutterstock.com/image-photo/rangoli-competition-260nw-1234567903.jpg'],
    ['Craft', 'Cultural', 'Fine Arts', 'Creative craft competition', '- Solo participation.\n- Time: 60 minutes.\n- Basic craft materials provided.\n- Theme announced on spot.', '₹3000', '₹1500', 'craft1: 9001112223', 'craft2: 9334445556', 'https://www.shutterstock.com/image-photo/craft-competition-260nw-1234567904.jpg'],

    // Sports -> Team Events
    ['Volleyball', 'Sports', 'Team Events', '6-a-side volleyball tournament', '- Team of up to 10 (6 on court, 4 subs).\n- Best of 3 sets (25 points).\n- FIVB rules apply.\n- Own kit required.', '₹12000', '₹6000', 'vb1: 9001112223', 'vb2: 9334445556', 'https://www.shutterstock.com/image-photo/volleyball-match-260nw-1234567905.jpg'],
    ['Football', 'Sports', 'Team Events', '7-a-side football tournament', '- Team of up to 10 players.\n- 15 min halves.\n- FIFA 7-a-side rules apply.\n- Shin guards mandatory.', '₹15000', '₹8000', 'fb1: 9667778889', 'fb2: 9990001112', 'https://www.shutterstock.com/image-photo/football-match-260nw-1234567906.jpg'],
    ['Cricket', 'Sports', 'Team Events', 'Tennis-ball cricket tournament', '- Team of up to 15 (11 fielding).\n- 10 overs per side.\n- Basic ICC rules.\n- Own kit required.', '₹20000', '₹10000', 'cr1: 9001112223', 'cr2: 9334445556', 'https://www.shutterstock.com/image-photo/cricket-match-260nw-1234567907.jpg'],

    // Sports -> Individual Events
    ['Badminton', 'Sports', 'Individual Events', 'Singles badminton', '- Knockout format.\n- Best of 3 games to 21.\n- BWF rules apply.\n- Own racket required.', '₹5000', '₹2500', 'bad1: 9888999000', 'bad2: 9111222333', 'https://www.shutterstock.com/image-photo/badminton-match-260nw-1234567908.jpg'],
    ['Table Tennis', 'Sports', 'Individual Events', 'Singles table tennis', '- Knockout format.\n- Best of 5 games to 11.\n- ITTF rules apply.\n- Bats provided on request.', '₹4000', '₹2000', 'tt1: 9222333444', 'tt2: 9555666777', 'https://www.shutterstock.com/image-photo/table-tennis-match-260nw-1234567909.jpg'],
    ['Chess', 'Sports', 'Individual Events', 'Rapid chess tournament', '- Swiss system (5-7 rounds).\n- 10+5 time control.\n- FIDE rules apply.\n- Bring your own chess clock if possible.', '₹3500', '₹1800', 'ch1: 9444555666', 'ch2: 9777888999', 'https://www.shutterstock.com/image-photo/chess-competition-260nw-1234567910.jpg'],
    ['Carrom', 'Sports', 'Individual Events', 'Singles carrom', '- Knockout format.\n- ICF rules apply.\n- Powder/striker provided.\n- Best of 3 boards.', '₹3000', '₹1500', 'car1: 9666777888', 'car2: 9999000111', 'https://www.shutterstock.com/image-photo/carrom-competition-260nw-1234567911.jpg'],

    // Sports -> Para Sports
    ['Para Badminton', 'Sports', 'Para Sports', 'Para badminton singles', '- Classification required.\n- Best of 3 games to 21.\n- BWF Para rules apply.\n- Wheelchair-accessible court.', '₹6000', '₹3000', 'pbad1: 9001112223', 'pbad2: 9334445556', 'https://www.shutterstock.com/image-photo/para-badminton-260nw-1234567912.jpg'],
    ['Para Table Tennis', 'Sports', 'Para Sports', 'Para TT singles', '- Classification required.\n- Best of 5 games to 11.\n- ITTF Para rules apply.\n- Wheelchair-accessible tables.', '₹5000', '₹2500', 'ptt1: 9222333444', 'ptt2: 9555666777', 'https://www.shutterstock.com/image-photo/para-table-tennis-260nw-1234567913.jpg'],
    ['Wheelchair Basketball', 'Sports', 'Para Sports', '5-a-side wheelchair basketball', '- Team of up to 10 players.\n- IWBF rules apply.\n- Chairs provided if needed.\n- Fair play mandatory.', '₹18000', '₹9000', 'wb1: 9888999000', 'wb2: 9111222333', 'https://www.shutterstock.com/image-photo/wheelchair-basketball-260nw-1234567914.jpg'],

    // Sports -> Track & Field
    ['100m Race', 'Sports', 'Track & Field', 'Sprint 100 meters', '- Heats + Final based on entries.\n- Spikes allowed.\n- Photo finish timing (if available).', '₹3000', '₹1500', 'tf1: 9001112223', 'tf2: 9334445556', 'https://www.shutterstock.com/image-photo/100m-race-260nw-1234567915.jpg'],
    ['400m Race', 'Sports', 'Track & Field', 'Sprint 400 meters', '- Heats + Final based on entries.\n- Spikes allowed.\n- Lane discipline to be followed.', '₹3500', '₹1800', 'tf3: 9667778889', 'tf4: 9990001112', 'https://www.shutterstock.com/image-photo/400m-race-260nw-1234567916.jpg'],
    ['Long Jump', 'Sports', 'Track & Field', 'Long jump event', '- 3 attempts per athlete.\n- Best legal jump counts.\n- Spikes allowed.', '₹3000', '₹1500', 'lj1: 9222333444', 'lj2: 9555666777', 'https://www.shutterstock.com/image-photo/long-jump-260nw-1234567917.jpg'],
    ['High Jump', 'Sports', 'Track & Field', 'High jump event', '- Bar height increases progressively.\n- 3 attempts per height.\n- Spikes allowed.', '₹3500', '₹1800', 'hj1: 9444555666', 'hj2: 9777888999', 'https://www.shutterstock.com/image-photo/high-jump-260nw-1234567918.jpg'],
];

// Insert all events
$stmt = $pdo->prepare("INSERT INTO events (event_name, category, subcategory, description, rules, prize_first, prize_second, contact_1, contact_2, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$existsStmt = $pdo->prepare("SELECT 1 FROM events WHERE event_name = ? LIMIT 1");

$count = 0;
foreach($events as $event) {
    try {
        // $event[0] is event_name
        $existsStmt->execute([$event[0]]);
        if ($existsStmt->fetchColumn()) {
            echo "➜ Skipped (already exists): {$event[0]}<br>";
            continue;
        }
        $stmt->execute($event);
        $count++;
        echo "✓ Inserted: {$event[0]}<br>";
    } catch(Exception $e) {
        echo "✗ Failed: {$event[0]} - {$e->getMessage()}<br>";
    }
}

echo "<br><strong>Total {$count} events inserted successfully!</strong>";
echo "<br><br><strong style='color:red;'>⚠️ Delete this file (insert_events.php) after successful insertion for security!</strong>";
?>
