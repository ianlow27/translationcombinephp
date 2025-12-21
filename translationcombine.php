<?php
echo PHP_VERSION;
$usage = "
  Usage: php $argv[0] [-h]
Version: 0.0.2_251221-1256
  About: $argv[0] Combines 2 translated parallel texts into a single HTML page
 Author: Ian Low | Date: 2025-12-21 | Copyright (c) 2025 Ian Low | License: MIT
Options:
    -h   Display help information including run options
    -n   Create a new instance
";
if(isset($argv[1])){
  if($argv[1]=="-h"){
    echo $usage;
  }else if($argv[1]=="-n"){  
    echo "Please enter the following information or press 'Enter' for default...\n";
    echo "Project name (defaults to 'myprojphp'): "; $projname = trim(readline());
    if($projname=="") $projname = "myprojphp";
  }
}


// ---- Parameters ----
$aFile = $argv[1] ?? 'a.txt';
$bFile = $argv[2] ?? 'b.txt';

if (!file_exists($aFile) || !file_exists($bFile)) {
    fwrite(STDERR, "Error: Input files not found.\n");
    exit(1);
}

$outFile = $aFile . "_out.html";

// ---- Read files ----
$aLines = file($aFile, FILE_IGNORE_NEW_LINES);
$bLines = file($bFile, FILE_IGNORE_NEW_LINES);

// ---- Paragraph splitting ----
function splitParagraphs(array $lines): array {
    $paras = [];
    $current = [];

    foreach ($lines as $line) {
        $trim = trim($line);
        $markerStart = preg_match('/^(`+|¬+)/u', $trim);

        if ($trim === '' || $markerStart) {
            if (!empty($current)) {
                $paras[] = implode("\n", $current);
                $current = [];
            }
            if ($trim !== '') {
                $current[] = $line;
            }
        } else {
            $current[] = $line;
        }
    }

    if (!empty($current)) {
        $paras[] = implode("\n", $current);
    }

    return $paras;
}

$aParas = splitParagraphs($aLines);
$bParas = splitParagraphs($bLines);
$maxParas = max(count($aParas), count($bParas));

// ---- Marker detection / insertion ----
function detectMarker(string $para): ?array {
    $lines = explode("\n", ltrim($para));
    if (preg_match('/^(`+|¬+)/u', $lines[0], $m)) {
        return [
            'char'  => mb_substr($m[1], 0, 1, 'UTF-8'),
            'count' => mb_strlen($m[1], 'UTF-8')
        ];
    }
    return null;
}

function insertMarker(string $para, string $char, int $count): string {
    $lines = explode("\n", $para);
    $lines[0] = str_repeat($char, $count) . $lines[0];
    return implode("\n", $lines);
}

// ---- Sentence splitting ----
function splitSentencesXXX(string $text): array {
    $text = trim(preg_replace("/\s+/", " ", $text));
    if ($text === '') return [];
    return preg_split('/(?<=\.)\s+/', $text);
}
function splitSentencesXX(string $text): array {
    $text = trim(preg_replace("/\s+/", " ", $text));
    if ($text === '') return [];

    $abbreviations = 'Mr\.|Mrs\.|mr\.|mrs\.|etc\.|\&c\.';

    return preg_split(
        "/\b(?:$abbreviations)(*SKIP)(*F)|(?<=\.)\s+/",
        $text
    );
}
$ignoreFullStop = ['Mr.', 'Mrs.', 'mr.', 'mrs.', 'etc.', '&c.'];

function splitSentences(string $text): array {
    global $ignoreFullStop;

    $text = trim(preg_replace("/\s+/", " ", $text));
    if ($text === '') return [];

    // Replace ignored dots with placeholder
    foreach ($ignoreFullStop as $abbr) {
        $safe = str_replace('.', '__DOT__', $abbr);
        $text = str_replace($abbr, $safe, $text);
    }

    // Split sentences
    $sentences = preg_split('/(?<=\.)\s+/', $text);

    // Restore dots
    foreach ($sentences as &$sentence) {
        $sentence = str_replace('__DOT__', '.', $sentence);
    }

    return $sentences;
}




// ---- Line formatting ----
function formatLine(string $line, string $type): string {
    $style = [];
    $content = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if (preg_match('/^(`{1,3})(.*)$/', $line, $m)) {
        $n = strlen($m[1]);
        $content = htmlspecialchars(trim($m[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sizes = [1 => '120%', 2 => '150%', 3 => '180%'];
        $style = [
            "text-align:center",
            "font-weight:bold",
            "font-size:" . $sizes[$n]
        ];
    } elseif (preg_match('/^(¬{1,3})(.*)$/u', $line, $m)) {
        $n = mb_strlen($m[1], 'UTF-8');
        $content = htmlspecialchars(trim($m[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sizes = [1 => '80%', 2 => '70%', 3 => '60%'];
        $style = [
            "text-align:right",
            "font-size:" . $sizes[$n]
        ];
    }

    if ($type === 'a') {
        $style[] = "color:#000";
        $style[] = "font-size:110%";
    } else {
        $style[] = "color:#555";
        $style[] = "font-size:90%";
    }

    return "<div style=\"" . implode("; ", $style) . "\">$content</div>";
}

// ---- HTML header ----
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sentence-Aligned Parallel Text</title>
<style>
body {
    font-family: serif;
    margin: 2em;
    line-height: 1.5;
}
.block {
    margin-left: 2em;
}
.sentence {
    margin-bottom: 0.3em;
}
.parabreak {
    margin-bottom: 1.5em;
}
</style>
</head>
<body>

HTML;

// ---- Processing ----
for ($p = 0; $p < $maxParas; $p++) {
    $aPara = $aParas[$p] ?? '';
    $bPara = $bParas[$p] ?? '';

    $am = detectMarker($aPara);
    $bm = detectMarker($bPara);

    if ($am && !$bm && $bPara !== '') {
        $bPara = insertMarker($bPara, $am['char'], $am['count']);
    } elseif ($bm && !$am && $aPara !== '') {
        $aPara = insertMarker($aPara, $bm['char'], $bm['count']);
    }

    $aSent = splitSentences(str_replace("\n", " ", $aPara));
    $bSent = splitSentences(str_replace("\n", " ", $bPara));
    $maxSent = max(count($aSent), count($bSent));

    $html .= "<div class=\"block\">\n";

    for ($s = 0; $s < $maxSent; $s++) {
        if (!empty($aSent[$s])) {
            $html .= "<div class=\"sentence\">" .
                     formatLine($aSent[$s], 'a') .
                     "</div>\n";
        }
        if (!empty($bSent[$s])) {
            $html .= "<div class=\"sentence\">" .
                     formatLine($bSent[$s], 'b') .
                     "</div>\n";
        }
    }

    $html .= "</div>\n<div class=\"parabreak\"></div>\n";
}

$html .= "</body></html>";

file_put_contents($outFile, $html);
echo "Output written to $outFile\n";



?>
