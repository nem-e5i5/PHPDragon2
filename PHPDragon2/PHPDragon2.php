<?php
/*
Copyright or © or Copr. THOUVENIN Alexandre
nem-e5i5software@live.fr
This software is a computer program whose purpose is to help you to create a website with PHP.
This software is governed by the CeCILL license under French law and abiding by the rules of distribution of free software. You can use, modify and/ or redistribute the software under the terms of the CeCILL license as circulated by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
As a counterpart to the access to the source code and rights to copy, modify and redistribute granted by the license, users are provided only with a limited warranty and the software's author, the holder of the economic rights, and the successive licensors have only limited
liability.
In this respect, the user's attention is drawn to the risks associated with loading, using, modifying and/or developing or reproducing the software by the user in light of its specific status of free software, that may mean that it is complicated to manipulate, and that also
therefore means that it is reserved for developers and experienced professionals having in-depth computer knowledge. Users are therefore encouraged to load and test the software's suitability as regards their requirements in conditions enabling the security of their systems and/or data to be ensured and, more generally, to use and operate it in the same conditions as regards security.
The fact that you are presently reading this means that you have had knowledge of the CeCILL license and that you accept its terms. 
 */
include("BNFTranslation.php");

//Used before to parse the "query" synthax but no more used
#region Parse QUERY (Depreciated)
/*
define("PHPDragon2_RegExpInstruction", "(?:.+?)");
define("PHPDragon2_RegExpQuery", "query" . PHPDragon2_WhiteSpace . "?(\w*)");
define("PHPDragon2_RegExpMultilineInstruction", "{(" .  PHPDragon2_RegExpInstruction . ")}");
define("PHPDragon2_RegExpNormalDeclaration", "(?:" . PHPDragon2_RegExpQuery . 
    PHPDragon2_WhiteSpace . "?" . 
    PHPDragon2_RegExpArgList . 
    PHPDragon2_WhiteSpace . "?" . 
    PHPDragon2_RegExpMultilineInstruction . ")");
define("PHPDragon2_RegExpAbregedDeclaration", "(?:" . PHPDragon2_RegExpQuery . 
    PHPDragon2_WhiteSpace . "?" . 
    PHPDragon2_RegExpArgList . 
    PHPDragon2_WhiteSpace . "?" . "=>" . 
    PHPDragon2_WhiteSpace . "?(" . 
    PHPDragon2_RegExpInstruction . ");)");
define("PHPDragon2_RegExpDeclaration", "#" . PHPDragon2_RegExpNormalDeclaration . "|" . PHPDragon2_RegExpAbregedDeclaration . "#i");
*/
#endregion

/* parse the header of the "exported" syntax
 * <exported> ::= exported <complete PHP function declaration>
 */
#region Parse EXPORTED
define("PHPDragon2_WhiteSpace", "(?:\s+)"); 
define("PHPDragon2_RegExpArgList", "\((.*?)\)");
define("PHPDragon2_RegExpExported", "exported" . PHPDragon2_WhiteSpace . "function" . PHPDragon2_WhiteSpace . "?(\w*)");
define("PHPDragon2_RegExpExportedNormalDeclaration", "#" . PHPDragon2_RegExpExported . 
    PHPDragon2_WhiteSpace . "?" . 
    PHPDragon2_RegExpArgList . "#i");
#endregion

