<?php

class Compiler {
    public $_lines = []; // распарсеные фрагменты   
    public $_byte_lines = []; // Фрагменты, переведенные в цыфровые коды
    public $_errors = []; // Лог ошибок, выдающий компилятором
    
    public $_constants = [];
    public $_variables = [];
    public $_def_functions = [ // стандартные функции
        'print' => 1,
        'abs' => 31,
        'min' => 32,
        'max' => 33,
    ];
    private $_var_functions = [ // функции управления переменными умного дома
        'VAR_1' => 1,
        'VAR_2' => 2,
        'SHOWER_2_S' => 3,
        'SHOWER_2_R' => 4,
        'WC_1_FAN' => 5,
        'WC_2_FAN' => 6,
        'BEDROOM_3_WC_FAN' => 7,
        'SHOWER_FAN' => 8,
        'MASTER_FAN' => 9,
    ];
    
    public $_byte_code = [];
    public $_stack_size = 0;
    public $_max_stack_size = 0;
    
    private $_breake_stack_size = 0;
    
    
    function __construct($source) {
        $parts = $this->_splitToParts($source);
        $this->_parseBlock($parts);
        $this->_makeDigitalSource();
        if (count($this->_errors)) {
            return ;
        }
        $this->_compailBlock();
    }
    
    /**
     * Метод разбивает текст исходного кода на фрагменты, для обработки
     * 
     * @param type $source
     * @return type
     */
    private function _splitToParts($source) {
        $symbols = ['+', '-', '*', '/', '<', '>', '=', '!', '&', '|', '(', ')', ',', ';', ':', ' ', "\n"];
        
        // Разбиваем код на отдельные фрагменты
        $parts = [];
        $part = '';
        $is_symb = 0;
        $symb_num = 2;
        $prev_is_symb = -1;
        for ($i = 0; $i < strlen($source); $i++) {
            $c = $source[$i];
            if ($c == "\r") {
                $c = ' ';
            }
                        
            if (in_array($c, $symbols)) {
                $is_symb = $symb_num;
            } else {
                $is_symb = 1;
            }

            // Если после ! не стоит =, то это значит, что выполняется инверсия.
            if ($is_symb && $c == '!' && ($i + 1 < strlen($source)) && $source[$i + 1] != '=') {
                $symb_num++;
                $is_symb = $symb_num++;
            } else // Если скобка - то разбиваем на каждую скобку, а также пробелы и переносы
            if ($is_symb && ($c == '(' || $c == ')' || $c == '{' || $c == '}' || $c == ' ' || $c == "\n" || $c == ';')) {
                $symb_num++;
                $is_symb = $symb_num++;
            }

            if ($is_symb != $prev_is_symb) {
                if ($part != '') {
                    $parts[] = $part;
                    if ($this->_checkLastPartAsConstant($parts) == false) {
                        return ;
                    }
                }
                $part = $c;
            } else {
                $part .= $c;
            }        
            $prev_is_symb = $is_symb;
        }

        if ($part != '') {
            $parts[] = $part;
            if ($this->_checkLastPartAsConstant($parts) == false) {
                return ;
            }
        }
               
        return $parts;
    }
    
