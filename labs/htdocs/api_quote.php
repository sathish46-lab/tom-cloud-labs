<?php
// api_quote.php
header('Content-Type: application/json');

$quotes = [
    [ "p1" => "Innovate Fearlessly", "p2" => "Your Virtual Innovation Hub" ],
    [ "p1" => "Code Without Limits", "p2" => "The Ultimate Cloud IDE" ],
    [ "p1" => "Deploy in Seconds", "p2" => "Next-Gen Infrastructure" ],
    [ "p1" => "Secure by Design", "p2" => "Isolated Lab Environments" ],
    [ "p1" => "Master the Cloud", "p2" => "Your Technical Playground" ]
];

// Pick a random quote
$randomQuote = $quotes[array_rand($quotes)];
echo json_encode($randomQuote);