//Compile phpdragon to PHP
function PHPDragonCompile($file)
{
    $st = file_get_contents($file);
    
    #region transform the exported function xxx(yyy) into new Exported("xxx", "yyy", function(yyy) {
    global $PHPDragon_TemporaryPos;
    $PHPDragon_TemporaryPos = array($st, 0);
    $st = preg_replace_callback(
        PHPDragon2_RegExpExportedNormalDeclaration,
        "PHPDragon2_Internal_RegEXPExported",
        $st);
    #endregion
    #region transform the "}" at the end into "});"
    for ($i = 2; $i < count($PHPDragon_TemporaryPos); $i++)
    {
        $MustFind = 0; $strated = false;
        $si = $PHPDragon_TemporaryPos[$i];
        for($j = 0; $j + $si < strlen($st) ; $j++)
        {
            $chr = substr($st, $si + $j, 1);
            if ($chr == "{") {$MustFind ++; $strated = true;}
            else if ($chr == "}") $MustFind --;
            if ($MustFind == 0 && $strated)
            {
                $left = substr($st, 0, $si + $j);
                $right = substr($st, $si + $j + 1);
                $st = $left . "});" . $right;
                for ($k = $i + 1; $k < count($PHPDragon_TemporaryPos); $k++) $PHPDragon_TemporaryPos[$k] += 2;
                break;
            }
        }
    }
    unset($PHPDragon_TemporaryPos);
    #endregion
    #region transform the "query synthax into an valid php object notation (new query(...))
    $st1 = $st;
    while (strpos(strtolower($st1), "query") !== false)
    {
        $before = substr($st, 0, strlen($st) - strlen($st1)) . stristr($st1, "query", true);
        $rep = BNFTranslation::ParseQuery($st1);
        $rep = PHPDragon2_Internal_RegEXPQuery($rep);
        $after = ";" . $st1;
        $st = $before . $rep . $after;
    }
    #endregion
    #region transform the webpage synthax into an object notation with an associative array for vars
    $st2 = $st;
    while (strpos(strtolower($st2), "webpage") !== false)
    {
        $before = substr($st, 0, strlen($st) - strlen($st2)) . stristr($st2, "webpage", true);;
        $rep = BNFTranslation::ParseWebPage($st2);
        $rep = PHPDragon2_Internal_RegEXPPage($rep);
        $after = ";" . $st2;
        $st = $before . $rep . $after;
    }
    return $st;
    #endregion
}
//Compile, create a cache and include a phpdragon file
function PHPDragonIncluder($file, $CompileCacheDir = "./PHPDragonCache/")
{
    if (!file_exists($CompileCacheDir)) mkdir($CompileCacheDir);
    if (!file_exists(dirname($CompileCacheDir . $file))) mkdir(dirname($CompileCacheDir . $file));
    if (!file_exists($CompileCacheDir . $file)
        ||(file_exists($CompileCacheDir . $file . ".checksum")
        && file_get_contents($CompileCacheDir . $file . ".checksum") != md5_file($file)))
    {
        file_put_contents($CompileCacheDir . $file, PHPDragonCompile($file));
        file_put_contents($CompileCacheDir . $file . ".checksum", md5_file($file));
    }
    include($CompileCacheDir . $file);
}
//Handle external call to exported function
function PHPDragonExportHandler()
{
    if (isset($_POST["PHPDragon2_function"]))
    {
        $Func = json_decode($_POST["PHPDragon2_function"]);
        if (isset(Exported::$ExportedList[$Func]))
        {
            $a = array();
            $i = 0;
            while (isset($_POST[$i]))
            {
                $a[$i] = json_decode($_POST[$i]);
                $i++;
            }
            die(json_encode(call_user_func_array(Exported::$ExportedList[$Func], $a), JSON_HEX_QUOT | JSON_HEX_APOS));
        }
        else
        {
            die(json_encode("Undefined script function", JSON_HEX_QUOT | JSON_HEX_APOS));
        }
    }
}
//Show how you are strong with this picture !
function PHPDragonShow($border = "2", $ico = "PHPDragon.png")
{
    return '<div style="color:#886c1f;
    width:85px;
    border-style:solid;
    border-width:' . $border . 'px;
    border-color:#886c1f;
    text-align:center;
    font-weight:bold;">Powered by<br /><img style="margin:auto;" src="' . $ico . '" alt="PHPDragon2" />';
}

