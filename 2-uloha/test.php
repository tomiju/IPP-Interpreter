<?php

/*
 * Projekt: IPP - 2. část "Testovací rámec"
 * Author: Tomáš Julina (xjulin08)
 * Datum: 5.2. 2020
 */

# ERR KÓD
const err_missingParameter = 10;
const err_inputFilePermission = 11;

# POMOCNÉ PROMĚNNÉ
$parseOnly = false;
$intOnly = false;
$jexamxmlFile = "/pub/courses/ipp/jexamxml/jexamxml.jar";
$recursiveSearch = false;
$testsDirectory = getcwd();
$parseScriptFile = "./parse.php";
$intScriptFile = "./interpret.py";
$debug = false; # TODO: odevzdání = false, jinak true
$failedTestOutput;
$passedTestOutput;

$currentTestSRC;
$currentTestIN;
$currentTestOUT;
$currentTestRC;

# PODPOROVANÉ SPOUŠTĚCÍ PARAMETRY
 $longoptions = array(
   "help",
   "directory:",
   "recursive",
   "parse-script:",
   "int-script:",
   "parse-only",
   "int-only",
   "jexamxml:"
 );

$options = getopt("",$longoptions);

# ZPRACOVÁNÍ VSTUPNÍCH PARAMETRŮ
if (array_key_exists("help", $options))
{
  if (sizeof($options) != 1)
  {
    fwrite(STDERR, "\nERROR: Wrong input parameter combination.\n");
    exit(err_missingParameter);
  }
  else
  {
    echo "\n-> Help: test.php\n";
    echo "-> volitelné parametry spuštění:\n\n";
    echo "-> --directory=path ->  testy bude hledat v zadaném adresáři (chybí-li tento parametr, tak skript
     prochází aktuální adresář; v případě zadání neexistujícího adresáře dojde k chybě 11)\n\n";
    echo "-> --recursive  testy bude hledat nejen v zadaném adresáři, ale i rekurzivně ve všech jeho
     podadresářích\n\n";
    echo "-> --parse-script=file  soubor se skriptem v PHP 7.4 pro analýzu zdrojového kódu v IPPcode20
     (chybí-li tento parametr, tak implicitní hodnotou je parse.php uložený v aktuálním adresáři)\n\n";
    echo "-> --int-script=file  soubor se skriptem v Python 3.8 pro interpret XML reprezentace kódu
     v IPPcode20 (chybí-li tento parametr, tak implicitní hodnotou je interpret.py uložený v aktuálním adresáři)\n\n";
    echo "-> --parse-only  bude testován pouze skript pro analýzu zdrojového kódu v IPPcode20 (tento
     parametr se nesmí kombinovat s parametry --int-only a --int-script)\n\n";
    echo "-> --int-only  bude testován pouze skript pro interpret XML reprezentace kódu v IPPcode20
     (tento parametr se nesmí kombinovat s parametry --parse-only a --parse-script). Vstupní
     program reprezentován pomocí XML bude v souboru s příponou src\n\n";
    echo "-> --jexamxml=file  soubor s JAR balíčkem s nástrojem A7Soft JExamXML. Je-li parametr
     vynechán uvažuje se implicitní umístění /pub/courses/ipp/jexamxml/jexamxml.jar na serveru Merlin\n\n";
    exit;
  }
}

if (array_key_exists("parse-only", $options))
{
  if (array_key_exists("int-only", $options) || array_key_exists("int-script", $options))
  {
    fwrite(STDERR, "\nERROR: Wrong input parameter combination.\n");
    exit(err_missingParameter);
  }
  else
  {
    $parseOnly = true;
  }
}

if (array_key_exists("int-only", $options))
{
  if (array_key_exists("parse-only", $options) || array_key_exists("parse-script", $options))
  {
    fwrite(STDERR, "\nERROR: Wrong input parameter combination.\n");
    exit(err_missingParameter);
  }
  else
  {
    $intOnly = true;
  }
}

if (array_key_exists("directory", $options))
{
    if (!is_dir($options["directory"]))
    {
      fwrite(STDERR, "\nERROR: Directory doesn't exist.\n");
      exit(err_inputFilePermission);
    }

    $testsDirectory = $options["directory"];
}

if (array_key_exists("recursive", $options))
{
  $recursiveSearch = true;
}

if (array_key_exists("parse-script", $options))
{
  $parseScriptFile = $options["parse-script"];

  if (!is_file($parseScriptFile))
  {
    fwrite(STDERR, "\nERROR: Parse file wasn't found.\n");
    exit(err_inputFilePermission);
  }
}
else
{
  if (!is_file("./parse.php"))
  {
    fwrite(STDERR, "\nERROR: Parse file wasn't found.\n");
    exit(err_inputFilePermission);
  }
}

if (array_key_exists("int-script", $options))
{
  $intScriptFile = $options["int-script"];

  if (!is_file($intScriptFile))
  {
    fwrite(STDERR, "\nERROR: Interpret file wasn't found.\n");
    exit(err_inputFilePermission);
  }
}
else
{
  if (!is_file("./interpret.py"))
  {
    fwrite(STDERR, "\nERROR: Interpret file wasn't found.\n");
    exit(err_inputFilePermission);
  }
}

if (array_key_exists("parse-only", $options))
{
  $parseOnly = true;
}

