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
	
	function Send_Zohomail($from,$fromName,$to,$replyto,$CC_str="",$BCC_str="",$subject="",$str="",$subject_user="",$replystr="",$page="",$FILES="", $attachment="")
	{
			
			require __DIR__ . '/../phpmailer/vendor/autoload.php';
			$Mail = new PHPMailer();
		    $Mail->SMTPDebug = 0;                      //Enable verbose debug output
            $Mail->isSMTP();                                            //Send using SMTP
            $Mail->Host       = 'smtp.zoho.in'; //'smtppro.zoho.in';                     //Set the SMTP server to send through
            $Mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $Mail->Username   = 'tktno_reply@deltin.com'; //'concierge@deltin.com';                     //SMTP username
            $Mail->Password   = 'MNv980P%'; //'DRl585P%1'; //'UCj881K&';                               //SMTP password
            $Mail->SMTPSecure = 'tls'; //'ssl'; //PHPMailer::ENCRYPTION_SMTPS; ENCRYPTION_STARTTLS           //Enable implicit TLS encryption
            $Mail->Port       = 587; //465;  
/* 			$Mail->SMTPOptions = [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true,
				]
			];  */           
              //Recipients
            $Mail->setFrom('tktno_reply@deltin.com', 'Deltin');
            //$Mail->addAddress($to);
            
            if($to != ""){
    			$to_arr = explode(',',$to);
    			if(!empty($to_arr))
    			{
    				foreach($to_arr as $tos)
    				{
    					$Mail->addAddress($tos);
    				}
    			}     
            }
            
            //$Mail->addCC($to1);
    		//$Mail->addBCC('david@teaminertia.com');
		if($CC_str != "")
		{
			$CC = explode(',',$CC_str);
			if(!empty($CC))
			{
				foreach($CC as $values)
				{
					$Mail->addCC($values);
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
					$Mail->addBCC($value);
				}
			}
		}
		//$Mail->addBCC('sohel@teaminertia.com');
		//$Mail->addBCC('david@teaminertia.com');
		//DFA($FILES);
		if(isset($FILES) && !empty($FILES)) 
		{
    		//$Mail->AddAttachment($FILES['tmp_name'],$FILES['name']);
			$fileUrl  = $FILES;
			$tempPath = sys_get_temp_dir() . '/invoice.pdf';

			// Download from S3
			file_put_contents($tempPath, file_get_contents($fileUrl));

			// Attach to mail
			$Mail->addAttachment($tempPath, 'Invoice.pdf');
    	//	echo $FILES['tmp_name'];
		}			
			$Mail->addReplyTo($replyto);
	        
	        $Mail->isHTML(true); 
	        $Mail->Subject  = $subject;//Set email format to HTML
            //$Mail->Body    = 'This is the HTML message body <b>in bold!</b>';
           // $Mail->AltBody = $str;

			$Mail->MsgHTML($str);
			$Mail->WordWrap = 50;
		
		if(!empty($replystr))
		{
		    $AutoMail = new PHPMailer();
			$AutoMail->SMTPDebug = 0;                      //Enable verbose debug output
            $AutoMail->isSMTP();                                            //Send using SMTP
            $AutoMail->Host       = 'smtp.zoho.in'; //'smtppro.zoho.in';                     //Set the SMTP server to send through
            $AutoMail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $AutoMail->Username   = 'tktno_reply@deltin.com'; //'concierge@deltin.com';                     //SMTP username
            $AutoMail->Password   = 'MNv980P%'; //'UCj881K&';                               //SMTP password
            $AutoMail->SMTPSecure = 'tls'; //'ssl'; //PHPMailer::ENCRYPTION_SMTPS; ENCRYPTION_STARTTLS           //Enable implicit TLS encryption
            $AutoMail->Port       = 587; //465; 
			$AutoMail->From = 'tktno_reply@deltin.com'; // site email id
				   //Recipients
            $AutoMail->setFrom('tktno_reply@deltin.com', 'Deltin');
            $AutoMail->addAddress($replyto);
    	//	$AutoMail->addBCC('rebecca@teaminertia.com');
		//	$AutoMail->addBCC('smita@teaminertia.com');
			$AutoMail->addReplyTo($to);
/* 			$AutoMail->SMTPOptions = [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true,
				]
			];  */			
	        
	        $AutoMail->isHTML(true); 
			if(isset($attachment) && !empty($attachment)) 
			{
				$AutoMail->addAttachment($attachment);
			//	echo $FILES['tmp_name'];
			}	
			$AutoMail->Subject  = $subject_user;
			$AutoMail->MsgHTML($replystr);
			//$AutoMail->Send();
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