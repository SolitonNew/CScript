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
            vertical-align:top;
            font-family:monospace;
            width:20%;
            padding: 5px;
            border:1px solid #000;
        }
        .view-code div {
            overflow: auto;
            height:280px;
            max-height: 280px;
            width:100%;
        }
    </style>
</head>
<body style="position:relative;padding: 0px;margin:10px;">
    <table style="width:100%;" cellpadding="3">
    <tr>
        <td class="view-code">
            <b>Код:</b><br>
            <form method="POST" style="padding:0px;margin:0px;">
                <textarea name="source" style="width: calc(100% - 5px);" rows="16"><?php echo $source; ?></textarea>
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
                    echo $i.'. '.join(', ', $line).'<br>';
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
                    echo $i.'. ';
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
                        echo $i.'. '.join(', ', $line).'<br>';
                        $i++;
                    }
                ?>
            </div>
        </td>
    </tr>
    </table>
    <div style="word-break: break-word;">
        <b>CONSTANTS: </b>
        <?php echo join(', ', $compiler->_constants) ?>&nbsp;&nbsp;&nbsp;&nbsp;
        <b>VARIABLES: </b>
        <?php echo join(', ', $compiler->_variables) ?>&nbsp;&nbsp;&nbsp;&nbsp;
        <b>STACK SIZE: </b>
        <?php echo $compiler->_max_stack_size; ?>&nbsp;&nbsp;&nbsp;&nbsp;
        <b>BYTES: </b>
        <?php echo (strlen($byte_code) / 2); ?>&nbsp;&nbsp;&nbsp;&nbsp;<br>
        <?php 
            echo $byte_code;
        ?>
    </div>
    <div style="position:relative;display:inline-block;width:100%;height:360px;background-color:#f00;overflow:hidden;">
        <div style="position:absolute;width:100%;height:100%;overflow:auto;background-color:#000;color:#0f0;font-family:monospace;">
            <div style="padding: 10px;">
            <?php
                foreach($compiler->_errors as $line) {
                    echo $line.'<br>';
                }
                foreach($stream as $line) {
                    echo $line.'<br>';
                }
            ?>
            </div>
        </div>
    </div>
</body>    
</html>