<?php
/***
* @title IPP projekt 1 - 2020
* @author: Ondrej Kondek, xkonde04
* @file: scanner.php
***/

//funkcia zisti ci je parameter opcode
function is_opcode($command){

    $command = trim($command);
    $command = strtoupper($command);
    switch($command){
        case "MOVE": case "CREATEFRAME": case "PUSHFRAME": case "POPFRAME": case "DEFVAR":
        case "CALL": case "RETURN": case "PUSHS": case "POPS": case "ADD": case "SUB": case "MUL":
        case "IDIV": case "INT2CHAR": case "STRI2INT": case "READ": case "WRITE": case "CONCAT": case "STRLEN":
        case "GETCHAR": case "SETCHAR": case "TYPE": case "JUMP": case "JUMPIFEQ": case "JUMPIFNEQ": case "EXIT":
        case "DPRINT": case "BREAK": case "LABEL": case "LT": case "GT": case "EQ": case "AND": case "OR":
        case "NOT": return 0;
        default: return -1;
    }
}

// funkcia zisti ci je dany parameter typom
function is_type($command){

    $command = trim($command);
    $command = strtolower($command);
    // ak je to typ vrati 0
    if ((preg_match("/^string$/", $command)) || (preg_match("/^int$/", $command)) ||
    (preg_match("/^bool$/", $command)) || (preg_match("/^nil$/", $command))){
        return 0;
    }
    // ak to neni typ, bude to label -> -1
    else{
        return -1;
    }
}

/*
* 1 = opcode
* 2 = constant
* 3 = variable
* 4 = label
* 5 = type
*/
function get_token(){
    
    global $comments_num;

    $parsed_line = array();
    $token = array();

    if(feof(STDIN)){
        return 99;
    }

    $line = fgets(STDIN);

    // line je komentar - treba zmazat komentarovu cast
    if (preg_match("/#/", $line)){
        $comments_num++;
        $comment = preg_split("/#/", $line, 2);
        array_pop($comment);
        $line = $comment[0];
    }

    // v premennej $line je ulozeny riadok bez komentarov
    // ak by sa line = "" tak by to znamenalo ze tam bol bud komentar alebo whitespace character
    if (trim($line) != ""){
        // rozparsovanie riadku na 2D pole
        $parsed_line = preg_split("/\s/", $line);

        // while kvoli medzeram za prikazom ... dokym tam bude medzera resp. ""
        while (end($parsed_line) == ""){
            array_pop($parsed_line);
        }
        while ($parsed_line[0] == "") {
            array_shift($parsed_line);
        }
    }
    // return 100 ak to je prazdny riadok - bud komentar alebo whitespace character
    else{
        return 100;
    }

    //command je header
    if (strtolower($parsed_line[0]) == ".ippcode20"){
        return 60;
    }

    foreach ($parsed_line as $command){

        //viacero medzier medzi prikazmi
        if ($command == ""){
            continue;
        }
        
        //command je OPCODE
        if (($opcode = is_opcode($command))!= -1){
            array_push($token, array(1,trim($command)));
        }

        //command je premenna alebo constant
        elseif (preg_match("/@/",$command)){
            // command je constant
            if (preg_match("/^(string|bool|int|nil)/", $command)){
                // kontrola lexem v constant
                if ((preg_match("/^string@.*/", $command)) || (preg_match("/^bool@(true|false)$/", $command)) ||
                (preg_match("/^int@[+-]?[0-9]+$/", $command)) || (preg_match("/^nil@nil$/", $command))){
                    //kontrola escape sekvencie v stringu
                    if (preg_match("/^string@.*/", $command)){
                        if (preg_match("/\\\[0-9]{0,2}($|[a-zA-Z\-_$%*&!?])/", $command)){
                            return 23;
                        }
                    }
                    array_push($token, array(2, $command)); // pushnutie typu tokenu - return value
                }
                else{
                    return 23;
                }
            }
            //command je premmenna
            else{
                if (preg_match("/^(TF|GF|LF)@[a-zA-Z\-_$%*&!?][[:alnum:]\-_$%*&!?]*$/", $command)){
                    array_push($token, array(3, $command));
                }
                else{
                    return 23;
                }
            }
        }

        //command je label alebo type alebo header
        else{
            if(preg_match("/^[a-zA-Z\-_$%*&!?][[:alnum:]\-_$%*&!?]*$/", $command)){
                //command je type
                if (is_type($command) == 0){
                    array_push($token, array(5, $command));
                }
                // else command je label
                else{
                    array_push($token, array(4, $command));
                }
            }
            else{
                return 23;
            }
        }
    }

    return $token;
}
?>