if (array_key_exists("int-only", $options))
{
  $intOnly = true;
}

if (array_key_exists("jexamxml", $options))
{
  $jexamxmlFile = $options["jexamxml"];
}

# GENEROVÁNÍ HLAVIČKY VÝSLEDNÉ HTML STRÁNKY
function generateHTML_Header($testType, $directory, $parserZdroj, $interpretZdroj, $recursiveSearch)
{
  echo "<!DOCTYPE html>\n";
  echo "<html lang=\"en\" dir=\"ltr\">\n";
  echo "<head>\n";
  echo "<meta charset=\"utf-8\">\n";
  echo "<title>IPP TEST SCRIPT</title>";
  echo "<style>#Tabulka{border-collapse: collapse;}\n";
  echo "#Tabulka, #Tabulka2{border: 3px solid black;}</style>\n";
  echo "</head>\n";
  echo "<body>\n";
  echo "<h1><b><font size=\"10\"><u>Výsledky automatických testů:</u></font></b></h1>\n";
  echo "<br>\n";
  echo "<ul><li><a href=\"#Prehled\">Přehled</a></li>\n";
  echo "<li><a href=\"#Tabulka\">Tabulka výsledků</a></li>\n";
  echo "<li><a href=\"#Shrnuti\">Shrnutí výsledků</a></li>\n";
  echo "<br><br>\n";
  echo "<div>\n";
  echo "<table id=\"Prehled\" cellpadding=\"3\">\n";
  echo "<tr>\n";
  echo "<th align=\"left\"><h2>Druh testování:</h2></th>\n";
  echo "<th align=\"left\"><h2>".$testType."</h2></th>\n";
  echo "</tr>\n";
  echo "<tr>\n";
  echo "<th align=\"left\"><h2>Zdroj testů:</h2></th>\n";
  if ($recursiveSearch) echo "<th align=\"left\"><h2>Rekurzivní průchod ".$directory."</h2></th>\n";
  else echo "<th align=\"left\"><h2>".$directory."</h2></th>\n";
  echo "</tr>\n";
  echo "<tr>\n";
  echo "<th align=\"left\"><h2>Použitý parser:</h2></th>\n";
  echo "<th align=\"left\"><h2>".$parserZdroj."</h2></th>\n";
  echo "</tr>\n";
  echo "<tr>\n";
  echo "<th align=\"left\"><h2>Použitý interpret:</h2></th>\n";
  echo "<th align=\"left\"><h2>".$interpretZdroj."</h2></th>\n";
  echo "</tr>\n";
  echo "</table>\n";
  echo "<br><br>\n";
  echo "</div>\n";
  echo "<table id=\"Tabulka\">\n";
  echo "<tr id=\"Tabulka\">\n";
  echo "<th id=\"Tabulka\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
  echo "<th id=\"Tabulka\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
  echo "<th id=\"Tabulka\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
  echo "<th id=\"Tabulka\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
  echo "</tr>\n";
}

