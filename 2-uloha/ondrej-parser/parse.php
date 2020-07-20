<?php
/***
* @title IPP projekt 1 - 2020
* @author: Ondrej Kondek, xkonde04
* @file: parse.php
***/

// funkcia get_token() v module scanner.php
include 'scanner.php';

// nacitanie, spracovanie argumentov a kontrola
function load_arg(){
    global $argc;
    global $argv;

    global $f_stats;
    global $stats;
    global $loc;
    global $comments;
    global $jumps;
    global $labels;
    

    if ($argc == 1){
        return;
    }

    // ulozenie argumentov do jednoducheho pola bez prveho argumentu - nazov suboru
    $args = array();
    for ($i = 1; $i < $argc; $i++){
        $args[$i-1] = $argv[$i];
    }

    foreach ($args as $arg){
        
        if ($arg == "--help"){
            if ($argc > 2){
                if ($stats){
                    fclose($f_stats);
                }
                fputs(STDERR, "Nepovolena kombinacia parametrov\n");
                exit(10);
            }
            
            echo "--help \n \n";
            echo "Skript typu filtr (parse.php v jazyce PHP 7.4) načte ze standardního vstupu\n";
            echo "zdrojový kód v IPP- code20, zkontroluje lexikální a syntaktickou správnost kódu\n";
            echo "a vypíše na standardní výstup XML reprezentaci programu dle specifikace v sekci 3.1.\n \n";
            exit(0);
        }
        
        elseif(preg_match("/^--stats=.*/", $arg)){
            // ak za = neni nic
            if ($arg == "--stats="){
                fputs(STDERR, "--stats nema file\n");
                exit(10);
            }
            // rozparsovanie nazvu subora
            $file_stats = explode("=", $arg);
            array_shift($file_stats);
            $file_stats = implode("", $file_stats);
            $f_stats = fopen($file_stats, "w");
            $stats = true;    
        }
        elseif($arg == "--loc"){
            $loc = true;
        }
        elseif($arg == "--comments"){
            $comments = true;
        }
        elseif($arg == "--labels"){
            $labels = true;
        }
        elseif($arg == "--jumps"){
            $jumps = true;
        }
        
        else {
            echo $arg;
            fputs(STDERR, "Neznamy argument\n");
            exit(10);
        }
        
    }
}

/**********************************//**********************************//**********************************/
/**********************************//****** HLAVNE TELO PROGRAMU ******//**********************************/
/**********************************//**********************************//**********************************/

//definicie premmennych pre rozsirenie
$loc = false;
$comments = false;
$jumps = false;
$labels = false;
$stats = false;
$loc_num = 0;
$comments_num = 0;
$jumps_num = 0;
$labels_num = 0;

load_arg();

// ak sa nejaky z argumentov bude nachadzat bez stats tak chyba
if (($stats==false)&&(($loc)||($comments)||($jumps)||($labels))){
    fputs(STDERR, "Nepovolena kombinacia parametrov\n");
    exit(10);
}
/*******************************************************/
/*********** SYNTAX ANALYSIS & GENERATING XML **********/
/*******************************************************/

// generovanie hlavicky XML vystupu
$xmlFile = new DOMDocument('1.0', 'UTF-8');
$xmlProgram = $xmlFile->createElement("program");
$xmlProgram->setAttribute("language", "IPPcode20");

// kontrola hlavicky .IPPcode20
while(true){
    $header = get_token();
    //hlavicka sa tam nachadza vsetko OK
    if ($header == 60){
        break;
    }
    if ($header == 100){
        continue;
    }
    if (($header == 99) || ($header == 22)){
        fputs(STDERR, "Chyba v hlavicke .IPPcode20!\n");
        exit(21);
    }
    else{
        fputs(STDERR, "Hlavicka nie je prva\n");
        exit(21);
    }
}

// premenna na poradie instrukcii
$order = 0;

