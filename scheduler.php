<?php

// Load JSON file and decode it into an associative array
$data = json_decode(file_get_contents('schedule_config.json'), true);

// Check if JSON was successfully parsed
if (!$data) {
    die("Error: Unable to parse JSON file.\n");
}

// Extract necessary data from JSON
$season = $data['season'];
$programs = $data['programs'];
$divisions = $data['divisions'];
$fields = $data['fields'];
$teams = $data['teams'];
$special_games = $data['special_games'];

// Helper function to check if a field is available at a given time slot and not already scheduled
function is_field_available($field, $day, $time, $schedule) {
    global $fields;
    
    // Ensure the field exists in the dataset
    if (!isset($fields[$field])) return false;
    
    // Check blackout times for the field
    foreach ($fields[$field]['blackout_times'] as $blackout) {
        if ($blackout['day'] === $day && $time >= $blackout['start'] && $time < $blackout['end']) {
            return false; // Field is not available during this blackout period
        }
    }
    
    // Ensure no other game is already scheduled at this field and time
    foreach ($schedule as $game) {
        if ($game['field'] === $field && $game['day'] === $day && $game['time'] === $time) {
            return false; // Field is already booked at this time
        }
    }
    
    return true;
}

// Initialize an array to store the generated schedule
$schedule = [];

// Loop through each division to create a game schedule
foreach ($divisions as $division => $settings) {
    
    // Ensure all divisions, including Majors, are processed
    if (!isset($settings['min_games']) || !isset($settings['preferred_weekday_game_times']) || !isset($settings['preferred_weekend_game_times'])) {
        echo "Skipping division: $division due to missing settings.\n";
        continue; // Skip if division settings are incomplete
    }
    
    // Get teams in the current division
    $teams_in_division = array_filter($teams, fn($team) => $team['division'] === $division);
    $team_names = array_column($teams_in_division, 'name');
    
    // Ensure there are at least two teams to form matchups
    if (count($team_names) < 2) {
        echo "Skipping division: $division due to insufficient teams.\n";
        continue;
    }
    
    // Retrieve preferred fields for this division
    $preferred_fields = $settings['preferred_fields'] ?? array_keys($fields); // Default to all fields if not defined
    if (empty($preferred_fields)) {
        echo "No preferred fields found for division: $division. Using all available fields.\n";
        $preferred_fields = array_keys($fields);
    }
    
    // Generate schedule for 10 weeks
    for ($week = 1; $week <= 10; $week++) {
        shuffle($team_names); // Randomize team order each week
        
        // Loop through the teams in pairs to create matchups
        for ($i = 0; $i < count($team_names) - 1; $i += 2) {
            $team1 = $team_names[$i];
            $team2 = $team_names[$i + 1];
            
            // Determine if it's a weekday or weekend
            $day = ($week % 2 == 0) ? "Saturday" : "Wednesday"; // Example logic for alternating weekdays & weekends
            $preferred_times = ($day == "Saturday") ? $settings['preferred_weekend_game_times'] : $settings['preferred_weekday_game_times'];
            
            // Assign a field and time for the matchup
            $field_assigned = false;
            foreach ($preferred_fields as $field) {
                if (isset($fields[$field]) && in_array($division, $fields[$field]['divisions_allowed'])) {
                    foreach ($preferred_times as $time) {
                        if (is_field_available($field, $day, $time, $schedule)) {
                            // Add the scheduled game to the array
                            $schedule[] = [
                                "week" => $week,
                                "division" => $division,
                                "team1" => $team1,
                                "team2" => $team2,
                                "field" => $field,
                                "time" => $time,
                                "day" => $day
                            ];
                            $field_assigned = true;
                            break 2; // Exit both loops once a valid field and time are found
                        }
                    }
                }
            }
            
            if (!$field_assigned) {
                echo "Warning: No available field found for $team1 vs $team2 in $division, week $week.\n";
            }
        }
    }
}

// Save the generated schedule as a JSON file
file_put_contents('schedule_output.json', json_encode($schedule, JSON_PRETTY_PRINT));

echo "Schedule generated and saved as schedule_output.json\n";
?>