# FUNKCE PRO TESTOVÁNÍ PARSERU
function parse_only()
{
  global $jexamxmlFile;
  global $testsDirectory;
  global $parseScriptFile;
  global $recursiveSearch;
  global $debug;
  global $failedTestOutput;
  global $passedTestOutput;

  global $currentTestSRC;
  global $currentTestIN; # pro simulaci STDIN u instrukce READ
  global $currentTestOUT;
  global $currentTestRC;

  $successCount = 0;
  $totalTestsCount = 0;
  $newINfile = false;
  $newOUTfile = false;
  $newRCfile = false;
  $returnCode = 0; # return kód z .rc (defaultně 0)

  fwrite(STDERR, "Started testing...\n");
  if ($recursiveSearch) ## rekurzivní průchod složkami
  {
    $listOfSourceFiles = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDirectory)); # rekurzivně projde všechny složky v cestě
    generateHTML_Header("parse-only", $testsDirectory, $parseScriptFile, "<ŽÁDNÝ>", $recursiveSearch);

    foreach ($iterator as $file)
    {
      if ($file->isDir()) continue;
      $path = $file->getPathname(); # celá cesta k souboru i s jménem testu
      $fileNamesPOM = explode("/",$path);
      $fileName = $fileNamesPOM[sizeof($fileNamesPOM)-1]; # název souboru s testem
      $path_noFileName = str_replace($fileName, '', $path); # cesta k souboru bez jména (funkce jako $testsDirectory)
      $input = scandir($path_noFileName);

      if (preg_match("~.src$~",$fileName))
      {
        $pom = explode(".",$fileName);

        $currentTestSRC = fopen($path_noFileName."/".$fileName, 'r') or die("Can't open file");

        fwrite(STDERR, "Test File: ".$path_noFileName.$fileName."\n");

        if (!array_search($pom[0].".in",$input))
        {
          $currentTestIN = fopen($path_noFileName."/".$pom[0].".in", 'w') or die("Can't create file");
          fclose($currentTestIN);
          $newINfile = true;
        }
        else
        {
          $currentTestIN = fopen($path_noFileName."/".$pom[0].".in", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".out",$input))
        {
          $currentTestOUT = fopen($path_noFileName."/".$pom[0].".out", 'w') or die("Can't create file");
          fclose($currentTestOUT);
          $newOUTfile = true;
        }
        else
        {
          $currentTestOUT = fopen($path_noFileName."/".$pom[0].".out", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".rc",$input))
        {
          $currentTestRC = fopen($path_noFileName."/".$pom[0].".rc", 'w') or die("Can't create file");
          fwrite($currentTestRC, "0");
          fclose($currentTestRC);
          $newRCfile = true;
          $returnCode = 0;
        }
        else
        {
          $currentTestRC = fopen($path_noFileName."/".$pom[0].".rc", 'r') or die("Can't create file");
          $returnCode = (int)fread($currentTestRC,filesize($path_noFileName."/".$pom[0].".rc"));
        }

        array_push($listOfSourceFiles, $fileName);

        if($debug) exec('php '.$parseScriptFile.' <'.$path_noFileName."/".$fileName.' >parse-only-test_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné;
        else exec('php7.4 '.$parseScriptFile.' <'.$path_noFileName."/".$fileName.' >parse-only-test_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné

        if ($ret_value == 0 && $returnCode == 0)
        {
          if(!$debug) exec('java -jar /pub/courses/ipp/jexamxml/jexamxml.jar parse-only-test_output.xml '.$path_noFileName."/".$pom[0].".out".' delta.xml /pub/courses/ipp/jexamxml/options');

          if(!is_file("parse-only-test_output.xml.log")) # EOF
          {
            $tmpDelta = fopen("delta.xml", 'r');
            $tmpInput = fgets($tmpDelta);

            if(($tmpInput = fgets($tmpDelta)) == false) # EOF
            {
              //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."delta.xml");
              $successCount++;
              $totalTestsCount++;
            }
            else
            {
              //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."delta.xml");
              $totalTestsCount++;
            }
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            unlink(getcwd()."/"."parse-only-test_output.xml.log");
            $totalTestsCount++;
          }
        }
        else
        {
          if ($ret_value == $returnCode)
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $successCount++;
            $totalTestsCount++;
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $totalTestsCount++;
          }
        }

        fclose($currentTestSRC);
        unlink(getcwd()."/"."parse-only-test_output.xml");
        if (!$newINfile) fclose($currentTestIN);
        else unlink($path_noFileName."/".$pom[0].".in");
        if (!$newOUTfile) fclose($currentTestOUT);
        else unlink($path_noFileName."/".$pom[0].".out");
        if (!$newRCfile) fclose($currentTestRC);
        else unlink($path_noFileName."/".$pom[0].".rc");
        $newINfile = false;
        $newOUTfile = false;
        $newRCfile = false;
      }
    }
    //echo $failedTestOutput.$passedTestOutput;
    echo "</table>\n";
    echo "<div id=\"Shrnuti\"><br><br><br><h2>Shrnutí: ".$successCount."/".$totalTestsCount." testů bylo úspěšných.</h2><a href=\"#Prehled\">(nahoru)</a><br><br><br>Autor: Tomáš Julina (xjulin08)<br></div>\n";
    echo "<table id=\"Tabulka2\">\n";
    echo "<tr id=\"Tabulka2\">\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
    echo "</tr>\n";
    echo "<h2>Neúspěšné testy:</h2>";
    echo $failedTestOutput;
    echo "</table>\n";
    echo "<a href=\"#Prehled\">(nahoru)</a>";
    echo "</body>\n";
    echo "</html>\n";

    fwrite(STDERR, "Testing ended...\n");

    if (sizeof($listOfSourceFiles) == 0)
    {
      fwrite(STDERR, "\nERROR: No \".src\" files found.\n");
      exit(err_inputFilePermission);
    }
  }
  else
  {
    $input = scandir($testsDirectory);
    $listOfSourceFiles = array();

    generateHTML_Header("parse-only", $testsDirectory, $parseScriptFile, "<ŽÁDNÝ>", $recursiveSearch);

    foreach($input as $file)
    {
      if (preg_match("~.src$~",$file))
      {
        $pom = explode(".",$file);

        $currentTestSRC = fopen($testsDirectory."/".$file, 'r') or die("Can't open file");
        fwrite(STDERR, "Test File: ".$testsDirectory."/".$file."\n");
        if (!array_search($pom[0].".in",$input))
        {
          $currentTestIN = fopen($testsDirectory."/".$pom[0].".in", 'w') or die("Can't create file");
          fclose($currentTestIN);
          $newINfile = true;
        }
        else
        {
          $currentTestIN = fopen($testsDirectory."/".$pom[0].".in", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".out",$input))
        {
          $currentTestOUT = fopen($testsDirectory."/".$pom[0].".out", 'w') or die("Can't create file");
          fclose($currentTestOUT);
          $newOUTfile = true;
        }
        else
        {
          $currentTestOUT = fopen($testsDirectory."/".$pom[0].".out", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".rc",$input))
        {
          $currentTestRC = fopen($testsDirectory."/".$pom[0].".rc", 'w') or die("Can't create file");
          fwrite($currentTestRC, "0");
          fclose($currentTestRC);
          $newRCfile = true;
          $returnCode = 0;
        }
        else
        {
          $currentTestRC = fopen($testsDirectory."/".$pom[0].".rc", 'r') or die("Can't create file");
          $returnCode = (int)fread($currentTestRC,filesize($testsDirectory."/".$pom[0].".rc"));
        }

        array_push($listOfSourceFiles, $file);

        if($debug) exec('php '.$parseScriptFile.' <'.$testsDirectory."/".$file.' >parse-only-test_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné;
        else exec('php7.4 '.$parseScriptFile.' <'.$testsDirectory."/".$file.' >parse-only-test_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné

        if ($ret_value == 0 && $returnCode == 0)
        {
          if(!$debug) exec('java -jar /pub/courses/ipp/jexamxml/jexamxml.jar parse-only-test_output.xml '.$testsDirectory."/".$pom[0].".out".' delta.xml /pub/courses/ipp/jexamxml/options');

          if(!is_file("parse-only-test_output.xml.log")) # EOF
          {
            $tmpDelta = fopen("delta.xml", 'r');
            $tmpInput = fgets($tmpDelta);

            if(($tmpInput = fgets($tmpDelta)) == false) # EOF
            {
              //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."delta.xml");
              $successCount++;
              $totalTestsCount++;
            }
            else
            {
              //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."delta.xml");
              $totalTestsCount++;
            }
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            unlink(getcwd()."/"."parse-only-test_output.xml.log");
            $totalTestsCount++;
          }
        }
        else
        {
          if ($ret_value == $returnCode)
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $successCount++;
            $totalTestsCount++;
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $totalTestsCount++;
          }
        }

        fclose($currentTestSRC);
        unlink(getcwd()."/"."parse-only-test_output.xml");
        if (!$newINfile) fclose($currentTestIN);
        else unlink($testsDirectory."/".$pom[0].".in");
        if (!$newOUTfile) fclose($currentTestOUT);
        else unlink($testsDirectory."/".$pom[0].".out");
        if (!$newRCfile) fclose($currentTestRC);
        else unlink($testsDirectory."/".$pom[0].".rc");
        $newINfile = false;
        $newOUTfile = false;
        $newRCfile = false;
      }
    }
    //echo $failedTestOutput.$passedTestOutput;
    echo "</table>\n";
    echo "<div id=\"Shrnuti\"><br><br><br><h2>Shrnutí: ".$successCount."/".$totalTestsCount." testů bylo úspěšných.</h2><a href=\"#Prehled\">(nahoru)</a><br><br><br>Autor: Tomáš Julina (xjulin08)<br></div>\n";
    echo "<table id=\"Tabulka2\">\n";
    echo "<tr id=\"Tabulka2\">\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
    echo "</tr>\n";
    echo "<h2>Neúspěšné testy:</h2>";
    echo $failedTestOutput;
    echo "</table>\n";
    echo "<a href=\"#Prehled\">(nahoru)</a>";
    echo "</body>\n";
    echo "</html>\n";

    fwrite(STDERR, "Testing ended...\n");

    if (sizeof($listOfSourceFiles) == 0)
    {
      fwrite(STDERR, "\nERROR: No \".src\" files found.\n");
      exit(err_inputFilePermission);
    }
  }
}

