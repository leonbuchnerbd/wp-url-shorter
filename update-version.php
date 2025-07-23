#!/usr/bin/env php
<?php
/**
 * Automatisches Version-Update Script für URL-Shorter Plugin
 * 
 * Dieses Script:
 * 1. Liest die Version aus includes/version.php
 * 2. Aktualisiert die Version im Plugin-Header von url-shorter.php
 * 3. Zeigt die nächsten Schritte für Git-Tag und Release
 * 
 * Verwendung: php update-version.php
 */

// Pfade definieren
$versionFile = __DIR__ . '/includes/version.php';
$pluginFile = __DIR__ . '/url-shorter.php';

// Version aus version.php lesen
if (!file_exists($versionFile)) {
    die("❌ Fehler: version.php nicht gefunden!\n");
}

$versionContent = file_get_contents($versionFile);
if (!preg_match("/define\s*\(\s*['\"]URL_SHORTER_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $versionContent, $matches)) {
    die("❌ Fehler: Version in version.php nicht gefunden!\n");
}

$version = $matches[1];
echo "📋 Aktuelle Version gefunden: $version\n";

// Plugin-Header aktualisieren
if (!file_exists($pluginFile)) {
    die("❌ Fehler: url-shorter.php nicht gefunden!\n");
}

$pluginContent = file_get_contents($pluginFile);
$updatedContent = preg_replace(
    '/(\* Version:\s*)([0-9.]+)/',
    '$1' . $version,
    $pluginContent
);

if ($updatedContent === $pluginContent) {
    echo "✅ Plugin-Header bereits aktuell (Version $version)\n";
} else {
    file_put_contents($pluginFile, $updatedContent);
    echo "✅ Plugin-Header aktualisiert auf Version $version\n";
}

// Nächste Schritte anzeigen
echo "\n🚀 Nächste Schritte für Release:\n";
echo "1. git add .\n";
echo "2. git commit -m \"Version $version\"\n";
echo "3. git push\n";
echo "4. git tag v$version\n";
echo "5. git push origin v$version\n";
echo "\n💡 GitHub Actions erstellt automatisch das Release!\n";
