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

//Contains functions to parse the grammar of phpdragon
class BNFTranslation 
{
    /* parse the grammar of the "query" syntax
     * <declaration1> ::= =><some MySQL code>;
     * <declaration2> ::= {<some MySQL code>}
     * <query> ::= query <VarName without $>? (<comma-separeted argument list>) => <declaration1>|<declaration2>; 
     */
    public static function ParseQuery(&$t, $DoNotSearch = false)
    {
        $Ret = array();
        if ($DoNotSearch)
        {
            if (strtolower(substr($t, 0, 5)) != "query") throw new Exception("Parse Error, no terminal symbols since : " . $t);
        }
        else
        {
            $t = stristr($t, "query");
            if ($t === false) throw new Exception("Parse Error, no terminal symbols since : " . $t);
        }
        $t = substr($t, 5);
        BNFTranslation::ParseWspace($t, false);
        $Ret[] = BNFTranslation::ParseWord($t, ":/_ .");
        BNFTranslation::ParseWspace($t, false);
        if (substr($t, 0, 1) != "(") throw new Exception("Parse Error, no terminal symbols since : " . $t);
        $t = substr($t, 1);
        $Ret[] = BNFTranslation::ParseParam($t);
        BNFTranslation::ParseWspace($t);
        if (substr($t, 0, 2) == "=>")
        {
            $t = substr($t, 2);
            $out = "";
            $igquote = false;
            $inquote = false;
            $indquote = false;
            $stop = false;
            while (!$stop)
            {
                $chr = substr($t, 0, 1);
                $t = substr($t, 1);
                $out .= $chr;
                if($chr == "'" && !$igquote && !$indquote) $inquote = !$inquote; 
                else if($chr == '"' && !$igquote && !$inquote) $indquote = !$indquote; 
                if($chr == '\\' && !$igquote) $igquote = true;
                else $igquote = false;
                $stop = ($chr == ";") && !$inquote && !$indquote;
            }
        }
        else if (substr($t, 0, 1) == "{")
        {
            $t = substr($t, 1);
            $out = "";
            $stop = false;
            $ct = 1;
            while ($ct != 0)
            {
                $chr = substr($t, 0, 1);
                $t = substr($t, 1);
                $out .= $chr;
                if($chr == '{') $ct++; 
                else if($chr == '}') $ct--; 
            }
        }
        else throw new Exception("Parse Error, no terminal symbols since : " . $t);
        $Ret[] = substr($out, 0, -1);
        return $Ret;
    }
    
    /* parse the grammar of the "webpage" syntax
     * <webpage> ::= webpage <path to template> (<comma-separeted argument list>)
     * {
     *      <PHP valid varname (with $)>|root = {<Some PHP code>}|<a PHP line>|<webpage> ;
     * };
     */
    public static function ParseWebPage(&$t, $DoNotSearch = false)
    {
        $Ret = array();
        if ($DoNotSearch)
        {
            if (strtolower(substr($t, 0, 7)) != "webpage") throw new Exception("Parse Error, no terminal symbols since : " . $t);
        }
        else
        {
            $t = stristr($t, "webpage");
            if ($t === false) throw new Exception("Parse Error, no terminal symbols since : " . $t);
        }
        $t = substr($t, 7);
        BNFTranslation::ParseWspace($t, true);
        $par = BNFTranslation::ParseWord($t, ":/_ .");
        if ($par != "") array_push($Ret, $par);
        BNFTranslation::ParseWspace($t);
        if (substr($t, 0, 1) != "(") throw new Exception("Parse Error, no terminal symbols since : " . $t);
        $t = substr($t, 1);
        array_push($Ret, BNFTranslation::ParseParam($t));
        BNFTranslation::ParseWspace($t);
        if (substr($t, 0, 1) != "{") throw new Exception("Parse Error, no terminal symbols since : " . $t);
        $t = substr($t, 1);
        while (true) try
        { 
            BNFTranslation::ParseWspace($t);
            $Ret = array_merge($Ret, BNFTranslation::ParseLigne($t));
        }
        catch (Exception $e) {break;}
        if (substr($t, 0, 1) != "}") throw new Exception("Parse Error, no terminal symbols since : " . $t);
        $t = substr($t, 1);
        BNFTranslation::ParseWspace($t);
        if (substr($t, 0, 1) == ";") $t = substr($t, 1);
        return $Ret;
    }
    
    private static function ParseParam(&$t)
    {
        $out = strstr($t, ")", true);
        if ($out != false) 
        {
            $t = substr($t, strlen($out) + 1);
            return trim($out);
        }
        else if (substr($t, 0, 1) == ")") $t = substr($t, 1);
        else throw new Exception("Parse Error, no terminal symbols since : " . $t);
        return "";
        
    }
    private static function ParseLigne(&$t)
    {
        $out = array(BNFTranslation::ParseVar($t));
        BNFTranslation::ParseWspace($t);
        if (substr($t, 0, 1) == "=") $t = substr($t, 1);
        else throw new Exception("Parse Error, no terminal symbols since : " . $t);
        BNFTranslation::ParseWspace($t);
        try
        {
            $a = BNFTranslation::ParseWebPage($t, true);
            array_push($out, $a);
        }
        catch (Exception $e)
        {
            $s = "";
            $chr = substr($t, 0, 1);
            $t = substr($t, 1);
            $br = 0;
            if ($chr == "{") {$chr = 'function($scope){extract($scope);';  $br++;}
            do
            {
                $s .= $chr;
                if (strlen($t) == 0) throw $e;
                $chr = substr($t, 0, 1);
                $t = substr($t, 1);
                if ($chr == "{") $br++;
                else if ($chr == "}") $br--;
            } while ($chr != ";" || $br > 0);
            array_push($out, $s);
            if (substr($t, 0, 1) == ";") $t = substr($t, 1);
        }
        return $out;
    }  
    private static function ParseVar(&$t)
    {
        if (substr($t, 0, 4) == "root") { $t = substr($t, 4); return "root";}
        else if (substr($t, 0, 1) == "$")
        {
            $t = substr($t, 1);
            return "$" . BNFTranslation::ParseWord($t, "_");
        }
        else throw new Exception("Parse Error, no terminal symbols since : " . $t);
    }
    private static function ParseWord(&$t, $extension = "")
    {
        $out = "";
        while (BNFTranslation::IsWordOr(substr($t, 0, 1), $extension))
        {
            $out .= substr($t, 0, 1);
            $t = substr($t, 1);
        }
        return $out;
    }   
    private static function ParseWspace(&$t, $minOne = false)
    {
        if ($minOne && !BNFTranslation::IsWspace(substr($t, 0, 1))) throw new Exception("Parse Error, no terminal symbols since : " . $t);
        while (BNFTranslation::IsWspace(substr($t, 0, 1))) $t = substr($t, 1);
    }
    
    private static function IsWspace($chr)
    {
        return ($chr == " ") ||
        ($chr =="\t") ||
        ($chr =="\n") ||
        ($chr =="\r") ||
        ($chr =="\0") ||
        ($chr =="\x0B");
    }   
    private static function IsWordOr($chr, $or)
    {
        $s = (ord("a") <= ord($chr) && ord($chr) <= ord("z"))
            || (ord("A") <= ord($chr) && ord($chr) <= ord("Z"))
            || (ord("0") <= ord($chr) && ord($chr) <= ord("9"));
        if ($s) return true;
        else while (strlen($or) > 0)
            {
                $s = $s || $chr == substr($or, 0, 1);
                $or = substr($or, 1);
            }
        return $s;
    }
}
?>