while(true){
    $token = array();
    $token = get_token();

    //koniec suboru EOF
    if ($token == 99){
        break;
    }
    //najde komentar alebo prazdny riadok
    elseif ($token == 100){
        continue;
    }
    //najde .ippcode hlavicku na inom ako prvom riadku
    //alebo pride lex error
    elseif ($token == 23){
        fputs(STDERR, "LEX error!\n");
        exit(23);
    }
    elseif ($token == 60){
        fputs(STDERR, "Double header!\n");
        exit(22);
    }

    global $order;
    $order++;

    $loc_num++;
    /*
    * 1 = opcode
    * 2 = constant
    * 3 = variable
    * 4 = label
    * 5 = type
    */
    // Prva na riadku je instrukcia - OPcode
    if ($token[0][0] == 1){
        // generovanie xml
        $xmlInstruct = $xmlFile->createElement("instruction");
        $xmlInstruct->setAttribute("order", $order);
        $xmlInstruct->setAttribute("opcode", strtoupper($token[0][1]));

        // rozsirenie pocitanie jumpov
        if((strtoupper($token[0][1]) == "RETURN")||(strtoupper($token[0][1]) == "CALL")||(strtoupper($token[0][1]) == "JUMP")
        ||(strtoupper($token[0][1]) == "JUMPIFEQ")||(strtoupper($token[0][1]) == "JUMPIFNEQ")){
            $jumps_num++;
        }

        // prvy bol OpCode takze kontrolujeme podla syntaktickych pravidiel co je za nim
        switch(strtoupper($token[0][1])){

            //INSTRUKCIE ktore musia byt na riadku same - nemaju operand
            case "CREATEFRAME":
            case "PUSHFRAME":
            case "POPFRAME":
            case "RETURN":     //RETURN
            case "BREAK":    //BREAK
                if (count($token) != 1){
                    fputs(STDERR, "Syntakticka chyba - prilis vela operandov za opcode\n");
                    exit(23);
                }
                else{
                    break;
                }
            //INSTRUKCIE ktorych operandom je 1 premmenna
            case "POPS":
            case "DEFVAR":
                if (count($token) == 2){
                    if ($token[1][0] == 3){
                        $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                        $xmlOperand_1->setAttribute("type", "var");
                        $xmlInstruct->appendChild($xmlOperand_1);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - nespravna definicia premennej\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            //INSTRUKCIE len s labelom
            case "LABEL": $labels_num++;
            case "CALL":
            case "JUMP":
                if (count($token) == 2){
                    if (($token[1][0] == 4) || ($token[1][0] == 1)){
                        $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                        $xmlOperand_1->setAttribute("type", "label");
                        $xmlInstruct->appendChild($xmlOperand_1);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - INSTRUKCIE len s labelom\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            //INSTRUKCIE s premennou alebo konstantou
            case "PUSHS":
            case "WRITE":
            case "EXIT":
            case "DPRINT":
                if (count($token) == 2){
                    if (($token[1][0] == 2) || ($token[1][0] == 3)){
                        if ($token[1][0] == 3){
                            $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                            $xmlOperand_1->setAttribute("type", "var");
                        }
                        if ($token[1][0] == 2){
                            // get 42 out of int@42
                            $tmp = preg_split("/@/", $token[1][1], 2);
                            array_shift($tmp);
                            $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($tmp[0]));

                            if(preg_match("/^(string)/", $token[1][1]))
                                $xmlOperand_1->setAttribute("type", "string");
                            elseif(preg_match("/^(bool)/", $token[1][1]))
                                $xmlOperand_1->setAttribute("type", "bool");
                            elseif(preg_match("/^(int)/", $token[1][1]))
                                $xmlOperand_1->setAttribute("type", "int");
                            elseif(preg_match("/^(nil)/", $token[1][1]))
                                $xmlOperand_1->setAttribute("type", "nil");
                        }
                        $xmlInstruct->appendChild($xmlOperand_1);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - INSTRUKCIE s premennou alebo konstantou\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            //INSTRUKCIE s premennou a typom
            case "READ":
                if (count($token) == 3){
                    if (($token[1][0] == 3) && ($token[2][0] == 5)){
                        $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                        $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($token[2][1]));
                        $xmlOperand_1->setAttribute("type", "var");
                        $xmlOperand_2->setAttribute("type", "type");
                        $xmlInstruct->appendChild($xmlOperand_1);
                        $xmlInstruct->appendChild($xmlOperand_2);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - zle operandy v READ\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            //INSTRUKCIE 2 operandami - premmennymi alebo konstantami - <var> <symb>
            case "MOVE":case "INT2CHAR":
            case "STRLEN":case "TYPE":
            case "NOT":
                if (count($token) == 3){
                    if (($token[1][0] == 3) && (($token[2][0] == 2) || ($token[2][0] == 3))){
                        $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                        $xmlOperand_1->setAttribute("type", "var");

                        // oba operandy budu premenna
                        if ($token[2][0] == 3){
                            $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($token[2][1]));
                            $xmlOperand_2->setAttribute("type", "var");
                        }
                        // jeden operand bude konstanta
                        else{
                            // get 42 out of int@42
                            $tmp = preg_split("/@/", $token[2][1], 2);
                            array_shift($tmp);
                            $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($tmp[0]));

                            if(preg_match("/^string.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "string");
                            elseif(preg_match("/^bool.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "bool");
                            elseif(preg_match("/^int.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "int");
                            elseif(preg_match("/^nil.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "nil");
                        }
                        $xmlInstruct->appendChild($xmlOperand_1);
                        $xmlInstruct->appendChild($xmlOperand_2);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - zle operandy\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            //INSTRUKCIE s 3 operandami <var> <symb> <symb>
            case "ADD":case "SUB":case "MUL":case "IDIV":
            case "LT":case "GT":case "EQ":case "AND":
            case "OR":case "STRI2INT":case "CONCAT":
            case "GETCHAR":case "SETCHAR":
                if (count($token) == 4){
                    if (($token[1][0] == 3) && (($token[2][0] == 2) || ($token[2][0] == 3)) &&
                    (($token[3][0] == 2) || ($token[3][0] == 3))){
                        $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                        $xmlOperand_1->setAttribute("type", "var");
                        // oba operandy budu premenna
                        if ($token[2][0] == 3){
                            $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($token[2][1]));
                            $xmlOperand_2->setAttribute("type", "var");
                        }
                        if ($token[3][0] == 3){
                            $xmlOperand_3 = $xmlFile->createElement("arg3", htmlspecialchars($token[3][1]));
                            $xmlOperand_3->setAttribute("type", "var");
                        }
                        if ($token[2][0] == 2){
                            // get 42 out of int@42
                            $tmp = preg_split("/@/", $token[2][1], 2);
                            array_shift($tmp);
                            $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($tmp[0]));

                            if(preg_match("/^string.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "string");
                            elseif(preg_match("/^bool.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "bool");
                            elseif(preg_match("/^int.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "int");
                            elseif(preg_match("/^nil.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "nil");
                        }
                        if ($token[3][0] == 2){
                            // get 42 out of int@42
                            $tmp = preg_split("/@/", $token[3][1], 2);
                            array_shift($tmp);
                            $xmlOperand_3 = $xmlFile->createElement("arg3", htmlspecialchars($tmp[0]));

                            if(preg_match("/^string.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "string");
                            elseif(preg_match("/^bool.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "bool");
                            elseif(preg_match("/^int.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "int");
                            elseif(preg_match("/^nil.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "nil");
                        }
                        $xmlInstruct->appendChild($xmlOperand_1);
                        $xmlInstruct->appendChild($xmlOperand_2);
                        $xmlInstruct->appendChild($xmlOperand_3);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - zle operandy\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            //INSTRUKCIE s <label> <symb> <symb>
            case "JUMPIFEQ":
            case "JUMPIFNEQ":
                if (count($token) == 4){
                    if ((($token[1][0] == 4) || ($token[1][0] == 1)) && (($token[2][0] == 2) || ($token[2][0] == 3)) &&
                    (($token[3][0] == 2) || ($token[3][0] == 3))){
                        $xmlOperand_1 = $xmlFile->createElement("arg1", htmlspecialchars($token[1][1]));
                        $xmlOperand_1->setAttribute("type", "label");
                        // oba operandy budu premenna
                        if ($token[2][0] == 3){
                            $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($token[2][1]));
                            $xmlOperand_2->setAttribute("type", "var");
                        }
                        if ($token[3][0] == 3){
                            $xmlOperand_3 = $xmlFile->createElement("arg3", htmlspecialchars($token[3][1]));
                            $xmlOperand_3->setAttribute("type", "var");
                        }
                        if ($token[2][0] == 2){
                            // get 42 out of int@42
                            $tmp = preg_split("/@/", $token[2][1], 2);
                            array_shift($tmp);
                            $xmlOperand_2 = $xmlFile->createElement("arg2", htmlspecialchars($tmp[0]));

                            if(preg_match("/^string.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "string");
                            elseif(preg_match("/^bool.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "bool");
                            elseif(preg_match("/^int.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "int");
                            elseif(preg_match("/^nil.*/", $token[2][1]))
                                $xmlOperand_2->setAttribute("type", "nil");
                        }
                        if ($token[3][0] == 2){
                            // get 42 out of int@42
                            $tmp = preg_split("/@/", $token[3][1], 2);
                            array_shift($tmp);
                            $xmlOperand_3 = $xmlFile->createElement("arg3", htmlspecialchars($tmp[0]));

                            if(preg_match("/^string.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "string");
                            elseif(preg_match("/^bool.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "bool");
                            elseif(preg_match("/^int.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "int");
                            elseif(preg_match("/^nil.*/", $token[3][1]))
                                $xmlOperand_3->setAttribute("type", "nil");
                        }
                        $xmlInstruct->appendChild($xmlOperand_1);
                        $xmlInstruct->appendChild($xmlOperand_2);
                        $xmlInstruct->appendChild($xmlOperand_3);
                        break;
                    }
                    else{
                        fputs(STDERR, "Syntakticka chyba - zle operandy\n");
                        exit(23);
                    }
                }
                else{
                    fputs(STDERR, "Syntakticka chyba - nespravne operandy\n");
                    exit(23);
                }
            default:
                fputs(STDERR, "Syntakticka chyba\n");
                exit(23);
        }
        //vypis konca instrukcie - priradenie instrukcie (child) hlavnemu programu
        $xmlProgram->appendChild($xmlInstruct);
    }
    //nezacina OPcode takze chyba
    else{
        fputs(STDERR, "Syntakticka chyba - riadok nezacina OPcode\n");
        exit(22);
    }
}

//ukoncenie a tisk
$xmlFile->appendChild($xmlProgram);
$xmlFile->formatOutput = TRUE;
echo $xmlFile->saveXML();

// statistika
if ($stats==true){
    //ulozenie argumentov do pola okrem nazvu suboru
    $args = array();
    for ($i = 1; $i < $argc; $i++){
        $args[$i-1] = $argv[$i];
    }
    // vypis statistik podla argumentov
    foreach ($args as $arg){
        if ($arg == "--loc")
            fprintf($f_stats, $loc_num . "\n");
        if ($arg == "--labels")
            fprintf($f_stats, $labels_num . "\n");
        if ($arg == "--jumps")
            fprintf($f_stats, $jumps_num . "\n");
        if ($arg == "--comments")
            fprintf($f_stats, $comments_num . "\n");
    }
    
    fclose($f_stats);
}

return 0;
?>