//Thoose function transform a parsed script to object PHP
function PHPDragon2_Internal_RegEXPQuery($matches)
{
    return "new Query('" . PHPDragon2_Internal_Escape($matches[0])
    . "', '" . PHPDragon2_Internal_Escape($matches[1])
    . "', '" . PHPDragon2_Internal_Escape($matches[2])
        . "');";
}
function PHPDragon2_Internal_RegEXPPage($matches)
{
    $a = PHPDragon2_Internal_ExplodeAndRemWS($matches[1]);
    $str = "new WebPage('" . PHPDragon2_Internal_Escape($matches[0]) . "', array(";
    for ($i = 0; $i < count($a); $i++) $str .= "'%arg" . $i ."%'=>" . "'" . PHPDragon2_Internal_Escape(substr($a[$i], 1)) . "',";
    for ($i = 2; $i < count($matches); $i+=2)
    {
        if (strtolower($matches[$i]) == "root") $matches[$i] = "%root%";
        else if (substr($matches[$i], 0, 1) == "$") $matches[$i] = substr($matches[$i], 1);
        if (is_array($matches[$i + 1])) $matches[$i + 1] = PHPDragon2_Internal_RegEXPPage($matches[$i + 1]);
        $str .= "'" . PHPDragon2_Internal_Escape($matches[$i]) . "'=>'" . PHPDragon2_Internal_Escape($matches[$i + 1]) . "',";
    }
    $str = substr($str, 0, strlen($str) - 1) . "))";
    return $str;
}
function PHPDragon2_Internal_RegEXPExported($matches)
{
    global $PHPDragon_TemporaryPos;
    //"$" . $matches[1] . "=
    $ret = "new Exported('" . $matches[1] . "', '" . $matches[2] . "', function(" . $matches[2] . ")";
    $PHPDragon_TemporaryPos[] = strpos($PHPDragon_TemporaryPos[0] , $matches[0]) + $PHPDragon_TemporaryPos[1];
    $PHPDragon_TemporaryPos[1] += strlen($ret) - strlen($matches[0]);
    return $ret;
}
//Used to split an argument list
function PHPDragon2_Internal_ExplodeAndRemWS($str)
{
    $a = explode(",", $str);
    for($i = 0; $i < count($a); $i++) $a[$i] =  PHPDragon2_Internal_RemoveWhitespace($a[$i]);
    return $a;
}
function PHPDragon2_Internal_RemoveWhitespace($str)
{
    return str_replace(" ", "", str_replace("	", "", str_replace("\n", "", str_replace("\r", "", $str))));
}
//Is the array an associative array ?
function PHPDragon2_Internal_IsAssociative($arr)
{
    return array_keys($arr) !== range(0, count($arr) - 1);
}
//Transform a text that must be put between " and " and capable of recursion
function PHPDragon2_Internal_Escape($text)
{
    return base64_encode($text);
}
function PHPDragon2_Internal_Unescape($text)
{
    return base64_decode($text);
}

//This class is linked to the webpage keyword
class WebPage
{
    #region Not used yet
    /*
    const TypeList = 0;
    const TypeValue = 1;
    const TypeQuery = 2;
    const TypeExported = 3;
    private $VarCount;
    //*/
    #endregion
    
