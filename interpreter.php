<?php

$stream = [];
$variables = [];
$commands = [];
$stack = [];
$var_variables = [
    1 => [0, 0],
    2 => [0, 0],
];

function run($bytes_str) {
    global $commands;
    global $stream;
    global $stack;
    
    $run_count = 0;
    
    for($i = 0; $i < strlen($bytes_str); $i += 2) {
        $commands[] = hexdec(substr($bytes_str, $i, 2));
    }
    
    $begin = $commands[0] * 2 + 1;
    
    /*
    'else' => 12,
    'break' => 15,
    'switch' => 16,
    'case' => 17,
    // 'DEF FUNCTION' => 103,
    // 'VAR FUNCTION' => 104,
     */
    
    for($i = $begin; $i < count($commands); $i++) {
        $run_count++;
        if ($run_count >= 10000) {
            $stream[] = 'Превышен лимит выпоннения инструкций. MAX: '.$run_count;
            return ;
        }
        
        
        switch ($commands[$i]) {
            case 5: // '='
                setValue($i + 1, getValue($i + 3));
                $i += 4;
                break;
            
            
            case 21: // '+'
                $stack[$commands[$i + 1]] = getValue($i + 2) + getValue($i + 4);
                $i += 5;
                break;
            case 22: // '-'
                $stack[$commands[$i + 1]] = getValue($i + 2) - getValue($i + 4);
                $i += 5;
                break;
            case 23: // '*'
                $stack[$commands[$i + 1]] = getValue($i + 2) * getValue($i + 4);
                $i += 5;
                break;
            case 24: // '/'
                $stack[$commands[$i + 1]] = getValue($i + 2) / getValue($i + 4);
                $i += 5;
                break;
            
            
            case 25: // '+='
                setValue($i + 1, getValue($i + 1) + getValue($i + 3));
                $i += 4;
                break;
            case 26: // '-='
                setValue($i + 1, getValue($i + 1) - getValue($i + 3));
                $i += 4;
                break;
            case 27: // '++'
                $v = getValue($i + 2);
                $stack[$commands[$i + 1]] = $v;
                setValue($i + 2, $v + 1);
                $i += 3;
                break;
            case 28: // '--'
                $v = getValue($i + 2);
                $stack[$commands[$i + 1]] = $v;
                setValue($i + 2, $v - 1);
                $i += 3;
                break;            
            
            
            case 31: // '>'
                $stack[$commands[$i + 1]] = (getValue($i + 2) > getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            case 32: // '>='
                $stack[$commands[$i + 1]] = (getValue($i + 2) >= getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            case 33: // '<'
                $stack[$commands[$i + 1]] = (getValue($i + 2) < getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            case 34: // '<='
                $stack[$commands[$i + 1]] = (getValue($i + 2) <= getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            case 35: // '=='
                $stack[$commands[$i + 1]] = (getValue($i + 2) == getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            case 36: // '!='
                $stack[$commands[$i + 1]] = (getValue($i + 2) != getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            
            
            case 41: // '!'
                $stack[$commands[$i + 1]] = !getValue($i + 2);
                $i += 3;
                break;
            case 42: // '&&'
                $stack[$commands[$i + 1]] = (getValue($i + 2) && getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            case 43: // '||'
                $stack[$commands[$i + 1]] = (getValue($i + 2) || getValue($i + 4)) ? 1 : 0;
                $i += 5;
                break;
            
            
            case 11: // 'if'
                $v = getValue($i + 2);
                $stack[$commands[$i + 1]] = $v;
                if ($v) {
                    $i += 5;
                } else {
                    $i = $begin + (($commands[$i + 4] << 8) + $commands[$i + 5]);
                    $i--;
                }
                break;
            case 12: // 'else'
                if (!$stack[$commands[$i + 1]]) {
                    $i += 3;
                } else {
                    $i = $begin + (($commands[$i + 2] << 8) + $commands[$i + 3]);
                    $i--;
                }
                break;
                
            case 17: // case switch                
                if ($stack[$commands[$i + 1]] == getConstant($commands[$i + 2])) {
                    $i = $begin + (($commands[$i + 3] << 8) + $commands[$i + 4]);
                    $i--;
                } else {
                    $i += 4;
                }
                break;
                
            case 20: // goto
                $i = $begin + (($commands[$i + 1] << 8) + $commands[$i + 2]);
                $i--;
                break;
            
            case 103: // DEF FUNCTIONS
                $i = callDefFunction($i);
                break;
            case 104: // VAR FUNCTIONS
                $i = callVarFunction($i);
                break;
        }
    }
}


function getValue($index) {
    global $commands;
    global $variables;
    global $stack;
    
    $id = $commands[$index + 1];
    switch ($commands[$index]) {
        case 101:
            $b1 = $commands[$id * 2 + 1];
            $b2 = $commands[$id * 2 + 2];
            return ($b1 << 8) + $b2;
        case 102:
            return array_key_exists($id, $variables) ? $variables[$id] : 0;
        case 110:
            return array_key_exists($id, $stack) ? $stack[$id] : 0;
    }
}

function setValue($index, $value) {
    global $commands;
    global $variables;
    global $stack;
    
    $id = $commands[$index + 1];
    switch ($commands[$index]) {
        case 101:
            // константам присваивать нельзя
            break;
        case 102:
            $variables[$id] = $value;
            break;
        case 110:
            $stack[$id] = $value;
            break;
    }
}

function getConstant($index) {
    global $commands;
    
    $b1 = $commands[$index * 2 + 1];
    $b2 = $commands[$index * 2 + 2];
    return ($b1 << 8) + $b2;
}

function callDefFunction($index) {
    global $stream;
    global $commands;
    global $stack;
    
    switch($commands[$index + 1]) {
        case 1: // print
            $a_count = $commands[$index + 2];
            $line = [];
            for($i = 0; $i < $a_count; $i++) {
                $line[] = getValue($index + 4 + $i * 2);
            }
            $stream[] = join(', ', $line);
            $index += 3 + $a_count * 2;
            break;
        case 31: // abs 1
            $a_count = $commands[$index + 2];
            if ($a_count) {
                $stack[$commands[$index + 3]] = abs(getValue($index + 4));
            }            
            $index += 3 + $a_count * 2;
            break;
        case 32: // min 2
            $a_count = $commands[$index + 2];
            if ($a_count > 1) {
                $stack[$commands[$index + 3]] = min(getValue($index + 4), getValue($index + 6));
            }            
            $index += 3 + $a_count * 2;
            break;
        case 33: // max 2
            $a_count = $commands[$index + 2];
            if ($a_count > 1) {
                $stack[$commands[$index + 3]] = max(getValue($index + 4), getValue($index + 6));
            }            
            $index += 3 + $a_count * 2;
            break;
    }
    
    return $index;
}

function callVarFunction($index) {
    global $commands;
    global $stack;
    global $var_variables;
    
    $id = $commands[$index + 1];
    
    $a_count = $commands[$index + 2];
    if ($a_count) {
        $var_variables[$id][0] = getValue($index + 4);
        if ($a_count > 1) {
            $var_variables[$id][1] = getValue($index + 6);
        }
    }
    
    $v = isset($var_variables[$id]) ? $var_variables[$id][0] : 0;
    $stack[$commands[$index + 3]] = $v;
    
    $index += 3 + $a_count * 2;
        
    return $index;
}