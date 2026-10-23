<?php
/**
 * Test: LabUrl — single source of truth for public lab URLs.
 *
 * Real runtime only (per testing rules):
 *   - real class calls against the shipped LabUrl class
 *   - one real HTTP call to /api/labs/code_status with a real session cookie
 *     and a real instance document, proving the stored (stale) https credential
 *     is normalised to the effective scheme while the host comes from the
 *     instance document.
 *
 * Categories covered: happy path, scheme override, custom domain, fallback
 * host, stored-value normalisation, bad input, production behaviour.
 */
require_once __DIR__ . '/bootstrap.php';

use TomLabs\Labs\LabUrl;

echo "=== LabUrl Tests ===\n\n";

$hash   = '3fb0fe5d53738d9ccf5170b0f89f0458';
$lab    = [
    'instance_hash' => $hash,
    'code_domain'   => $hash . '.tomweb.shop',
    'gui_domain'    => 'gui-' . $hash . '.tomweb.shop',
    'domains'       => ['code2.tomweb.shop'],
    'credentials'   => ['code_server_url' => 'https://' . $hash . '.tomweb.shop'],
];

// The effective scheme follows the deployment configuration (env var > env.json).
$cfgScheme = strtolower((string)(get_config('code_url_scheme') ?: 'https'));
if ($cfgScheme !== 'http' && $cfgScheme !== 'https') {
    $cfgScheme = 'https';
}

// ── Scheme resolution ────────────────────────────────────────────────────────
test('Explicit scheme override wins', LabUrl::scheme('http') === 'http' && LabUrl::scheme('https') === 'https');
test('Invalid scheme override is ignored', LabUrl::scheme('ftp') === LabUrl::scheme(), 'ftp should not be honoured');
test('scheme() follows the configured scheme', LabUrl::scheme() === $cfgScheme, 'got ' . LabUrl::scheme() . ' want ' . $cfgScheme);

// ── Host resolution ──────────────────────────────────────────────────────────
test('IDE URL uses the instance code_domain',
    LabUrl::ideUrl($lab) === $cfgScheme . '://' . $hash . '.tomweb.shop',
    'got ' . LabUrl::ideUrl($lab));
test('User chosen custom domain is honoured',
    LabUrl::ideUrl(['instance_hash' => $hash, 'code_domain' => 'code2.tomweb.shop']) === $cfgScheme . '://code2.tomweb.shop',
    'got ' . LabUrl::ideUrl(['instance_hash' => $hash, 'code_domain' => 'code2.tomweb.shop']));
test('IDE URL falls back to code-{hash}.{base}',
    LabUrl::ideUrl(['instance_hash' => 'abc123']) === $cfgScheme . '://code-abc123.tomweb.shop',
    'got ' . LabUrl::ideUrl(['instance_hash' => 'abc123']));
test('GUI URL uses the instance gui_domain',
    LabUrl::guiUrl($lab) === $cfgScheme . '://gui-' . $hash . '.tomweb.shop',
    'got ' . LabUrl::guiUrl($lab));
test('GUI URL falls back to gui-{hash}.{base}',
    LabUrl::guiUrl(['instance_hash' => 'abc123']) === $cfgScheme . '://gui-abc123.tomweb.shop',
    'got ' . LabUrl::guiUrl(['instance_hash' => 'abc123']));
test('baseDomain is derived from the instance code_domain',
    LabUrl::baseDomain(['code_domain' => 'code2.tomweb.shop']) === 'tomweb.shop',
    'got ' . LabUrl::baseDomain(['code_domain' => 'code2.tomweb.shop']));

// ── Stored value normalisation ───────────────────────────────────────────────
test('withScheme rewrites the scheme and keeps host, port and path',
    LabUrl::withScheme('https://host.tomweb.shop:9443/path?q=1') === $cfgScheme . '://host.tomweb.shop:9443/path?q=1',
    'got ' . LabUrl::withScheme('https://host.tomweb.shop:9443/path?q=1'));
test('withScheme prefixes a bare host with the scheme',
    LabUrl::withScheme('host.tomweb.shop') === $cfgScheme . '://host.tomweb.shop',
    'got ' . LabUrl::withScheme('host.tomweb.shop'));
test('Stored https credential is normalised to the effective scheme',
    LabUrl::withScheme($lab['credentials']['code_server_url']) === $cfgScheme . '://' . $hash . '.tomweb.shop',
    'got ' . LabUrl::withScheme($lab['credentials']['code_server_url']));
test('Empty input stays empty',
    LabUrl::withScheme('') === '' && LabUrl::ideUrl([]) === '' && LabUrl::url('') === '');

// ── Production behaviour (no local override) ─────────────────────────────────
test('Override https reproduces production URLs',
    LabUrl::ideUrl($lab, 'https') === 'https://' . $hash . '.tomweb.shop'
    && LabUrl::guiUrl($lab, 'https') === 'https://gui-' . $hash . '.tomweb.shop'
    && LabUrl::withScheme('http://host.tomweb.shop', 'https') === 'https://host.tomweb.shop');

// ── Real HTTP: the API returns the normalised URL ────────────────────────────
$email = 'laburl_' . time() . '@example.com';
$token = create_test_user($email, 'user');
$cookie = 'session_token=' . $token;

$salt         = '8b51626f3a468904e8b6f83747f2fcf1';
$instanceHash = md5($email . 'essentials' . $salt);
$chosenDomain = 'chosen-' . substr($instanceHash, 0, 8) . '.tomweb.shop';

$labsDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$labsDb->machine_labs->insertOne([
    'instance_hash' => $instanceHash,
    'email'         => $email,
    'username'      => explode('@', $email)[0],
    'status'        => 'running',
    'lab_type'      => 'essentials',
    'code_domain'   => $chosenDomain,
    'domains'       => [],
    // Deliberately stale: stored https, while the effective scheme is http.
    'credentials'   => ['code_server_url' => 'https://' . $chosenDomain],
    'created_at'    => time(),
]);

$resp = http_request('POST', '/api/labs/code_status', [
    'cookie' => $cookie,
    'body'   => ['lab' => 'essentials'],
]);
$json = $resp['body_json'] ?? [];
$apiUrl = (string)($json['url'] ?? '');

test('code_status API returns the normalised IDE URL',
    $apiUrl === $cfgScheme . '://' . $chosenDomain,
    'http=' . $resp['status'] . ' got=' . $apiUrl . ' want=' . $cfgScheme . '://' . $chosenDomain);

$labsDb->machine_labs->deleteOne(['instance_hash' => $instanceHash]);
cleanup_test_user($email);

test_summary();