# FUNKCE PRO TESTOVÁNÍ INTERPRETU
function int_only()
{
  global $testsDirectory;
  global $intScriptFile;
  global $recursiveSearch;
  global $debug;

  global $currentTestSRC;
  global $currentTestIN; # pro simulaci STDIN u instrukce READ
  global $currentTestOUT;
  global $currentTestRC;
  global $failedTestOutput;

  $successCount = 0;
  $totalTestsCount = 0;
  $newINfile = false;
  $newOUTfile = false;
  $newRCfile = false;
  $returnCode = 0; # return kód z .rc (defaultně 0)

  fwrite(STDERR, "Started testing...\n");
  if ($recursiveSearch) ## rekurzivní průchod složkami
  {
    $listOfSourceFiles = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDirectory)); # rekurzivně projde všechny složky v cestě
    generateHTML_Header("int-only", $testsDirectory, "<ŽÁDNÝ>",$intScriptFile, $recursiveSearch);

    foreach ($iterator as $file)
    {
      if ($file->isDir()) continue;
      $path = $file->getPathname(); # celá cesta k souboru i s jménem testu
      $fileNamesPOM = explode("/",$path);
      $fileName = $fileNamesPOM[sizeof($fileNamesPOM)-1]; # název souboru s testem
      $path_noFileName = str_replace($fileName, '', $path); # cesta k souboru bez jména (funkce jako $testsDirectory)
      $input = scandir($path_noFileName);

      if (preg_match("~.src$~",$fileName))
      {
        $pom = explode(".",$fileName);

        $currentTestSRC = fopen($path_noFileName."/".$fileName, 'r') or die("Can't open file");
        fwrite(STDERR, "Test File: ".$path_noFileName.$fileName."\n");
        if (!array_search($pom[0].".in",$input))
        {
          $currentTestIN = fopen($path_noFileName."/".$pom[0].".in", 'w') or die("Can't create file");
          fclose($currentTestIN);
          $newINfile = true;
        }
        else
        {
          $currentTestIN = fopen($path_noFileName."/".$pom[0].".in", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".out",$input))
        {
          $currentTestOUT = fopen($path_noFileName."/".$pom[0].".out", 'w') or die("Can't create file");
          fclose($currentTestOUT);
          $newOUTfile = true;
        }
        else
        {
          $currentTestOUT = fopen($path_noFileName."/".$pom[0].".out", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".rc",$input))
        {
          $currentTestRC = fopen($path_noFileName."/".$pom[0].".rc", 'w') or die("Can't create file");
          fwrite($currentTestRC, "0");
          fclose($currentTestRC);
          $newRCfile = true;
          $returnCode = 0;
        }
        else
        {
          $currentTestRC = fopen($path_noFileName."/".$pom[0].".rc", 'r') or die("Can't create file");
          $returnCode = (int)fread($currentTestRC,filesize($path_noFileName."/".$pom[0].".rc"));
        }

        array_push($listOfSourceFiles, $fileName);

        if($debug) exec('python3 '.$intScriptFile.' --source='.$path_noFileName."/".$fileName.' --input='.$path_noFileName."/".$pom[0].".in".' >int-only-test_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné;
        else exec('python3.8 '.$intScriptFile.' --source='.$path_noFileName."/".$fileName.' --input='.$path_noFileName."/".$pom[0].".in".' >int-only-test_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné

        if ($ret_value == 0 && $returnCode == 0)
        {
          exec('diff -q int-only-test_output.out '.$path_noFileName."/".$pom[0].".out >diff.out");

          if(filesize("diff.out") != 0)
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            unlink(getcwd()."/"."diff.out");
            $totalTestsCount++;
          }
          else
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            unlink(getcwd()."/"."diff.out");
            $successCount++;
            $totalTestsCount++;
          }
        }
        else
        {
          if ($ret_value == $returnCode)
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $successCount++;
            $totalTestsCount++;
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $totalTestsCount++;
          }
        }

        fclose($currentTestSRC);
        unlink(getcwd()."/"."int-only-test_output.out");
        if (!$newINfile) fclose($currentTestIN);
        else unlink($path_noFileName."/".$pom[0].".in");
        if (!$newOUTfile) fclose($currentTestOUT);
        else unlink($path_noFileName."/".$pom[0].".out");
        if (!$newRCfile) fclose($currentTestRC);
        else unlink($path_noFileName."/".$pom[0].".rc");
        $newINfile = false;
        $newOUTfile = false;
        $newRCfile = false;
      }
    }
    //echo $failedTestOutput.$passedTestOutput;
    echo "</table>\n";
    echo "<div id=\"Shrnuti\"><br><br><br><h2>Shrnutí: ".$successCount."/".$totalTestsCount." testů bylo úspěšných.</h2><a href=\"#Prehled\">(nahoru)</a><br><br><br>Autor: Tomáš Julina (xjulin08)<br></div>\n";
    echo "<table id=\"Tabulka2\">\n";
    echo "<tr id=\"Tabulka2\">\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
    echo "</tr>\n";
    echo "<h2>Neúspěšné testy:</h2>";
    echo $failedTestOutput;
    echo "</table>\n";
    echo "<a href=\"#Prehled\">(nahoru)</a>";
    echo "</body>\n";
    echo "</html>\n";

    fwrite(STDERR, "Testing ended...\n");

    if (sizeof($listOfSourceFiles) == 0)
    {
      fwrite(STDERR, "\nERROR: No \".src\" files found.\n");
      exit(err_inputFilePermission);
    }
  }
  else
  {
    $input = scandir($testsDirectory);
    $listOfSourceFiles = array();

    generateHTML_Header("int-only", $testsDirectory, "<ŽÁDNÝ>", $intScriptFile, $recursiveSearch);

    foreach($input as $file)
    {
      if (preg_match("~.src$~",$file))
      {
        $pom = explode(".",$file);

        $currentTestSRC = fopen($testsDirectory."/".$file, 'r') or die("Can't open file");
        fwrite(STDERR, "Test File: ".$testsDirectory."/".$file."\n");
        if (!array_search($pom[0].".in",$input))
        {
          $currentTestIN = fopen($testsDirectory."/".$pom[0].".in", 'w') or die("Can't create file");
          fclose($currentTestIN);
          $newINfile = true;
        }
        else
        {
          $currentTestIN = fopen($testsDirectory."/".$pom[0].".in", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".out",$input))
        {
          $currentTestOUT = fopen($testsDirectory."/".$pom[0].".out", 'w') or die("Can't create file");
          fclose($currentTestOUT);
          $newOUTfile = true;
        }
        else
        {
          $currentTestOUT = fopen($testsDirectory."/".$pom[0].".out", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".rc",$input))
        {
          $currentTestRC = fopen($testsDirectory."/".$pom[0].".rc", 'w') or die("Can't create file");
          fwrite($currentTestRC, "0");
          fclose($currentTestRC);
          $newRCfile = true;
          $returnCode = 0;
        }
        else
        {
          $currentTestRC = fopen($testsDirectory."/".$pom[0].".rc", 'r') or die("Can't create file");
          $returnCode = (int)fread($currentTestRC,filesize($testsDirectory."/".$pom[0].".rc"));
        }

        array_push($listOfSourceFiles, $file);

        if($debug) exec('python3 '.$intScriptFile.' --source='.$testsDirectory."/".$file.' --input='.$testsDirectory."/".$pom[0].'.in >int-only-test_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné;
        else exec('python3.8 '.$intScriptFile.' --source='.$testsDirectory."/".$file.' --input='.$testsDirectory."/".$pom[0].'.in >int-only-test_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné

        if ($ret_value == 0 && $returnCode == 0)
        {
          exec('diff -q int-only-test_output.out '.$testsDirectory."/".$pom[0].".out >diff.out");

          if(filesize("diff.out") != 0)
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            unlink(getcwd()."/"."diff.out");
            $totalTestsCount++;
          }
          else
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            unlink(getcwd()."/"."diff.out");
            $successCount++;
            $totalTestsCount++;
          }
        }
        else
        {
          if ($ret_value == $returnCode)
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $successCount++;
            $totalTestsCount++;
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $totalTestsCount++;
          }
        }

        fclose($currentTestSRC);
        unlink(getcwd()."/"."int-only-test_output.out");
        if (!$newINfile) fclose($currentTestIN);
        else unlink($testsDirectory."/".$pom[0].".in");
        if (!$newOUTfile) fclose($currentTestOUT);
        else unlink($testsDirectory."/".$pom[0].".out");
        if (!$newRCfile) fclose($currentTestRC);
        else unlink($testsDirectory."/".$pom[0].".rc");
        $newINfile = false;
        $newOUTfile = false;
        $newRCfile = false;
      }
    }
    //echo $failedTestOutput.$passedTestOutput;
    echo "</table>\n";
    echo "<div id=\"Shrnuti\"><br><br><br><h2>Shrnutí: ".$successCount."/".$totalTestsCount." testů bylo úspěšných.</h2><a href=\"#Prehled\">(nahoru)</a><br><br><br>Autor: Tomáš Julina (xjulin08)<br></div>\n";
    echo "<table id=\"Tabulka2\">\n";
    echo "<tr id=\"Tabulka2\">\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
    echo "</tr>\n";
    echo "<h2>Neúspěšné testy:</h2>";
    echo $failedTestOutput;
    echo "</table>\n";
    echo "<a href=\"#Prehled\">(nahoru)</a>";
    echo "</body>\n";
    echo "</html>\n";

    fwrite(STDERR, "Testing ended...\n");

    if (sizeof($listOfSourceFiles) == 0)
    {
      fwrite(STDERR, "\nERROR: No \".src\" files found.\n");
      exit(err_inputFilePermission);
    }
  }
}

