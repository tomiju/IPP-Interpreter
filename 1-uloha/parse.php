<?php

/*
 * Projekt: IPP - 1. část "Analyzátor kódu IPPcode20"
 * Author: Tomáš Julina (xjulin08)
 * Datum: 5.2. 2020
 */

# ERROR KÓDY
const err_missingParameter = 10;
const err_inputFile = 11;
const err_outputFile = 12;
const err_missingHeader = 21;
const err_unknownOpCode = 22;
const err_lexSyntax = 23;
const err_internal = 99;

# POMOCNÉ TOKENY PRO LEX. A SYNTAX. ANALÝZU
const tokenHeader = 0;
const tokenInstruction = 1;
const tokenLabel = 2;
const tokenLabelType = 3; # type kvůli instrukcím (read atd.)
const tokenVar = 4;
const tokenConst = 5;
const tokenEOF = 6;

# SEZNAM INSTRUKCÍ PRO TVORBU XML
$instructions = array(
    0 => "MOVE",
    1 => "CREATEFRAME",
    2 => "PUSHFRAME",
    3 => "POPFRAME",
    4 => "DEFVAR",
    5 => "CALL",
    6 => "RETURN",
    7 => "PUSHS",
    8 => "POPS",
    9 => "ADD",
    10 => "SUB",
    11 => "MUL",
    12 => "IDIV",
    13 => "LT",
    14 => "GT",
    15 => "EQ",
    16 => "AND",
    17 => "OR",
    18 => "NOT",
    19 => "INT2CHAR",
    20 => "STRI2INT",
    21 => "READ",
    22 => "WRITE",
    23 => "CONCAT",
    24 => "STRLEN",
    25 => "GETCHAR",
    26 => "SETCHAR",
    27 => "TYPE",
    28 => "LABEL",
    29 => "JUMP",
    30 => "JUMPIFEQ",
    31 => "JUMPIFNEQ",
    32 => "EXIT",
    33 => "DPRINT",
    34 => "BREAK");


# ZPRACOVÁNÍ VSTUPNÍCH ARGUMENTŮ

$statsCnt = 0;
$statsFile;
$statsArgs = array();

if ($argc > 1)
{
  for ($x=1; $x < $argc ; $x++)
  {
    if ($argv[$x] == "--help")
    {
      if ($argc == 2)
      {
        echo "-> Help: parse.php\n";
        echo "-> Argumenty:\n";
        echo "-> --stats=file  --loc --comments --jumps --labels   = uloží statistiky do souboru file\n";
        echo "-> --help   = vyvolá tuto zprávu (nelze kombinovat s dalšími argumenty)";
        echo "-> Skript typu filtr (parse.php v jazyce PHP 7.4) načte ze standardního vstupu
-> zdrojový kód v IPPcode20, zkontroluje lexikální a syntaktickou správnost kódu a vypíše na standardní
-> výstup XML reprezentaci programu.\n";
        exit;
      }
      else
      {
        fwrite(STDERR, "ERROR: Wrong input parameter.\n");
        exit(err_missingParameter);
      }
    }
    else if(preg_match("~^--stats=~", $argv[$x]))
    {
      if ($statsCnt != 0)
      {
        fwrite(STDERR, "ERROR: Arg. stats -> can't use multiple stats arguments!.\n");
        exit(err_missingParameter);
      }

      $statsInput = explode("=", $argv[$x]);

      if(sizeof($statsInput) != 2 || $statsInput[1] == "")
      {
        fwrite(STDERR, "ERROR: Arg. stats -> missing file.\n");
        exit(err_missingParameter);
      }

      $statsFile = $statsInput[1];

      $statsCnt++;
    }
    else if($argv[$x] == "--comments")
    {
      array_push($statsArgs, "--comments");
    }
    else if($argv[$x] == "--jumps")
    {
      array_push($statsArgs, "--jumps");
    }
    else if($argv[$x] == "--labels")
    {
      array_push($statsArgs, "--labels");
    }
    else if($argv[$x] == "--loc")
    {
      array_push($statsArgs, "--loc");
    }
    else
    {
      fwrite(STDERR, "ERROR: Unknown argument.\n");
      exit(err_missingParameter);
    }
  }
  if (sizeof($statsArgs) != 0 && $statsCnt == 0)
  {
    fwrite(STDERR, "ERROR: Missing argument --stats.\n");
    exit(err_missingParameter);
  }
}

# POMOCNÉ PROMĚNNÉ
$InstructionOrder = 0; # pro atribut order
$STDIN=STDIN;
$firstLine = -1;

