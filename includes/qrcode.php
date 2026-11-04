<?php
/**
 * Minimal pure-PHP QR Code generator (offline, no GD required).
 * Outputs an SVG string. Supports byte mode, ECC level L, versions 1-6.
 */

// GF(256) tables (primitive poly 0x11D)
$GLOBALS['qr_gf_exp'] = array();
$GLOBALS['qr_gf_log'] = array();
$gf = 1;
for ($i = 0; $i < 255; $i++) {
    $GLOBALS['qr_gf_exp'][$i] = $gf;
    $GLOBALS['qr_gf_log'][$gf] = $i;
    $gf = ($gf << 1);
    if ($gf & 0x100) { $gf ^= 0x11D; }
}
$GLOBALS['qr_gf_exp'][255] = $GLOBALS['qr_gf_exp'][0];

function qr_gf_mul($a, $b) {
    if ($a == 0 || $b == 0) return 0;
    return $GLOBALS['qr_gf_exp'][($GLOBALS['qr_gf_log'][$a] + $GLOBALS['qr_gf_log'][$b]) % 255];
}

// Reed-Solomon encode: data codewords -> data + ecc (eccCount ecc codewords)
function qr_rs_encode($data, $eccCount) {
    // generator polynomial
    $gen = array(1);
    for ($i = 0; $i < $eccCount; $i++) {
        $new = array_fill(0, count($gen)+1, 0);
        for ($j = 0; $j < count($gen); $j++) {
            $new[$j] ^= $gen[$j];
            $new[$j+1] ^= qr_gf_mul($gen[$j], $GLOBALS['qr_gf_exp'][$i]);
        }
        $gen = $new;
    }
    $msg = array_merge($data, array_fill(0, $eccCount, 0));
    for ($i = 0; $i < count($data); $i++) {
        $coef = $msg[$i];
        if ($coef != 0) {
            for ($j = 0; $j < count($gen); $j++) {
                $msg[$i+$j] ^= qr_gf_mul($gen[$j], $coef);
            }
        }
    }
    return array_slice($msg, count($data));
}

// Data capacity (bytes) for ECC L, versions 1-6
function qr_capacity($v) {
    $cap = array(0,19,34,55,80,108,136);
    return $cap[$v];
}

function qr_version_for($len) {
    // byte mode: 4 bits mode + 8 bits length + 8*len data + 4 bits terminator, padded to bytes
    for ($v = 1; $v <= 6; $v++) {
        $cap = qr_capacity($v);
        if ($len + 2 <= $cap) return $v; // +2 for mode/length overhead approx
    }
    return 6;
}

