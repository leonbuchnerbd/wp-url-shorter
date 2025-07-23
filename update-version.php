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

// Debug: Aktuelle Version im Plugin-Header finden
if (preg_match('/\*\s*Version:\s*([0-9.]+)/', $pluginContent, $currentVersionMatch)) {
    echo "🔍 Aktuelle Version im Plugin-Header: " . $currentVersionMatch[1] . "\n";
} else {
    echo "⚠️  Warnung: Keine Version im Plugin-Header gefunden!\n";
    echo "🔍 Suche nach beschädigten Versionszeilen...\n";
    
    // Suche nach beschädigten Patterns
    if (preg_match('/\*\s*Version:\s*/', $pluginContent)) {
        echo "✅ Version-Label gefunden\n";
    }
    if (preg_match('/\s+\.[0-9]+/', $pluginContent)) {
        echo "⚠️  Beschädigte Version gefunden (beginnt mit Punkt)\n";
    }
}

// Robusteres Pattern: Ersetze alles nach "Version:" bis zur nächsten Zeile
$updatedContent = preg_replace(
    '/(\*\s*Version:\s*)[0-9.]*([^\r\n]*)/',
    '${1}' . $version,
    $pluginContent
);

// Debug: Prüfen ob Ersetzung stattgefunden hat
if (preg_match('/\*\s*Version:\s*([0-9.]+)/', $updatedContent, $newVersionMatch)) {
    echo "🔍 Neue Version im Plugin-Header: " . $newVersionMatch[1] . "\n";
} else {
    echo "❌ Ersetzung fehlgeschlagen!\n";
}

if ($updatedContent === $pluginContent) {
    echo "✅ Plugin-Header bereits aktuell (Version $version)\n";
} else {
    file_put_contents($pluginFile, $updatedContent);
    echo "✅ Plugin-Header aktualisiert auf Version $version\n";
}

// Nächste Schritte anzeigen
echo "\n🚀 Nächste Schritte für Release:\n";
echo "git add .\n";
echo "git commit -m \"Version $version\"\n";
echo "git push\n";
echo "git tag v$version\n";
echo "git push origin v$version\n";
echo "\n💡 GitHub Actions erstellt automatisch das Release!\n";
