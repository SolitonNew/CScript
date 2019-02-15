<?php
    include 'Compiler.php';
    include 'interpreter.php';
    
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $compiler = new Compiler($source);
    
    $byte_code = $compiler->genByteCode();
    
    run($byte_code);
    
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .view-code {
            vertical-align: top;
            font-family: monospace;
            width: 20%;
            padding: 5px;
            border: 1px solid #000;
        }
        .view-code div {
            overflow: auto;
            height: calc(50vh - 40px);
            max-height: calc(50vh - 40px);
            width: 100%;
        }
        
        button {
            background-color: #000;
            color: #0f0;
            border: 1px solid #000;
            padding: 5px 25px;
            cursor: pointer;
        }
                
        button:active {
            background-color: #333;
        }
        
    </style>
</head>
<body style="position:relative;padding: 0px;margin:10px;">
    <table style="width:100%;" cellpadding="3">
    <tr>
        <td class="view-code" rowspan="2" style="background-color: #eee;">
            <b>Код:</b><br>
            <form method="POST" style="padding:0px;margin:0px;">
                <textarea name="source" style="width: calc(100% - 11px);height: calc(100vh - 110px);border: 1px solid #333;padding:5px;"><?php echo $source; ?></textarea>
                <br>
                <button><b>RUN</b></button>
            </form>
        </td>
        <td class="view-code">
            <b>Фрагменты:</b><br>
            <div>
                <?php 
                $i = 0;
                foreach($compiler->_lines as $line) {
                    echo '<b>'.$i.'.</b> '.join(', ', $line).'<br>';
                    $i++;
                }
                ?>
            </div>
        </td>
        <td class="view-code">
            <b>Конструкции:</b><br>
            <div>
                <?php 
                $i = 0;
                foreach($compiler->_byte_lines as $line) {
                    echo '<b>'.$i.'.</b> ';
                    foreach($line as $part) {
                        echo '['.join(',', $part).']';
                    }
                    echo '<br>';
                    $i++;
                }
                ?>
            </div>
        </td>
        <td class="view-code">
            <b>Инструкции:</b><br>
            <div>
                <?php
                    $i = 1;
                    foreach($compiler->_byte_code as $line) {
                        echo '<b>'.$i.'.</b> '.join(', ', $line).'<br>';
                        $i++;
                    }
                ?>
            </div>
        </td>
    </tr>
    <tr>
        <td class="view-code" style="background-color:#000;color:#0f0;font-family:monospace;">
            <div>
                <?php
                    foreach($compiler->_errors as $line) {
                        echo $line.'<br>';
                    }
                    foreach($stream as $line) {
                        echo $line.'<br>';
                    }
                ?>
            </div>
        </td>
        <td class="view-code" colspan="2">
            <div style="word-break: break-word;">
                <b>CONSTANTS: </b><?php echo join(', ', $compiler->_constants) ?><br>
                <b>VARIABLES: </b><?php echo join(', ', $compiler->_variables) ?><br>
                <b>STACK SIZE: </b><?php echo $compiler->_max_stack_size; ?><br>
                <br>
                <b>BYTE CODE [<?php echo (strlen($byte_code) / 2); ?>]:</b><br>
                <?php 
                    echo $byte_code;
                ?>
            </div>
        </td>
    </tr>
    </table>
</body>    
</html>