# FUNKCE PRO TESTOVÁNÍ PARSERU I INTERPRETU ZÁROVEŇ
function do_both()
{
  global $jexamxmlFile;
  global $testsDirectory;
  global $parseScriptFile;
  global $intScriptFile;
  global $recursiveSearch;
  global $debug;

  global $currentTestSRC;
  global $currentTestIN; # pro simulaci STDIN u instrukce READ
  global $currentTestOUT;
  global $currentTestRC;
  global $failedTestOutput;

  $successCount = 0;
  $totalTestsCount = 0;
  $newINfile = false;
  $newOUTfile = false;
  $newRCfile = false;
  $returnCode = 0; # return kód z .rc (defaultně 0)

  fwrite(STDERR, "Started testing...\n");
  if ($recursiveSearch) ## rekurzivní průchod složkami
  {
    $listOfSourceFiles = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDirectory)); # rekurzivně projde všechny složky v cestě
    generateHTML_Header("parse & interpret", $testsDirectory, $parseScriptFile, $intScriptFile, $recursiveSearch);

    foreach ($iterator as $file)
    {
      if ($file->isDir()) continue;
      $path = $file->getPathname(); # celá cesta k souboru i s jménem testu
      $fileNamesPOM = explode("/",$path);
      $fileName = $fileNamesPOM[sizeof($fileNamesPOM)-1]; # název souboru s testem
      $path_noFileName = str_replace($fileName, '', $path); # cesta k souboru bez jména (funkce jako $testsDirectory)
      $input = scandir($path_noFileName);

      if (preg_match("~.src$~",$fileName))
      {
        $pom = explode(".",$fileName);

        $currentTestSRC = fopen($path_noFileName."/".$fileName, 'r') or die("Can't open file");
        fwrite(STDERR, "Test File: ".$path_noFileName.$fileName."\n");
        if (!array_search($pom[0].".in",$input))
        {
          $currentTestIN = fopen($path_noFileName."/".$pom[0].".in", 'w') or die("Can't create file");
          fclose($currentTestIN);
          $newINfile = true;
        }
        else
        {
          $currentTestIN = fopen($path_noFileName."/".$pom[0].".in", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".out",$input))
        {
          $currentTestOUT = fopen($path_noFileName."/".$pom[0].".out", 'w') or die("Can't create file");
          fclose($currentTestOUT);
          $newOUTfile = true;
        }
        else
        {
          $currentTestOUT = fopen($path_noFileName."/".$pom[0].".out", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".rc",$input))
        {
          $currentTestRC = fopen($path_noFileName."/".$pom[0].".rc", 'w') or die("Can't create file");
          fwrite($currentTestRC, "0");
          fclose($currentTestRC);
          $newRCfile = true;
          $returnCode = 0;
        }
        else
        {
          $currentTestRC = fopen($path_noFileName."/".$pom[0].".rc", 'r') or die("Can't create file");
          $returnCode = (int)fread($currentTestRC,filesize($path_noFileName."/".$pom[0].".rc"));
        }

        array_push($listOfSourceFiles, $fileName);

        if($debug) exec('php '.$parseScriptFile.' <'.$path_noFileName."/".$fileName.' >both-parse_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné;
        else exec('php7.4 '.$parseScriptFile.' <'.$path_noFileName."/".$fileName.' >both-parse_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné

        if ($ret_value == 0 && $returnCode == 0)
        {
          if($debug) exec('python3 '.$intScriptFile.' --source=both-parse_output.xml --input='.$path_noFileName."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné;
          else exec('python3.8 '.$intScriptFile.' --source=both-parse_output.xml --input='.$path_noFileName."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné

          if ($ret_value == 0 && $returnCode == 0)
          {
            exec('diff -q both-int_output.out '.$path_noFileName."/".$pom[0].".out >diff.out");

            unlink(getcwd()."/"."both-int_output.out");
            if(filesize("diff.out") != 0)
            {
              //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."diff.out");
              $totalTestsCount++;
            }
            else
            {
              //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."diff.out");
              $successCount++;
              $totalTestsCount++;
            }
          }
          else
          {
            unlink(getcwd()."/"."both-int_output.out");
            if ($ret_value == $returnCode)
            {
              //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              $successCount++;
              $totalTestsCount++;
            }
            else
            {
              //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              $totalTestsCount++;
            }
          }
        }
        elseif ($ret_value == $returnCode)
        {
          //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
          echo "<tr id=\"Tabulka\">\n";
          echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
          echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
          echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
          echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
          echo "</tr>\n";
          $successCount++;
          $totalTestsCount++;
        }
        else
        {
          if($debug) exec('python3 '.$intScriptFile.' --source=both-parse_output.xml --input='.$path_noFileName."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné;
          else exec('python3.8 '.$intScriptFile.' --source=both-parse_output.xml --input='.$path_noFileName."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné

          unlink(getcwd()."/"."both-int_output.out");
          if ($ret_value == $returnCode)
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $successCount++;
            $totalTestsCount++;
          }
          else
          {
            //$failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$path_noFileName.$fileName."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$path_noFileName$fileName</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $totalTestsCount++;
          }
        }

        fclose($currentTestSRC);
        unlink(getcwd()."/"."both-parse_output.xml");
        if (!$newINfile) fclose($currentTestIN);
        else unlink($path_noFileName."/".$pom[0].".in");
        if (!$newOUTfile) fclose($currentTestOUT);
        else unlink($path_noFileName."/".$pom[0].".out");
        if (!$newRCfile) fclose($currentTestRC);
        else unlink($path_noFileName."/".$pom[0].".rc");
        $newINfile = false;
        $newOUTfile = false;
        $newRCfile = false;
      }
    }
    //echo $failedTestOutput.$passedTestOutput;
    echo "</table>\n";
    echo "<div id=\"Shrnuti\"><br><br><br><h2>Shrnutí: ".$successCount."/".$totalTestsCount." testů bylo úspěšných.</h2><a href=\"#Prehled\">(nahoru)</a><br><br><br>Autor: Tomáš Julina (xjulin08)<br></div>\n";
    echo "<table id=\"Tabulka2\">\n";
    echo "<tr id=\"Tabulka2\">\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
    echo "</tr>\n";
    echo "<h2>Neúspěšné testy:</h2>";
    echo $failedTestOutput;
    echo "</table>\n";
    echo "<a href=\"#Prehled\">(nahoru)</a>";
    echo "</body>\n";
    echo "</html>\n";

    fwrite(STDERR, "Testing ended...\n");

    if (sizeof($listOfSourceFiles) == 0)
    {
      fwrite(STDERR, "\nERROR: No \".src\" files found.\n");
      exit(err_inputFilePermission);
    }
  }
  else
  {
    $input = scandir($testsDirectory);
    $listOfSourceFiles = array();

    generateHTML_Header("parse & interpret", $testsDirectory, $parseScriptFile, $intScriptFile, $recursiveSearch);

    foreach($input as $file)
    {
      if (preg_match("~.src$~",$file))
      {
        $pom = explode(".",$file);

        $currentTestSRC = fopen($testsDirectory."/".$file, 'r') or die("Can't open file");
        fwrite(STDERR, "Test File: ".$testsDirectory."/".$file."\n");
        if (!array_search($pom[0].".in",$input))
        {
          $currentTestIN = fopen($testsDirectory."/".$pom[0].".in", 'w') or die("Can't create file");
          fclose($currentTestIN);
          $newINfile = true;
        }
        else
        {
          $currentTestIN = fopen($testsDirectory."/".$pom[0].".in", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".out",$input))
        {
          $currentTestOUT = fopen($testsDirectory."/".$pom[0].".out", 'w') or die("Can't create file");
          fclose($currentTestOUT);
          $newOUTfile = true;
        }
        else
        {
          $currentTestOUT = fopen($testsDirectory."/".$pom[0].".out", 'r') or die("Can't create file");
        }

        if (!array_search($pom[0].".rc",$input))
        {
          $currentTestRC = fopen($testsDirectory."/".$pom[0].".rc", 'w') or die("Can't create file");
          fwrite($currentTestRC, "0");
          fclose($currentTestRC);
          $newRCfile = true;
          $returnCode = 0;
        }
        else
        {
          $currentTestRC = fopen($testsDirectory."/".$pom[0].".rc", 'r') or die("Can't create file");
          $returnCode = (int)fread($currentTestRC,filesize($testsDirectory."/".$pom[0].".rc"));
        }

        array_push($listOfSourceFiles, $file);

        if($debug) exec('php '.$parseScriptFile.' <'.$testsDirectory."/".$file.' >both-parse_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné;
        else exec('php7.4 '.$parseScriptFile.' <'.$testsDirectory."/".$file.' >both-parse_output.xml 2> /dev/null',$nic,$ret_value); # spustí parse a uloží obsah do souboru + kód do proměnné

        if ($ret_value == 0 && $returnCode == 0)
        {
          if($debug) exec('python3 '.$intScriptFile.' --source=both-parse_output.xml --input='.$testsDirectory."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné;
          else exec('python3.8 '.$intScriptFile.' --source=both-parse_output.xml --input='.$testsDirectory."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné

          if ($ret_value == 0 && $returnCode == 0)
          {
            exec('diff -q both-int_output.out '.$testsDirectory."/".$pom[0].".out >diff.out");
            unlink(getcwd()."/"."both-int_output.out");

            if(filesize("diff.out") != 0)
            {
              $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."diff.out");
              $totalTestsCount++;
            }
            else
            {
              //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              unlink(getcwd()."/"."diff.out");
              $successCount++;
              $totalTestsCount++;
            }
          }
          else
          {
            unlink(getcwd()."/"."both-int_output.out");
            if ($ret_value == $returnCode)
            {
              //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              $successCount++;
              $totalTestsCount++;
            }
            else
            {
              $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
              echo "<tr id=\"Tabulka\">\n";
              echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
              echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
              echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
              echo "</tr>\n";
              $totalTestsCount++;
            }
          }
        }
        elseif ($ret_value == $returnCode)
        {
          unlink(getcwd()."/"."both-int_output.out");
          //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
          echo "<tr id=\"Tabulka\">\n";
          echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
          echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
          echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
          echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
          echo "</tr>\n";
          $successCount++;
          $totalTestsCount++;
        }
        else
        {
          if($debug) exec('python3 '.$intScriptFile.' --source=both-parse_output.xml --input='.$testsDirectory."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné;
          else exec('python3.8 '.$intScriptFile.' --source=both-parse_output.xml --input='.$testsDirectory."/".$pom[0].".in".' >both-int_output.out 2> /dev/null',$nic,$ret_value); # spustí interpret a uloží obsah do souboru + kód do proměnné

          unlink(getcwd()."/"."both-int_output.out");

          if ($ret_value == $returnCode)
          {
            //$passedTestOutput = $passedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:green;\"><font size=\"6\">ÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $successCount++;
            $totalTestsCount++;
          }
          else
          {
            $failedTestOutput = $failedTestOutput."<tr id=\"Tabulka\">\n"."<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">".$testsDirectory."/".$file."</font></th>\n"."<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n"."<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n"."</tr>\n";
            echo "<tr id=\"Tabulka\">\n";
            echo "<th id=\"Tabulka\" style=\"padding:10px\" align=\"left\"><font size=\"6\">$testsDirectory/$file</font></th>\n";
            echo "<th id=\"Tabulka\" style=\"color:red;\"><font size=\"6\">NEÚSPĚCH</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$ret_value."</font></th>\n";
            echo "<th id=\"Tabulka\"><font size=\"6\">".$returnCode."</font></th>\n";
            echo "</tr>\n";
            $totalTestsCount++;
          }
        }

        fclose($currentTestSRC);
        unlink(getcwd()."/"."both-parse_output.xml");
        if (!$newINfile) fclose($currentTestIN);
        else unlink($testsDirectory."/".$pom[0].".in");
        if (!$newOUTfile) fclose($currentTestOUT);
        else unlink($testsDirectory."/".$pom[0].".out");
        if (!$newRCfile) fclose($currentTestRC);
        else unlink($testsDirectory."/".$pom[0].".rc");
        $newINfile = false;
        $newOUTfile = false;
        $newRCfile = false;
      }
    }
    //echo $failedTestOutput.$passedTestOutput;
    echo "</table>\n";
    echo "<div id=\"Shrnuti\"><br><br><br><h2>Shrnutí: ".$successCount."/".$totalTestsCount." testů bylo úspěšných.</h2><a href=\"#Prehled\">(nahoru)</a><br><br><br>Autor: Tomáš Julina (xjulin08)<br></div>\n";
    echo "<table id=\"Tabulka2\">\n";
    echo "<tr id=\"Tabulka2\">\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Název testu</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Status</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Návratový kód</u></font></b></th>\n";
    echo "<th id=\"Tabulka2\" width=\"400px\"><b><font size=\"10\"><u>Očekávaný kód</u></font></b></th>\n";
    echo "</tr>\n";
    echo "<h2>Neúspěšné testy:</h2>";
    echo $failedTestOutput;
    echo "</table>\n";
    echo "<a href=\"#Prehled\">(nahoru)</a>";
    echo "</body>\n";
    echo "</html>\n";

    fwrite(STDERR, "Testing ended...\n");

    if (sizeof($listOfSourceFiles) == 0)
    {
      fwrite(STDERR, "\nERROR: No \".src\" files found.\n");
      exit(err_inputFilePermission);
    }
  }
}

# SPUŠTĚNÍ TESTŮ
if ($parseOnly) parse_only();
elseif ($intOnly) int_only();
else do_both();

 ?>
