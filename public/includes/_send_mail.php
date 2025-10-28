<?php
	include "class.phpmailer.php";
	//include "recaptchalib.php";
	
	
	function Send_mail($from,$fromName,$to,$replyto,$CC_str="",$BCC_str="",$subject="",$str="",$subject_user="",$replystr="",$page="",$FILES="")
	{
		$Mail = new PHPMailer();
		$Mail->From = 'website@thatlifestylecoach.com';
		$Mail->FromName = 'ThatLifestyleCoach'; 
		$Mail->AddAddress($to);
		$Mail->isMail();
		$Mail->SMTPDebug = false;
		$Mail->Host = 'thatlifestylecoach.com';
		$Mail->Port = 465;
		$Mail->SMTPSecure = 'ssl'; 				
		$Mail->SMTPAuth = true;
		$Mail->Username = 'thatlifestylecoach.com';
		$Mail->Password = 'thatlifestylecoach.com';
		
		if($CC_str != "")
		{
			$CC = explode(',',$CC_str);
			if(!empty($CC))
			{
				foreach($CC as $values)
				{
					$Mail->AddCC($values);
				}
			}
		}
		
		if($BCC_str != "")
		{	
			$BCC = explode(',',$BCC_str);
			if(!empty($BCC))
			{
				foreach($BCC as $value)
				{
					$Mail->AddBCC($value);
				}
			}
		}
		
		/*if(!empty($FILES)) 
		{
			//echo BROUCHER_PATH.'=>'.BROUCHER_FILENAME;
    		//$Mail->AddAttachment($FILES['myfiles']['tmp_name'],$FILES['myfiles']['name']);
    		$AutoMail->AddAttachment(BROUCHER_PATH.BROUCHER_FILENAME);
		}		*/
		
		$Mail->AddReplyTo($replyto); 
		$Mail->WordWrap = 50; 
		$Mail->IsHTML(true);
		$Mail->Subject = $subject;
		$Mail->MsgHTML($str);
		
		if(!empty($replystr))
		{
			$AutoMail = new PHPMailer();
			$AutoMail->From = 'website@thatlifestylecoach.com';
			$AutoMail->FromName = 'ThatLifestyleCoach';
			$AutoMail->AddAddress($replyto);
			$AutoMail->isMail();
			$AutoMail->SMTPDebug = false;
			$AutoMail->Host = 'thatlifestylecoach.com';
			$AutoMail->Port = 465;
			$AutoMail->SMTPSecure = 'ssl'; 				
			$AutoMail->SMTPAuth = true;
			$AutoMail->Username = 'thatlifestylecoach.com';
			$AutoMail->Password = 'thatlifestylecoach.com';		
			$AutoMail->AddReplyTo($to);
			$AutoMail->WordWrap = 50; 
			$AutoMail->IsHTML(true);
			$AutoMail->Subject = $subject_user;
			$AutoMail->MsgHTML($replystr);

			if(!empty($FILES) && $FILES=='detailed-curriculum')
			{
				//echo BROUCHER_PATH.'=>'.BROUCHER_FILENAME;
	    		//$Mail->AddAttachment($FILES['myfiles']['tmp_name'],$FILES['myfiles']['name']);
	    		$AutoMail->AddAttachment(BROUCHER_PATH.BROUCHER_FILENAME);
			}		

			$AutoMail->Send();
		}
				
		if($Mail->Send())
		{
			return $page.'?done=1';
		}
		else
		{
			return $page.'?done=2';
		}
	}
	
?>