# STATISTIKY (STATP)
$locStatp = -1;
$commentsStatp = 0;
$labelsStatp = 0;
$jumpsStatp = 0;
$labelsArray = array();

# STATS
function run_stats()
{
  global $locStatp;
  global $commentsStatp;
  global $labelsStatp;
  global $jumpsStatp;

  global $statsFile;
  global $statsArgs;

  $statsFile = fopen($statsFile, "w") or die("Unable to open file!");

  for ($i=0; $i < sizeof($statsArgs); $i++)
  {
    if ($statsArgs[$i] == "--comments")
    {
      fwrite($statsFile, $commentsStatp."\n");
    }
    else if($statsArgs[$i] == "--jumps")
    {
      fwrite($statsFile, $jumpsStatp."\n");
    }
    else if($statsArgs[$i] == "--labels")
    {
      fwrite($statsFile, $labelsStatp."\n");
    }
    else if($statsArgs[$i] == "--loc")
    {
      fwrite($statsFile, $locStatp."\n");
    }
  }

  fclose($statsFile);
}

# SCANNER - Lexikální kontrola
function scanner()
{
  global $STDIN;
  global $instructions;
  global $firstLine;

  # STATISTIKY
  global $locStatp;
  global $commentsStatp;
  global $labelsStatp;

  $output = array();

  while(42) # zpracování vstupu (po řádcích)
  {
    if(($unprocessedLine = fgets($STDIN)) == false) # EOF + načítání řádků
    {
      array_push($output, array(tokenEOF));
      return $output;
    }

    $locStatp++;

    $firstLine++;

    if (preg_match("~^\s*#~", $unprocessedLine)) # komentář na začátku řádku -> nezajímá mě, přeskočím
    {
      $commentsStatp++;
      $locStatp--;
      continue;
    }
    else if(preg_match("~^\s*$~", $unprocessedLine)) # řádek jenom s bílými znaky -> nezajímá mě, přeskočím
    {
      $locStatp--;
      continue;
    }

    $splitLine = explode("#", $unprocessedLine); # rozdělí řádek a potom beru jen část, která není komentář

    if(sizeof($splitLine) > 1)
    {
      $commentsStatp++;
    }

    $words = preg_split("~\s+~", $splitLine[0]); # rozdělím řádek na jednotlivá slova pomocí bílých znaků

    if (end($words) == "")
    {
      array_pop($words); # explode z nějakého důvodu uloží na začátek a konec také řetězec "", což způsobí chybu v lexikální kontrole - tímto je z pole odstraním
    }

    if ($words[0] == "")
    {
      array_shift($words);
    }

    break;
  }

  $firstToken = 0; # pomocná proměnná pro rozhodnutí, zda se jedná o instrukci či návěští - začátek řádku = instrukce, jinak název návěští

 # LEXIKÁLNÍ KONTROLY A TVORBA TOKENŮ
 foreach($words as $word)
 {
   if (preg_match("~@~", $word)) # když má "@" -> proměnná(konstanta), rámec
   {
     if (preg_match("~^(int|bool|string|nil)~", $word)) # konstanta = const
     {
       if (preg_match("~^int@[+,-]?\d+$~", $word) || preg_match("~^bool@(true|false)$~", $word) || preg_match("~^nil@nil$~", $word) || preg_match("~^string@([^\s\\\\]*([\\\]{1}[0-9]{3})*[^\s\\\\]*)*$~", $word))
       {
         array_push($output, array_merge(array(tokenConst), explode("@", $word, 2))); # uložím si druh tokenu, druh konstanty a obsah konstanty do pole
       }
       else
       {
         fwrite(STDERR, "ERROR: Wrong CONSTANT format: "." \"".$word."\"");
         exit(err_lexSyntax);
       }
     }
     else # rámec (proměnné = variable)
     {
       if (preg_match("~^(GF|LF|TF)~", $word))
       {
         if (preg_match("~^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9\-$&%*!?]*$~", $word))
         {
           array_push($output, array(tokenVar, $word));
         }
         else
         {
           fwrite(STDERR, "ERROR: Wrong VARIABLE format: "." \"".$word."\"");
           exit(err_lexSyntax);
         }
       }
       else
       {
         fwrite(STDERR, "ERROR: Wrong VARIABLE format: "." \"".$word."\"");
         exit(err_lexSyntax);
       }
     }

   }
   else # jinak instrukce, header, label, label type
   {
     if(preg_match("~^(int|bool|string)$~", $word)) # label type - příkaz "read"
     {
       array_push($output, array(tokenLabelType, $word));
     }
     else
     {
       if(preg_match("~^\.ippcode20$~i", $word)) # header
       {
         array_push($output, array(tokenHeader));
       }
       else if (($instructionOutput = checkInstruction($word)) != -1) # instrukce nebo label
       {
         if ($firstToken == 0) # pokud se jedná o instrukci a zároveň je první na řádku => je to instrukce
         {
           array_push($output, array(tokenInstruction, $instructionOutput)); # uložím si token instrukce a její číslo (z array)
         }
         else # pokud instrukce není jako první slovo, tak se jedná o label
         {
           array_push($output, array(tokenLabel, $word));
         }
       }
       else # label
       {
         if (preg_match("~^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$~", $word))
         {
           array_push($output, array(tokenLabel, $word));
         }
         else
         {
           if ($firstLine != 0)
           {
             fwrite(STDERR, "ERROR: Other lex/syntax error. "." \"".$word."\"\n");
             exit(err_lexSyntax);
           }
           else
           {
             fwrite(STDERR, "ERROR: Missing/Wrong header. "." \"".$word."\"\n");
             exit(err_missingHeader);
           }
         }
       }
     }
   }
   $firstToken++; # už se nemůže jednat o instrukci
 }
 return $output;
}

