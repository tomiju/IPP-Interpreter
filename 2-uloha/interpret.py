import xml.etree.ElementTree as ET # pro zpracování XML
import getopt # pro zpracování vstupních argumentů
import sys # pro stdin, stderr...
import re # pro regulární výrazy

"""

   Projekt: IPP - 2. část "Interpret XML reprezentace kódu"
   Author: Tomáš Julina (xjulin08)
   Datum: 20.2.2020

"""

# POMOCNÉ PROMĚNNÉ PRO VSTUPNÍ ARGUMENTY
helpArg = 0
sourceArg = 0
inputArg = 0

sourceFile = sys.stdin
inputFile = sys.stdin

# ZPRACOVÁNÍ VSTUPNÍCH ARGUMENTŮ
options, remainder = getopt.getopt(sys.argv[1:], '', ['help','source=','input=',])

for opt, arg in options:
    if opt == '--help':
        help = 1
        print("""
-> Help: interpret.py

-> Možné vstupní argumenty:
-> --source=file vstupní soubor s XML reprezentací zdrojového kódu
-> --input=file soubor se vstupy pro samotnou interpretaci zadaného zdrojového kódu

-> Alespoň jeden z parametrů (--source nebo --input) musí být vždy zadán.
-> Pokud jeden z nich chybí, tak jsou odpovídající data načítána ze standardního vstupu.""")
        exit()
    elif opt == '--source': # vstup pro program v XML - buď STDIN nebo ze SOUBORU
        if help == 1:
            print("ERROR: Wrong input parameter combination.", file=sys.stderr)
            exit(10)
        sourceFile = arg
        sourceArg = 1
    elif opt == '--input': # vstup pro "READ" buď ze STDIN nebo ze SOUBORU
        if help == 1:
            print("ERROR: Wrong input parameter combination.", file=sys.stderr)
            exit(10)
        inputFile = open(arg, "r")
        inputArg = 1

if (sourceArg == 0 and inputArg == 0):
    print("ERROR: Missing parameter.", file=sys.stderr)
    exit(10)


# POMOCNÉ TŘÍDY

# TŘÍDA PRO PRÁCI S RÁMCI (FRAMES)
class Frames:
    globalFrame = {}
    localFrame = None # na začátku je nedefinován
    temporaryFrame = None # na začátku je nedefinován
    frameStack = [] # zásobník rámců pro PUSHFRAME, POPFRAME

    @classmethod
    def addVarToFrame(self, var_name, arg_num):
        frameName = var_name['arg' + arg_num + '-varFrame']

        if frameName == "GF":
            frame = self.globalFrame
            pass
        elif frameName == "LF":
            if self.localFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.localFrame
            pass
        elif frameName == "TF":
            if self.temporaryFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.temporaryFrame
            pass
        else:
            print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
            exit(55)

        varName = var_name['arg' + arg_num + '-value']

        if varName in frame:
            print("ERROR: Attempted to redefine existing variable: \""+ varName+"\"", file=sys.stderr)
            exit(52)

        frame[varName] = ("uninitialised", None)
        pass

    @classmethod
    def changeVarValueInFrame(self, var_name, arg_num, value, type): # ukládá datový typ a hodnotu
        name_pom1 = 'arg' + arg_num + '-varFrame'
        name_pom2 = 'arg' + arg_num + '-value'
        frameName = var_name[name_pom1]

        if frameName == "GF":
            frame = self.globalFrame
            pass
        elif frameName == "LF":
            if self.localFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.localFrame
            if not self.frameStack:
                print("ERROR: Local frame is empty", file=sys.stderr)
                exit(55)
            else:
                self.localFrame = self.frameStack[-1]
            pass
        elif frameName == "TF":
            if self.temporaryFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.temporaryFrame
            pass
        else:
            print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
            exit(55)

        varName = var_name[name_pom2]

        if varName not in frame:
            print("ERROR: Attempted to write value to non-existing variable: \""+ varName+"\"", file=sys.stderr)
            exit(54)

        frame[varName] = (type, value)
        pass

    @classmethod
    def getVarValueFromFrame(self, var_name, arg_num): # vrací datový typ a hodnotu
        frameName = var_name['arg' + arg_num + '-varFrame']

        if frameName == "GF":
            frame = self.globalFrame
            pass
        elif frameName == "LF":
            if self.localFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.localFrame
            pass
        elif frameName == "TF":
            if self.temporaryFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.temporaryFrame
            pass
        else:
            print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
            exit(55)

        varName = var_name['arg' + arg_num + '-value']

        if varName not in frame:
            print("ERROR: Attempted to read value from non-existing variable: \""+ varName+"\"", file=sys.stderr)
            exit(54)

        if frame[varName][0] == "uninitialised":
            print("ERROR: Attempted to read value from unitialized variable: \""+ varName+"\"", file=sys.stderr)
            exit(56)

        return frame[varName][1], frame[varName][0]
        pass

    @classmethod
    def is_Initialized(self, var_name, arg_num): # kontroluje, zda je rámec inicializovaný
        frameName = var_name['arg' + arg_num + '-varFrame']

        if frameName == "GF":
            frame = self.globalFrame
            pass
        elif frameName == "LF":
            if self.localFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.localFrame
            pass
        elif frameName == "TF":
            if self.temporaryFrame == None:
                print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
                exit(55)
            frame = self.temporaryFrame
            pass
        else:
            print("ERROR: Frame \"" + frameName + "\" doesn't exist", file=sys.stderr)
            exit(55)

        varName = var_name['arg' + arg_num + '-value']

        if varName not in frame:
            print("ERROR: Attempted to read value from non-existing variable: \""+ varName+"\"", file=sys.stderr)
            exit(54)

        if frame[varName][0] == "uninitialised":
            return False

        return True

# TŘÍDA PRO PRÁCI S NÁVĚŠTÍMI (LABEL)
class Labels:
    labels = {}

    @classmethod
    def addToLabel(self, labelName): # definuje nový label
        lblName = str(labelName)

        if lblName in self.labels:
            print("ERROR: Attempted to redefine existing label: \""+ lblName+"\"", file=sys.stderr)
            exit(52)
        else:
            self.labels[lblName] = Interpret.instructionOrder

    @classmethod
    def jumpToLabel(self, labelName): # přejde na label, pokud existuje
        lblName = str(labelName)

        if lblName in self.labels:
            Interpret.instructionOrder = self.labels[lblName]
        else:
            print("ERROR: Attempted to jump to non-existing label: \""+ lblName+"\"", file=sys.stderr)
            exit(52)
        pass
    pass

