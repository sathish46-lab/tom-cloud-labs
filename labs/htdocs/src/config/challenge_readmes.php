<?php
/**
 * Challenge Labs Readme Information Configuration
 * 
 * Edit this file to customize the "Lab Information Readme" displayed on the challenge dashboard
 * for each specific challenge lab.
 */

return [
    'shadow-partner' => 'You are part of the Tamil Nadu Cyber Crime team in Chennai. Over the past few weeks, the city has seen a sharp rise in digital fraud. People are falling for fake links, cloned profiles, and well-timed traps. These attacks feel too organized to be random.

Your investigation points to a hidden network operating in the shadows. Every clue leads to a single mastermind who uses advanced digital tactics to stay invisible while targeting citizens across the city.

Your mission is to find and expose this mastermind. Every second matters. More people fall victim with each passing hour. This is not just an assignment. It is your responsibility to protect them.

One warning from intelligence: the criminals have built strong defenses. Their systems block anyone who tries too hard or too fast. If you trigger their lockouts, you will have to start over from the beginning. Move carefully.

Note: This is an LLM-based machine; therefore, please limit your requests according to the following constraints.. Token usage for the chat endpoint is capped at 50 req/hour. Additionally, a global rate limiter is enforced with 20 req/minute, and 5,000 requests req/hour. The renewable option is disabled; once the time or soft limit is exhausted, the machine must be redeployed.',

    'zombie-breakout' => 'An infected terminal inside the quarantine containment zone has begun broadcasting encrypted signals to the outside world. It seems a rogue automated protocol is trying to release the pathogen containment seal.

Your team must breach the firewall, analyze the quarantine control server, and prevent the automated release before the countdown reaches zero. Every second counts as humanity\'s future lies in your cyber containment skills.',

    'backrooms' => 'A mysterious, infinite corporate maze of empty offices and buzzing fluorescent lights has manifested digitally. Our network traffic shows a lost node wandering inside, transmitting high-frequency static.

Navigate the digital backrooms, decrypt the network beacons, and extract the corrupted node before the system security sweeps the memory sectors.',

    'block-with-buster' => 'The system\'s defenses are extremely aggressive. Standard request paths are heavily rate-limited and locked down by an elite threat-prevention firewall.

Bypass the rate limiters, utilize clever payload fragmentation, and crack the main gateway. Move carefully to avoid triggering full host lockdowns.',

    'operation-warehouse' => 'A hidden warehouse server containing contraband databases has been discovered. The host is protected by a legacy industrial network architecture with several undocumented ports open.

Perform reconnaissance, enumerate the database services, and retrieve the contraband records without raising alarms in the central supervisor logs.',

    'proxy-pipeline' => 'A series of multi-layered proxy servers is masking illegal transaction endpoints. To trace the funds, you need to dissect the pipeline traffic and trace it hop-by-hop.

Expose the proxy chain, exploit the transit routing vulnerability, and extract the transaction receipts from the root ledger.',

    'sql-injection' => 'The internal portal for the target organization is suspected of having a vulnerability in its authentication mechanism. They rely on an old, unpatched database to store administrative credentials.

Your mission is to bypass the login portal without having valid credentials. Analyze the input fields, craft a payload that manipulates the backend database query, and extract the secret flag from the admin dashboard.

Note: This challenge container is intentionally isolated from the internet and can only be accessed via the WireGuard VPN.',

    'flask-server-side-injection' => 'The ancient digital realm of Flaskhaven has been compromised. The Cipher Stone — a sacred artifact that protects the realm\'s secrets — has been hidden deep within the server\'s file system by a rogue sorcerer.

The Binary Sentinels have called upon you to infiltrate the Flaskhaven Gateway Terminal. The gateway accepts a codename through its transmission interface, but the sorcerer\'s arrogance left the temple unguarded — the gateway renders your input directly into its magical templates without any protection.

Your mission: Exploit the Server-Side Template Injection (SSTI) vulnerability to traverse the server\'s internals and locate the Cipher Stone flag hidden where the password file is stored in this folder.

Hint: Check whether there is any name parameter and find the flag "where the password file is stored in this folder".

Note: This challenge container is intentionally isolated from the internet and can only be accessed via the WireGuard VPN.'
];