    public $Template; //Template path
    public $Content; //Associative array that contains the webpage tree
    public $Arguments; //array of arguments' name
    //Create the object from the notation created before
    public function __construct($template, $params = null)
    {
        $this->Template = PHPDragon2_Internal_Unescape($template);
        if ($params != null)
        {
            $this->Arguments = array();
            for ($i = 0; isset($params["%arg" . $i ."%"]); $i++) if ($params["%arg" . $i ."%"] != "")
            {
                array_push($this->Arguments, PHPDragon2_Internal_Unescape($params["%arg" . $i ."%"]));
                unset($params["%arg" . $i . "%"]);
            }
            $this->Content = array();
            foreach($params as $key => $value) $this->Content[PHPDragon2_Internal_Unescape($key)] = PHPDragon2_Internal_Unescape($value);
        }
    }   
    //Compile the webpage with given arguments
    public function __invoke()
    {
        #region Add arguments values to this context
        $this->RootArray();
        $invoke_template = file_get_contents($this->Template);
        $invoke_cnt = func_num_args();
        if ($invoke_cnt != count($this->Arguments)) die ("vous avez " . $invoke_cnt . " / " . count($this->Arguments) . " arguments pour l'invoquation d'une page web");
        for ($invoke_i = 0; $invoke_i < $invoke_cnt; $invoke_i++) 
        {
            if (!isset(${$this->Arguments[$invoke_i]})) ${$this->Arguments[$invoke_i]} = func_get_arg($invoke_i);
        }
        #endregion
        $invoke_MaxRepeat = 0xFFFFFF;
        $invoke_Return = array();
        foreach ($this->Content as $invoke_key => $invoke_value)
        {
            if (!is_array($invoke_value) && !is_object($invoke_value)) $invoke_value = eval('return ' . $invoke_value . ";");
            if (is_a($invoke_value, "WebPage"))
            {
                //If it's a webpage, call it with the good argument list
                $invoke_args = array();
                foreach ($invoke_value->Arguments as $invoke_arg)
                {
                    if (isset(${$invoke_arg})) array_push($invoke_args, ${$invoke_arg});
                }
                $invoke_value = call_user_func_array($invoke_value, $invoke_args);
            }
            else if (is_a($invoke_value, "Query")) $invoke_value = $invoke_value();
            else if (is_a($invoke_value, "Exported")) $invoke_value = $invoke_value->CreateJS();
            //it's a closure : mean that it is a multiline instruction and in this case the context is inherited
            else if (is_callable($invoke_value)) $invoke_value = $invoke_value(get_defined_vars());
            //it's an array : mean that this template must be repeated and keep it for later
            if (is_array($invoke_value))
            {
                $invoke_MaxRepeat = min(count($invoke_value), $invoke_MaxRepeat);
                for ($invoke_i = 0; $invoke_i < $invoke_MaxRepeat; $invoke_i++)
                {
                    $invoke_Return[$invoke_i][$invoke_key] = $invoke_value[$invoke_i];
                }
            }
            //else just replace
            else $invoke_template = str_replace("<@" . $invoke_key . "@>", $invoke_value, $invoke_template);
        }
        //later is now
        $invoke_ret = "";
        if (count($invoke_Return) > 0) for ($invoke_i = 0; $invoke_i < $invoke_MaxRepeat; $invoke_i++)
        {
            $invoke_tpl = $invoke_template;
            foreach($invoke_Return[$invoke_i] as $invoke_key => $invoke_value)
            {
                $invoke_tpl = str_replace("<@" . $invoke_key . "@>", $invoke_value, $invoke_tpl);
            }
            $invoke_ret .= $invoke_tpl;
        }
        if ($invoke_ret != "") return $invoke_ret;
        else return $invoke_template;
    }
    // the "root" var is special and is handled here
    public function RootArray()
    {
        if (isset($this->Content["%root%"]))
        {
            $root = eval('return ' .$this->Content["%root%"] . ";");
            if (is_a($invoke_value, "Query")) $root = $root();
            if (is_callable($root)) $root = $root(get_defined_vars());
            if (!PHPDragon2_Internal_IsAssociative($root))
            {
                for ($i = 0; $i < count($root); $i++)
                {
                    foreach($root[$i] as $key => $value)
                    {
                        if (!array_key_exists($key, $this->Content)) $this->Content[$key] = array();
                        $this->Content[$key][] = $value;
                    }
                }
            }
            else
            {
                $this->Content = array_merge($this->Content, $root);
            }
            unset($this->Content["%root%"]);
            unset($value);
            unset($key);
            unset($root);
        }
        unset($this->Content["%root%"]);
    }
    #region WIP : Final compilation
    /*
    public function Build()
    {
        $VarCount = 0;
        RootArray();
        $template = file_get_contents($this->Template);
        $header = 'include("PHPDragon2.php");';
        $cnt = func_num_args();
        for ($i = 1; $i < $cnt; $i+=2)
        {
            switch(func_get_arg($i))
            {
                case self::TypeValue    : self::BuildValue    ($template, $header, $this->Content[func_get_arg($i + 1)], func_get_arg($i + 1)); break;
                case self::TypeList     : self::BuildList     ($template, $header, $this->Content[func_get_arg($i + 1)], func_get_arg($i + 1)); break;
                case self::TypeQuery    : self::BuildQuery    ($template, $header, $this->Content[func_get_arg($i + 1)], func_get_arg($i + 1)); break;
                case self::TypeExported : self::BuildExported ($template, $header, $this->Content[func_get_arg($i + 1)], func_get_arg($i + 1)); break;
            }
        }
        return $header . $template;
    }
    public static function BuildValue(&$template, &$header, $PHP, $Key)
    {
        
        if (substr_count($template, $Key) > 1)
        {
            $fname = RandName();
            $header = "<?php function " . $fname . "(){echo " . $PHP . ";} ?>\r\n" . $header;
            $template = str_replace( "<@" . $Key . "@>", "<?php " . $fname . "(); ?>", $template);
        }
        else
        {
            $template = str_replace( "<@" . $Key . "@>", "<?php echo " . $PHP . " ?>", $template);
        }
        
    }
    public static function BuildList(&$template, &$header, $PHP, $Key, $Query = false)
    {
        static $isList = false;
        static $vname = null;
        if (!$isList)
        {
            $vname = '$' . RandName();
            $aname = '$' . RandName();
            $template = "<?php " . $aname . " = " . $PHP . "; for(" . $vname . "=0;" . $vname . "<count(" . $aname . ");" . $vname . "++){ ?>"
            . str_replace("<@" . $Key . "@>", "<?php echo " . $aname . "[" . $vname . "]" . ($Query ? "[" . $key . "]" : "") . "; ?>", $template) . 
            "<?php } ?>";
        }
        else
        {
            $aname = '$' . RandName();
            $template = "<?php " . $aname . " = " . $PHP . "; ?>" . 
                str_replace("<@" . $Key . "@>", "<?php echo " . $aname . "[" . $vname . "]" . ($Query ? "[" . $key . "]" : "") . "; ?>", $template);
        }
        $isList = true;
    }
    public static function BuildQuery(&$template, &$header, $PHP, $Key)
    {
        $func = eval('return ' . $PHP . ";");
        $PHP = function(){
        self::BuildList($template, $header, $PHP, $Key, true);
    }
    public static function BuildExported(&$template, &$header, $PHP, $Key)
    {
        $func = eval('return ' . $PHP . ";");
        $header = "<?php function " . $func->Name . "(" . implode(",", $func->Arguments) . "){" . GetFuncSrc($func->Callback) . "} ?>" . $header;
        $header = '<script type=text/javascript>' . $func->CreateJS() . '</script>' . $header;
        $header = '<?php' . 
            'if (isset($_POST["PHPDragon2_function"]) && json_decode($_POST["PHPDragon2_function"]) == ' . $func->Name . '){' .
            '$a=array();for($i=0;isset($_POST[$i]);$i++)$a[$i]=json_decode($_POST[$i]);die(json_encode(call_user_func_array("' . $func->Name . '", $a), JSON_HEX_QUOT | JSON_HEX_APOS));' .
            ' ?>' . $header;
    }
    private static function RandName()
    {
        return md5(rand()) . '_' . ++$this->VarCount;
    }
    private static function GetFuncSrc($func)
    {
        $func = new ReflectionFunction($func);
        $filename = $func->getFileName();
        $start_line = $func->getStartLine();
        $end_line = $func->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        return implode("", array_slice($source, $start_line, $length));
    }
     // */
    #endregion
}
//This class is linked to the query keyword (a lot of useless things because it was the test and I'm too lazy to clean up)
class Query
{
    public static $SQLConnection = null;