class Stacks:
    def __init__(self):
        self.values = []

    def push(self, value):
        self.values.append(value)
        pass

    def pop(self):
        if not self.values:
            print("ERROR: Attempted to pop empty stack.", file=sys.stderr)
            exit(56)
            pass
        value = self.values.pop()
        return value[0], value[1]
        pass

# HLAVNÍ TŘÍDA CELÉHO INTERPRETU - volá potřebné funkce a spouští instrukce
class Interpret:
    instructionOrder = 1
    callStack = Stacks() # pro uložení aktuální pozice z čítače při volání funkce CALL...
    dataStack = Stacks() # pro uložení hodnoty při instrukci PUSHS...

    def __init__(self, sourceFile):
        self.instructionOrder = Interpret.instructionOrder # instanční verze třídní proměnné pro číslo aktuální instrukce
        self.instrList = parseInput(sourceFile) # list instrukcí - počítá se od 0!!!

    # funkce najde všechny labels a poté provede všechny instrukce, které se nacházejí v seznamu
    def doEverything(self):
        self.sortInstructionList(self.instrList)

        self.findLabels(self.instrList, self.instructionOrder) # v první řadě musím najít labels, protože je možné, že bude potřeba hned na začátku někam skočit pomocí jump
        self.instructionOrder = 1 # reset
        Interpret.instructionOrder = 1

        self.executeInstructions(self.instrList, self.instructionOrder)

    # najde LABEL a provede příkaz LABEL
    def findLabels(self, instrList, instructionOrder):
        while 42:
            instr = self.instrList[self.instructionOrder-1] # -1 protože pole je indexované od 0

            if instr is None:
                break

            if instr.opCode == "LABEL":
                Interpret.instructionOrder = self.instructionOrder
                instr.checkArguments(instr.opCode)
                instr.exec()

            self.instructionOrder += 1

    # provede všechny instrukce kromě LABEL
    def executeInstructions(self, instrList, instructionOrder):
        while 42:
            instr = self.instrList[self.instructionOrder-1]

            if instr is None:
                break

            if instr.opCode == "LABEL":
                self.instructionOrder += 1
                Interpret.instructionOrder = self.instructionOrder
                continue

            instr.checkArguments(instr.opCode)
            instr.exec()

            self.instructionOrder = Interpret.instructionOrder

            self.instructionOrder += 1
            Interpret.instructionOrder += 1
        pass

    def sortInstructionList(self, instrList): # klasický bubble sort na seřazení listu příkazů podle atributu order
        instCnt = len(instrList)
        for i in range(instCnt):
            for j in range(0, instCnt-i-1):
                if j != (instCnt-1) and j + 1 != (instCnt-1):
                    if instrList[j].operands['order'] > instrList[j+1].operands['order'] :
                        instrList[j], instrList[j+1] = instrList[j+1], instrList[j]