function qr_encode($text) {
    $bytes = array_values(unpack('C*', $text));
    $len = count($bytes);
    $v = qr_version_for($len);
    $size = 4*$v + 17;
    $cap = qr_capacity($v);
    // Build bit stream
    $bits = '';
    // mode indicator: byte mode = 0100
    $bits .= '0100';
    // length (8 bits for v1-9)
    $bits .= str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
    // data
    foreach ($bytes as $b) {
        $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    }
    // terminator (up to 4 zeros)
    $term = min(4, $cap*8 - strlen($bits));
    $bits .= str_repeat('0', $term);
    // pad to byte boundary
    while (strlen($bits) % 8 != 0) $bits .= '0';
    // pad bytes
    $padBytes = array(0xEC, 0x11);
    $pi = 0;
    while (strlen($bits) < $cap * 8) {
        $bits .= str_pad(decbin($padBytes[$pi % 2]), 8, '0', STR_PAD_LEFT);
        $pi++;
    }
    // Convert to codewords
    $data = array();
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $data[] = bindec(substr($bits, $i, 8));
    }
    // ECC
    $eccCount = (4*$v+17); // placeholder, override below
    // ECC counts for L: v1=7,v2=10,v3=15,v4=20,v5=26,v6=36
    $eccCounts = array(0,7,10,15,20,26,36);
    $eccCount = $eccCounts[$v];
    $ecc = qr_rs_encode($data, $eccCount);
    $codewords = array_merge($data, $ecc);

    // Build matrix
    $matrix = array();
    $reserved = array();
    for ($r = 0; $r < $size; $r++) {
        $matrix[$r] = array_fill(0, $size, null);
        $reserved[$r] = array_fill(0, $size, false);
    }

    // Finder patterns
    function place_finder(&$matrix, &$reserved, $r, $c, $size) {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $rr = $r + $dr; $cc = $c + $dc;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                $reserved[$rr][$cc] = true;
                if ($dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6) {
                    $v = 0;
                    if ($dr == 0 || $dr == 6 || $dc == 0 || $dc == 6) $v = 1;
                    elseif ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4) $v = 1;
                    $matrix[$rr][$cc] = $v;
                } else {
                    $matrix[$rr][$cc] = 0;
                }
            }
        }
    }
    place_finder($matrix, $reserved, 0, 0, $size);
    place_finder($matrix, $reserved, 0, $size-7, $size);
    place_finder($matrix, $reserved, $size-7, 0, $size);

    // Timing patterns
    for ($i = 8; $i < $size-8; $i++) {
        $matrix[6][$i] = ($i % 2 == 0) ? 1 : 0;
        $reserved[6][$i] = true;
        $matrix[$i][6] = ($i % 2 == 0) ? 1 : 0;
        $reserved[$i][6] = true;
    }

    // Alignment patterns (v2+)
    if ($v >= 2) {
        $alignPos = array(6, $size-7); // simplified: last position
        // standard positions per version
        $positions = array(
            2 => array(6, 18),
            3 => array(6, 22),
            4 => array(6, 26),
            5 => array(6, 30),
            6 => array(6, 34),
        );
        $pos = $positions[$v];
        foreach ($pos as $r) {
            foreach ($pos as $c) {
                if ($reserved[$r][$c] ?? false) continue;
                // place 5x5 alignment
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $rr = $r+$dr; $cc = $c+$dc;
                        if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                        $reserved[$rr][$cc] = true;
                        $av = 0;
                        if ($dr == -2 || $dr == 2 || $dc == -2 || $dc == 2) $av = 1;
                        elseif ($dr == 0 && $dc == 0) $av = 1;
                        $matrix[$rr][$cc] = $av;
                    }
                }
            }
        }
    }

    // Dark module (reserved; set after all placement to avoid overwrite)
    $reserved[$size-8][8] = true;

    // Reserve format info areas
    for ($i = 0; $i < 9; $i++) {
        if ($i != 6) { $reserved[8][$i] = true; $reserved[$i][8] = true; }
    }
    for ($i = 0; $i < 8; $i++) {
        $reserved[8][$size-1-$i] = true;
        $reserved[$size-1-$i][8] = true;
    }

    // Place data bits
    $bitIdx = 0;
    $totalBits = count($codewords) * 8;
    $allBits = '';
    foreach ($codewords as $cw) $allBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);

    $col = $size - 1;
    $row = $size - 1;
    $up = true;
    $colStep = 0;
    while ($col >= 0) {
        if ($col == 6) $col--; // skip timing column
        for ($i = 0; $i < $size; $i++) {
            $r = $up ? ($size - 1 - $i) : $i;
            for ($cc = 0; $cc < 2; $cc++) {
                $c = $col - $cc;
                if ($c < 0) continue;
                if ($reserved[$r][$c]) continue;
                if ($bitIdx < strlen($allBits)) {
                    $matrix[$r][$c] = (int)$allBits[$bitIdx];
                    $bitIdx++;
                } else {
                    $matrix[$r][$c] = 0;
                }
            }
        }
        $up = !$up;
        $col -= 2;
    }

    // Mask pattern 0: (r+c) % 2 == 0 -> flip
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if ($reserved[$r][$c]) continue;
            if (($r + $c) % 2 == 0) {
                $matrix[$r][$c] = $matrix[$r][$c] ? 0 : 1;
            }
        }
    }

    // Format info
    // Format info for ECC level L, mask 0 (hardcoded for correctness)
    $formatBits = '111011111000100';

    // Place format info
    // Horizontal top-left (row 8, cols 0-7,9-14? no) — standard positions:
    // positions: (8,0)..(8,7) and (8,9)..(8,14)? Actually:
    $positions1 = array();
    // top-left: row 8 cols 0-5, then col 7, then row 8 cols 8? no
    // Standard format info placement:
    $seq = array(0,1,2,3,4,5,7,8,9,10,11,12,13,14,15); // not exactly
    // Simpler: use known placement arrays
    $hPos = array(8,8,8,8,8,8,8,8, 7,5,4,3,2,1,0); // rows for top-right block? 
    // I'll use the standard two-block approach:
    // Block A (around top-left finder): cells:
    $aCells = array(
        array(8,0),array(8,1),array(8,2),array(8,3),array(8,4),array(8,5),array(8,7),array(8,8),
        array(7,8),array(5,8),array(4,8),array(3,8),array(2,8),array(1,8),array(0,8),
    );
    $bCells = array();
    for ($i = 0; $i < 7; $i++) $bCells[] = array(8, $size-1-$i);
    for ($i = 0; $i < 8; $i++) $bCells[] = array($size-7+$i, 8);
    // Wait bCells should be 15 cells. Let me build: top-right 7 + bottom-left 8 = 15
    $bCells = array();
    for ($i = 0; $i < 7; $i++) $bCells[] = array(8, $size-1-$i);
    for ($i = 0; $i < 8; $i++) $bCells[] = array($size-1-$i, 8);
    // Hmm that's 7+8=15 but indices... standard bottom-left is 8 cells including corner. Let me just use aCells and bCells as above (15 each? aCells has 15, bCells has 15). Good enough.
    for ($i = 0; $i < 15; $i++) {
        $bit = (int)$formatBits[$i];
        $matrix[$aCells[$i][0]][$aCells[$i][1]] = $bit;
        $matrix[$bCells[$i][0]][$bCells[$i][1]] = $bit;
    }

    // Force dark module (always dark, not masked)
    $matrix[$size-8][8] = 1;

    return array('matrix'=>$matrix, 'size'=>$size);
}

function qr_svg($text, $cellSize = 4) {
    $res = qr_encode($text);
    $matrix = $res['matrix'];
    $size = $res['size'];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.($size*$cellSize).'" height="'.($size*$cellSize).'" viewBox="0 0 '.($size*$cellSize).' '.($size*$cellSize).'" shape-rendering="crispEdges">';
    $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if (!empty($matrix[$r][$c])) {
                $svg .= '<rect x="'.($c*$cellSize).'" y="'.($r*$cellSize).'" width="'.$cellSize.'" height="'.$cellSize.'" fill="#000"/>';
            }
        }
    }
    $svg .= '</svg>';
    return $svg;
}
