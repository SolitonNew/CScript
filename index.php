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
    </style>
</head>
<body style="position:relative;padding: 0px;margin:10px;">
    <table style="width:100%;" cellpadding="3">
    <tr>
        <td class="view-code">
            <b>Код:</b><br>
            <form method="POST" style="padding:0px;margin:0px;">
                <textarea name="source" style="width: calc(100% - 5px);height: calc(50vh - 70px);"><?php echo $source; ?></textarea>
                <br>
                <button>RUN</button>
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
        <td class="view-code">
            <div>
                <b>CONSTANTS: </b><br>
                <?php echo join(', ', $compiler->_constants) ?><br><br>
                <b>VARIABLES: </b><br>
                <?php echo join(', ', $compiler->_variables) ?><br><br>
                <b>STACK SIZE: </b><br>
                <?php echo $compiler->_max_stack_size; ?><br><br>
            </div>
        </td>
        <td class="view-code" colspan="2">
            <div style="word-break: break-word;">
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