    private function _checkLastPartAsConstant(&$parts) {
        $digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $index = count($parts) - 1;
        $part = $parts[$index];
        
        if (!in_array($part[0], $digits)) { // первый символ не цыфра - значит дальше не проверяем
            return true;
        }
        
        // проверяем константу полностью. Чтобы были все цыфры
        for($i = 1; $i < strlen($part); $i++) {
            if (!in_array($part[$i], $digits)) {
                $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $index).']: Константа может состоять только из цыфр';
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 
     * 
     * @param type $parts
     * @param type $start
     * @return string
     */
    private function _parseBlock(&$parts, $start = 0, $end = -1) {
        $master = ($start == 0); // Это самый главный блок и он проходится полностью
        
        if ($end == -1) {
            $with_end = false;
            $end = count($parts);
        } else {
            $with_end = true;
        }
        
        $start = $this->_findNextRealPart($parts, $start, $end);
        
        if ($start == -1) {
            return $end;
        }
        
        $with_begin = false;
        if ($parts[$start] == '{') {
            $with_begin = true;
            $start++;
        }
        
        if ($with_end) {
            $multi_block = true;
        } else {
            $multi_block = ($with_begin || $master);
            // $multi_block true  означает, что этот блок многострочный и закончится он только по зеркальному символу
            // $multi_block false означает, что этот блок должен быть однострочным.
        }
        
        $prev_start = $start;
        $start = $this->_findNextRealPart($parts, $start, $end);
        if ($start == -1) {
            $start = $prev_start;
        }
        
        $p_i = $this->_findNextRealPart($parts, $start, $end);
        if ($p_i != -1 && $parts[$p_i] == '}') {
            if ($with_begin == false) {
                $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $p_i).']: Неожиданно встретился символ "}"';
            }
            return count($parts);
        }
        
        for($i = $start; $i < $end; $i++) {
            // пропускаем пустые фрагменты
            $p_i = $this->_findNextRealPart($parts, $i, $end);
            if ($p_i == -1) {
                if ($with_begin) {
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $p_i).']: не найдено "}"';
                }
                return count($parts);
            }
            $i = $p_i;
            
            // ---------------------------
            
            $part = $parts[$i];

            if ($part == '//') { // Начался коментарий
                $p_i = $this->_findNextPart($parts, $i, "\n");
                if ($p_i == -1) {
                    $i = count($parts);
                } else {
                    $i = $p_i;
                }
            } else
            if ($part == '/*') { // Начался коментарий
                $p_i = $this->_findNextPart($parts, $i, "*/");
                if ($p_i == -1) {
                    $i = count($parts);
                } else {
                    $i = $p_i;
                }
            } else
            if ($part == '}') {
                if ($with_begin == false) {
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $i).']: Неожиданно встретился символ "}"';
                }
                return $i + 1;
            } else
            if ($part == 'if') {
                $i = $this->_parseBlockIf($parts, $i);
                if (!$multi_block) return $i;
            } else
            if ($part == 'while') {
                $i = $this->_parseBlockWhile($parts, $i);
                if (!$multi_block) return $i;
            } else
            if ($part == 'for') {
                $i = $this->_parseBlockFor($parts, $i);
                if (!$multi_block) return $i;
            } else
            if ($part == 'switch') {
                $i = $this->_parseBlockSwitch($parts, $i);
                if (!$multi_block) return $i;
            } else { // это просто строчка с коммандами. Она должна заканчиваться ';'
                $p_i = $this->_findNextPart($parts, $i, ';', $end);
                if ($p_i == -1) {
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $i).']: Не найдено ";"';
                    return count($parts);
                }
                $this->_copyPartsToLines($parts, $i, $p_i);
                $i = $p_i;
                if (!$multi_block) return $i;
            }
        }
        
        if ($with_begin) {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, count($parts) - 1).']: не найдено "}"';
        }
        
        return $end;
    }
    
    /**
     * 
     * @param type $index
     */
    private function _calcLineNumber(&$parts, $index) {
        $result = 1;
        for($i = $index; $i >= 0; $i--) {
            if ($parts[$i] == "\n") {
                $result++;
            }
        }
        return $result;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $start
     * @param type $end
     * @param type $tag
     */
    private function _copyPartsToLines(&$parts, $start, $end, $tag = []) {
        $start = $this->_findNextRealPart($parts, $start);
        $line = [];
        for ($i = $start; $i < $end; $i++) {
            $c = $parts[$i];
            if ($c != ' ' && $c != "\n") {
                $line[] = $parts[$i];
            }
        }
        if (count($line)) {
            $this->_lines[] = $line;
        }
    }
    
    /**
     * 
     * @param type $parts
     * @param type $start
     */
    private function _parseBlockIf(&$parts, $start) {
        $ifIndex = $this->_findNextRealPart($parts, $start + 1);
        if ($ifIndex == -1 || $parts[$ifIndex] != '(') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается "("';
            return count($parts);
        }
        
        $i = $this->_findNextRealPart($parts, $ifIndex + 1);
        if ($i == -1 || $parts[$i] == ')') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: выражение блока if не задано';
            return count($parts);
        }
        
        $ifIndex = $this->_findArgumentsEnd($parts, $ifIndex);
        
        if ($ifIndex == -1) {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается ")"';
            return count($parts);
        }
        
        // переносим инструкцию if
        $this->_copyPartsToLines($parts, $start, $ifIndex);
        
        // вкидываем начало блока if
        $this->_lines[] = ['{', 0];
        $label = count($this->_lines) - 1;
        
        $ifBlock = $this->_parseBlock($parts, $ifIndex);
        
        $this->_lines[$label][1] = count($this->_lines) - $label - 1;
        
        $elseIndex = $this->_findNextRealPart($parts, $ifBlock + 1);
        if ($elseIndex > -1 && $parts[$elseIndex] == 'else') {
            $this->_copyPartsToLines($parts, $elseIndex, $elseIndex + 1);
            
            // вкидываем начало блока if
            $this->_lines[] = ['{', 0];
            $label = count($this->_lines) - 1;
            
            $elseBlock = $this->_parseBlock($parts, $elseIndex + 1);
            
            $this->_lines[$label][1] = count($this->_lines) - $label - 1;
            return $elseBlock;
        } else {
            return $ifBlock;
        }        
    }
    
    /**
     * 
     * @param type $parts
     * @param type $start
     */
    private function _parseBlockWhile(&$parts, $start) {
        $whileIndex = $this->_findNextRealPart($parts, $start + 1);
        if ($whileIndex == -1 || $parts[$whileIndex] != '(') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается "("';
            return count($parts);
        }
        
        $i = $this->_findNextRealPart($parts, $whileIndex + 1);
        if ($i == -1 || $parts[$i] == ')') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: выражение блока while не задано';
            return count($parts);
        }
        
        $whileIndex = $this->_findArgumentsEnd($parts, $whileIndex);
        
        if ($whileIndex == -1) {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается ")"';
            return count($parts);
        }        
        
        // переносим инструкцию while
        $this->_copyPartsToLines($parts, $start, $whileIndex);
        
        // вкидываем начало блока while
        $this->_lines[] = ['{', 0];
        $label = count($this->_lines) - 1;
        
        $whileBlock = $this->_parseBlock($parts, $whileIndex);
        
        $this->_lines[$label][1] = count($this->_lines) - $label - 1;
        
        return $whileBlock;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $start
     */
    private function _parseBlockFor(&$parts, $start) {
        $forIndex = $this->_findNextRealPart($parts, $start + 1);
        if ($forIndex == -1 || $parts[$forIndex] != '(') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается "("';
            return count($parts);
        }
        
        $i = $this->_findNextRealPart($parts, $forIndex + 1);
        if ($i == -1 || $parts[$i] == ')') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: выражение блока for не задано';
            return count($parts);
        }
        
        $forIndex = $this->_findArgumentsEnd($parts, $forIndex);
        
        if ($forIndex == -1) {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается ")"';
            return count($parts);
        }
        
        // переносим инструкцию while
        $this->_copyPartsToLines($parts, $start, $forIndex);
        
        // вкидываем начало блока while
        $this->_lines[] = ['{', 0];
        $label = count($this->_lines) - 1;
        
        $forBlock = $this->_parseBlock($parts, $forIndex);
        
        $this->_lines[$label][1] = count($this->_lines) - $label - 1;
        
        return $forBlock;
    }
    
    private function _parseBlockSwitch(&$parts, $start) {
        $digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $switchIndex = $this->_findNextRealPart($parts, $start + 1);
        if ($switchIndex == -1 || $parts[$switchIndex] != '(') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается "("';
            return count($parts);
        }
        
        $i = $this->_findNextRealPart($parts, $switchIndex + 1);
        if ($i == -1 || $parts[$i] == ')') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: выражение блока switch не задано';
            return count($parts);
        }
        
        $switchIndex = $this->_findArgumentsEnd($parts, $switchIndex);
        
        if ($switchIndex == -1) {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: ожидается ")"';
            return count($parts);
        }
        
        // переносим инструкцию switch
        $this->_copyPartsToLines($parts, $start, $switchIndex);
        
        $this->_lines[] = ['{', 0];
        $blockSizeIndex = count($this->_lines) - 1;
        
        $index = $this->_findNextRealPart($parts, $switchIndex + 1);
        
        if ($index == -1 || $parts[$index] != '{') {
            $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $index).']: ожидается "{"';
            return count($parts);
        }
        
        for($i = $index + 1; $i < count($parts); $i++) {
            $i = $this->_findNextRealPart($parts, $i);
            if ($i == -1) {
                break;
            }
            
            $part = $parts[$i];
            
            if ($part == '}') {
                $this->_lines[$blockSizeIndex][1] = count($this->_lines) - $blockSizeIndex - 1;
                return $i;
            } else
            if ($part == 'case') {                
                // Проверяем адекватность оформления блока
                $caseIndex = $i;
                $i = $this->_findNextRealPart($parts, $i + 1);
                if ($i == -1 || !in_array($parts[$i][0], $digits)) {
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $caseIndex).']: Ожидается константа';
                    return count($parts);
                }
                
                $i = $this->_findNextRealPart($parts, $i + 1);
                if ($i == -1 || $parts[$i] != ':') {
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $caseIndex).']: Ожидается ":"';
                    return count($parts);
                }
                
                $this->_copyPartsToLines($parts, $caseIndex, $i);
                
                // Проверяем содержимое уровня
                $i = $this->_parseBlockSwitchDetail($parts, $i + 1);
            } else
            if ($part == 'default') {
                $defaultIndex = $i;
                $i = $this->_findNextRealPart($parts, $i + 1);
                if ($i == -1 || $parts[$i] != ':') {
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $defaultIndex).']: Ожидается ":"';
                    return count($parts);
                }
                
                $this->_copyPartsToLines($parts, $defaultIndex, $i);
                
                // Проверяем содержимое уровня
                $i = $this->_parseBlockSwitchDetail($parts, $i + 1);
            } else {
                
            }
        }
        
        
        $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: Ожидается "}"';
        return count($parts);
    }
    
    /**
     * 
     * @param type $parts
     * @param type $start
     */
    private function _parseBlockSwitchDetail(&$parts, $start) {
        $start = $this->_findNextRealPart($parts, $start);
        if ($start == -1) {
            return $start;
        }
        
        $in_count = 0;
        for ($i = $start; $i < count($parts); $i++) {
            $part = $parts[$i];
            if ($part == '{') {
                $in_count++;
            } else
            if ($in_count == 0 && ($part == 'case' || $part == 'default' || $part == '}' )) {
                $this->_parseBlock($parts, $start, $i);
                return $i - 1;
            } else
            if ($part == '}') {    
                $in_count--;
                if ($in_count < 0) { // 
                    $this->_errors[] = 'ERROR ['.$this->_calcLineNumber($parts, $start).']: Неожиданно встретился "}"';
                    return count($parts);
                }
            }
        }
        
        return count($parts);
    }
    
    /**
     * Возвращает инлдекс следующего фрагмент, которые не пробел.
     * 
     * @param type $parts
     * @param type $start
     */
    private function _findNextRealPart(&$parts, $start, $end = -1) {
        if ($end == -1) {
            $end = count($parts);
        }
        
        if ($start < 0) {
            $start = 0;
        }
        
        for ($i = $start; $i < min($end, count($parts)); $i++) {
            if ($parts[$i] != ' ' && $parts[$i] != "\n") {
                return $i;
            }
        }
        return -1;
    }
    
    /**
     * Возвращает индекс фрагмента, заданого в параметре
     * 
     * @param type $parts
     * @param type $start
     * @return type
     */
    private function _findNextPart(&$parts, $start, $char, $end = -1) {
        if ($end == -1) {
            $end = count($parts);
        }
        for ($i = $start; $i < $end; $i++) {
            if ($parts[$i] == $char) {
                return $i;
            }
        }
        return -1;
    }
    
    /**
     * Выполняет поиск закрытой скобки после указанного индекса с подсчетом скобок внутри.
     * 
     * @param type $parts
     * @param type $start
     * @return type
     */
    private function _findArgumentsEnd(&$parts, $start) {
        if ($parts[$start] != '(') {
            return -2;
        }
        
        $in_count = 0;
        for($i = $start; $i < count($parts); $i++) {
            if ($parts[$i] == '(') {
                $in_count++;
            } else
            if ($parts[$i] == ')') {
                $in_count--;
                if ($in_count == 0) {
                    return $i + 1;
                }
            }
        }
        return -1;
    }
    
    /**
     * Кодируем массив _lines кодами и формируем подмассивы для каждого фрагмента.
     * Для кодирования используем справочник. Все что не в справочнике считается:
     *    - константой (если первый символ цыфра)
     *    - вызов функции (если после фрагмента идет "(" )
     *    - переменной во всех остальных случаях
     * 
     * @param type $parts
     */
    private function _makeDigitalSource() {
        $keywords = [
            // 'PROGRAM FINISH' => 0
            '(' => 1,
            ')' => 2,
            ';' => 3,
            ',' => 4,
            '=' => 5,

            
            'if' => 11,
            'else' => 12,
            'for' => 13,
            'while' => 14,
            'break' => 15,
            'switch' => 16,
            'case' => 17,
            'default' => 18,
            //'goto' => 20,
            

            '+' => 21,
            '-' => 22,
            '*' => 23,
            '/' => 24,
            '+=' => 25,
            '-=' => 26,
            '++' => 27,
            '--' => 28,

            '>' => 31,
            '>=' => 32,
            '<' => 33,
            '<=' => 34,
            '==' => 35,
            '!=' => 36,

            '!' => 41,
            '&&' => 42,
            '||' => 43,

            // '{' => 91,
            // '}' => 92,

            // 'CONSTANT' => 101,
            // 'VARIABLE' => 102,
            // 'DEF FUNCTION' => 103,
            // 'VAR FUNCTION' => 104,
            // 
            // 'STACK VARIABLE' => 110,

        ];
        
        $digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $this->_byte_lines = [];
        foreach($this->_lines as $line) {
            $byte_line = [];
            if ($line[0] == '{') { // Это заглавие блока, его кодируем отдельно
                $byte_line[] = [91, $line[1]];
            } else
            for($i = 0; $i < count($line); $i++) {
                $part = $line[$i];
                if (array_key_exists($part, $keywords)) { // это справочные значения
                    $byte_line[] = [$keywords[$part]];
                } else
                if (in_array($part[0], $digits)) { // это константа
                    $c_index = 0;
                    if (in_array($part, $this->_constants)) {
                        $c_index = array_search($part, $this->_constants);
                    } else {
                        $this->_constants[] = $part;
                        $c_index = count($this->_constants) - 1;
                    }
                    $byte_line[] = [101, $c_index];
                } else
                if ($i < count($line) - 1 && $line[$i + 1] == '(') { // это вызов функции
                    if (array_key_exists($part, $this->_def_functions)) { // это стандартная функция
                        $byte_line[] = [103, $this->_def_functions[$part]];
                    } else
                    if (array_key_exists($part, $this->_var_functions)) { // это спец функция управления умным домом
                        $byte_line[] = [104, $this->_var_functions[$part]];
                    } else { // это ошибка
                        $this->_errors[] = 'ERROR: функция "'.$part.'" не найдена';
                        return ;
                    }
                } else { // это переменная
                    $v_index = 0;
                    if (in_array($part, $this->_variables)) {
                        $v_index = array_search($part, $this->_variables);
                    } else {
                        $this->_variables[] = $part;
                        $v_index = count($this->_variables) - 1;
                    }
                    $byte_line[] = [102, $v_index];
                }
            }
            
            $this->_byte_lines[] = $byte_line;
        }
    }
    
    /**
     * 
     * @param int $start
     * @param type $end
     */
    public function _compailBlock($start = -1, $end = -1) {
        if ($start == -1) {
            $start = 0;
        }

        if ($end == -1) {
            $end = count($this->_byte_lines);
        }

        for($i = $start; $i < $end; $i++) {
            $line = $this->_byte_lines[$i];
            switch($this->_getPartType($line, 0)) {
                case 11: // if else
                    $i = $this->_compailIfElse($line, $i);
                    break;
                case 13: // for
                    $i = $this->_compailFor($line, $i);
                    break;
                case 14: // while
                    $i = $this->_compailWhile($line, $i);
                    break;
                case 15: // break
                    if (count($this->_breake_stack_size)) {
                        $this->_byte_code[] = [
                            15,
                            $this->_breake_stack_size,
                            0
                        ];
                    }
                    break;
                case 16: // switch
                    $i = $this->_compailSwitch($line, $i);
                    break;
                default:                    
                    $prev_stack_size = $this->_stack_size;
                    $this->_compailExpression($line);
                    $this->_stack_size = $prev_stack_size;
            }
        }
    }
    
    /**
     * 
     * @param type $parts
     * @param type $index
     * @return type
     */
    private function _getPartType(&$parts, $index) {
        return $parts[$index][0];
    }
    
    /**
     * 
     * @param type $parts
     * @param int $start
     * @param type $end
     * @return type
     */
    private function _compailExpression(&$parts, $start = -1, $end = -1) {
        if ($start == -1) {
            $start = 0;
        }
        if ($end == -1) {
            $end = count($parts);
        }
        
        if (count($parts) == 0 || $end - $start < 1) {
            return ;
        }
        
        // Запоминаем размер использованого стека, что бы по зачершению вернуть все лишнее
        $prev_stack_size = $this->_stack_size;
        
        // Разбираемся с функциями
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 103: // DEF FUNCTIONS
                case 104: // VAR FUNCTIONS
                    $func_index = $i;
                    $i = $this->_compailFunctionArguments($parts, $i, $type);
                    $parts[$func_index] = [110, $this->_stack_size - 1];                    
                    break;
            }
        }
        
        $in_start = -1;
        $in_count = 0;
        // Разбираемся со скобками
        for ($i = $start; $i < $end; $i++) {
            switch($this->_getPartType($parts, $i)) {
                case 1: // '(':
                    if ($in_count == 0) {
                        $in_start = $i;
                    }
                    $in_count++;
                    break;
                case 2: // ')':
                    $in_count--;
                    // Если закрытая скобка последняя - обрабатываем внутренности
                    if ($in_count == 0) {
                        if ($i > $in_start + 1) {
                            $this->_compailExpression($parts, $in_start + 1, $i);
                        }
                        $parts[$in_start] = [0, 0];
                        $parts[$i] = [0, 0];
                    }
                    break;
            }
        }
        
        // Ищем инверсии
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 41: // '!':
                    $b = $this->_getRightValIndex($parts, $i, $start, $end);
                    if ($b >= 0) {
                        $tv = $this->_stack_size++;
                        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
                        $this->_byte_code[] = [
                            $type,
                            $tv,
                            $parts[$b][0],
                            $parts[$b][1],
                        ];
                        $parts[$i] = [0, 0];
                        $parts[$b] = [110, $tv];
                    }
                    break;
            }
        }

        // Ищем умножение / деление
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 23: // '*':
                case 24: // '/':
                    $a = $this->_getLeftValIndex($parts, $i, $start, $end);
                    $b = $this->_getRightValIndex($parts, $i, $start, $end);
                    if ($a >= 0 && $b >= 0) {
                        $tv = $this->_stack_size++;
                        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
                        $this->_byte_code[] = [
                            $type,
                            $tv,
                            $parts[$a][0],
                            $parts[$a][1],
                            $parts[$b][0],
                            $parts[$b][1],
                        ];
                        $parts[$a] = [110, $tv];
                        $parts[$i] = [0, 0];
                        $parts[$b] = [0, 0];
                    }
                    break;
            }
        }

        // Ищем сложение и вычитание
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 21: // '+':
                case 22: // '-':
                    $a = $this->_getLeftValIndex($parts, $i, $start, $end);
                    $b = $this->_getRightValIndex($parts, $i, $start, $end);
                    if ($a >= 0 && $b >= 0) {
                        $tv = $this->_stack_size++;
                        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
                        $this->_byte_code[] = [
                            $type,
                            $tv,
                            $parts[$a][0],
                            $parts[$a][1],
                            $parts[$b][0],
                            $parts[$b][1],
                        ];
                        $parts[$a] = [110, $tv];
                        $parts[$i] = [0, 0];
                        $parts[$b] = [0, 0];
                    }
                    break;
            }
        }

        // Логические сравнения
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 42: // '&&':
                case 43: // '||':
                    $a = $this->_getLeftValIndex($parts, $i, $start, $end);
                    $b = $this->_getRightValIndex($parts, $i, $start, $end);
                    if ($a >= 0 && $b >= 0) {
                        $tv = $this->_stack_size++;
                        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
                        $this->_byte_code[] = [
                            $type,
                            $tv,
                            $parts[$a][0],
                            $parts[$a][1],
                            $parts[$b][0],
                            $parts[$b][1],
                        ];
                        $parts[$a] = [110, $tv];
                        $parts[$i] = [0, 0];
                        $parts[$b] = [0, 0];
                    }
                    break;
            }
        }

        // Ищем операции сравнения
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 31: // '>':
                case 32: // '>=':
                case 33: // '<':
                case 34: // '<=':
                case 35: //'==':
                case 36: //'!=':
                    $a = $this->_getLeftValIndex($parts, $i, $start, $end);
                    $b = $this->_getRightValIndex($parts, $i, $start, $end);
                    if ($a >= 0 && $b >= 0) {
                        $tv = $this->_stack_size++;
                        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
                        $this->_byte_code[] = [
                            $type,
                            $tv,
                            $parts[$a][0],
                            $parts[$a][1],
                            $parts[$b][0],
                            $parts[$b][1],
                        ];
                        $parts[$a] = [110, $tv];
                        $parts[$i] = [0, 0];
                        $parts[$b] = [0, 0];
                    }
                    break;
            }
        }

        // Ищем присваивание
        for ($i = $start; $i < $end; $i++) {
            $type = $this->_getPartType($parts, $i);
            switch($type) {
                case 5: // '=':
                case 25: // '+='
                case 26: // '-='
                    $a = $this->_getLeftValIndex($parts, $i, $start, $end);
                    $b = $this->_getRightValIndex($parts, $i, $start, $end);
                    if ($a >= 0 && $b >= 0) {
                        if ($parts[$a][0] == 102 || $parts[$a][0] == 110) {
                            $this->_byte_code[] = [
                                $type,
                                $parts[$a][0],
                                $parts[$a][1],
                                $parts[$b][0],
                                $parts[$b][1],
                            ];
                        }
                        $parts[$i] = [0, 0];
                        $parts[$b] = [0, 0];
                    }
                    break;
                case 27: // '++'
                case 28: // '--'
                    $a = $this->_getLeftValIndex($parts, $i, $start, $end);
                    if ($a >= 0) {
                        if ($parts[$a][0] == 102 || $parts[$a][0] == 110) {
                            $tv = $this->_stack_size++;
                            $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
                            $this->_byte_code[] = [
                                $type,
                                $tv,
                                $parts[$a][0],
                                $parts[$a][1],
                            ];
                            $parts[$a] = [110, $tv];
                        }
                        $parts[$i] = [0, 0];
                    }
                    break;
            }
        }

        $res_index = $this->_getRightValIndex($parts, $start - 1, $start, $end);
        
        if ($res_index == -1) {
            return $end;
        }
        
        if ($parts[$res_index][0] == 110) { // Это значит, что результат был записан в стек и его мы пока не можем подчистить.
            $this->_stack_size = $prev_stack_size + 1;
        } else {
            $this->_stack_size = $prev_stack_size;
        }
        
        return $res_index;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $index
     * @param type $start
     * @param type $end
     * @return type
     */
    private function _getLeftValIndex(&$parts, $index, $start, $end) {
        for ($i = $index - 1; $i >= $start; $i--) {
            if ($this->_getPartType($parts, $i) && $this->_getPartType($parts, $i) != 41 /* '!' */) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * 
     * @param type $parts
     * @param type $index
     * @param type $start
     * @param type $end
     * @return type
     */
    private function _getRightValIndex(&$parts, $index, $start, $end) {
        for ($i = $index + 1; $i < $end; $i++) {
            if ($this->_getPartType($parts, $i) && $this->_getPartType($parts, $i) != 41 /* '!' */) {
                return $i;
            }
        }    
        return -1;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $index
     * @param type $type
     * @return int
     */
    private function _compailFunctionArguments(&$parts, $index, $type) {
        $command = [
            $type,
            $parts[$index][1],
            0, // количество аргументов
            $this->_stack_size++,
        ];
        
        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
        $a_start = $index + 2;
        $a_count = 0;
        // Собираем аргументы к функции
        $in_count = 0;
        for ($i = $index + 1; $i < count($parts); $i++) {
            if ($parts[$i][0] == 1) { // '('
                $in_count++;
            } else
            if ($parts[$i][0] == 2) { // ')'
                $in_count--;
                if ($in_count == 0) { // Нашли конец списка аргументов
                    if ($i - $a_start) {
                        $a_i = $this->_compailExpression($parts, $a_start, $i);
                        $command[] = $parts[$a_i][0];
                        $command[] = $parts[$a_i][1];
                        $a_count++;
                    }
                    $command[2] = $a_count;
                    $this->_byte_code[] = $command;
                    return $i;
                }
            } else
            if ($in_count == 1 && $parts[$i][0] == 4) { // Нашли разделитель аргументов. Сразу его посчитаем.
                $a_i = $this->_compailExpression($parts, $a_start, $i);
                $a_start = $i + 1;
                $command[] = $parts[$a_i][0];
                $command[] = $parts[$a_i][1];
                $a_count++;
            }
        }
        
        return count($parts);
    }
    
    /**
     * 
     * @return type
     */
    private function _calcRealEndAddr() {
        $result = 0;
        for($i = 0; $i < count($this->_byte_code); $i++) {
            $result += count($this->_byte_code[$i]);
        }
        return $result;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $index
     * @return type
     */
    private function _compailIfElse(&$parts, $index) {
        $prev_stack_size = $this->_stack_size;
        
        $tv = $this->_stack_size++;
        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
        
        $ifIndex = $this->_compailExpression($parts, 1);
        $index++;
        $block_if_count = $this->_byte_lines[$index++][0][1]; // Длина блока If
        if ($block_if_count) {
            $goto_i = count($this->_byte_code);
            $this->_byte_code[] = [
                11, // код условия
                $tv,
                $parts[$ifIndex][0], // тип аргумента
                $parts[$ifIndex][1], // индекс аргумента
                0, // байт 1 смещения если FALSE
                0  // байт 2 смещения если FALSE
            ];
            $start_block = count($this->_byte_code);
            $this->_compailBlock($index, $index + $block_if_count);            
            $this->_byte_code[$goto_i][4] = $this->_calcRealEndAddr();
        }
        $index += $block_if_count - 1;
        $block_else_count = 0;
        if ($index < count($this->_byte_lines) - 2 && $this->_byte_lines[$index + 1][0][0] == 12) { // Нашли условие else
            $index += 2;
            $block_else_count = $this->_byte_lines[$index++][0][1]; // Длина блока else
            if ($block_else_count) {
                // Здесь будет конструкция вида
                // 12, $ifIndex, $block_if_count
                // По последнее допишем, когда закнчим обрабатівать вложенній блок
                $goto_i = count($this->_byte_code);
                $this->_byte_code[] = [
                    12,
                    $tv,
                    0,
                    0
                ];
                $start_block = count($this->_byte_code);
                $this->_compailBlock($index, $index + $block_else_count);
                $this->_byte_code[$goto_i][2] = $this->_calcRealEndAddr();
            }
            $index += $block_else_count - 1;
        }
        
        $this->_stack_size = $prev_stack_size;
        
        return $index;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $index
     * @return type
     */
    private function _compailFor(&$parts, $index) {
        // Регистрируем цикл в стэке
        $this->_breake_stack_size++;
        
        $prev_stack_size = $this->_stack_size;
        
        $tv = $this->_stack_size++;
        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
        
        // Собираем аргументы к конструкции. Вычисляем их положения
        $args = [2];
        $in_count = 0;
        for ($i = 1; $i < count($parts); $i++) {
            if ($parts[$i][0] == 1) { // '('
                $in_count++;
            } else
            if ($parts[$i][0] == 2) { // ')'
                $in_count--;
                if ($in_count == 0) { // Нашли конец списка аргументов
                    $args[] = $i;
                    break;
                }
            } else
            if ($in_count == 1 && $parts[$i][0] == 3) { // Нашли разделитель аргументов. Сразу его посчитаем.
                $args[] = $i + 1;
            }
        }
        
        // Байт код инициализации
        $this->_compailExpression($parts, $args[0], $args[1] - 1);
        
        $goto_for = $this->_calcRealEndAddr(); // это адрес начала цыкла. сюда прыгаем после каждой итэрации
        
        $start_while = count($this->_byte_code); // Это самое начало условия. Его выполнять нужно на каждой итерации
        $expression_index = $this->_compailExpression($parts, $args[1], $args[2]); // Адрес результата условия
        $index++;
        $block_count = $this->_byte_lines[$index++][0][1]; // Длина блока while
        // Здесь будет конструкция вида
        // 11, $query_index, $block_count
        // Но последнее допишем, когда закнчим обрабатівать вложенній блок
        // Превращаем условный цыкл в условие с блоком и в конец блока 
        // допишем безусловный переход назад на $block_count
        $goto_exit = count($this->_byte_code);
        $this->_byte_code[] = [
            11,
            $tv,
            $parts[$expression_index][0],
            $parts[$expression_index][1],
            0,
            0
        ];
        $start_block = count($this->_byte_code);
        $this->_compailBlock($index, $index + $block_count);
        $index += $block_count - 1;
        
        // Дописываем выражение счетчика
        $this->_compailExpression($parts, $args[2], $args[3]);
        
        // Дописываем в конец блока безусловный переход на начало
        $this->_byte_code[] = [
            20, // goto
            $goto_for,
            0
        ];
        
        $exit = $this->_calcRealEndAddr();
        $this->_byte_code[$goto_exit][4] = $exit;
        
        $this->_stack_size = $prev_stack_size;
        
        // подчищаем стэк break
        for($i = $start_while; $i < count($this->_byte_code); $i++) {
            if ($this->_byte_code[$i][0] == 15 && $this->_byte_code[$i][1] == $this->_breake_stack_size) {
                $this->_byte_code[$i] = [
                    20, 
                    $exit,
                    0
                ];
            }
        }
        $this->_breake_stack_size--;
        
        return $index;
    }
    
    /**
     * 
     * @param type $parts
     * @param type $index
     * @return type
     */
    private function _compailWhile(&$parts, $index) {
        $this->_breake_stack_size++;
        
        $prev_stack_size = $this->_stack_size;
        
        $tv = $this->_stack_size++;
        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
        
        $goto_while = $this->_calcRealEndAddr(); // это адрес начала цыкла. сюда прыгаем после каждой итэрации
        
        $start_while = count($this->_byte_code); // Это самое начало условия. Его выполнять нужно на каждой итерации
        $expression_index = $this->_compailExpression($parts, 1); // Адрес результата условия
        $index++;
        $block_count = $this->_byte_lines[$index++][0][1]; // Длина блока while
        // Здесь будет конструкция вида
        // 11, $query_index, $block_count
        // Но последнее допишем, когда закнчим обрабатівать вложенній блок
        // Превращаем условный цыкл в условие с блоком и в конец блока 
        // допишем безусловный переход назад на $block_count
        $goto_exit = count($this->_byte_code);
        $this->_byte_code[] = [
            11, 
            $tv,
            $parts[$expression_index][0],
            $parts[$expression_index][1],
            0,
            0
        ];
        $start_block = count($this->_byte_code);
        $this->_compailBlock($index, $index + $block_count);
        $index += $block_count - 1;
        // Дописываем в конец блока безусловный переход на начало
        $this->_byte_code[] = [
            20, // goto
            $goto_while,
            0
        ];
        
        $exit = $this->_calcRealEndAddr();
        $this->_byte_code[$goto_exit][4] = $exit;
        
        $this->_stack_size = $prev_stack_size;
        
        // подчищаем стэк break
        for($i = $start_while; $i < count($this->_byte_code); $i++) {
            if ($this->_byte_code[$i][0] == 15 && $this->_byte_code[$i][1] == $this->_breake_stack_size) {
                $this->_byte_code[$i] = [
                    20, 
                    $exit,
                    0
                ];
            }
        }
        $this->_breake_stack_size--;
        
        return $index;
    }
    
    private function _compailSwitch(&$parts, $index) {
        $this->_breake_stack_size++;
        
        $prev_stack_size = $this->_stack_size;
        
        $expression_index = $this->_compailExpression($parts, 1); // Адрес результата условия
        
        $tv = $this->_stack_size++; // Выделяем переменную в стеке для хранения условия
        $this->_max_stack_size = max($this->_max_stack_size, $this->_stack_size);
        
        $this->_byte_code[] = [
            5, 
            110,
            $tv,
            $parts[$expression_index][0], 
            $parts[$expression_index][1],
        ];
        
        $index++;
        $block_count = $this->_byte_lines[$index++][0][1];
        
        // проанализируем список условий и отберем метки
        $cases = [];
        for ($i = $index; $i < $index + $block_count; $i++) {
            $line = $this->_byte_lines[$i];
            if ($line[0][0] == 91) { // встретился блок - пропустим его
                $i += $line[0][1];
            } else
            if ($line[0][0] == 17 || $line[0][0] == 18) { // встретился case или default
                $cases[] = [$i];
            }
        }
                
        // формируем условия кейсов с прямыми переходами
        foreach($cases as &$cc) {
            $case = $this->_byte_lines[$cc[0]];
            if ($case[0][0] == 17) {
                // регаем константу и получаем ее индекс
                $c_index = $case[1][1];
                // ------------------------
                $this->_byte_code[] = [
                    17,                // прямой переход
                    $tv, // указатель на выражение сравнения в стэке
                    $c_index,          // указатель на константу кейса
                    0,                 // смещение если условие выполнено
                    0
                ];
                $cc[1] = count($this->_byte_code) - 1;
            } else
            if ($case[0][0] == 18) {
                $this->_byte_code[] = [
                    20, // переход по умолчанию. если дошли сюда, значит ничего не подошло
                    0,  // смещение на начало секции
                    0
                ];
                $cc[1] = count($this->_byte_code) - 1;
            }
        }
                
        // добавим безусловный переход в конец. Если сюда дошли, значит ничего не делам
        $finish_index = count($this->_byte_code);
        $this->_byte_code[] = [
            20,
            0,
            0
        ];
        
        $start_switch = count($this->_byte_code);
        
        // Выполняем компиляцию блоков
        $cases[] = [$index + $block_count];
        
        for ($i = 0; $i < count($cases) - 1; $i++) {
            $c_start = $cases[$i][0] + 1;
            $c_end = $cases[$i + 1][0];
            
            $block_addr = $this->_calcRealEndAddr(); // сохраняем адрес блока
            
            $bc_i = $cases[$i][1];
            if ($this->_byte_code[$bc_i][0] == 17) {
                $this->_byte_code[$bc_i][3] = $block_addr;
            } else
            if ($this->_byte_code[$bc_i][0] == 20) {
                $this->_byte_code[$bc_i][1] = $block_addr;
            }
            
            $this->_compailBlock($c_start, $c_end);
        }
        
        // определяем адрес выхода из конструкции и назначаем адрес
        $exit = $this->_calcRealEndAddr(); // это адрес выхода
        $this->_byte_code[$finish_index][1] = $exit;
        
        $this->_stack_size = $prev_stack_size;
        
        // подчищаем стэк break
        for($i = $start_switch; $i < count($this->_byte_code); $i++) {
            if ($this->_byte_code[$i][0] == 15 && $this->_byte_code[$i][1] == $this->_breake_stack_size) {
                $this->_byte_code[$i] = [
                    20, 
                    $exit,
                    0
                ];
            }
        }
        $this->_breake_stack_size--;
        
        return $index + $block_count - 1;
    }
    
    /**
     * 
     * @return type
     */
    public function genByteCode() {
        $bytes = [];
        $bytes[] = $this->_byte2hex(count($this->_constants));
        foreach($this->_constants as $const) {
            $b1 = ($const & 0xff00) >> 8;
            $b2 = ($const & 0x00ff);            
            $bytes[] = $this->_byte2hex($b1);
            $bytes[] = $this->_byte2hex($b2);
        }
        
        foreach($this->_byte_code as $line) {
            // Разбиваем двухбайтовые адреса
            switch($line[0]) {
                case 11: // if
                    $a = $line[4];
                    $line[4] = ($a & 0xff00) >> 8;
                    $line[5] = ($a & 0x00ff);
                    break;
                case 12: // else
                    $a = $line[2];
                    $line[2] = ($a & 0xff00) >> 8;
                    $line[3] = ($a & 0x00ff);
                    break;
                case 17: // case
                    $a = $line[3];
                    $line[3] = ($a & 0xff00) >> 8;
                    $line[4] = ($a & 0x00ff);
                    break;
                case 20: // goto
                    $a = $line[1];
                    $line[1] = ($a & 0xff00) >> 8;
                    $line[2] = ($a & 0x00ff);
                    break;
                case 15: // break
                    $a = $line[1];
                    $line[1] = ($a & 0xff00) >> 8;
                    $line[2] = ($a & 0x00ff);
                    break;
                
            }
            
            foreach($line as $part) {
                $bytes[] = $this->_byte2hex($part);
            }
        }
        
        return join('', $bytes);
    }
    
    /**
     * 
     * @param type $byte
     * @return string
     */
    private function _byte2hex($byte) {
        $h = dechex($byte);
        if (strlen($h) == 1) {
            $h = '0'.$h;
        }
        return $h;
    }
    
}