    public $ArgumentList;
    public $SQLText = "";
    
    public $Result = null;
    
    public function __construct()
    {
        if (func_num_args() == 0) return;
        else if(func_num_args() == 1) 
        {
            $Source = func_get_arg(0);
            if (preg_match(PHPDragon2_RegExpNormalDeclaration, $Source, $Matches));
            else if (preg_match(PHPDragon2_RegExpAbregedDeclaration, $Source, $Matches));
            else die("Erreur De compilation des requêtes dans " . $Source);
            $Name = $Matches[1];
            $gen = Query::create($Matches[2], $Matches[3]);
        }
        else if(func_num_args() == 2) 
        {
            $Name =  "";
            $gen = Query::create(func_get_arg(0), func_get_arg(1));
        }
        else if(func_num_args() == 3) 
        {
            $Name = PHPDragon2_Internal_Unescape(func_get_arg(0));
            $gen = Query::create(func_get_arg(1), func_get_arg(2));
        }
        else return; 
        
        
        if ($Name != "") 
        {
            global $$Name;
            $$Name = $gen;
        }
        else Query::copy($gen, $this) ;
        
    }   
    private static function create($a, $s)
    {
        $v = new Query();
        $v->ArgumentList = PHPDragon2_Internal_ExplodeAndRemWS(PHPDragon2_Internal_Unescape($a));
        $v->SQLText = PHPDragon2_Internal_Unescape($s);
        return $v;
    }
    private static function copy($source, $dest)
    {
        $dest->ArgumentList = $source->ArgumentList;
        $dest->SQLText = $source->SQLText;
    }
    