# POMOCNÁ FUNKCE - pro zjištění, zda aktuálně zpracovávané slovo je instrukce, či nikoliv
function checkInstruction($word) # porovná aktuální slovo se seznamem instrukcí a vrátí číslo instrukce nebo -1
{
  global $jumpsStatp;

  global $instructions;
  $instructionNumber = 0;
  $found = false;

  foreach ($instructions as $instruction)
  {
    if (preg_match("~^". $instruction. "$~i", $word)) # porovnání instrukce s kontrolovaným slovem (i = nezáleží na velikosti písmen)
    {
      $found = true;
      if (strtoupper($instruction) == "JUMP" || strtoupper($instruction) == "JUMPIFEQ" || strtoupper($instruction) == "JUMPIFNEQ" || strtoupper($instruction) == "CALL" || strtoupper($instruction) == "RETURN")
      {
        $jumpsStatp++; # statistiky
      }
      break;
    }
    $instructionNumber++; # inkrementace čísla aktuální instrukce
  }

  if ($found == true)
  {
    return $instructionNumber; # pokud je to instrukce, vrátím její číslo
  }
  else
  {
    return -1; # jinak není instrukce
  }
}

# SYNTAKTICKÁ KONTROLA
function syntax()
{
  global $labelsStatp;
  global $labelsArray;

  global $InstructionOrder; # počítadlo pro "order"
  global $instructions;

  # GENEROVÁNÍ XML
  $domtree = new DOMDocument('1.0', 'UTF-8');
  $domtree->preserveWhiteSpace = false;
  $domtree->formatOutput = true;
  $xmlRoot = $domtree->createElement("program");
  $xmlRoot->setAttribute("language", "IPPcode20");
  $xmlRoot = $domtree->appendChild($xmlRoot);

  $currentLine = scanner();
  if (count($currentLine) > 1 || $currentLine[0][0] != tokenHeader) # kontrola prvního řádku
  {
    fwrite(STDERR, "ERROR: Missing header.\n");
    exit(err_missingHeader);
  }

  while(42) # :)
  {
    $currentLine = scanner();

    if (count($currentLine) == 1 && $currentLine[0][0] == tokenEOF) # konec zpracovávání
    {
      break;
    }
    else if ($currentLine[0][0] == tokenInstruction)
    {
      $InstructionOrder++;

      $xmlInstruction = $domtree->createElement("instruction");
      $xmlInstruction->setAttribute("order", $InstructionOrder);
      $xmlInstruction->setAttribute("opcode", $instructions[$currentLine[0][1]]);

      # [0][1] je číslo instrukce
      switch ($currentLine[0][1])
      {
        # 0 argumentů
        case "1": # CREATEFRAME
        case "2": # PUSHFRAME
        case "3": # POPFRAME
        case "6": # RETURN
        case "34": # BREAK
          if (count($currentLine) > 1 || count($currentLine) < 1)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of zero-argument instruction.\n");
            exit(err_lexSyntax);
          }
          break;

        # 1 argument
        case "7": # PUSHS (symb)
        case "22": # WRITE (symb)
        case "32": # EXIT (symb)
        case "33": # DPRINT (symb)
          if (count($currentLine) > 2 || count($currentLine) < 2)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of one-argument instruction.\n");
            exit(err_lexSyntax);
          }
          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1", htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else if ($currentLine[1][0] == tokenConst)
          {
            $xmlArg1 = $domtree->createElement("arg1", htmlspecialchars($currentLine[1][2]));
            $xmlArg1->setAttribute("type", $currentLine[1][1]);
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of one-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          break;

        case "4": # DEFVAR (var)
        case "8": # POPS (var)
          if (count($currentLine) > 2 || count($currentLine) < 2)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of one-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1])); # htmlspecialchars převede znaky jako "<, >, &, ..." na odpovídající XML entity
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of one-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          break;

        case "5": # CALL (label)
        case "28": # LABEL (label)
        case "29": # JUMP (label)
          if (count($currentLine) > 2 || count($currentLine) < 2)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of one-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[0][1] == "28") # statistiky
          {
            if (!in_array($currentLine[1][1], $labelsArray)) # pokud je to nový label - započítat ho a přidat do pole
            {
              array_push($labelsArray, $currentLine[1][1]);
              $labelsStatp++;
            }
          }

          if ($currentLine[1][0] == tokenLabel)
          {
            $xmlArg1 = $domtree->createElement("arg1", $currentLine[1][1]);
            $xmlArg1->setAttribute("type", "label");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of one-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          break;

        # 2 argumenty
        case "0": # MOVE (var) (symb)
        case "19": # INT2CHAR (var) (symb)
        case "24": # STRLEN (var) (symb)
        case "27": # TYPE (var) (symb)
        case "21": # READ (var) (type)
        case "18": # NOT (var) (symb) bool
          if (count($currentLine) > 3 || count($currentLine) < 3)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of two-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of two-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[0][1] == 21)
          {
            if ($currentLine[2][0] == tokenLabelType)
            {
              $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
              $xmlArg2->setAttribute("type", "type");
            }
            else
            {
              fwrite(STDERR, "ERROR: Wrong syntax of two-argument instruction.\n");
              exit(err_lexSyntax);
            }
          }
          else
          {
            if ($currentLine[2][0] == tokenConst)
            {
              if ($currentLine[0][1] == 18)
              {
                if ($currentLine[2][1] == "bool")
                {
                  $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
                  $xmlArg2->setAttribute("type", $currentLine[2][1]);
                }
                else
                {
                  fwrite(STDERR, "ERROR: Wrong syntax of two-argument instruction.\n");
                  exit(err_lexSyntax);
                }
              }
                $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
                $xmlArg2->setAttribute("type", $currentLine[2][1]);
            }
            else if ($currentLine[2][0] == tokenVar)
            {
              $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
              $xmlArg2->setAttribute("type", "var");
            }
            else
            {
              fwrite(STDERR, "ERROR: Wrong syntax of two-argument instruction.\n");
              exit(err_lexSyntax);
            }
         }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          break;

        # 3 argumenty
        case "9": # ADD (var) (symb1) (symb2) int
        case "10": # SUB (var) (symb1) (symb2) int
        case "11": # MUL (var) (symb1) (symb2) int
        case "12": # IDIV (var) (symb1) (symb2) int
          if (count($currentLine) > 4 || count($currentLine) < 4)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[2][1] == "int")
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
            $xmlArg2->setAttribute("type", $currentLine[2][1]);
          }
          else if ($currentLine[2][0] == tokenVar)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
            $xmlArg2->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[3][1] == "int")
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][2]));
            $xmlArg3->setAttribute("type", $currentLine[3][1]);
          }
          else if ($currentLine[3][0] == tokenVar)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][1]));
            $xmlArg3->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          $xmlInstruction->appendChild($xmlArg3);
          break;

        case "13": # LT (var) (symb1) (symb2) int/bool/string
        case "14": # GT (var) (symb1) (symb2) int/bool/string
        case "15": # EQ (var) (symb1) (symb2) int/bool/string
          if (count($currentLine) > 4 || count($currentLine) < 4)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[2][1] == "int" || $currentLine[2][1] == "bool" || $currentLine[2][1] == "string" || $currentLine[2][1] == "nil" )
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
            $xmlArg2->setAttribute("type", $currentLine[2][1]);
          }
          else if ($currentLine[2][0] == tokenVar)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
            $xmlArg2->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[3][1] == "int" || $currentLine[3][1] == "bool" || $currentLine[3][1] == "string" || $currentLine[3][1] == "nil")
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][2]));
            $xmlArg3->setAttribute("type", $currentLine[3][1]);
          }
          else if ($currentLine[3][0] == tokenVar)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][1]));
            $xmlArg3->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          $xmlInstruction->appendChild($xmlArg3);
          break;

        case "16": # AND (var) (symb1) (symb2) bool
        case "17": # OR (var) (symb1) (symb2) bool
          if (count($currentLine) > 4 || count($currentLine) < 4)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[2][1] == "bool")
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
            $xmlArg2->setAttribute("type", $currentLine[2][1]);
          }
          else if ($currentLine[2][0] == tokenVar)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
            $xmlArg2->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[3][1] == "bool")
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][2]));
            $xmlArg3->setAttribute("type", $currentLine[3][1]);
          }
          else if ($currentLine[3][0] == tokenVar)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][1]));
            $xmlArg3->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          $xmlInstruction->appendChild($xmlArg3);
          break;

        case "20": # STRI2INT (var) (symb1) (symb2) string int
        case "25": # GETCHAR (var) (symb1) (symb2) string int
          if (count($currentLine) > 4 || count($currentLine) < 4)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[2][1] == "string")
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
            $xmlArg2->setAttribute("type", $currentLine[2][1]);
          }
          else if ($currentLine[2][0] == tokenVar)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
            $xmlArg2->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[3][1] == "int")
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][2]));
            $xmlArg3->setAttribute("type", $currentLine[3][1]);
          }
          else if ($currentLine[3][0] == tokenVar)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][1]));
            $xmlArg3->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          $xmlInstruction->appendChild($xmlArg3);
          break;

        case "23": # CONCAT (var) (symb1) (symb2) string string
        case "26": # SETCHAR (var) (symb1) (symb2) int string
          if (count($currentLine) > 4 || count($currentLine) < 4)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenVar)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[2][1] == "string" || $currentLine[2][1] == "int")
          {
            if ($currentLine[0][1] == "23" && $currentLine[2][1] == "int")
            {
              fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
              exit(err_lexSyntax);
            }
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
            $xmlArg2->setAttribute("type", $currentLine[2][1]);
          }
          else if ($currentLine[2][0] == tokenVar)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
            $xmlArg2->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[3][1] == "string")
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][2]));
            $xmlArg3->setAttribute("type", $currentLine[3][1]);
          }
          else if ($currentLine[3][0] == tokenVar)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][1]));
            $xmlArg3->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          $xmlInstruction->appendChild($xmlArg3);
          break;

        case "30": # JUMPIFEQ (label) (symb1) (symb2)
        case "31": # JUMPIFNEQ (label) (symb1) (symb2)
          if (count($currentLine) > 4 || count($currentLine) < 4)
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[1][0] == tokenLabel)
          {
            $xmlArg1 = $domtree->createElement("arg1",htmlspecialchars($currentLine[1][1]));
            $xmlArg1->setAttribute("type", "label");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[2][0] == tokenConst)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][2]));
            $xmlArg2->setAttribute("type", $currentLine[2][1]);
          }
          else if ($currentLine[2][0] == tokenVar)
          {
            $xmlArg2 = $domtree->createElement("arg2", htmlspecialchars($currentLine[2][1]));
            $xmlArg2->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          if ($currentLine[3][0] == tokenConst)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][2]));
            $xmlArg3->setAttribute("type", $currentLine[3][1]);
          }
          else if ($currentLine[3][0] == tokenVar)
          {
            $xmlArg3 = $domtree->createElement("arg3", htmlspecialchars($currentLine[3][1]));
            $xmlArg3->setAttribute("type", "var");
          }
          else
          {
            fwrite(STDERR, "ERROR: Wrong syntax of three-argument instruction.\n");
            exit(err_lexSyntax);
          }

          $xmlInstruction->appendChild($xmlArg1);
          $xmlInstruction->appendChild($xmlArg2);
          $xmlInstruction->appendChild($xmlArg3);
          break;

        default:
          fwrite(STDERR, "ERROR: Default syntax error.\n");
          exit(err_lexSyntax);
          break;
      }

      $xmlRoot->appendChild($xmlInstruction);
    }
    else
    {
      fwrite(STDERR, "ERROR: Wrong instruction format.\n");
      exit(err_unknownOpCode);
    }

  }
  echo $domtree->saveXML();
}

# SPUŠTĚNÍ PROGRAMU

syntax();

if ($statsCnt == 1) # pokud byl zadán argument pro tvorbu statistik -> spustit statistiky
{
  run_stats();
}

# EOF
 ?>
