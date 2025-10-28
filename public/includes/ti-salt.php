<?php
class SaltIT
{
	var $key_str;	// project specific key
	var $code_mode;	// whether the code will include Alphabet, Numbers, Symbols...
	var $char_pool;	// character pool
	var $matrix = array();	// 2D array, encoding grid
	
	function __construct()
	{
		$this->key_str = "avenue";
		$this->code_mode = "ABNS";
		
		$this->char_pool = $this->GenerateData();
		$this->matrix = $this->GenerateMatrix();
	}

	// explodes a string into an array
	function String2Array($str)
	{
		$arr = array();
	
		for($i=0; $i < strlen($str); $i++)
			$arr[$i] = substr($str, $i, 1);
			
		return $arr;
	}
	
	// implodes an array into a string
	function Array2String($arr)
	{
		return implode("", $arr);
	}
	
	// generates the character-pool
	function GenerateData() // Alphabet, Numbers, Symbols
	{
		$str = "";
		
		$alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$beta = "abcdefghijklmnopqrstuvwxyz";
		$num = "1234567890";
		$symb = "-=[];,./~!@#$%^&*()_+|{}:?";
//		$symb = "`-=\\[];',./~!@#$%^&*()_+|{}:\"<>?";
		
		
		$arr = $this->String2Array($this->code_mode);
		
		if(in_array("A", $arr))	$str .= $alpha;	// upper-case alphabets
		if(in_array("B", $arr))	$str .= $beta;	// lower-case alphabets
		if(in_array("N", $arr))	$str .= $num;	// numeric
		if(in_array("S", $arr))	$str .= $symb;	// special characters/ symbols

		return $str;
	}

	// generate the encoding grid
	function GenerateMatrix()
	{
		$char_pool_arr = array();
		$char_pool_arr = $this->String2Array($this->char_pool);
		$i = count($char_pool_arr);
		$matrix = array();
	
		for($rows=0; $rows < $i; $rows++)
		{
			for($cols=0; $cols < $i; $cols++)
			{
				$index = $rows + $cols;
				
				$x_index = ($index >= $i)? $index - $i: $index;
				
				$row_index = $char_pool_arr[$rows];
				$col_index = $char_pool_arr[$cols];
	
				$matrix[$row_index][$col_index] = array("ROW"=>$row_index, "COL"=>$col_index, "VAL"=>$char_pool_arr[$x_index]);
			}
		}

		return $matrix;
	}
	
	function PrintMatrix($arr)
	{
		$str = "";
		
		$str .= "<table cellpadding=0 cellspacing=1>";
		foreach($arr as $R)	
		{
			$str .= "<tr>";
			foreach($R as $C)
				$str .= "<td width='10' align='center' style='border: 1px solid #ff0000;font-size: 10px;'>" . $C["VAL"] . "</td>";
				
			$str .= "</tr>";
		}
		$str .= "</table>";
		
		return $str;
	}
	
	function GenerateKeyString($data)
	{
		$key_code_arr = array();
		$data_arr = $this->String2Array($data);
		$key_arr = $this->String2Array($this->key_str);

		$data_len = count($data_arr);
		$key_len = count($key_arr);
	
		for($i=0, $index=0; $i < $data_len; $i++, $index++)
		{
			$index = ($index < $key_len)? $index: $index - $key_len;
			$key_code_arr[$i] = $key_arr[$index];
		}
		
		$key_code = $this->Array2String($key_code_arr);	//		return $key_str;
		return $key_code;
	}

	function EnCode($data)	//($str="", $key="")
	{
		$data_arr = $this->String2Array($data);
		$data_len = count($data_arr);
		
		$key_arr = $this->String2Array($this->key_str);
		
		$key_code = $this->GenerateKeyString($data);
		$code_arr = $this->String2Array($key_code);
		$code_len = count($code_arr);
		
		$matrix = $this->matrix;
		
		$cipher_arr = array();
		
		for($i=0; $i < $code_len; $i++)
		{
			$s = $data_arr[$i];
			$c = $code_arr[$i];
			
			//echo "$i $c $s " . $matrix[$c][$s]["VAL"] . "<br>";
			$cipher_arr[$i] = $matrix[$c][$s]["VAL"];
		}
		
		$cipher = $this->Array2String($cipher_arr);	
		return $cipher;
	}
	
	function DeCode($cipher)
	{
		$cipher_arr = $this->String2Array($cipher);
		$cipher_len = count($cipher_arr);

		$key_arr = $this->String2Array($this->key_str);

		$code = $this->GenerateKeyString($cipher);
		$code_arr = $this->String2Array($code);		
		$code_len = count($code_arr);
	
		$matrix = $this->matrix;
		$str_arr = array();
	
		for($i=0; $i < $code_len; $i++)
		{
			$x = $cipher_arr[$i];
			$c = $code_arr[$i];
		
			$x_row = $matrix[$c];
			
			foreach($x_row as $x_col)
				if($x_col["VAL"] == $x)
					$str_arr[$i] =  $x_col["COL"];
		}
		
		$str = $this->Array2String($str_arr);
		return $str;
	}
}
?>