    public function __invoke()
    {
        if (Query::$SQLConnection == null) $this->Connect();
        if ($this->Result == null) 
        {
            $numargs = func_num_args();  
            $tq = $this->SQLText;
            
            for ($i = 0; $i < $numargs; $i++)
            {
                $argname = $this->ArgumentList[$i];
                $arg = func_get_arg($i);
                $tq = str_replace('$' . $argname, $arg, $tq);
                if (is_array($arg)) 
                {
                    if (PHPDragon2_Internal_IsAssociative($arg))
                    {
                        $keys = array_keys($arg);
                        $values = array_values($arg);
                        for ($i = 0; $i < count($arg); $i++)
                        {
                            $keys[$i] = "'" . Query::$SQLConnection->escape_string($keys[$i]) . "'";
                            $values[$i] = "'" . Query::$SQLConnection->escape_string($values[$i]) . "'";
                        }
                        $arg = "(" . implode(",", $keys) . ") VALUES (" . implode(",", $values) .")";               
                    }
                    else if (is_array($arg[0]))
                    {
                        for ($i = 0; $i < count($arg); $i++)
                        {
                            for ($j = 0; $j < count($arg[$i]); $j++)
                            {
                                $arg[$i][$j] = "'" . Query::$SQLConnection->escape_string($arg[$i]) . "'";
                            }
                            $arg[$i] = "(" . implode(",", $arg[$i]) . ")";
                        }
                        $arg = implode(",", $arg);
                    }
                    else
                    {
                        for ($i = 0; $i < count($arg); $i++)
                        {
                            $arg[$i] = "'" . Query::$SQLConnection->escape_string($arg[$i]) . "'";
                        }
                        $arg = implode(",", $arg);
                    }
                }
                else
                {
                    $arg = "'" . Query::$SQLConnection->escape_string($arg) . "'";
                }
                $tq = str_replace($argname, $arg, $tq);
            }
            
            $queryr = Query::$SQLConnection->Query($tq);
            if ($queryr === false) die(Query::$SQLConnection->error);
            if (is_a($queryr, "mysqli_result"))
            {
                if (method_exists('mysqli_result', 'fetch_all')) $this->Result = $queryr->fetch_all();
                else for ($this->Result = array(); $tmp = $queryr->fetch_array(MYSQLI_ASSOC);) $this->Result[] = $tmp;
            }
        }
        return $queryr === true ? $tq : $this->Result;
    }
    public function ClearCache()
    {
        $this->Result = null;
    }
    public function Connect($host = null, $username = null, $password = null, $dbname = null)
    {
        $host = is_null($host) ? constant("SQL_HOST") : $host;
        $username = is_null($username) ? constant("SQL_USERNAME") : $username;
        $password = is_null($password) ? constant("SQL_PASSWORD") : $password;
        $dbname = is_null($dbname) ? constant("SQL_DB") : $dbname;
        
        Query::$SQLConnection = new mysqli($host, $username, $password, $dbname);
    }   
    public static function Disconnect()
    {
        Query::$SQLConnection->Close();
        Query::$SQLConnection = null;
    }
}
//This class is linked to the exported keyword (nothing difficult here)
class Exported
{
    public static $ExportedList = null;
    public $ExportedName;
    public $Arguments;
    public $Callback;
    
    public function __construct($Name, $Arguments, $Callback)
    {
        if (Exported::$ExportedList == null) Exported::$ExportedList = array();
        $this->Arguments = PHPDragon2_Internal_ExplodeAndRemWS($Arguments);
        $this->Callback = $Callback;
        $this->ExportedName = $Name;
        Exported::$ExportedList[$this->ExportedName] = $this;
        $GLOBALS[$Name] = $this;
    }
    
    public function __invoke()
    {
        return call_user_func_array($this->Callback, func_get_args());
    }
    public function CreateJS($RegenPage = false)
    {
        $s = "
            function " . $this->ExportedName . "(" . implode(", ", $this->Arguments) . ") {
               return " . ($RegenPage ? "PHPDragon2_openWithPostData" : "PHPDragon2_openWithXHR") . "(document.URL,
                    {
                        'PHPDragon2_function' : '" . $this->ExportedName . "'";
        for ($i = 0; $i < count($this->Arguments); $i++) $s .= ', ' . $i . ":" . $this->Arguments[$i];
        $s .= "
                    });
            }
        ";
        return $s;
    }
}
?>