# TŘÍDA REPREZENTUJÍCÍ INSTRUKCI - obsahuje potřebné funkce pro provádění instrukcí a práci s tím spojenou
class Instruction:
    def __init__(self, opcode, operands):
        self.opCode = opcode
        self.operands = operands
        self.operandsCnt = int(len(self.operands)/3)

    def checkArguments(self, currentOpCode): # zkontroluje jednoznačné chyby v argumentech
        if self.operandsCnt == 0:
            if self.opCode == "CREATEFRAME" or self.opCode == "PUSHFRAME" or self.opCode == "POPFRAME" or self.opCode == "RETURN" or self.opCode == "BREAK":
                # CREATEFRAME
                # PUSHFRAME
                # POPFRAME
                # RETURN
                # BREAK
                pass
            else:
                print("ERROR: Something is wrong with zero-argument instruction: ", self.opCode,file=sys.stderr)
                exit(32)
            pass
        elif self.operandsCnt == 1:
            if self.opCode == "PUSHS" or self.opCode == "WRITE" or self.opCode == "EXIT" or self.opCode == "DPRINT":
                # PUSHS (symb)
                # WRITE (symb)
                # EXIT (symb)
                # DPRINT (symb)
                try:
                    if self.operands['arg1-type'] != "var" and self.operands['arg1-type'] not in ['int', 'bool', 'string', 'nil']:
                        print("ERROR: Wrong arg type of one-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in one-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "DEFVAR" or self.opCode == "POPS":
                # DEFVAR (var)
                # POPS (var)
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of one-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in one-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "CALL" or self.opCode == "LABEL" or self.opCode == "JUMP":
                # CALL (label)
                # LABEL (label)
                # JUMP (label)
                try:
                    if self.operands['arg1-type'] != "label":
                        print("ERROR: Wrong arg type of one-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in one-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            else:
                print("ERROR: Something is wrong with one-argument instruction: ", self.opCode,file=sys.stderr)
                exit(32)
            pass
        elif self.operandsCnt == 2:
            if self.opCode == "MOVE" or self.opCode == "INT2CHAR" or self.opCode == "STRLEN" or self.opCode == "TYPE":
                # MOVE (var) (symb)
                # INT2CHAR (var) (symb)
                # STRLEN (var) (symb)
                # TYPE (var) (symb)
                try:
                    if self.opCode == "INT2CHAR":
                        if self.operands['arg1-type'] != "var":
                            print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "int":
                            print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                    else:
                        if self.operands['arg1-type'] != "var":
                            print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] not in ['int', 'bool', 'string', 'nil']:
                            print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in two-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "READ":
                # READ (var) (type)
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "type":
                        print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in two-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
            elif self.opCode == "NOT":
                # NOT (var) (symb) bool
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "bool":
                        print("ERROR: Wrong arg type of two-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in two-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            else:
                print("ERROR: Something is wrong with two-argument instruction ", self.opCode,file=sys.stderr)
                exit(32)
        elif self.operandsCnt == 3:
            if self.opCode == "ADD" or self.opCode == "SUB" or self.opCode == "MUL" or self.opCode == "IDIV":
                # ADD (var) (symb1) (symb2) int
                # SUB (var) (symb1) (symb2) int
                # MUL (var) (symb1) (symb2) int
                # IDIV (var) (symb1) (symb2) int
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "int":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] != "int":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "LT" or self.opCode == "GT" or self.opCode == "EQ":
                # LT (var) (symb1) (symb2) int/bool/string
                # GT (var) (symb1) (symb2) int/bool/string
                # EQ (var) (symb1) (symb2) int/bool/string
                try:
                    if self.opCode == "EQ":
                        if self.operands['arg1-type'] != "var":
                            print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] not in ['int', 'bool', 'string', 'nil']:
                            print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] not in ['int', 'bool', 'string', 'nil']:
                            print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        pass
                    else:
                        if self.operands['arg1-type'] != "var":
                            print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] not in ['int', 'bool', 'string']:
                            print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                        if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] not in ['int', 'bool', 'string']:
                            print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                            exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "AND" or self.opCode == "OR":
                # AND (var) (symb1) (symb2) bool
                # OR (var) (symb1) (symb2) bool
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "bool":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] != "bool":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "STRI2INT" or self.opCode == "GETCHAR":
                # STRI2INT (var) (symb1) (symb2) string int
                # GETCHAR (var) (symb1) (symb2) string int
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "string":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] != "int":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "CONCAT":
                # CONCAT (var) (symb1) (symb2) string string
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "string":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] != "string":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "SETCHAR":
                # SETCHAR (var) (symb1) (symb2) int string
                try:
                    if self.operands['arg1-type'] != "var":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] != "int":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] != "string":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            elif self.opCode == "JUMPIFEQ" or self.opCode == "JUMPIFNEQ":
                # JUMPIFEQ (label) (symb1) (symb2)
                # JUMPIFNEQ (label) (symb1) (symb2)
                try:
                    if self.operands['arg1-type'] != "label":
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg2-type'] != "var" and self.operands['arg2-type'] not in ['int', 'bool', 'string', 'nil']:
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                    if self.operands['arg3-type'] != "var" and self.operands['arg3-type'] not in ['int', 'bool', 'string', 'nil']:
                        print("ERROR: Wrong arg type of three-argument instruction: ", self.opCode, file=sys.stderr)
                        exit(53)
                except KeyError:
                    print("ERROR: Unexpected argument number (XML) in three-argument instruction: ", self.opCode, file=sys.stderr)
                    exit(32)
                pass
            else:
                print("ERROR: Something is wrong with three-argument instruction: ", self.opCode,file=sys.stderr)
                exit(32)
        else:
            print("ERROR: Something was wrong with instruction "+ self.opCode +" during argument checking.", file=sys.stderr)
            exit(32)
        pass

    def exec(self):
        if self.opCode == "LABEL":
            self.LABEL()
        elif self.opCode == "MOVE":
            self.MOVE()
        elif self.opCode == "CREATEFRAME":
            self.CREATEFRAME()
        elif self.opCode == "PUSHFRAME":
            self.PUSHFRAME()
        elif self.opCode == "POPFRAME":
            self.POPFRAME()
        elif self.opCode == "DEFVAR":
            self.DEFVAR()
        elif self.opCode == "CALL":
            self.CALL()
        elif self.opCode == "RETURN":
            self.RETURN()
        elif self.opCode == "PUSHS":
            self.PUSHS()
        elif self.opCode == "POPS":
            self.POPS()
        elif self.opCode == "ADD":
            self.ADD()
        elif self.opCode == "MUL":
            self.MUL()
        elif self.opCode == "SUB":
            self.SUB()
        elif self.opCode == "IDIV":
            self.IDIV()
        elif self.opCode == "LT":
            self.LT()
        elif self.opCode == "GT":
            self.GT()
        elif self.opCode == "EQ":
            self.EQ()
        elif self.opCode == "AND":
            self.AND()
        elif self.opCode == "OR":
            self.OR()
        elif self.opCode == "NOT":
            self.NOT()
        elif self.opCode == "INT2CHAR":
            self.INT2CHAR()
        elif self.opCode == "STRI2INT":
            self.STRI2INT()
        elif self.opCode == "READ":
            self.READ()
        elif self.opCode == "WRITE":
            self.WRITE()
        elif self.opCode == "CONCAT":
            self.CONCAT()
        elif self.opCode == "STRLEN":
            self.STRLEN()
        elif self.opCode == "GETCHAR":
            self.GETCHAR()
        elif self.opCode == "SETCHAR":
            self.SETCHAR()
        elif self.opCode == "TYPE":
            self.TYPE()
        elif self.opCode == "JUMP":
            self.JUMP()
        elif self.opCode == "JUMPIFEQ":
            self.JUMPIFEQ()
        elif self.opCode == "JUMPIFNEQ":
            self.JUMPIFNEQ()
        elif self.opCode == "EXIT":
            self.EXIT()
        elif self.opCode == "DPRINT":
            self.DPRINT()
        elif self.opCode == "BREAK":
            self.BREAK()
        else:
            print("ERROR: Unknown instruction.", file=sys.stderr)
            exit(32)
            pass
        pass

    # FUNKCE, KTERÉ PROVEDOU DANÉ INSTRUKCE
    def MOVE(self):
        if self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            Frames.changeVarValueInFrame(self.operands, "1", self.operands['arg2-value'], self.operands['arg2-type'])
            pass
        elif self.operands['arg2-type'] == "var":
            value, type = Frames.getVarValueFromFrame(self.operands, "2")
            Frames.changeVarValueInFrame(self.operands, "1", value, type)
            pass
        pass

    def CREATEFRAME(self):
        Frames.temporaryFrame = {}
        pass

    def PUSHFRAME(self):
        if Frames.temporaryFrame is None:
            print("ERROR: Temporary frame was not defined - use instruction CREATEFRAME first.", file=sys.stderr)
            exit(55)
            pass
        Frames.frameStack.append(Frames.temporaryFrame) # přesuň TF na zásobník rámců
        Frames.localFrame = Frames.frameStack[-1] # LF ukazuje na vrchol zásobníku rámců
        Frames.temporaryFrame = None # TF je po provedení nedefinován
        pass

    def POPFRAME(self):
        if not Frames.frameStack:
            print("ERROR: Can't pop local frame - it's empty.", file=sys.stderr)
            exit(55)
        Frames.temporaryFrame = Frames.frameStack[-1] # poslední hodnotu ze stacku uložit do TF
        Frames.frameStack.pop();

        if not Frames.frameStack:
            Frames.localFrame = None
        else:
            Frames.localFrame = Frames.frameStack[-1]
        pass

    def DEFVAR(self):
        Frames.addVarToFrame(self.operands, "1")
        pass

    def CALL(self):
        Interpret.callStack.push(("int", Interpret.instructionOrder)) # debug
        Labels.jumpToLabel(self.operands['arg1-value'])
        pass

    def RETURN(self):
        type, Interpret.instructionOrder = Interpret.callStack.pop()
        pass

    def PUSHS(self):
        if self.operands['arg1-type'] in ['int', 'bool', 'string', 'nil']:
            Interpret.dataStack.push((self.operands['arg1-type'],self.operands['arg1-value']))
            pass
        elif self.operands['arg1-type'] == "var":
            value, type = Frames.getVarValueFromFrame(self.operands, str(self.operandsCnt))
            Interpret.dataStack.push((type, value))
            print(Interpret.dataStack.pop())
            pass
        pass

    def POPS(self):
        type, value = Interpret.dataStack.pop()
        Frames.changeVarValueInFrame(self.operands, "1", value, type)
        pass

    def ADD(self):
        if self.operands['arg2-type'] == "int":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "int":
                print("ERROR: Instruction ADD operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction ADD operand is not int.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "int":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "int":
                print("ERROR: Instruction ADD operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction ADD operand is not int.", file=sys.stderr)
            exit(53)
            pass

        result = int(arg2_value) + int(arg3_value)
        Frames.changeVarValueInFrame(self.operands, "1", result, "int")
        pass

    def SUB(self):
        if self.operands['arg2-type'] == "int":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "int":
                print("ERROR: Instruction SUB operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction ADD operand is not int.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "int":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "int":
                print("ERROR: Instruction SUB operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction SUB operand is not int.", file=sys.stderr)
            exit(53)
            pass

        result = int(arg2_value) - int(arg3_value)
        Frames.changeVarValueInFrame(self.operands, "1", result, "int")
        pass

    def MUL(self):
        if self.operands['arg2-type'] == "int":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "int":
                print("ERROR: Instruction MUL operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction MUL operand is not int.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "int":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "int":
                print("ERROR: Instruction MUL operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction MUL operand is not int.", file=sys.stderr)
            exit(53)
            pass

        result = int(arg2_value) * int(arg3_value)
        Frames.changeVarValueInFrame(self.operands, "1", result, "int")
        pass

    def IDIV(self):
        if self.operands['arg2-type'] == "int":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "int":
                print("ERROR: Instruction IDIV operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction IDIV operand is not int.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "int":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "int":
                print("ERROR: Instruction IDIV operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction IDIV operand is not int.", file=sys.stderr)
            exit(53)
            pass
        if int(arg3_value) == 0:
            print("ERROR: Attempted to divide by 0!", file=sys.stderr)
            exit(57)
            pass
        result = int(arg2_value) / int(arg3_value)
        Frames.changeVarValueInFrame(self.operands, "1", int(result), "int")
        pass

    def LT(self):
        if self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type not in ['int', 'bool', 'string']:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            arg2_value = self.operands['arg2-value']
            if self.operands['arg2-type'] == 'bool':
                arg2_type = 'bool'
                pass
            elif self.operands['arg2-type'] == 'int':
                arg2_type = 'int'
                pass
            elif self.operands['arg2-type'] == 'string':
                arg2_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass

        if self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type not in ['int', 'bool', 'string']:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg3-type'] in ['int', 'bool', 'string', 'nil']:
            arg3_value = self.operands['arg3-value']
            if self.operands['arg3-type'] == 'bool':
                arg3_type = 'bool'
                pass
            elif self.operands['arg3-type'] == 'int':
                arg3_type = 'int'
                pass
            elif self.operands['arg3-type'] == 'string':
                arg3_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass

        if arg2_type == arg3_type:
            if arg2_type == "int":
                result = int(arg2_value) < int(arg3_value)
            elif arg2_type == "string":
                result = str(arg2_value) < str(arg3_value)
            elif arg2_type == "bool":
                result = bool(arg2_value) < bool(arg3_value)
            Frames.changeVarValueInFrame(self.operands, "1", str(result).lower(), "bool")
            pass
        else:
            print("ERROR: Wrong operand data type.", file=sys.stderr)
            exit(53)
            pass
        pass

    def GT(self):
        if self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type not in ['int', 'bool', 'string']:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            arg2_value = self.operands['arg2-value']
            if self.operands['arg2-type'] == 'bool':
                arg2_type = 'bool'
                pass
            elif self.operands['arg2-type'] == 'int':
                arg2_type = 'int'
                pass
            elif self.operands['arg2-type'] == 'string':
                arg2_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass

        if self.operands['arg3-type'] == "var":
            arg3_value, arg3_value = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type not in ['int', 'bool', 'string']:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg3-type'] in ['int', 'bool', 'string', 'nil']:
            arg3_value = self.operands['arg3-value']
            if self.operands['arg3-type'] == 'bool':
                arg3_type = 'bool'
                pass
            elif self.operands['arg3-type'] == 'int':
                arg3_type = 'int'
                pass
            elif self.operands['arg3-type'] == 'string':
                arg3_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass

        if arg2_type == arg3_type:
            if arg2_type == "int":
                result = int(arg2_value) > int(arg3_value)
            elif arg2_type == "string":
                result = str(arg2_value) > str(arg3_value)
            elif arg2_type == "bool":
                result = bool(arg2_value) > bool(arg3_value)
            Frames.changeVarValueInFrame(self.operands, "1", str(result).lower(), "bool")
            pass
        else:
            print("ERROR: Wrong operand data type.", file=sys.stderr)
            exit(53)
            pass
        pass

    def EQ(self):
        if self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type not in ['int', 'bool', 'string', 'nil']:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            arg2_value = self.operands['arg2-value']
            if self.operands['arg2-type'] == 'bool':
                arg2_type = 'bool'
                pass
            elif self.operands['arg2-type'] == 'int':
                arg2_type = 'int'
                pass
            elif self.operands['arg2-type'] == 'nil':
                arg2_type = 'nil'
                pass
            elif self.operands['arg2-type'] == 'string':
                arg2_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass

        if self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type not in ['int', 'bool', 'string', 'nil']:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg3-type'] in ['int', 'bool', 'string', 'nil']:
            arg3_value = self.operands['arg3-value']
            if self.operands['arg3-type'] == 'bool':
                arg3_type = 'bool'
                pass
            elif self.operands['arg3-type'] == 'int':
                arg3_type = 'int'
                pass
            elif self.operands['arg3-type'] == 'nil':
                arg3_type = 'nil'
                pass
            elif self.operands['arg3-type'] == 'string':
                arg3_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
        if arg2_type == arg3_type or (arg2_type == 'nil' or arg3_type == 'nil'):
            if arg2_type == "int":
                if arg3_type == "nil":
                    result = False
                else:
                    result = int(arg2_value) == int(arg3_value)
            elif arg2_type == "string":
                if arg3_type == "nil":
                    result = False
                else:
                    result = str(arg2_value) == str(arg3_value)
            elif arg2_type == "bool":
                if arg3_type == "nil":
                    result = False
                else:
                    result = bool(arg2_value) == bool(arg3_value)
            elif arg2_type == "nil" and arg3_type == "nil":
                result = True
            else:
                result = False
            Frames.changeVarValueInFrame(self.operands, "1", str(result).lower(), "bool")
            pass
        else:
            print("ERROR: Wrong operand data type.", file=sys.stderr)
            exit(53)
            pass
        pass

    def AND(self):
        if self.operands['arg2-type'] == "bool":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "bool":
                print("ERROR: Instruction AND operand is not bool.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction AND operand is not bool.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "bool":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "bool":
                print("ERROR: Instruction AND operand is not bool.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction AND operand is not bool.", file=sys.stderr)
            exit(53)
            pass

        result = arg2_value and arg3_value
        Frames.changeVarValueInFrame(self.operands, "1", str(result).lower(), "bool")
        pass

    def OR(self):
        if self.operands['arg2-type'] == "bool":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "bool":
                print("ERROR: Instruction OR operand is not bool.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction OR operand is not bool.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "bool":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "bool":
                print("ERROR: Instruction OR operand is not bool.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction OR operand is not bool.", file=sys.stderr)
            exit(53)
            pass

        result = arg2_value or arg3_value
        Frames.changeVarValueInFrame(self.operands, "1", str(result).lower(), "bool")
        pass

    def NOT(self):
        if self.operands['arg2-type'] == "bool":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "bool":
                print("ERROR: Instruction NOT operand is not bool.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction NOT operand is not bool.", file=sys.stderr)
            exit(53)
            pass

        result = not arg2_value
        Frames.changeVarValueInFrame(self.operands, "1", str(result).lower(), "bool")
        pass

    def INT2CHAR(self):
        if self.operands['arg2-type'] == "int":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "int":
                print("ERROR: Instruction INT2CHAR operand is not int.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Instruction INT2CHAR operand is not int.", file=sys.stderr)
            exit(53)
            pass

        try:
            result = chr(int(arg2_value))
            Frames.changeVarValueInFrame(self.operands, "1", result, "string")
        except ValueError:
            print("ERROR: Not a valid ordinal value.", file=sys.stderr)
            exit(58)
        pass

    def STRI2INT(self):
        if self.operands['arg2-type'] == "string":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "string":
                print("ERROR: Wrong STRI2INT operand (2).", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong STRI2INT operand.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "int":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "int":
                print("ERROR: Wrong STRI2INT operand (3).", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong STRI2INT operand.", file=sys.stderr)
            exit(53)
            pass

        if int(arg3_value) < len(arg2_value) and not int(arg3_value) < 0:
            Frames.changeVarValueInFrame(self.operands, "1", ord(arg2_value[int(arg3_value)]), "int")
        else:
            print("ERROR: Can't access values outside the string. (wrong indexation)", file=sys.stderr)
            exit(58)
        pass

    def READ(self):
        if inputArg == 1:
            inputFromFile = inputFile.readline()
            pass
        else:
            inputFromFile = input()

        if inputFromFile == "":
            Frames.changeVarValueInFrame(self.operands, "1", "nil", "nil")
            pass
        else:
            inputFromFile = inputFromFile.rstrip()
            if self.operands['arg2-value'] == "int":
                if is_int(inputFromFile) and re.match(r'[^\s\\]*', inputFromFile, re.UNICODE):
                    Frames.changeVarValueInFrame(self.operands, "1", inputFromFile, "int")
                    pass
                else:
                    Frames.changeVarValueInFrame(self.operands, "1", "nil", "nil")
                    pass
                pass
            elif self.operands['arg2-value'] == "string":
                if re.match(r'^([^\s\\]*([\\]{1}[0-9]{3})*[^\s\\]*)*', inputFromFile, re.UNICODE) and is_string(inputFromFile):
                    escapeSequences = set(re.findall(r"\\([0-9]{3})", inputFromFile)) # najdu všechny správné escape sekvence a uložím si číselné hodnoty - pomocí set vyhodím duplicity
                    escapeSequences = list(escapeSequences) # konvertuji set na list a potom pomocí for každou ASCII hodnotu konvertuji na znak
                    for escapeSequence in escapeSequences:
                        if escapeSequence == "092": # při testování jsem zjistil, že "\" z nějakého důvodu skončí s errorem - je třeba nahradit to zvlášť
                            inputFromFile = re.sub("\\\\092", "\\\\", inputFromFile)
                            continue
                        inputFromFile = re.sub("\\\\" + escapeSequence, chr(int(escapeSequence)), inputFromFile) # nahrazení

                    Frames.changeVarValueInFrame(self.operands, "1", inputFromFile, "string")
                    pass
                else:
                    Frames.changeVarValueInFrame(self.operands, "1", "nil", "nil")
                    pass
                pass
            elif self.operands['arg2-value'] == "bool":
                if inputFromFile.lower() == "true":
                    Frames.changeVarValueInFrame(self.operands, "1", "true", "bool")
                    pass
                else:
                    Frames.changeVarValueInFrame(self.operands, "1", "false", "bool")
                pass
            else:
                Frames.changeVarValueInFrame(self.operands, "1", "nil", "nil")
                pass
        pass

    def WRITE(self):
        if self.operands['arg1-type'] == "var":
            value, type = Frames.getVarValueFromFrame(self.operands, "1")
            if type == "bool":
                print(str(value).lower(), end='',file=sys.stdout)
                pass
            elif type == "nil":
                print("", end='', file=sys.stdout)
                pass
            else:
                print(value, end='' ,file=sys.stdout)
                pass
            pass
        else:
            value = self.operands['arg1-value']
            if is_bool(value) and self.operands['arg1-type'] == "bool":
                print(str(value).lower(), end='', file=sys.stdout)
                pass
            elif is_nil(value) and self.operands['arg1-type'] == "nil":
                print("", end='', file=sys.stdout)
                pass
            else:
                print(value, end='' ,file=sys.stdout)
                pass
        pass

    def CONCAT(self):
        if self.operands['arg2-type'] == "string":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "string":
                print("ERROR: Wrong CONCAT operand.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong CONCAT operand.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "string":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "string":
                print("ERROR: Wrong CONCAT operand.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong CONCAT operand.", file=sys.stderr)
            exit(53)
            pass

        result = arg2_value + arg3_value
        Frames.changeVarValueInFrame(self.operands, "1", result, "string")
        pass

    def STRLEN(self):
        if self.operands['arg2-type'] == "string":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "string":
                print("ERROR: Wrong STRLEN operand.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong STRLEN operand.", file=sys.stderr)
            exit(53)
            pass

        result = len(arg2_value)
        Frames.changeVarValueInFrame(self.operands, "1", int(result), "int")

    def GETCHAR(self):
        if self.operands['arg2-type'] == "string":
            arg2_value = self.operands['arg2-value']
            pass
        elif self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type != "string":
                print("ERROR: Wrong GETCHAR operand.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong GETCHAR operand.", file=sys.stderr)
            exit(53)
            pass

        if self.operands['arg3-type'] == "int":
            arg3_value = self.operands['arg3-value']
            pass
        elif self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type != "int":
                print("ERROR: Wrong GETCHAR operand.", file=sys.stderr)
                exit(53)
                pass
            pass
        else:
            print("ERROR: Wrong GETCHAR operand.", file=sys.stderr)
            exit(53)
            pass

        if int(arg3_value) < len(arg2_value) and not int(arg3_value) < 0:
            Frames.changeVarValueInFrame(self.operands, "1", arg2_value[int(arg3_value)], "string")
        else:
            print("ERROR: Can't access values outside the string. (wrong indexation)", file=sys.stderr)
            exit(58)
        pass

    def SETCHAR(self):
        arg1_value, arg1_type = Frames.getVarValueFromFrame(self.operands, "1")
        if arg1_type == "string":
            if self.operands['arg3-type'] == "string":
                arg3_value = self.operands['arg3-value']
                pass
            elif self.operands['arg3-type'] == "var":
                arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
                if arg3_type != "string":
                    print("ERROR: Wrong SETCHAR operand.", file=sys.stderr)
                    exit(53)
                    pass
                pass
            else:
                print("ERROR: Wrong SETCHAR operand.", file=sys.stderr)
                exit(53)
                pass

            if len(arg3_value) == 0:
                print("ERROR: SETCHAR arg3 can't be empty string!", file=sys.stderr)
                exit(58)

            if self.operands['arg2-type'] == "int":
                arg2_value = self.operands['arg2-value']
                pass
            elif self.operands['arg2-type'] == "var":
                arg2_value, arg2_value = Frames.getVarValueFromFrame(self.operands, "2")
                if arg2_value != "int":
                    print("ERROR: Wrong SETCHAR operand.", file=sys.stderr)
                    exit(53)
                    pass
                pass
            else:
                print("ERROR: Wrong SETCHAR operand.", file=sys.stderr)
                exit(53)
                pass

            if self.operands['arg1-type'] == "var":
                arg1_value, arg1_type = Frames.getVarValueFromFrame(self.operands, "1")
                pass
            else:
                print("ERROR: Wrong SETCHAR operand.", file=sys.stderr)
                exit(53)

            if int(arg2_value) < len(arg1_value) and not int(arg2_value) < 0:
                arg1_value = list(arg1_value)
                arg1_value[int(arg2_value)] = arg3_value[0]
                arg1_value = "".join(arg1_value)

                Frames.changeVarValueInFrame(self.operands, "1", arg1_value, "string")
            else:
                print("ERROR: Can't access values outside the string. (wrong indexation)", file=sys.stderr)
                exit(58)
            pass
        else:
            print("ERROR: Wrong SETCHAR operand.", file=sys.stderr)
            exit(53)

    def TYPE(self):
        if self.operands['arg2-type'] == "var":
            if Frames.is_Initialized(self.operands, str(self.operandsCnt)):
                value, type = Frames.getVarValueFromFrame(self.operands, str(self.operandsCnt))
            else:
                value = ""
                type = "uninitialised"

            if type == "bool":
                Frames.changeVarValueInFrame(self.operands, "1", 'bool', "string")
                pass
            elif type == "int":
                Frames.changeVarValueInFrame(self.operands, "1", 'int', "string")
                pass
            elif type == "nil":
                Frames.changeVarValueInFrame(self.operands, "1", 'nil', "string")
                pass
            elif type == "string":
                Frames.changeVarValueInFrame(self.operands, "1", 'string', "string")
                pass
            elif type == "uninitialised":
                Frames.changeVarValueInFrame(self.operands, "1", '', "")
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            value = self.operands['arg2-value']
            if self.operands['arg2-type'] == 'bool':
                Frames.changeVarValueInFrame(self.operands, "1", 'bool', "string")
                pass
            elif self.operands['arg2-type'] == 'int':
                Frames.changeVarValueInFrame(self.operands, "1", 'int', "string")
                pass
            elif self.operands['arg2-type'] == 'nil':
                Frames.changeVarValueInFrame(self.operands, "1", 'nil', "string")
                pass
            elif self.operands['arg2-type'] == 'string':
                Frames.changeVarValueInFrame(self.operands, "1", 'string', "string")
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        pass

    def LABEL(self):
        Labels.addToLabel(self.operands['arg1-value'])
        pass

    def JUMP(self):
        Labels.jumpToLabel(self.operands['arg1-value'])
        pass

    def JUMPIFEQ(self):
        if self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type not in ["int", "bool", "nil", "string"]:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            arg2_value = self.operands['arg2-value']
            if self.operands['arg2-type'] == 'bool':
                arg2_type = 'bool'
                pass
            elif self.operands['arg2-type'] == 'int':
                arg2_type = 'int'
                arg2_value = int(arg2_value)
                pass
            elif self.operands['arg2-type'] == 'nil':
                arg2_type = 'nil'
                pass
            elif self.operands['arg2-type'] == 'string':
                arg2_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass

        if self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type not in ["int", "bool", "nil", "string"]:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg3-type'] in ['int', 'bool', 'string', 'nil']:
            arg3_value = self.operands['arg3-value']
            if self.operands['arg3-type'] == 'bool':
                arg3_type = 'bool'
                pass
            elif self.operands['arg3-type'] == 'int':
                arg3_type = 'int'
                arg3_value = int(arg3_value)
                pass
            elif self.operands['arg3-type'] == 'nil':
                arg3_type = 'nil'
                pass
            elif self.operands['arg3-type'] == 'string':
                arg3_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass

        if arg2_type == arg3_type or (arg2_type == 'nil' or arg3_type == 'nil'):
            if arg2_value == arg3_value:

                Labels.jumpToLabel(self.operands['arg1-value'])
                pass
            elif arg2_type == 'nil' or arg3_type == 'nil':

                pass
        else:
            print("ERROR: JUMPIFEQ - Wrong arguments.", file=sys.stderr)
            exit(53)

    def JUMPIFNEQ(self):
        if self.operands['arg2-type'] == "var":
            arg2_value, arg2_type = Frames.getVarValueFromFrame(self.operands, "2")
            if arg2_type not in ["int", "bool", "nil", "string"]:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg2-type'] in ['int', 'bool', 'string', 'nil']:
            arg2_value = self.operands['arg2-value']
            if self.operands['arg2-type'] == 'bool':
                arg2_type = 'bool'
                pass
            elif self.operands['arg2-type'] == 'int':
                arg2_type = 'int'
                arg2_value = int(arg2_value)
                pass
            elif self.operands['arg2-type'] == 'nil':
                arg2_type = 'nil'
                pass
            elif self.operands['arg2-type'] == 'string':
                arg2_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass

        if self.operands['arg3-type'] == "var":
            arg3_value, arg3_type = Frames.getVarValueFromFrame(self.operands, "3")
            if arg3_type not in ["int", "bool", "nil", "string"]:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass
            pass
        elif self.operands['arg3-type'] in ['int', 'bool', 'string', 'nil']:
            arg3_value = self.operands['arg3-value']
            if self.operands['arg3-type'] == 'bool':
                arg3_type = 'bool'
                pass
            elif self.operands['arg3-type'] == 'int':
                arg3_type = 'int'
                arg3_value = int(arg3_value)
                pass
            elif self.operands['arg3-type'] == 'nil':
                arg3_type = 'nil'
                pass
            elif self.operands['arg3-type'] == 'string':
                arg3_type = 'string'
                pass
            else:
                print("ERROR: Unknown data-type.", file=sys.stderr)
                exit(32)
                pass

        if arg2_type == arg3_type or (arg2_type == 'nil' or arg3_type == 'nil'):
            if arg2_value != arg3_value:
                Labels.jumpToLabel(self.operands['arg1-value'])
                pass
        else:
            print("ERROR: JUMPIFNEQ - Wrong arguments.", file=sys.stderr)
            exit(53)

    def EXIT(self):
        if self.operands['arg1-type'] == "var":
            value, type = Frames.getVarValueFromFrame(self.operands, "1")
            if type == "int":
                if 0 <= int(value) <= 49:
                    exit(int(value))
                else:
                    print("ERROR: Wrong EXIT code.", file=sys.stderr)
                    exit(57)
            else:
                print("ERROR: EXIT - Wrong operand type.", file=sys.stderr)
                exit(53)
        elif self.operands['arg1-type'] == "int":
            if is_int(self.operands['arg1-value']):
                if 0 <= int(self.operands['arg1-value']) <= 49:
                    exit(int(self.operands['arg1-value']))
                else:
                    print("ERROR: Wrong EXIT code.", file=sys.stderr)
                    exit(57)
            else:
                print("ERROR: EXIT - Wrong operand type.", file=sys.stderr)
                exit(53)
        else:
            print("ERROR: EXIT - Wrong operand type.", file=sys.stderr)
            exit(53)

    def DPRINT(self):
        if self.operands['arg1-type'] == "var":
            value, type = Frames.getVarValueFromFrame(self.operands, "1")
            print(value, file=sys.stderr)
            pass
        else:
            print(self.operands['arg1-value'], file=sys.stderr)
        pass

    def BREAK(self):
        print("Interpret state: \"BREAK\"", file=sys.stderr)
        print("Position in code (order): ", Interpret.instructionOrder, file=sys.stderr)
        print("Frames state: ", file=sys.stderr)
        print("- GF: " + str(Frames.globalFrame), file=sys.stderr)
        print("- LF: " + str(Frames.localFrame), file=sys.stderr)
        print("- TF: " + str(Frames.temporaryFrame), file=sys.stderr)
        print("- Frame stack: " + str(Frames.frameStack), file=sys.stderr)
        print("- Call stack: " + str(Interpret.callStack.values), file=sys.stderr)
        print("- Data stack: " + str(Interpret.dataStack.values), file=sys.stderr)
        print("Saved labels: " + str(Labels.labels), file=sys.stderr)
        print("", file=sys.stderr)
        pass

# HLAVNÍ FUNKCE PROGRAMU - spustí interpret
def main():
    interpret = Interpret(sourceFile)

    interpret.doEverything() # :)
    pass

# FUNKCE ZPRACUJE VSTUPNÍ XML SOUBOR A VYTVOŘÍ LIST INSTRUKCÍ
def parseInput(sourceFile):
    if sourceArg == 1:
        try:
            tree = ET.parse(sourceFile)
            root = tree.getroot()
        except ET.ParseError:
            print("ERROR: Wrong XML format.", file=sys.stderr)
            exit(31)
    else:
        try:
            tree = ET.parse(sys.stdin)
            root = tree.getroot()
        except ET.ParseError:
            print("ERROR: Wrong XML format.", file=sys.stderr)
            exit(31)

    instructionsList = []
    orderList = []
    orderAttrib = 1

    if not re.match("ippcode20", root.attrib['language'], re.I): # když je špatný druh jazyka - chyba
        print("ERROR: Wrong language name in XML file.", file=sys.stderr)
        exit(32)

    for attrib in root.attrib:
        if attrib not in ['name', 'language', 'description']: # kontrola povolených kořenových atributů
            print("ERROR: Unsupported root attribute.", file=sys.stderr)
            exit(32)

    for child in root: # procházím jednotlivé instrukce
        if not re.match("instruction", child.tag, re.I):
            print("ERROR: Wrong XML structure.", file=sys.stderr)
            exit(32)

        operands = {} # dictionary pro jednotlivé operandy konkrétní instrukce

        if "order" not in child.attrib or "opcode" not in child.attrib or len(child.attrib) != 2:
            print("ERROR: Wrong XML instruction elements.", file=sys.stderr)
            exit(32)

        if is_int(child.attrib['order']):
            if child.attrib['order'] in orderList or int(child.attrib['order']) < 1:
                print("ERROR: Repeating or negative order attribute.", file=sys.stderr)
                exit(32)
            orderAttrib = child.attrib['order']
            orderList.append(child.attrib['order'])
        else:
            print("ERROR: Order attribute isn't a number.", file=sys.stderr)
            exit(32)

        counter = 1 # počítadlo počtu argumentů

        for instAttributes in child: # pořadí argumentů nehraje roli - může být arg2 a potom arg1 i naopak - pouze nesmí být více než 3 argumenty
            pom = instAttributes.tag

            try:
                argNumber = int(''.join(number for number in pom if number.isdigit()))
            except ValueError:
                print("ERROR: Wrong XML argument name.", file=sys.stderr)
                exit(32)

            if argNumber == 1:
                counter = 1

            if counter > 3 or instAttributes.tag != 'arg' + str(argNumber):
                print("ERROR: Wrong number or order of arguments in instruction.", file=sys.stderr)
                exit(32)

            counter = counter + 1

            if 'type' not in instAttributes.attrib or len(instAttributes.attrib) != 1:
                print("ERROR: Wrong XML instruction elements.", file=sys.stderr)
                exit(32)

            operandValue = '' # hodnota operandu

            if instAttributes.text is not None: # musí mít nějakou hodnotu
                operandValue = instAttributes.text

            operandType = ''
            operandFrame = ''

            if instAttributes.attrib['type'] == 'var':
                if re.match(r'^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9\-$&%*!?]*$', operandValue):
                    operandType = 'var'
                    operandFrame, operandValue = instAttributes.text.split('@')
                else:
                    print("ERROR: Wrong variable format.", file=sys.stderr)
                    exit(32)
            elif instAttributes.attrib['type'] == 'label':
                if re.match(r'^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$', operandValue):
                    operandType = 'label'
                else:
                    print("ERROR: Wrong label name format: ", operandValue,file=sys.stderr)
                    exit(32)
            elif instAttributes.attrib['type'] in ['int', 'bool', 'string', 'nil']:
                if instAttributes.attrib['type'] == 'int' and re.match(r'[+-]?\d+$', operandValue):
                    operandType = 'int'
                elif instAttributes.attrib['type'] == 'bool' and re.match(r'(true|false)$', operandValue):
                    operandType = 'bool'
                    if operandValue == 'true':
                        operandValue = True
                    else:
                        operandValue = False
                elif instAttributes.attrib['type'] == 'string' and re.match(r'^([^\s\\]*([\\]{1}[0-9]{3})*[^\s\\]*)*$', operandValue, re.UNICODE):
                    escapeSequences = set(re.findall(r"\\([0-9]{3})", operandValue)) # najdu všechny správné escape sekvence a uložím si číselné hodnoty - pomocí set vyhodím duplicity
                    escapeSequences = list(escapeSequences) # konvertuji set na list a potom pomocí for každou ASCII hodnotu konvertuji na znak
                    for escapeSequence in escapeSequences:
                        if escapeSequence == "092": # při testování jsem zjistil, že "\" z nějakého důvodu skončí s errorem - je třeba nahradit to zvlášť
                            operandValue = re.sub("\\\\092", "\\\\", operandValue)
                            continue
                        operandValue = re.sub("\\\\" + escapeSequence, chr(int(escapeSequence)), operandValue) # nahrazení
                    operandType = 'string'
                elif instAttributes.attrib['type'] == 'nil' and re.match(r'^nil$', operandValue):
                    operandType = 'nil'
                else:
                    print("ERROR: Syntax error.", file=sys.stderr)
                    exit(32)
            elif instAttributes.attrib['type'] == 'type': # labelType
                if operandValue in ['int', 'bool', 'string']:
                    operandType = 'type'
                else:
                    print("ERROR: Wrong label type format.", file=sys.stderr)
                    exit(32)
            else:
                print("ERROR: Wrong XML instruction operand type: ", operandValue,file=sys.stderr)
                exit(32)

            operands["arg"+str(argNumber)+"-type"] = operandType
            operands["arg"+str(argNumber)+"-value"] = operandValue
            operands["arg"+str(argNumber)+"-varFrame"] = operandFrame


        operands["order"] = int(orderAttrib)
        instructionsList.append(Instruction(child.attrib['opcode'].upper(), operands))

    instructionsList.append(None)
    return instructionsList

# POMOCNÉ FUNKCE NA KONTROLU DATOVÝCH TYPŮ
def is_int(value):
    try:
        int(value)
        return True
    except ValueError:
        return False

def is_bool(value):
    try:
        str(value)
        if str(value) == "True" or str(value) == "False" or str(value) == "false" or str(value) == "true":
            return True
            pass
        else:
            return False
    except ValueError:
        return False

def is_string(value):
    try:
        str(value)
        return True
    except ValueError:
        return False

def is_nil(value):
    try:
        str(value)
        if str(value) == "nil":
            return True
            pass
        else:
            return False
    except ValueError:
        return False

# SPUŠTĚNÍ INTERPRETU

main()

# EOF
